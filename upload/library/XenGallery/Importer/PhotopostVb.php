<?php

class XenGallery_Importer_PhotopostVb extends XenForo_Importer_Abstract
{
	/**
	 * Source database connection.
	 *
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_sourceDb;

	protected $_prefix;

	protected $_fileRoot;

	protected $_userIdMap;

	/**
	 * XF database connection.
	 *
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_vBDb;

	protected $_config;

	protected $_defaultTables = array();

	public static function getName()
	{
		return ' XFMG: Import From Photopost - vBulletin';
	}

	public function configure(XenForo_ControllerAdmin_Abstract $controller, array &$config)
	{
		if ($config)
		{
			$errors = $this->validateConfiguration($config);
			if ($errors)
			{
				return $controller->responseError($errors);
			}

			$this->_bootstrap($config);

			return true;
		}
		else
		{
			$viewParams = array('ppinput' => array
			(
				'port' => 3306,
				'charset' => '',

				'host' => 'localhost',
				'dbname' => '',
				'username' => '',
				'password' => '',
				'prefix' => ''
			), 'vbinput' => array
			(
				'port' => 3306,
				'charset' => '',

				'host' => 'localhost',
				'dbname' => '',
				'username' => '',
				'password' => '',
				'prefix' => ''
			),
				'productName' => str_replace('XFMG: Import From ', '', self::getName()),
			);

			return $controller->responseView('XenForo_ViewAdmin_Import_Photopost_Config', 'xengallery_import_config_photopost_vbulletin', $viewParams);
		}
	}

	public function validateConfiguration(array &$config)
	{
		$errors = array();

		$config['db']['prefix'] = preg_replace('/[^a-z0-9_]/i', '', $config['db']['prefix']);

		if (empty($config['importLog']))
		{
			$errors[] = new XenForo_Phrase('xengallery_no_import_log_table_specified');
		}

		try
		{
			$db = Zend_Db::factory('mysqli',
				array(
					'host' => $config['db']['host'],
					'port' => $config['db']['port'],
					'username' => $config['db']['username'],
					'password' => $config['db']['password'],
					'dbname' => $config['db']['dbname'],
					'charset' => $config['db']['charset']
				)
			);
			$db->getConnection();
		}
		catch (Zend_Db_Exception $e)
		{
			$errors[] = new XenForo_Phrase('source_database_connection_details_not_correct_x', array('error' => $e->getMessage()));
		}

		$config['vb']['prefix'] = preg_replace('/[^a-z0-9_]/i', '', $config['vb']['prefix']);

		try
		{
			$vb = Zend_Db::factory('mysqli',
				array(
					'host' => $config['vb']['host'],
					'port' => $config['vb']['port'],
					'username' => $config['vb']['username'],
					'password' => $config['vb']['password'],
					'dbname' => $config['vb']['dbname'],
					'charset' => $config['vb']['charset']
				)
			);
			$vb->getConnection();
		}
		catch (Zend_Db_Exception $e)
		{
			$errors[] = new XenForo_Phrase('source_database_connection_details_not_correct_x', array('error' => 'vBulletin: ' . $e->getMessage()));
		}

		if ($errors)
		{
			return $errors;
		}

		try
		{
			$db->query('
				SELECT userid
				FROM ' . $config['db']['prefix'] . 'users
				LIMIT 1
			');
		}
		catch (Zend_Db_Exception $e)
		{
			if ($config['db']['dbname'] === '')
			{
				$errors[] = new XenForo_Phrase('please_enter_database_name');
			}
			else
			{
				$errors[] = new XenForo_Phrase('table_prefix_or_database_name_is_not_correct');
			}
		}

		try
		{
			$vb->query('
				SELECT userid
				FROM ' . $config['vb']['prefix'] . 'user
				LIMIT 1
			');
		}
		catch (Zend_Db_Exception $e)
		{
			if ($config['vb']['dbname'] === '')
			{
				$errors[] = 'vBulletin: ' . new XenForo_Phrase('please_enter_database_name');
			}
			else
			{
				$errors[] = 'vBulletin: ' . new XenForo_Phrase('table_prefix_or_database_name_is_not_correct');
			}
		}

		if (isset($config['fileLocation']) && $config['fileLocation'])
		{
			if (!file_exists($config['fileLocation']))
			{
				$errors[] = new XenForo_Phrase('xengallery_specified_file_location_does_not_exist');
			}
			else
			{
				$this->_fileRoot = $config['fileLocation'];
			}
		}

		$config['noRatingsTable'] = false;

		try
		{
			$ratingsTableExists = $db->fetchOne('
				SHOW TABLES LIKE ' . XenForo_Db::quoteLike($config['db']['prefix'] . 'ratings', '') . '
			');

			if (!$ratingsTableExists)
			{
				$config['noRatingsTable'] = true;
			}
		}
		catch (Zend_Db_Exception $e) {}

		return $errors;
	}

	protected function _bootstrap(array $config)
	{
		if ($this->_sourceDb || $this->_vBDb)
		{
			return;
		}

		@set_time_limit(0);

		$this->_config = $config;

		$this->_sourceDb = Zend_Db::factory('mysqli',
			array(
				'host' => $config['db']['host'],
				'port' => $config['db']['port'],
				'username' => $config['db']['username'],
				'password' => $config['db']['password'],
				'dbname' => $config['db']['dbname'],
				'charset' => $config['db']['charset']
			)
		);

		$this->_vBDb = Zend_Db::factory('mysqli',
			array(
				'host' => $config['vb']['host'],
				'port' => $config['vb']['port'],
				'username' => $config['vb']['username'],
				'password' => $config['vb']['password'],
				'dbname' => $config['vb']['dbname'],
				'charset' => $config['vb']['charset']
			)
		);

		if (empty($config['db']['charset']))
		{
			$this->_sourceDb->query('SET character_set_results = NULL');
		}

		if (empty($config['vb']['charset']))
		{
			$this->_vBDb->query('SET character_set_results = NULL');
		}

		$this->_prefix = preg_replace('/[^a-z0-9_]/i', '', $config['db']['prefix']);
		$this->_vBPrefix = preg_replace('/[^a-z0-9_]/i', '', $config['vb']['prefix']);

		if (!empty($config['db']['charset']))
		{
			$this->_charset = $config['db']['charset'];
		}

		if (!empty($config['vb']['charset']))
		{
			$this->_charset = $config['vb']['charset'];
		}

		define('IMPORT_LOG_TABLE', $this->_config['importLog']);
	}

	public function retainKeysReset()
	{
		$db = XenForo_Application::getDb();

		$mediaCount = $db->fetchOne('
			SELECT COUNT(*)
			FROM xengallery_media
		');

		$categoryCount = $db->fetchOne('
			SELECT COUNT(*)
			FROM xengallery_category
			WHERE category_id > 1
		');

		if ($mediaCount || $categoryCount)
		{
			throw new XenForo_Exception(new XenForo_Phrase('xengallery_gallery_must_be_empty_before_importing'), true);
		}

		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Category', XenForo_DataWriter::ERROR_SILENT);
		if ($dw->setExistingData(1))
		{
			$dw->delete();
		}
	}

	public function getSteps()
	{
		return array(
			'albums' => array(
				'title' => new XenForo_Phrase('xengallery_import_albums')
			),
			'categories' => array(
				'title' => new XenForo_Phrase('xengallery_import_categories')
			),
			'media' => array(
				'title' => new XenForo_Phrase('xengallery_import_media'),
				'depends' => array('albums', 'categories')
			),
			'comments' => array(
				'title' => new XenForo_Phrase('xengallery_import_comments'),
				'depends' => array('albums', 'categories', 'media')
			),
			'contenttags' => array(
				'title' => new XenForo_Phrase('import_content_tags'),
				'depends' => array('albums', 'categories', 'media')
			),
			'ratings' => array(
				'title' => new XenForo_Phrase('xengallery_import_ratings'),
				'depends' => array('albums', 'categories', 'media')
			)
		);
	}

	public function stepAlbums($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(id)
				FROM ' . $prefix . 'categories
				WHERE cattype = \'a\'
			');
		}

		$albums = $sDb->fetchAll(
			$sDb->limit('
				SELECT category.*
				FROM ' . $prefix . 'categories AS category
				WHERE category.id > ?
					AND cattype = \'a\'
				ORDER BY category.id ASC
			', $options['limit'])
			, $start);

		if (!$albums)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$this->_userIdMap = $this->_importModel->getUserIdsMapFromArray($albums, 'parent');
		foreach ($albums AS $album)
		{
			$next = $album['id'];
			if (!isset($this->_userIdMap[$album['parent']]))
			{
				continue;
			}

			$imported = $this->_importAlbum($album, $options);
			if ($imported)
			{
				$total++;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importAlbum(array $album, array $options)
	{
		$model = $this->_getMediaGalleryImportersModel();

		$db = $this->_sourceDb;
		$xfDb = XenForo_Application::getDb();

		$dates = $db->fetchCol('
			SELECT date
			FROM ' . $this->_prefix . 'photos
			WHERE cat = ?
			ORDER BY date ASC
		', $album['id']);

		$albumCreateDate = reset($dates);
		$albumLastUpdateDate = end($dates);

		$userId = $this->_userIdMap[$album['parent']];

		$user = $xfDb->fetchRow('
			SELECT user_id, username
			FROM xf_user
			WHERE user_id = ?
		', $userId);
		if (!$user)
		{
			return false;
		}

		$albumPrivacy = $album['private'] == 'yes' ? 'private' : 'public';

		$noTitle = new XenForo_Phrase('xengallery_imported_item');
		$xengalleryAlbum = array(
			'album_title' => $album['catname'] ? $this->_convertToUtf8($album['catname'], true) : $noTitle->render(),
			'album_description' => XenForo_Template_Helper_Core::helperSnippet(
				$this->_convertToUtf8($album['description'], true), 0, array('stripHtml' => true)
			),
			'album_create_date' => $albumCreateDate
				? $albumCreateDate
				: XenForo_Application::$time,
			'last_update_date' => $albumLastUpdateDate
				? $albumLastUpdateDate
				: XenForo_Application::$time,
			'media_cache' => array(),
			'album_state' => 'visible',
			'album_user_id' => $user['user_id'],
			'album_username' => $user['username'],
			'ip_id' => $model->getLatestIpIdFromUserId($user['user_id']),
			'album_media_count' => $album['photos'],
			'album_likes' => false,
			'album_like_users' => false
		);

		$importedAlbumId = $model->importAlbum($album['id'], $xengalleryAlbum, $albumPrivacy);

		return $importedAlbumId;
	}

	public function stepCategories($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 10,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(id)
				FROM ' . $prefix . 'categories
				WHERE cattype = \'c\'
			');
		}

		$albums = $sDb->fetchAll(
			$sDb->limit('
				SELECT category.*
				FROM ' . $prefix . 'categories AS category
				WHERE category.id > ?
					AND cattype = \'c\'
				ORDER BY category.id ASC
			', $options['limit'])
			, $start);

		if (!$albums)
		{
			return $this->_getNextCategoryStep();
		}

		$next = 0;
		$total = 0;

		foreach ($albums AS $album)
		{
			$next = $album['id'];

			$imported = $this->_importCategory($album, $options);
			if ($imported)
			{
				$total++;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _getNextCategoryStep()
	{
		return 'rebuildCategories';
	}

	public function stepRebuildCategories($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 10,
			'max' => false
		), $options);

		$xfDb = XenForo_Application::getDb();

		if ($options['max'] === false)
		{
			$options['max'] = $xfDb->fetchOne('
				SELECT MAX(category_id)
				FROM xengallery_category
			');
		}

		$categories = $xfDb->fetchAll(
			$xfDb->limit('
				SELECT category.*
				FROM xengallery_category AS category
				WHERE category.category_id > ?
				ORDER BY category.category_id ASC
			', $options['limit'])
			, $start
		);

		if (!$categories)
		{
			XenForo_Model::create('XenGallery_Model_Category')->rebuildCategoryStructure();
			return true;
		}

		$next = 0;
		$total = 0;

		$model = $this->_getMediaGalleryImportersModel();

		foreach ($categories AS $category)
		{
			$next = $category['category_id'];

			$mappedId = $model->mapCategoryId($category['parent_category_id']);
			if (!$mappedId)
			{
				continue;
			}

			$xfDb->update('xengallery_category', array('parent_category_id' => $mappedId), 'category_id = ' . $category['category_id']);
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importCategory(array $category, array $options)
	{
		$model = $this->_getMediaGalleryImportersModel();

		$xengalleryCategory = array(
			'category_title' => $this->_convertToUtf8($category['catname'], true),
			'category_description' => XenForo_Template_Helper_Core::helperSnippet(
				$this->_convertToUtf8($category['description'], true), 0, array('stripHtml' => true)
			),
			'upload_user_groups' => unserialize('a:1:{i:0;i:-1;}'),
			'view_user_groups' => unserialize('a:1:{i:0;i:-1;}'),
			'allowed_types' => unserialize('a:1:{i:0;s:3:"all";}'),
			'parent_category_id' => $category['parent'],
			'display_order' => $category['catorder'],
			'category_breadcrumb' => unserialize('a:0:{}'),
			'depth' => 0,
			'category_media_count' => $category['photos'],
			'field_cache' => unserialize('a:0:{}')
		);

		$importedCategoryId = $model->importCategory($category['id'], $xengalleryCategory);

		return $importedCategoryId;
	}

	public function stepMedia($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 5,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(id)
				FROM ' . $prefix . 'photos
			');
		}

		$media = $sDb->fetchAll(
			$sDb->limit('
				SELECT photo.*, category.parent,
					category.cattype, category.private
				FROM ' . $prefix . 'photos AS photo
				LEFT JOIN ' . $prefix . 'categories AS category ON
					(photo.cat = category.id)
				WHERE photo.id > ?
				ORDER BY photo.id
			', $options['limit'])
		, $start);

		if (!$media)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$this->_userIdMap = $this->_importModel->getUserIdsMapFromArray($media, 'userid');
		foreach ($media AS $item)
		{
			$next = $item['id'];
			if (!isset($this->_userIdMap[$item['userid']]))
			{
				continue;
			}

			$imported = $this->_importMedia($item, $options);
			if ($imported)
			{
				$total++;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importMedia(array $item, array $options)
	{
		$sDb = $this->_sourceDb;
		$xfDb = XenForo_Application::getDb();

		if (!$this->_fileRoot)
		{
			$this->_fileRoot = $sDb->fetchOne('SELECT setting FROM ' . $this->_prefix . 'settings WHERE varname = \'datafull\'');
		}

		$originalFilePath = sprintf('%s/%d/%s',
			$this->_fileRoot,
			$item['cat'],
			$item['bigimage']
		);
		if (!file_exists($originalFilePath))
		{
			return false;
		}

		$xenOptions = XenForo_Application::getOptions();

		$imageExtensions = preg_split('/\s+/', trim($xenOptions->xengalleryImageExtensions));
		$videoExtensions = preg_split('/\s+/', trim($xenOptions->xengalleryVideoExtensions));

		$extension = XenForo_Helper_File::getFileExtension($originalFilePath);
		if (in_array($extension, $imageExtensions))
		{
			$mediaType = 'image_upload';
		}
		else if (in_array($extension, $videoExtensions))
		{
			$mediaType = 'video_upload';
		}
		else
		{
			return false;
		}

		$tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
		copy($originalFilePath, $tempFile);

		$model = $this->_getMediaGalleryImportersModel();

		$albumId = 0;
		$categoryId = 0;
		$privacy = 'public';

		if ($item['cattype'] == 'a')
		{
			$albumId = $model->mapAlbumId($item['cat']);
			$privacy = $item['private'] == 'yes' ? 'private' : 'public';
		}
		elseif ($item['cattype'] == 'c')
		{
			$categoryId = $model->mapCategoryId($item['cat']);
			$privacy = 'category';
		}

		$userId = $this->_userIdMap[$item['userid']];
		$user = $xfDb->fetchRow('
			SELECT user_id, username
			FROM xf_user
			WHERE user_id = ?
		', $userId);
		if (!$user)
		{
			return false;
		}

		$xengalleryMedia = array(
			'media_title' => $this->_convertToUtf8($item['title'], true),
			'media_description' => XenForo_Template_Helper_Core::helperSnippet(
				$this->_convertToUtf8($item['description'], true), 0, array('stripHtml' => true)
			),
			'media_date' => $item['date'],
			'last_edit_date' => $item['lastpost'],
			'last_comment_date' => $item['lastpost'],
			'media_type' => $mediaType,
			'media_state' => 'visible',
			'album_id' => $albumId,
			'category_id' => $categoryId,
			'media_privacy' => $privacy,
			'attachment_id' => 0,
			'user_id' => $user['user_id'],
			'username' => $user['username'],
			'ip_id' => $model->getLatestIpIdFromUserId($user['user_id']),
			'likes' => 0,
			'like_users' => array(),
			'comment_count' => $item['numcom'],
			'rating_count' => 0,
			'media_view_count' => $item['views']
		);

		$xfAttachment = array(
			'data_id' => 0,
			'content_type' => 'xengallery_media',
			'content_id' => 0,
			'attach_date' => $item['date'],
			'temp_hash' => '',
			'unassociated' => 0,
			'view_count' => $item['views']
		);

		$xfAttachmentData = array(
			'user_id' => $user['user_id'],
			'upload_date' => $item['date'],
			'filename' => $item['bigimage'],
			'attach_count' => 1
		);

		$importedMediaId = $model->importMedia($item['id'], $tempFile, '', $xengalleryMedia, $xfAttachment, $xfAttachmentData);

		@unlink($tempFile);

		return $importedMediaId;
	}

	public function stepComments($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 10,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(id)
				FROM ' . $prefix . 'comments
				WHERE approved = 1
			');
		}

		$comments = $sDb->fetchAll($sDb->limit(
			'
				SELECT comment.*
				FROM ' . $prefix . 'comments AS comment
				WHERE comment.id > ?
				AND comment.approved = 1
				ORDER BY comment.id
			', $options['limit']
		), $start);
		if (!$comments)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$this->_userIdMap = $this->_importModel->getUserIdsMapFromArray($comments, 'userid');
		foreach ($comments AS $comment)
		{
			$next = $comment['id'];
			if (!isset($this->_userIdMap[$comment['userid']]))
			{
				continue;
			}

			$success = $this->_importComment($comment, $options);
			if ($success)
			{
				$total++;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importComment(array $comment, array $options)
	{
		$model = $this->_getMediaGalleryImportersModel();

		$xfDb = XenForo_Application::getDb();

		$userId = $this->_userIdMap[$comment['userid']];
		$user = $xfDb->fetchRow('
			SELECT user_id, username
			FROM xf_user
			WHERE user_id = ?
		', $userId);
		if (!$user)
		{
			return false;
		}

		$contentId = $model->mapMediaId($comment['photo']);
		$message = htmlspecialchars(strip_tags(html_entity_decode($comment['comment'])), ENT_COMPAT, null, false);
		if (!$contentId || !$message)
		{
			return false;
		}

		$xengalleryComment = array(
			'content_id' => $contentId,
			'content_type' => 'media',
			'message' => $this->_convertToUtf8($message, true),
			'user_id' => $user['user_id'],
			'username' => $user['username'],
			'comment_date' => $comment['date'],
			'comment_state' => 'visible',
			'likes' => 0,
			'like_users' => array()
		);

		$importedCommentId = $model->importComment($comment['id'], $xengalleryComment);

		return $importedCommentId;
	}

	public function stepContentTags($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 5,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(id)
				FROM ' . $prefix . 'photos
				WHERE keywords <> \'\'
			');
		}

		$tags = $sDb->fetchAll($sDb->limit(
			'
				SELECT *
				FROM ' . $prefix . 'photos
				WHERE keywords <> \'\'
					AND id > ?
				ORDER BY id ASC
			', $options['limit']
		), $start);
		if (!$tags)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		foreach ($tags AS $tag)
		{
			$next = $tag['id'];

			$keywords = preg_split('/[\ \n\,]+/', $tag['keywords']);
			$keywords = array_unique(array_map('utf8_strtolower', $keywords));

			foreach ($keywords AS $keyword)
			{
				$success = $this->_importTag(array('id' => $next, 'keyword' => $keyword), $options);
				if ($success)
				{
					$total++;
				}
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importTag(array $tag, array $options)
	{
		$model = $this->_getMediaGalleryImportersModel();
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		$mediaId = $model->mapMediaId($tag['id']);
		$media = $mediaModel->getMediaById($mediaId);

		if (!$media)
		{
			return false;
		}

		$importedTagId = $model->importTag($this->_convertToUtf8($tag['keyword'], true), 'xengallery_media', $mediaId, array(
			'add_user_id' => $media['user_id'],
			'add_date' => $media['media_date'],
			'visible' => true,
			'content_date' => $media['media_date']
		));

		return $importedTagId;
	}

	public function stepRatings($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;
		$noRatingsTable = $this->_config['noRatingsTable'];

		if ($options['max'] === false)
		{
			if ($noRatingsTable)
			{
				$options['max'] = $sDb->fetchOne('
					SELECT MAX(id)
					FROM ' . $prefix . 'comments
					WHERE userid > 0
						AND rating > 0
				');
			}
			else
			{
				$options['max'] = $sDb->fetchOne('
					SELECT MAX(id)
					FROM ' . $prefix . 'ratings
					WHERE userid > 0
						AND rating > 0
				');
			}
		}

		if ($noRatingsTable)
		{
			$ratings = $sDb->fetchAll($sDb->limit(
					'
				SELECT *
				FROM ' . $prefix . 'comments
				WHERE userid > 0
					AND rating > 0
					AND id > ?
				ORDER BY id ASC
			', $options['limit']
				), $start);
		}
		else
		{
			$ratings = $sDb->fetchAll($sDb->limit(
					'
				SELECT *
				FROM ' . $prefix . 'ratings
				WHERE userid > 0
					AND rating > 0
					AND id > ?
				ORDER BY id ASC
			', $options['limit']
				), $start);
		}
		if (!$ratings)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$this->_userIdMap = $this->_importModel->getUserIdsMapFromArray($ratings, 'userid');
		foreach ($ratings AS $rating)
		{
			$next = $rating['id'];
			if (!isset($this->_userIdMap[$rating['userid']]))
			{
				continue;
			}

			$success = $this->_importRating($rating, $options);
			if ($success)
			{
				$total++;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importRating(array $rating, array $options)
	{
		$xfDb = XenForo_Application::getDb();

		$model = $this->_getMediaGalleryImportersModel();

		$userId = $this->_userIdMap[$rating['userid']];
		$user = $xfDb->fetchRow('
			SELECT user_id, username
			FROM xf_user
			WHERE user_id = ?
		', $userId);

		if (!$user)
		{
			return false;
		}

		$contentId = $model->mapMediaId($rating['photo']);
		if (!$contentId)
		{
			return false;
		}

		$xengalleryRating = array(
			'content_id' => $contentId,
			'content_type' => 'media',
			'user_id' => $user['user_id'],
			'username' => $user['username'],
			'rating_date' => $rating['date'],
			'rating' => intval(round($rating['rating'] / 2))
		);

		$existingRating = $xfDb->fetchRow('
			SELECT *
			FROM xengallery_rating
			WHERE content_type = \'media\'
				AND content_id = ?
				AND user_id = ?
		', array($contentId, $user['user_id']));

		if ($existingRating)
		{
			return $xfDb->update('xengallery_rating', $xengalleryRating, 'rating_id = ' . $xfDb->quote($existingRating['rating_id']));
		}

		$importedRatingId = $model->importRating($rating['id'], $xengalleryRating, $this->_config['noRatingsTable']);

		return $importedRatingId;
	}

		/**
	 * @return XenGallery_Model_Importers
	 */
	protected function _getMediaGalleryImportersModel()
	{
		$retainKeys = false;
		if (!empty($this->_config['retain_keys']))
		{
			$retainKeys = true;
		}

		/* @var $model XenGallery_Model_Importers */
		$model = XenForo_Model::create('XenGallery_Model_Importers');

		$model->retainKeys($retainKeys);

		return $model;
	}
}
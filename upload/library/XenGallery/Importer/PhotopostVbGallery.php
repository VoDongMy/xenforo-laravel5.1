<?php

class XenGallery_Importer_PhotopostVbGallery extends XenForo_Importer_Abstract
{
	const OPTION_VIEW = 0x01;
	const OPTION_UPLOAD = 0x02;
	const OPTION_REPLY = 0x04;
	const OPTION_RATE = 0x08;

	/**
	 * Source database connection.
	 *
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_sourceDb;

	protected $_prefix;

	protected $_fileRoot = '';

	protected $_charset = 'windows-1252';

	protected $_config;

	protected $_userIdMap;

	protected $_imageTypeMap = array(
		'gif' => IMAGETYPE_GIF,
		'jpg' => IMAGETYPE_JPEG,
		'jpeg' => IMAGETYPE_JPEG,
		'jpe' => IMAGETYPE_JPEG,
		'png' => IMAGETYPE_PNG
	);

	protected $_fieldChoicesCache = array();

	public static function getName()
	{
		return ' XFMG: Import From Photopost - vBGallery for vBulletin';
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

			if (isset($config['validated']))
			{
				return true;
			}

			return $controller->responseView('XenGallery_ViewAdmin_Import_Config', 'xengallery_import_config_vbulletin', array(
				'config' => $config,
				'productName' => str_replace('XFMG: Import From ', '', self::getName()),
				'retainKeys' => $config['retain_keys'],
				'fileLocation' => $config['fileLocation']
			));
		}
		else
		{
			$configPath = getcwd() . '/includes/config.php';
			if (file_exists($configPath) && is_readable($configPath))
			{
				$config = array();
				include($configPath);

				$viewParams = array('input' => $config);
			}
			else
			{
				$viewParams = array('input' => array
				(
					'MasterServer' => array
					(
						'servername' => 'localhost',
						'port' => 3306,
						'username' => '',
						'password' => '',
					),
					'Database' => array
					(
						'dbname' => '',
						'tableprefix' => ''
					),
					'Mysqli' => array
					(
						'charset' => ''
					),
				),
					'productName' => str_replace('XFMG: Import From ', '', self::getName()),
				);
			}

			return $controller->responseView('XenForo_ViewAdmin_Import_vBulletin_Config', 'xengallery_import_config_vbulletin', $viewParams);
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

		if ($errors)
		{
			return $errors;
		}

		try
		{
			$db->query('
				SELECT userid
				FROM ' . $config['db']['prefix'] . 'user
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

		if (!$errors)
		{
			$defaultLanguageId = $db->fetchOne('
				SELECT value
				FROM ' . $config['db']['prefix'] . 'setting
				WHERE varname = \'languageid\'
			');
			$defaultCharset = $db->fetchOne('
				SELECT charset
				FROM ' . $config['db']['prefix'] . 'language
				WHERE languageid = ?
			', $defaultLanguageId);
			if (!$defaultCharset || str_replace('-', '', strtolower($defaultCharset)) == 'iso88591')
			{
				$config['charset'] = 'windows-1252';
			}
			else
			{
				$config['charset'] = strtolower($defaultCharset);
			}
		}

		if (isset($config['fileLocation']) && $config['fileLocation'])
		{
			if (!file_exists($config['fileLocation']))
			{
				$errors[] = new XenForo_Phrase('xengallery_specified_file_location_does_not_exist');
			}
		}

		return $errors;
	}

	protected function _bootstrap(array $config)
	{
		if ($this->_sourceDb)
		{
			return true;
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

		if (empty($config['db']['charset']))
		{
			$this->_sourceDb->query('SET character_set_results = NULL');
		}

		$this->_prefix = preg_replace('/[^a-z0-9_]/i', '', $config['db']['prefix']);

		if (!empty($config['charset']))
		{
			$this->_charset = $config['charset'];
		}

		if (!empty($config['fileLocation']))
		{
			$this->_fileRoot = $config['fileLocation'];
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
			'membercategories' => array(
				'title' => new XenForo_Phrase('xengallery_import_member_categories'),
				'depends' => array('albums', 'categories')
			),
			'media' => array(
				'title' => new XenForo_Phrase('xengallery_import_media'),
				'depends' => array('albums', 'categories', 'membercategories')
			),
			'comments' => array(
				'title' => new XenForo_Phrase('xengallery_import_comments'),
				'depends' => array('albums', 'categories', 'membercategories', 'media')
			),
			'contenttags' => array(
				'title' => new XenForo_Phrase('import_content_tags'),
				'depends' => array('albums', 'categories', 'membercategories', 'media')
			),
			'customfields' => array(
				'title' => new XenForo_Phrase('xengallery_import_custom_fields'),
				'depends' => array('albums', 'categories', 'membercategories', 'media')
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
				SELECT MAX(catid)
				FROM ' . $prefix . 'ppgal_categories
				WHERE catuserid > 0
					AND membercat = 0
			');
		}

		$albums = $sDb->fetchAll(
			$sDb->limit('
				SELECT *
				FROM ' . $prefix . 'ppgal_categories
				WHERE catid > ?
					AND catuserid > 0
					AND membercat = 0
				ORDER BY catid ASC
			', $options['limit'])
			, $start);

		if (!$albums)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$this->_userIdMap = $this->_importModel->getUserIdsMapFromArray($albums, 'catuserid');
		foreach ($albums AS $album)
		{
			$next = $album['catid'];
			if (!isset($this->_userIdMap[$album['catuserid']]))
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
			SELECT dateline
			FROM ' . $this->_prefix . 'ppgal_images
			WHERE catid = ?
			ORDER BY dateline ASC
		', $album['catid']);

		$albumCreateDate = reset($dates);
		$albumLastUpdateDate = end($dates);

		$userId = $this->_userIdMap[$album['catuserid']];
		$user = $xfDb->fetchRow('
			SELECT user_id, username
			FROM xf_user
			WHERE user_id = ?
		', $userId);
		if (!$user)
		{
			return false;
		}

		$albumPrivacy = $album['useroptions'] & self::OPTION_VIEW ? 'public' : 'private';
		$xengalleryAlbum = array(
			'album_title' => $this->_convertToUtf8($album['title'], true),
			'album_description' => $this->_convertToUtf8($album['description'], true),
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
			'album_like_users' => array(),
			'album_media_count' => $album['imagecount'],
			'album_likes' => false,
			'album_like_users' => false
		);

		$importedAlbumId = $model->importAlbum($album['catid'], $xengalleryAlbum, $albumPrivacy);

		return $importedAlbumId;
	}

	public function stepCategories($start, array $options)
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
				SELECT MAX(catid)
				FROM ' . $prefix . 'ppgal_categories
				WHERE catuserid = 0
					AND membercat = 0
			');
		}

		$categories = $sDb->fetchAll(
			$sDb->limit('
				SELECT *
				FROM ' . $prefix . 'ppgal_categories
				WHERE catid > ?
					AND catuserid = 0
					AND membercat = 0
				ORDER BY catid ASC
			', $options['limit'])
			, $start);

		if (!$categories)
		{
			return $this->_getNextCategoryStep();
		}

		$next = 0;
		$total = 0;

		foreach ($categories AS $category)
		{
			$next = $category['catid'];

			$imported = $this->_importCategory($category, $options);
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
			'category_title' => $this->_convertToUtf8($category['title'], true),
			'category_description' => XenForo_Template_Helper_Core::helperSnippet(
				$this->_convertToUtf8($category['description'], true), 0, array('stripHtml' => true)
			),
			'upload_user_groups' => $category['hasimages'] ? unserialize('a:1:{i:0;i:-1;}') : unserialize('a:0:{}'),
			'view_user_groups' => unserialize('a:1:{i:0;i:-1;}'),
			'allowed_types' => unserialize('a:1:{i:0;s:3:"all";}'),
			'parent_category_id' => $category['parent'],
			'display_order' => $category['displayorder'],
			'category_breadcrumb' => unserialize('a:0:{}'),
			'depth' => 0,
			'category_media_count' => $category['imagecount'],
			'field_cache' => unserialize('a:0:{}')
		);

		$importedCategoryId = $model->importCategory($category['catid'], $xengalleryCategory);

		return $importedCategoryId;
	}

	public function stepMemberCategories($start, array $options)
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
				SELECT MAX(img.userid)
				FROM ' . $prefix . 'ppgal_categories AS cat
				LEFT JOIN ' . $prefix . 'ppgal_images AS img ON
					(img.catid = cat.catid)
				WHERE cat.membercat = 1
					AND cat.catuserid = 0
			');
		}

		$categories = $sDb->fetchAll(
			$sDb->limit('
				SELECT img.userid, cat.catid
				FROM ' . $prefix . 'ppgal_categories AS cat
				LEFT JOIN ' . $prefix . 'ppgal_images AS img ON
					(img.catid = cat.catid)
				WHERE img.userid > ?
					AND cat.catuserid = 0
					AND cat.membercat = 1
				GROUP BY cat.catid, img.userid
				ORDER BY cat.catid ASC
		', $options['limit'])
		, $start);
		if (!$categories)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$xfDb = XenForo_Application::getDb();

		$this->_userIdMap = $this->_importModel->getUserIdsMapFromArray($categories, 'userid');
		foreach ($categories AS $category)
		{
			$next = $category['userid'];
			if (!isset($this->_userIdMap[$category['userid']]))
			{
				continue;
			}

			$userId = $this->_userIdMap[$category['userid']];
			$user = $xfDb->fetchRow('
				SELECT user_id, username
				FROM xf_user
				WHERE user_id = ?
			', $userId);
			if (!$user)
			{
				continue;
			}

			$titlePhrase = new XenForo_Phrase('xengallery_member_album_by_x', array('name' => $user['username']));
			$albumTitle = $titlePhrase->render();

			$exists = $xfDb->fetchOne('
				SELECT album_id
				FROM xengallery_album
				WHERE album_title = ?
			', $albumTitle);

			if ($exists)
			{
				continue;
			}

			$imported = $this->_importMemberCatToAlbum($category, $user, $albumTitle);
			if ($imported)
			{
				$total++;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importMemberCatToAlbum(array $category, array $user, $albumTitle)
	{
		$sDb = $this->_sourceDb;
		$model = $this->_getMediaGalleryImportersModel();

		$dates = $sDb->fetchCol('
			SELECT dateline
			FROM ' . $this->_prefix . 'ppgal_images
			WHERE catid = ?
				AND userid = ?
			ORDER BY dateline ASC
		', array($category['catid'], $category['userid']));

		$albumCreateDate = reset($dates);
		$albumLastUpdateDate = end($dates);

		$xengalleryAlbum = array(
			'album_title' => $albumTitle,
			'album_description' => '',
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
			'album_media_count' => 0, // advised to rebuild after import anyway
			'album_likes' => false,
			'album_like_users' => false
		);

		$oldId = array(
			'c' => $category['catid'],
			'u' => $category['userid']
		);

		$importedAlbumId = $model->importAlbum(serialize($oldId), $xengalleryAlbum);

		return $importedAlbumId;
	}

	public function stepMedia($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 5,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		$imageTypes = $sDb->quote(array_keys($this->_imageTypeMap));

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(imageid)
				FROM ' . $prefix . 'ppgal_images
				WHERE extension IN (' . $imageTypes . ')
			');
		}

		$media = $sDb->fetchAll(
			$sDb->limit('
				SELECT image.*, category.parent, category.membercat,
					category.catuserid, category.useroptions, image.imageid
				FROM ' . $prefix . 'ppgal_images AS image
				LEFT JOIN ' . $prefix . 'ppgal_categories AS category ON
					(image.catid = category.catid)
				WHERE image.imageid > ?
					AND extension IN (' . $imageTypes . ')
				ORDER BY image.imageid
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
			$next = $item['imageid'];
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
			$this->_fileRoot = $sDb->fetchOne('SELECT value FROM ' . $this->_prefix . 'ppgal_setting WHERE varname = \'gallery_filedirectory\'');
		}

		$originalFilePath = sprintf('%s/%s/%s',
			$this->_fileRoot,
			implode('/', str_split($item['userid'])),
			$item['originalname'] ? $item['originalname'] : $item['filename']
		);
		if (!file_exists($originalFilePath))
		{
			return false;
		}

		$tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
		copy($originalFilePath, $tempFile);

		$model = $this->_getMediaGalleryImportersModel();

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

		$albumId = 0;
		$categoryId = 0;

		if ($item['catuserid'] > 0)
		{
			$albumId = $model->mapAlbumId($item['catid']);
			$albumPrivacy = $item['useroptions'] & self::OPTION_VIEW ? 'public' : 'private';
		}
		elseif ($item['catuserid'] == 0 && !$item['membercat'])
		{
			$categoryId = $model->mapCategoryId($item['catid']);
			$albumPrivacy = 'category';
		}
		elseif ($item['catuserid'] == 0 && $item['membercat'])
		{
			$titlePhrase = new XenForo_Phrase('xengallery_member_album_by_x', array('name' => $user['username']));
			$albumTitle = $titlePhrase->render();

			$albumId = $xfDb->fetchOne('
				SELECT album_id
				FROM xengallery_album
				WHERE album_title = ?
			', $albumTitle);

			if (!$albumId)
			{
				return false;
			}

			$albumPrivacy = 'public';
		}

		$xengalleryMedia = array(
			'media_title' => $this->_convertToUtf8($item['title'], true),
			'media_description' => $this->_convertToUtf8($item['description'], true),
			'media_date' => $item['dateline'],
			'last_edit_date' => $item['lastpostdateline'],
			'last_comment_date' => $item['lastpostdateline'],
			'media_type' => 'image_upload',
			'media_state' => 'visible',
			'album_id' => $albumId,
			'category_id' => $categoryId,
			'media_privacy' => $albumPrivacy,
			'attachment_id' => 0,
			'user_id' => $user['user_id'],
			'username' => $user['username'],
			'ip_id' => $model->getLatestIpIdFromUserId($user['user_id']),
			'likes' => 0,
			'like_users' => array(),
			'comment_count' => $item['posts'],
			'rating_count' => 0,
			'media_view_count' => $item['views']
		);

		$xfAttachment = array(
			'data_id' => 0,
			'content_type' => 'xengallery_media',
			'content_id' => 0,
			'attach_date' => $item['dateline'],
			'temp_hash' => '',
			'unassociated' => 0,
			'view_count' => $item['views']
		);

		$xfAttachmentData = array(
			'user_id' => $user['user_id'],
			'upload_date' => $item['dateline'],
			'filename' => $item['filename'],
			'attach_count' => 1
		);

		$importedMediaId = $model->importMedia($item['imageid'], $tempFile, '', $xengalleryMedia, $xfAttachment, $xfAttachmentData);

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
				SELECT MAX(postid)
				FROM ' . $prefix . 'ppgal_posts
				WHERE visible = 1
			');
		}

		$comments = $sDb->fetchAll($sDb->limit(
				'
				SELECT *
					FROM ' . $prefix . 'ppgal_posts
				WHERE postid > ?
					AND visible = 1
				ORDER BY postid
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
			$next = $comment['postid'];
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

		$xengalleryComment = array(
			'content_id' => $model->mapMediaId($comment['imageid']),
			'content_type' => 'media',
			'message' => $this->_convertToUtf8(htmlspecialchars(strip_tags(html_entity_decode($comment['pagetext'])), ENT_COMPAT, null, false), true),
			'user_id' => $user['user_id'],
			'username' => $user['username'],
			'comment_date' => $comment['dateline'],
			'comment_state' => $comment['visible'] ? 'visible' : 'moderated',
			'likes' => 0,
			'like_users' => array()
		);

		$importedCommentId = $model->importComment($comment['postid'], $xengalleryComment);

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
				SELECT MAX(imageid)
				FROM ' . $prefix . 'ppgal_images
				WHERE keywords <> \'\'
			');
		}

		$tags = $sDb->fetchAll($sDb->limit(
				'
					SELECT *
					FROM ' . $prefix . 'ppgal_images
				WHERE keywords <> \'\'
					AND imageid > ?
				ORDER BY imageid ASC
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
			$next = $tag['imageid'];

			$keywords = explode(' ', $tag['keywords']);
			foreach ($keywords AS $keyword)
			{
				$success = $this->_importTag(array('imageid' => $next, 'keyword' => $keyword), $options);
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

		$mediaId = $model->mapMediaId($tag['imageid']);
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

	public function stepCustomFields($start, array $options)
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
				SELECT MAX(fieldid)
				FROM ' . $prefix . 'ppgal_customfields
			');
		}

		$fields = $sDb->fetchAll($sDb->limit(
					'
				SELECT *
				FROM ' . $prefix . 'ppgal_customfields
				WHERE fieldid > ?
				ORDER BY fieldid ASC
			', $options['limit']
		), $start);
		if (!$fields)
		{
			return $this->_getNextCustomFieldStep();
		}

		$next = 0;
		$total = 0;

		foreach ($fields AS $field)
		{
			$next = $field['fieldid'];

			$success = $this->_importField($field, $options);
			if ($success)
			{
				$total++;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _getNextCustomFieldStep()
	{
		return 'fieldValues';
	}

	public function stepFieldValues($start, $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(imgid)
				FROM ' . $prefix . 'ppgal_customfields_entries
			');
		}

		$media = $sDb->fetchAll(
			$sDb->limit('
				SELECT *
				FROM ' . $prefix . 'ppgal_customfields_entries
				WHERE imgid > ?
				ORDER BY imgid
			', $options['limit'])
			, $start);

		if (!$media)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		foreach ($media AS $item)
		{
			$next = $item['imgid'];

			$imported = $this->_importFieldValues($item);
			if ($imported)
			{
				$total++;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importField(array $field, array $options)
	{
		$model = $this->_getMediaGalleryImportersModel();

		$xengalleryField = array(
			'field_id' => $field['fieldid'],
			'display_group' => 'below_media',
			'display_order' => $field['displayorder'],
			'field_type' => $field['type'] == 'text' ? 'textbox' : $field['type'],
			'match_type' => 'none',
			'match_regex' => '',
			'max_length' => $field['maxlength'],
			'album_use' => 1
		);

		$fieldChoices = preg_split('/\r?\n/', trim($field['options']), -1, PREG_SPLIT_NO_EMPTY);
		if (!is_array($fieldChoices))
		{
			$fieldChoices = array();
		}
		else
		{
			$this->_session->setExtraData('fieldChoices', $field['fieldid'], $fieldChoices);
		}

		$categoryIds = XenForo_Application::getDb()->fetchCol('
			SELECT category_id
			FROM xengallery_category
		');

		$valuesGrouped = array();

		return $model->importField($xengalleryField, $fieldChoices, $valuesGrouped, $field['title'], $field['description'], $categoryIds);
	}

	protected function _importFieldValues(array $item)
	{
		$model = $this->_getMediaGalleryImportersModel();
		$sDb = $this->_sourceDb;

		$fieldIds = $sDb->fetchPairs('
			SELECT fieldid, type
			FROM ' . $this->_prefix . 'ppgal_customfields
		');

		foreach (array_keys($fieldIds) AS $fieldId)
		{
			$valuesGrouped = array();

			if (!empty($item['field' . $fieldId]))
			{
				$value = $item['field' . $fieldId];

				$fieldChoices = $this->_session->getExtraData('fieldChoices', $fieldId);
				if ($fieldChoices)
				{
					$explodedValue = explode(',', $value);
					if (is_array($explodedValue))
					{
						$value = array();
						foreach ($explodedValue AS $stringValue)
						{
							$stringKey = array_search(trim($stringValue), $fieldChoices);

							switch ($fieldIds[$fieldId])
							{
								case 'checkbox':
									$value[] = strval($stringKey);
									break;

								default:
									$value = $stringKey;
							}
						}
					}
				}

				if (is_array($value))
				{
					$value = serialize($value);
				}

				$valuesGrouped[$model->mapMediaId($item['imgid'])][] = $value;
			}

			$model->importFieldValues($valuesGrouped, $fieldId);
		}

		return true;
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
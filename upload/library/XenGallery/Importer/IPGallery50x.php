<?php

class XenGallery_Importer_IPGallery50x extends XenForo_Importer_Abstract
{
	/**
	 * Source database connection.
	 *
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_sourceDb;

	protected $_prefix;

	protected $_charset = 'windows-1252';

	protected $_config;

	protected $_albumTypes = array(
		1 => 'public',
		2 => 'private',
		3 => 'followed'
	);

	protected $_userIdMap;

	public static function getName()
	{
		return ' XFMG: Import From IP.Gallery 5.0.x (Beta)';
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
			$viewParams = array('input' => array
			(
				'sql_host' => 'localhost',
				'sql_port' => 3306,
				'sql_user' => '',
				'sql_pass' => '',
				'sql_database' => '',
				'sql_tbl_prefix' => '',

				//'ipboard_path' => getcwd(),
				'ipboard_path' => $_SERVER['DOCUMENT_ROOT'],
			));

			$configPath = getcwd() . '/conf_global.php';
			if (file_exists($configPath))
			{
				include($configPath);

				$viewParams['input'] = array_merge($viewParams['input'], $INFO);
			}

			return $controller->responseView('XenGallery_ViewAdmin_Import_IPGallery50x_Config', 'xengallery_import_config_ipgallery', $viewParams);
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
				SELECT member_id
				FROM ' . $config['db']['prefix'] . 'members
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

		if (!empty($config['ipboard_path']))
		{
			if (!file_exists($config['ipboard_path']) || !is_dir($config['ipboard_path'] . '/uploads'))
			{
				$errors[] = new XenForo_Phrase('error_could_not_find_uploads_directory_at_specified_path');
			}
		}

		if (!$errors)
		{
			$defaultCharset = $db->fetchOne("
				SELECT IF(conf_value = '' OR conf_value IS NULL, conf_default, conf_value)
				FROM {$config['db']['prefix']}core_sys_conf_settings
				WHERE conf_key = 'gb_char_set'
			");
			if (!$defaultCharset || str_replace('-', '', strtolower($defaultCharset)) == 'iso88591')
			{
				$config['charset'] = 'windows-1252';
			}
			else
			{
				$config['charset'] = strtolower($defaultCharset);
			}
		}

		return $errors;
	}

	protected function _bootstrap(array $config)
	{
		if ($this->_sourceDb)
		{
			// already run
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
		if (empty($config['db']['charset']))
		{
			$this->_sourceDb->query('SET character_set_results = NULL');
		}

		$this->_prefix = preg_replace('/[^a-z0-9_]/i', '', $config['db']['prefix']);

		if (!empty($config['charset']))
		{
			$this->_charset = $config['charset'];
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
				'depends' => array('albums')
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
				SELECT MAX(album_id)
				FROM ' . $prefix . 'gallery_albums
			');
		}

		$albums = $sDb->fetchAll(
			$sDb->limit('
				SELECT album.*, member.name
				FROM ' . $prefix . 'gallery_albums AS album
				INNER JOIN ' . $prefix . 'members AS member ON
					(album.album_owner_id = member.member_id)
				WHERE album.album_id > ?
				ORDER BY album.album_id ASC
			', $options['limit'])
		, $start);

		if (!$albums)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$this->_userIdMap = $this->_importModel->getUserIdsMapFromArray($albums, 'album_owner_id');
		foreach ($albums AS $album)
		{
			$next = $album['album_id'];
			if (!isset($this->_userIdMap[$album['album_owner_id']]))
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
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		$userId = $this->_userIdMap[$album['album_owner_id']];
		$shareUsers = array();

		$albumPrivacy = isset($this->_albumTypes[$album['album_type']]) ? $this->_albumTypes[$album['album_type']] : 1;
		if ($albumPrivacy == 'followed')
		{
			/** @var $userModel XenForo_Model_User */
			$userModel = XenForo_Model::create('XenForo_Model_User');

			$followedUsers = $userModel->getFollowedUserProfiles($userId, 0, 'user.user_id');
			$userIds = array_keys($followedUsers);

			$shareUsers = $userModel->getUsersByIds($userIds);
			if (!$shareUsers)
			{
				$albumPrivacy = 'private'; // bail out of being a followed album if not following anyone...
			}
		}

		$albumCreateDate = $sDb->fetchOne('SELECT image_date FROM ' . $prefix . 'gallery_images WHERE image_album_id = ? ORDER BY image_date ASC LIMIT 1', $album['album_id']);

		$xengalleryAlbum = array(
			'album_title' => $this->_convertToUtf8($album['album_name'], true),
			'album_description' => $this->_convertToUtf8($album['album_description'], true),
			'album_create_date' => $albumCreateDate ? $albumCreateDate : time(),
			'last_update_date' => $album['album_last_img_date'],
			'media_cache' => array(),
			'album_state' => 'visible',
			'album_user_id' => $userId,
			'album_username' => $this->_convertToUtf8($album['name'], true),
			'ip_id' => $model->getLatestIpIdFromUserId($userId),
			'album_media_count' => $album['album_count_imgs'],
			'album_likes' => false,
			'album_like_users' => false
		);

		$importedAlbumId = $model->importAlbum($album['album_id'], $xengalleryAlbum, $albumPrivacy, '', $shareUsers);

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
				SELECT MAX(category_id)
				FROM ' . $prefix . 'gallery_categories
			');
		}

		$categories = $sDb->fetchAll(
			$sDb->limit('
				SELECT *
				FROM ' . $prefix . 'gallery_categories
				WHERE category_id > ?
				ORDER BY category_id ASC
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
			$next = $category['category_id'];
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
			'category_title' => $this->_convertToUtf8($category['category_name'], true),
			'category_description' => XenForo_Template_Helper_Core::helperSnippet(
				$this->_convertToUtf8($category['category_description'], true), 0, array('stripHtml' => true)
			),
			'upload_user_groups' => $category['category_type'] == 2 ? unserialize('a:1:{i:0;i:-1;}') : unserialize('a:0:{}'),
			'view_user_groups' => unserialize('a:1:{i:0;i:-1;}'),
			'allowed_types' => unserialize('a:1:{i:0;s:3:"all";}'),
			'parent_category_id' => $category['category_parent_id'],
			'display_order' => $category['category_position'],
			'category_breadcrumb' => unserialize('a:0:{}'),
			'depth' => 0,
			'category_media_count' => $category['category_type'] ? $category['category_count_imgs'] : 0,
			'field_cache' => unserialize('a:0:{}')
		);

		$importedCategoryId = $model->importCategory($category['category_id'], $xengalleryCategory);

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
				SELECT MAX(image_id)
				FROM ' . $prefix . 'gallery_images
			');
		}

		$media = $sDb->fetchAll(
			$sDb->limit('
				SELECT image.*, member.name
				FROM ' . $prefix . 'gallery_images AS image
				INNER JOIN ' . $prefix . 'members AS member ON
					(image.image_member_id = member.member_id)
				WHERE image.image_id > ?
					AND image.image_approved = 1
				ORDER BY image.image_id ASC
			', $options['limit'])
		, $start);

		if (!$media)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$this->_userIdMap = $this->_importModel->getUserIdsMapFromArray($media, 'image_member_id');
		foreach ($media AS $item)
		{
			$next = $item['image_id'];
			if (!isset($this->_userIdMap[$item['image_member_id']]))
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
		$originalFilePath = $this->_config['ipboard_path'] . '/uploads/' . $item['image_directory'] . '/' . $item['image_masked_file_name'];
		if (!file_exists($originalFilePath))
		{
			return false;
		}

		$tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
		copy($originalFilePath, $tempFile);

		/** @var XenGallery_Model_Album $albumModel */
		$albumModel = XenForo_Model::create('XenGallery_Model_Album');
		$model = $this->_getMediaGalleryImportersModel();

		$mediaPrivacy = 'private';
		$albumId = 0;
		$categoryId = 0;

		if ($item['image_album_id'] > 0)
		{
			$albumId = $model->mapAlbumId($item['image_album_id']);
			$permission = $albumModel->getUserAlbumPermission($albumId, 'view');
			if ($permission)
			{
				$mediaPrivacy = $permission['access_type'];
			}
		}
		else if ($item['image_album_id'] == 0 && $item['image_category_id'] > 0)
		{
			$categoryId = $model->mapCategoryId($item['image_category_id']);
			$mediaPrivacy = 'category';
		}

		$userId = $this->_userIdMap[$item['image_member_id']];
		$xengalleryMedia = array(
			'media_title' => $this->_convertToUtf8($item['image_caption'], true),
			'media_description' => XenForo_Template_Helper_Core::helperSnippet(
				$this->_convertToUtf8($item['image_description'], true), 0, array('stripHtml' => true)
			),
			'media_date' => $item['image_date'],
			'last_edit_date' => $item['image_date'],
			'last_comment_date' => $item['image_last_comment'],
			'media_type' => 'image_upload',
			'media_state' => 'visible',
			'album_id' => $albumId,
			'category_id' => $categoryId,
			'media_privacy' => $mediaPrivacy,
			'attachment_id' => 0,
			'user_id' => $userId,
			'username' => $item['name'],
			'ip_id' => $model->getLatestIpIdFromUserId($userId),
			'likes' => 0,
			'like_users' => array(),
			'comment_count' => $item['image_comments'],
			'rating_count' => 0,
			'media_view_count' => $item['image_views']
		);

		$xfAttachment = array(
			'data_id' => 0,
			'content_type' => 'xengallery_media',
			'content_id' => 0,
			'attach_date' => $item['image_date'],
			'temp_hash' => '',
			'unassociated' => 0,
			'view_count' => $item['image_views']
		);

		$xfAttachmentData = array(
			'user_id' => $userId,
			'upload_date' => $item['image_date'],
			'filename' => utf8_substr($this->_convertToUtf8($item['image_file_name']), 0, 100),
			'attach_count' => 1
		);

		$importedMediaId = $model->importMedia($item['image_id'], $tempFile, '', $xengalleryMedia, $xfAttachment, $xfAttachmentData);

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
				SELECT MAX(comment_id)
				FROM ' . $prefix . 'gallery_comments
			');
		}

		$comments = $sDb->fetchAll($sDb->limit(
			'
				SELECT comment.*, member.name
				FROM ' . $prefix . 'gallery_comments AS comment
				INNER JOIN ' . $prefix . 'members AS member ON
					(comment.comment_author_id = member.member_id)
				WHERE comment.comment_id > ?
					AND comment.comment_approved = 1
				ORDER BY comment.comment_id ASC
			', $options['limit']
		), $start);

		if (!$comments)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$this->_userIdMap = $this->_importModel->getUserIdsMapFromArray($comments, 'comment_author_id');
		foreach ($comments AS $comment)
		{
			$next = $comment['comment_id'];
			if (!isset($this->_userIdMap[$comment['comment_author_id']]))
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

	protected function _importComment($comment, array $options)
	{
		$model = $this->_getMediaGalleryImportersModel();

		$userId = $this->_userIdMap[$comment['comment_author_id']];
		$xengalleryComment = array(
			'content_id' => $model->mapMediaId($comment['comment_img_id']),
			'content_type' => 'media',
			'message' => $message = $this->_parseIPBoardBbCode($comment['comment_text']),
			'user_id' => $userId,
			'username' => $comment['name'],
			'ip_id' => $model->getLatestIpIdFromUserId($userId),
			'comment_date' => $comment['comment_post_date'],
			'comment_state' => 'visible',
			'likes' => 0,
			'like_users' => array()
		);

		$importedCommentId = $model->importComment($comment['comment_id'], $xengalleryComment);

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
				SELECT MAX(tag_id)
				FROM ' . $prefix . 'core_tags
				WHERE tag_meta_app = \'gallery\'
					AND tag_meta_area = \'images\'
			');
		}

		$tags = $sDb->fetchAll($sDb->limit(
			'
					SELECT *
					FROM ' . $prefix . 'core_tags
				WHERE tag_meta_app = \'gallery\'
					AND tag_meta_area = \'images\'
					AND tag_id > ?
				ORDER BY tag_id ASC
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
			$next = $tag['tag_id'];

			$success = $this->_importTag($tag, $options);
			if ($success)
			{
				$total++;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importTag(array $tag, array $options)
	{
		$model = $this->_getMediaGalleryImportersModel();
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		$mediaId = $model->mapMediaId($tag['tag_meta_id']);
		$media = $mediaModel->getMediaById($mediaId);

		if (!$media)
		{
			return false;
		}

		$importedTagId = $model->importTag($this->_convertToUtf8($tag['tag_text'], true), 'xengallery_media', $mediaId, array(
			'add_user_id' => $media['user_id'],
			'add_date' => $tag['tag_added'],
			'visible' => ($media['media_state'] == 'visible'),
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

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(rate_id)
				FROM ' . $prefix . 'gallery_ratings
			');
		}

		$ratings = $sDb->fetchAll($sDb->limit(
			'
				SELECT rating.*, member.name
				FROM ' . $prefix . 'gallery_ratings AS rating
				INNER JOIN ' . $prefix . 'members AS member ON
					(rating.rate_member_id = member.member_id)
				WHERE rating.rate_id > ?
				ORDER BY rating.rate_id ASC
			', $options['limit']
		), $start);

		if (!$ratings)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$this->_userIdMap = $this->_importModel->getUserIdsMapFromArray($ratings, 'rate_member_id');
		foreach ($ratings AS $rating)
		{
			$next = $rating['rate_id'];
			if (!isset($this->_userIdMap[$rating['rate_member_id']]))
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
		$model = $this->_getMediaGalleryImportersModel();

		if ($rating['rate_type'] == 'image')
		{
			$contentId = $model->mapMediaId($rating['rate_type_id']);
			$contentType = 'media';
		}
		else
		{
			$contentId = $model->mapAlbumId($rating['rate_type_id']);
			$contentType = 'album';
		}

		if (!$contentId)
		{
			return false;
		}

		$userId = $this->_userIdMap[$rating['rate_member_id']];

		$xengalleryRating = array(
			'content_id' => $contentId,
			'content_type' => $contentType,
			'user_id' => $userId,
			'username' => $rating['name'],
			'rating_date' => $rating['rate_date'],
			'rating' => intval($rating['rate_rate'])
		);

		$xfDb = XenForo_Application::getDb();

		$existingRating = $xfDb->fetchRow('
			SELECT *
			FROM xengallery_rating
			WHERE content_type = ?
				AND content_id = ?
				AND user_id = ?
		', array($contentType, $contentId, $userId));

		if ($existingRating)
		{
			return $xfDb->update('xengallery_rating', $xengalleryRating, 'rating_id = ' . $xfDb->quote($existingRating['rating_id']));
		}

		$importedRatingId = $model->importRating($rating['rate_id'], $xengalleryRating);

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

	// IPB data handling functions

	/**
	 * Remove HTML line breaks and UTF-8 conversion
	 *
	 * @param string $message
	 *
	 * @return string
	 */
	protected function _parseIPBoardText($message)
	{
		// Handle HTML line breaks
		$message = preg_replace('/<br( \/)?>(\s*)/si', "\n", $message);
		$message = str_replace('&nbsp;' , ' ', $message);

		return $this->_convertToUtf8($message, true);
	}

	/**
	 * Parse out HTML smilies and other stuff we can't use from IP.Board BB code
	 *
	 * @param string $message
	 * @param boolean Auto-link URLs in IP.Board messages
	 *
	 * @return string
	 */
	protected function _parseIPBoardBbCode($message, $autoLink = true)
	{
		$message = $this->_parseIPBoardText($message);

		// handle the IPB media format
		if (stripos($message, '[media') !== false)
		{
			$message = $this->_parseIPBoardMediaCode($message);
		}

		$search = $this->_getIPBoardBBCodeReplacements();

		return strip_tags(preg_replace(array_keys($search), $search, $message));
	}

	protected function _getIPBoardBBCodeReplacements()
	{
		return array(
			// HTML image <img /> smilies
			"/<img\s+src='([^']+)'\s+class='bbc_emoticon'\s+alt='([^']+)'\s+\/>/siU"
			=> '\2',
			"/<img[^>]+src=(\"|')[^\"']+(\"|')[^>]*emoid=(\"|')([^\"']+)(\"|')[^>]*>/siU"
			=> '\4',

			// translate attachments to something resembling our format in all cases (for quoted content in particular)
			"/\[attachment=(\d+):[^\]]+\]/siU"
			=> '[ATTACH]\1.IPB[/ATTACH]',

			// strip anything after a comma in [FONT]
			'/\[(font)=(\'|"|)([^,\]]+)(,[^\]]*)(\2)\]/siU'
			=> '[\1=\2\3\2]',

			'#<span [^>]*style="color:\s*([^";\\]]+?)[^"]*"[^>]*>(.*)</span>#siU' => '[COLOR=\\1]\\2[/COLOR]',
			'#<span [^>]*style="font-family:\s*([^";\\],]+?)[^"]*"[^>]*>(.*)</span>#siU' => '[FONT=\\1]\\2[/FONT]',
			'#<span [^>]*style="font-size:\s*([^";\\]]+?)[^"]*"[^>]*>(.*)</span>#siU' => '[SIZE=\\1]\\2[/SIZE]',
			'#<span[^>]*>(.*)</span>#siU' => '\\1',
			'#<(strong|b)>(.*)</\\1>#siU' => '[B]\\2[/B]',
			'#<(em|i)>(.*)</\\1>#siU' => '[I]\\2[/I]',
			'#<(u)>(.*)</\\1>#siU' => '[U]\\2[/U]',
			'#<(strike)>(.*)</\\1>#siU' => '[S]\\2[/S]',
			'#<a [^>]*href=(\'|")([^"\']+)\\1[^>]*>(.*)</a>#siU' => '[URL="\\2"]\\3[/URL]',
			'#<img [^>]*src="([^"]+)"[^>]*>#' => '[IMG]\\1[/IMG]',
			'#<img [^>]*src=\'([^\']+)\'[^>]*>#' => '[IMG]\\1[/IMG]',

			'#<(p|div) [^>]*style="text-align:\s*left;?">(.*)</\\1>(\r?\n)??#siU' => "[LEFT]\\2[/LEFT]\n",
			'#<(p|div) [^>]*style="text-align:\s*center;?">(.*)</\\1>(\r?\n)??#siU' => "[CENTER]\\2[/CENTER]\n",
			'#<(p|div) [^>]*style="text-align:\s*right;?">(.*)</\\1>(\r?\n)??#siU' => "[RIGHT]\\2[/RIGHT]\n",
			'#<(p|div) [^>]*class="bbc_left"[^>]*>(.*)</\\1>(\r?\n)??#siU' => "[LEFT]\\2[/LEFT]\n",
			'#<(p|div) [^>]*class="bbc_center"[^>]*>(.*)</\\1>(\r?\n)??#siU' => "[CENTER]\\2[/CENTER]\n",
			'#<(p|div) [^>]*class="bbc_right"[^>]*>(.*)</\\1>(\r?\n)??#siU' => "[RIGHT]\\2[/RIGHT]\n",

			'#<ul[^>]*>(.*)</ul>(\r?\n)??#siU' => "[LIST]\\1[/LIST]\n",
			'#<ol[^>]*>(.*)</ol>(\r?\n)??#siU' => "[LIST=1]\\1[/LIST]\n",
			'#<li[^>]*>(.*)</li>(\r?\n)??#siU' => "[*]\\1\n",

			'#<blockquote [^>]*class="ipsBlockquote"\s+data-author="([^"]+)"[^>]*>(.*)</blockquote>(\r?\n)??#siU' => '[QUOTE=\\1]\\2[/QUOTE]',
			'#<blockquote [^>]*class="ipsBlockquote"[^>]*>(.*)</blockquote>(\r?\n)??#siU' => '[QUOTE]\\1[/QUOTE]',

			'#<(p|pre)[^>]*>(&nbsp;|' . chr(0xC2) . chr(0xA0) .'|\s)*</\\1>(\r?\n)??#siU' => "\n",
			'#<p[^>]*>(.*)</p>(\r?\n)??#siU' => "\\1\n",
			'#<div[^>]*>(.*)</div>(\r?\n)??#siU' => "\\1\n",

			'#<pre[^>]*>(.*)</pre>(\r?\n)??#siU' => "[CODE]\\1[/CODE]\n",

			'#<!--.*-->#siU' => ''
		);
	}

	protected function _parseIPBoardMediaCode($message)
	{
		return preg_replace_callback('#\[media[^\]]*\](http://.*)\[/media\]#siU', array($this, '_convertIPBoardMediaTag'), $message);
	}

	protected function _convertIPBoardMediaTag(array $regexMatches)
	{
		if ($embedHtml = XenForo_Helper_Media::convertMediaLinkToEmbedHtml($regexMatches[1]))
		{
			return $embedHtml;
		}
		else
		{
			return '[url]' . $regexMatches[1] . '[/url]';
		}
	}
}
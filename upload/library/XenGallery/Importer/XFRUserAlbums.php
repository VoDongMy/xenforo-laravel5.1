<?php

class XenGallery_Importer_XFRUserAlbums extends XenForo_Importer_Abstract
{
	/**
	 * Source database connection.
	 *
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_sourceDb;

	protected $_config;

	protected $_defaultTables = array(
		'xfr_useralbum',
		'xfr_useralbum_image',
		'xfr_useralbum_image_data',
		'xfr_useralbum_image_comment'
	);

	public static function getName()
	{
		return ' XFMG: Import From [xfr] User Albums';
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

			return true;
		}
		else
		{
			$config = XenForo_Application::getConfig();
			$dbConfig = $config->get('db');

			$viewParams = array(
				'config' => array(
					'db' => array(
						'host' => $dbConfig->host,
						'port' => $dbConfig->port,
						'username' => $dbConfig->username,
						'password' => $dbConfig->password,
						'dbname' => $dbConfig->dbname
					)
				),
				'addOnName' => str_replace('XFMG: Import From ', '', self::getName())
			);
		}

		return $controller->responseView('XenGallery_ViewAdmin_Import_Config', 'xengallery_import_config', $viewParams);
	}

	public function validateConfiguration(array &$config)
	{
		$errors = array();

		try
		{
			$db = Zend_Db::factory('mysqli',
				array(
					'host' => $config['db']['host'],
					'port' => $config['db']['port'],
					'username' => $config['db']['username'],
					'password' => $config['db']['password'],
					'dbname' => $config['db']['dbname'],
					'charset' => 'utf-8'
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

		foreach ($this->_defaultTables AS $table)
		{
			$exists = $db->fetchOne("SHOW TABLES LIKE '$table'");

			if (!$exists)
			{
				$errors[] = new XenForo_Phrase('xengallery_table_x_does_not_exist', array('tablename' => $table));
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
				'charset' => 'utf8'
			)
		);
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
			'media' => array(
				'title' => new XenForo_Phrase('xengallery_import_media'),
				'depends' => array('albums')
			),
			'comments' => array(
				'title' => new XenForo_Phrase('xengallery_import_comments'),
				'depends' => array('albums', 'media')
			)
		);
	}

	public function configStepAlbums(array $options)
	{
		if ($options)
		{
			return false;
		}

		return $this->_controller->responseView('XenGallery_ViewAdmin_Import_Config_XFRUserAlbums_Albums', 'xengallery_import_config_xfruseralbums_albums');
	}

	public function stepAlbums($start, array $options)
	{
		$options = array_merge(array(
			'globalToCategory' => '0',
			'limit' => 100,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(album_id)
				FROM xfr_useralbum
			');
		}

		$albums = $sDb->fetchAll(
			$sDb->limit('
				SELECT *
				FROM xfr_useralbum
				WHERE album_id > ?
				ORDER BY album_id ASC
			', $options['limit'])
			, $start);
		if (!$albums)
		{
			return true;
		}

		XenForo_Db::beginTransaction();

		$next = 0;
		$total = 0;

		$categories = array();
		foreach ($albums AS $album)
		{
			$next = $album['album_id'];

			if ($options['globalToCategory'] && $album['album_type'] == 'global')
			{
				$categories[$album['album_id']] = $album;
				continue;
			}

			$imported = $this->_importAlbum($album, $options);
			if ($imported)
			{
				$total++;
			}
		}

		if ($categories)
		{
			foreach ($categories AS $category)
			{
				if ($category['moderation'])
				{
					continue;
				}

				$imported = $this->_importCategory($category, $options);
				if ($imported)
				{
					$total++;
				}
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importCategory(array $category, array $options)
	{
		$model = $this->_getMediaGalleryImportersModel();

		$xengalleryCategory = array(
			'category_title' => $category['title'],
			'category_description' => XenForo_Helper_String::bbCodeStrip($category['description'], true),
			'upload_user_groups' => unserialize('a:1:{i:0;i:-1;}'),
			'view_user_groups' => unserialize('a:1:{i:0;i:-1;}'),
			'allowed_types' => unserialize('a:1:{i:0;s:3:"all";}'),
			'parent_category_id' => 0,
			'display_order' => intval($category['album_id']) + 10000,
			'category_breadcrumb' => unserialize('a:0:{}'),
			'depth' => 0,
			'category_media_count' => $category['image_count'],
			'field_cache' => unserialize('a:0:{}')
		);

		$importedCategoryId = $model->importCategory($category['album_id'], $xengalleryCategory, 'xengallery_album');

		return $importedCategoryId;
	}

	protected function _importAlbum(array $album, array $options)
	{
		$model = $this->_getMediaGalleryImportersModel();

		$sDb = $this->_sourceDb;
		$user = $sDb->fetchRow('
				SELECT user_id, username
				FROM xf_user
				WHERE user_id = ?
			', $album['user_id']);
		if (!$user)
		{
			return false;
		}

		$shareUsers = array();

		$albumPrivacy = $album['album_type'] == 'global' ? 'public' : $album['album_type'];
		if ($albumPrivacy == 'followed' || $albumPrivacy == 'selected')
		{
			$userIds = array();

			if ($albumPrivacy == 'selected'
				&& !empty($album['selected_users'])
				&& is_array($album['selected_users'])
			)
			{
				$albumPrivacy = 'shared';
				$userIds = explode(',', $album['selected_users']);
			}

			/** @var $userModel XenForo_Model_User */
			$userModel = XenForo_Model::create('XenForo_Model_User');

			if ($albumPrivacy == 'followed')
			{
				$followedUsers = $userModel->getFollowedUserProfiles($album['user_id'], 0, 'user.user_id');
				$userIds = array_keys($followedUsers);
			}

			$shareUsers = $userModel->getUsersByIds($userIds);
		}

		$xengalleryAlbum = array(
			'album_title' => $album['title'],
			'album_description' => XenForo_Helper_String::bbCodeStrip($album['description'], true),
			'album_create_date' => $album['createdate'],
			'last_update_date' => $album['last_image_date'],
			'media_cache' => array(),
			'album_state' => $album['moderation'] ? 'moderated' : 'visible',
			'album_user_id' => $album['user_id'],
			'album_username' => $model->getUsernameByUserId($album['user_id']),
			'ip_id' => $model->getLatestIpIdFromUserId($album['user_id']),
			'album_likes' => $album['likes'],
			'album_like_users' => unserialize($album['like_users']),
			'album_media_count' => $album['image_count'],
			'album_view_count' => $album['view_count'],
		);

		$importedAlbumId = $model->importAlbum($album['album_id'], $xengalleryAlbum, $albumPrivacy, 'xfr_useralbum', $shareUsers);

		return $importedAlbumId;
	}

	public function stepMedia($start, array $options)
	{
		$options = array_merge(array(
			'path' => XenForo_Helper_File::getInternalDataPath() . '/xfru/useralbums/images',
			'limit' => 5,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(image_id)
				FROM xfr_useralbum_image
			');
		}

		$userAlbumsMoreInstalled = '';
		if (XenForo_Application::isRegistered('addOns'))
		{
			$addOns = XenForo_Application::get('addOns');
			if (!empty($addOns['XfRuUserAlbumsMore']))
			{
				$userAlbumsMoreInstalled = ', album.selected_users';
			}
		}

		$media = $sDb->fetchAll($sDb->limit(
			'
				SELECT image.*, data.*,
				album.album_id, album.album_type, album.moderation
				' . $userAlbumsMoreInstalled . '
				FROM xfr_useralbum_image AS image
				INNER JOIN xfr_useralbum_image_data AS data ON
					(image.data_id = data.data_id)
				INNER JOIN xfr_useralbum AS album ON
					(image.album_id = album.album_id)
				WHERE image.image_id > ' . $sDb->quote($start) . '
					AND image.unassociated = 0
					AND data.attach_count > 0
				ORDER BY image.image_id
			', $options['limit']
		));
		if (!$media)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		foreach ($media AS $item)
		{
			$next = $item['image_id'];

			$user = $sDb->fetchRow('
				SELECT user_id, username
				FROM xf_user
				WHERE user_id = ?
			', $item['user_id']);
			if (!$user)
			{
				continue;
			}

			$success = $this->_importMedia($item, $options);
			if ($success)
			{
				$total++;
			}
			else
			{
				continue;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importMedia(array $item, array $options)
	{
		$originalFilePath = sprintf('%s/%d/%d-%s.data',
			$options['path'],
			floor($item['data_id'] / 1000),
			$item['data_id'],
			$item['file_hash']
		);
		if (!file_exists($originalFilePath))
		{
			return false;
		}

		$tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
		copy($originalFilePath, $tempFile);

		$model = $this->_getMediaGalleryImportersModel();

		$categoryId = 0;
		$albumId = $model->mapAlbumId($item['album_id']);
		if (strstr($albumId, 'category_'))
		{
			$categoryId = str_replace('category_', '', $albumId);
			$albumId = 0;
		}

		$mediaPrivacy = $item['album_type'] == 'global' ? 'public' : $item['album_type'];
		if ($mediaPrivacy == 'selected')
		{
			$mediaPrivacy = 'shared';
		}

		$noTitle = new XenForo_Phrase('xengallery_imported_item');
		$lastCommentDate = $model->getLastCommentDateFromImageIdXFRUA($item['image_id']);
		$xengalleryMedia = array(
			'media_title' => $item['filename'] ? $item['filename'] : $noTitle->render(),
			'media_description' => $item['description'],
			'media_date' => $item['upload_date'],
			'last_edit_date' => XenForo_Application::$time,
			'last_comment_date' => $lastCommentDate ? $lastCommentDate : 0,
			'media_type' => 'image_upload',
			'media_state' => $item['moderation'] ? 'moderated' : 'visible',
			'album_id' => $albumId,
			'category_id' => $categoryId,
			'media_privacy' => $mediaPrivacy,
			'attachment_id' => 0,
			'user_id' => $item['user_id'],
			'username' => $model->getUsernameByUserId($item['user_id']),
			'ip_id' => $model->getLatestIpIdFromUserId($item['user_id']),
			'likes' => $item['likes'],
			'like_users' => unserialize($item['like_users']),
			'comment_count' => $item['comment_count'],
			'rating_count' => 0,
			'media_view_count' => $item['view_count']
		);

		$xfAttachment = array(
			'data_id' => 0,
			'content_type' => 'xengallery_media',
			'content_id' => 0,
			'attach_date' => $item['upload_date'],
			'temp_hash' => '',
			'unassociated' => 0,
			'view_count' => $item['view_count']
		);

		$xfAttachmentData = array(
			'user_id' => $item['user_id'],
			'upload_date' => $item['upload_date'],
			'filename' => $item['filename'],
			'attach_count' => $item['attach_count']
		);

		$importedMediaId = $model->importMedia($item['image_id'], $tempFile, 'xfr_useralbum_image', $xengalleryMedia, $xfAttachment, $xfAttachmentData);

		@unlink($tempFile);

		return $importedMediaId;
	}

	public function stepComments($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(comment_id)
				FROM xfr_useralbum_image_comment
			');
		}

		$comments = $sDb->fetchAll($sDb->limit(
			'
				SELECT *
				FROM xfr_useralbum_image_comment
				WHERE comment_id > ' . $sDb->quote($start) . '
				ORDER BY comment_id
			', $options['limit']
		));
		if (!$comments)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		foreach ($comments AS $comment)
		{
			$next = $comment['comment_id'];

			$user = $sDb->fetchRow('
				SELECT user_id, username
				FROM xf_user
				WHERE user_id = ?
			', $comment['user_id']);
			if (!$user)
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

		$xengalleryComment = array(
			'content_id' => $model->mapMediaId($comment['image_id']),
			'content_type' => 'media',
			'message' => $comment['message'],
			'user_id' => $comment['user_id'],
			'username' => $comment['username'],
			'comment_date' => $comment['comment_date'],
			'comment_state' => 'visible',
			'likes' => 0,
			'like_users' => array()
		);

		$importedCommentId = $model->importComment($comment['comment_id'], $xengalleryComment);

		return $importedCommentId;
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
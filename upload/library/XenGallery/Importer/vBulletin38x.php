<?php

class XenGallery_Importer_vBulletin38x extends XenForo_Importer_Abstract
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

	protected $_userIdMap;

	protected $_imageTypeMap = array(
		'gif' => IMAGETYPE_GIF,
		'jpg' => IMAGETYPE_JPEG,
		'jpeg' => IMAGETYPE_JPEG,
		'jpe' => IMAGETYPE_JPEG,
		'png' => IMAGETYPE_PNG
	);

	public static function getName()
	{
		return ' XFMG: Import From vBulletin 3.8 Albums';
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

			if (isset($config['albumPicPath']))
			{
				return true;
			}

			$this->_bootstrap($config);

			$settings = $this->_sourceDb->fetchPairs('
				SELECT varname, value
				FROM ' . $this->_prefix . 'setting
				WHERE varname IN (\'album_picpath\', \'album_dataloc\')
			');
			if (($settings['album_picpath'] && $settings['album_dataloc'] != 'db'))
			{
				return $controller->responseView('XenGallery_ViewAdmin_Import_Config', 'xengallery_import_config_vbulletin', array(
					'config' => $config,
					'productName' => str_replace('XFMG: Import From ', '', self::getName()),
					'albumPicPath' => $settings['album_picpath'],
					'retainKeys' => $config['retain_keys'],
				));
			}

			return true;
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

		if (!empty($config['albumPicPath']))
		{
			if (!file_exists($config['albumPicPath']) || !is_dir($config['albumPicPath']))
			{
				$errors[] = new XenForo_Phrase('xengallery_album_pic_directory_not_found');
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

		return $errors;
	}

	protected function _bootstrap(array $config)
	{
		if ($this->_sourceDb)
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

		if ($mediaCount)
		{
			throw new XenForo_Exception(new XenForo_Phrase('xengallery_gallery_must_be_empty_before_importing'), true);
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
				SELECT MAX(albumid)
				FROM ' . $prefix . 'album
			');
		}

		$albums = $sDb->fetchAll(
			$sDb->limit('
				SELECT album.*, user.username, albumupdate.dateline AS last_update_date
				FROM ' . $prefix . 'album AS album
				LEFT JOIN ' . $prefix . 'user AS user ON
					(album.userid = user.userid)
				LEFT JOIN ' . $prefix . 'albumupdate AS albumupdate ON
					(album.albumid = albumupdate.albumid)
				WHERE album.albumid > ?
				ORDER BY album.albumid ASC
			', $options['limit'])
		, $start);

		if (!$albums)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$this->_userIdMap = $this->_importModel->getUserIdsMapFromArray($albums, 'userid');
		foreach ($albums AS $album)
		{
			$next = $album['albumid'];
			if (!isset($this->_userIdMap[$album['userid']]))
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

		$albumPrivacy = $album['state'] == 'private' ? $album['state'] : 'public';

		$userId = $this->_userIdMap[$album['userid']];
		$xengalleryAlbum = array(
			'album_title' => $this->_convertToUtf8($album['title'], true),
			'album_description' => $this->_convertToUtf8($album['description'], true),
			'album_create_date' => $album['createdate'],
			'last_update_date' => $album['last_update_date'] ? $album['last_update_date'] : $album['createdate'],
			'media_cache' => array(),
			'album_state' => 'visible',
			'album_user_id' => $userId,
			'album_username' => $this->_convertToUtf8($album['username'], true),
			'ip_id' => $model->getLatestIpIdFromUserId($userId),
			'album_media_count' => $album['visible'],
			'album_likes' => false,
			'album_like_users' => false
		);

		$importedAlbumId = $model->importAlbum($album['albumid'], $xengalleryAlbum, $albumPrivacy);

		return $importedAlbumId;
	}

	public function stepMedia($start, array $options)
	{
		$options = array_merge(array(
			'path' => isset($this->_config['albumPicPath']) ? $this->_config['albumPicPath'] : '',
			'limit' => 5,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		$imageTypes = $sDb->quote(array_keys($this->_imageTypeMap));

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(albumpicture.pictureid)
				FROM ' . $prefix . 'albumpicture AS albumpicture
				LEFT JOIN ' . $prefix . 'picture AS picture ON
					(albumpicture.pictureid = picture.pictureid)
				WHERE picture.extension IN (' . $imageTypes . ')
					AND picture.state = \'visible\'
			');
		}

		$media = $sDb->fetchAll($sDb->limit(
			'
				SELECT albumpicture.*, picture.*, user.*,
					album.*, albumpicture.pictureid
				FROM ' . $prefix . 'albumpicture AS albumpicture
				LEFT JOIN ' . $prefix . 'album AS album ON
					(albumpicture.albumid = album.albumid)
				LEFT JOIN ' . $prefix . 'picture AS picture ON
					(albumpicture.pictureid = picture.pictureid)
				LEFT JOIN ' . $prefix . 'user AS user ON
					(picture.userid = user.userid)
				WHERE albumpicture.pictureid > ' . $sDb->quote($start) . '
					AND picture.extension IN (' . $imageTypes . ')
					AND picture.state = \'visible\'
				ORDER BY albumpicture.pictureid
			', $options['limit']
		));

		if (!$media)
		{
			return true;
		}

		$this->_userIdMap = $this->_importModel->getUserIdsMapFromArray($media, 'userid');

		$model = $this->_getMediaGalleryImportersModel();

		$next = 0;
		$total = 0;

		foreach ($media AS $item)
		{
			$next = $item['pictureid'];
			if (!isset($this->_userIdMap[$item['userid']]))
			{
				continue;
			}

			$albumId = $model->mapAlbumId($item['albumid']);
			$userId = $this->_userIdMap[$item['userid']];

			if (!$options['path'])
			{
				$fData = $sDb->fetchOne('
					SELECT filedata
					FROM ' . $prefix . 'picture
					WHERE pictureid = ' . $sDb->quote($item['pictureid'])
				);
				if ($fData === '')
				{
					continue;
				}

				$tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
				if (!$tempFile || !@file_put_contents($tempFile, $fData))
				{
					continue;
				}

			}
			else
			{
				$pictureFileOrig = sprintf('%s/%d/%d.picture',
					$options['path'],
					floor($item['pictureid'] / 1000),
					$item['pictureid']
				);
				if (!file_exists($pictureFileOrig))
				{
					continue;
				}

				$tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
				copy($pictureFileOrig, $tempFile);
			}

			$commentCount = $sDb->fetchOne('
				SELECT COUNT(*)
				FROM ' . $prefix . 'picturecomment
				WHERE pictureid = ?
			', $item['pictureid']);

			$titleMaxLength = XenForo_Application::getOptions()->xengalleryMaxTitleLength;

			$xengalleryMedia = array(
				'media_title' => $item['caption'] ? utf8_substr($this->_convertToUtf8($item['caption'], true), 0, $titleMaxLength) : "$item[pictureid].$item[extension]",
				'media_date' => $item['dateline'],
				'last_edit_date' => XenForo_Application::$time,
				'media_type' => 'image_upload',
				'media_state' => 'visible',
				'album_id' => $albumId,
				'category_id' => 0,
				'media_privacy' => $item['state'] == 'private' ? $item['state'] : 'public',
				'attachment_id' => 0,
				'user_id' => $userId,
				'username' => $this->_convertToUtf8($item['username'], true),
				'ip_id' => $model->getLatestIpIdFromUserId($userId),
				'likes' => 0,
				'like_users' => array(),
				'comment_count' => $commentCount,
				'rating_count' => 0,
				'media_view_count' => 0
			);

			$xfAttachment = array(
				'data_id' => 0,
				'content_type' => 'xengallery_media',
				'content_id' => 0,
				'attach_date' => $item['dateline'],
				'temp_hash' => '',
				'unassociated' => 0,
				'view_count' => 0
			);

			$xfAttachmentData = array(
				'user_id' => $userId,
				'upload_date' => $item['dateline'],
				'filename' => sprintf('%d-%d-%s.%s',
					$item['pictureid'],
					$item['dateline'],
					md5($item['idhash']),
					$item['extension']
				),
				'attach_count' => 1
			);

			$imported = $model->importMedia($item['pictureid'], $tempFile, '', $xengalleryMedia, $xfAttachment, $xfAttachmentData);
			if ($imported)
			{
				$total++;
			}

			@unlink($tempFile);
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public function stepComments($start, array $options)
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
				SELECT MAX(commentid)
				FROM ' . $prefix . 'picturecomment
				WHERE state = \'visible\'
			');
		}

		$comments = $sDb->fetchAll($sDb->limit(
			'
				SELECT comment.*, user.*
				FROM ' . $prefix . 'picturecomment AS comment
				LEFT JOIN ' . $prefix . 'user AS user ON
					(comment.postuserid = user.userid)
				WHERE comment.commentid > ?
					AND comment.state = \'visible\'
				ORDER BY comment.commentid
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
			$next = $comment['commentid'];
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

		$mediaId = $model->mapMediaId($comment['pictureid']);
		if (!$mediaId)
		{
			return false;
		}

		$xengalleryComment = array(
			'content_id' => $mediaId,
			'content_type' => 'media',
			'message' => $this->_convertToUtf8($comment['pagetext'], true),
			'user_id' => $this->_userIdMap[$comment['userid']],
			'username' => $this->_convertToUtf8($comment['username'], true),
			'comment_date' => $comment['dateline'],
			'comment_state' => 'visible',
			'likes' => 0,
			'like_users' => array()
		);

		$importedCommentId = $model->importComment($comment['commentid'], $xengalleryComment);

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
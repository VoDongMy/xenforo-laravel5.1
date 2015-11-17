<?php

/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
class sonnb_XenGallery_Importer_vbulletin38 extends sonnb_XenGallery_Importer_Abstract
{
	public static function getName()
	{
		return 'vBB 3.8.x User Albums => sonnb - XenGallery';
	}

	public function configure(XenForo_ControllerAdmin_Abstract $controller, array &$config)
	{
		if ($config)
		{
			if ($errors = $this->validateConfiguration($config))
			{
				return $controller->responseError($errors);
			}

			if (isset($config['attachmentPath']))
			{
				return true;
			}

			$this->_bootstrap($config);

			$settings = $this->_sourceDb->fetchPairs('
				SELECT varname, value
				FROM ' . $this->_prefix . 'setting
				WHERE varname IN (\'album_picpath\', \'album_dataloc\')
			');

			if ($settings['album_dataloc'] != 'db' && $settings['album_picpath'])
			{
				return $controller->responseView(
					'XenForo_ViewAdmin_Import_vBulletin_Config',
					'sonnb_xengallery_import_vbulletion_config',
					array(
						'config' => $config,
						'attachmentPath' => $settings['album_picpath'],
						'retainKeys' => $config['retainKeys'],
				));
			}

			return true;
		}

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
			));
		}

		return $controller->responseView(
			'XenForo_ViewAdmin_Import_vBulletin_Config',
			'sonnb_xengallery_import_vbulletion_config',
			$viewParams
		);
	}

	public function validateConfiguration(array &$config)
	{
		$errors = array();

		if (preg_match('/[^a-z0-9_]/i', $config['importlog']))
		{
			$errors[] = new XenForo_Phrase('error_table_name_illegal');
		}
		else
		{
			try
			{
				$table = $this->_db->describeTable($config['importlog']);
				if (!isset($table['content_type']) || !isset($table['old_id']) || !isset($table['new_id']))
					$errors[] = new XenForo_Phrase('sonnb_xengallery_import_log_table_invalid');
			}
			catch (Zend_Db_Exception $e)
			{
				$errors[] = $e->getMessage();
			}
		}

		$config['db']['prefix'] = preg_replace('/[^a-z0-9_]/i', '', $config['db']['prefix']);

		try
		{
			$db = Zend_Db::factory('mysqli',
				array(
					'host' => $config['db']['host'],
					'port' => $config['db']['port'],
					'username' => $config['db']['username'],
					'password' => $config['db']['password'],
					'dbname' => $config['db']['dbname']
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

		if (!empty($config['attachmentPath']))
		{
			if (!file_exists($config['attachmentPath']) || !is_dir($config['attachmentPath']))
			{
				$errors[] = new XenForo_Phrase('attachments_directory_not_found');
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

		$this->_prefix = preg_replace('/[^a-z0-9_]/i', '', $config['db']['prefix']);

		if (!empty($config['charset']))
		{
			$this->_charset = $config['charset'];
		}

		define('IMPORT_LOG_TABLE', $this->_config['importlog']);
	}

	public function stepAlbums($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow('
                SELECT MAX(albumid) AS max, COUNT(albumid) AS rows
                FROM ' . $prefix . 'album'
			);

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Albums ...");
		}

		$albums = $sDb->fetchAll($sDb->limit('
                SELECT album.*, user.username, albumupdate.dateline AS album_update_date
                FROM ' . $prefix . 'album AS album
                LEFT JOIN ' . $prefix . 'user AS user ON (album.userid = user.userid)
                    LEFT JOIN ' . $prefix . 'albumupdate AS albumupdate ON (album.albumid = albumupdate.albumid)
                WHERE album.albumid > ' . $sDb->quote($start) . '
                ORDER BY album.albumid
            ', $options['limit']
		));

		if (!$albums)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$userIdMap = $model->getUserIdsMapFromArray($albums, 'userid');

		foreach ($albums AS $album)
		{
			$next = $album['albumid'];

			if (!isset($userIdMap[$album['userid']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$album['userid']];
				$username = $this->_convertToUtf8($album['username'], true);
				$username = $this->_mbTrim($username, 50, $userId);

				$import = array(
					'title' => $this->_convertToUtf8($album['title'], true),
					'description' => $this->_convertToUtf8($album['description'], true),
					'user_id' => $userId,
					'username' => $username,
					'album_date' => $album['createdate'],
					'album_updated_date' => $album['album_update_date'] ? $album['album_update_date'] : $album['createdate'],
					'album_state' => 'visible',
					'cover_content_id' => 0,
					'album_privacy' => array(
						'allow_view' => 'everyone',
						'allow_view_data' => array(),
						'allow_comment' => 'everyone',
						'allow_comment_data' => array(),
						'allow_add_photo' => 'none',
						'allow_add_photo_data' => array(),
						'allow_add_video' => 'everyone',
						'allow_add_video_data' => array(),
						'allow_download' => 'none',
						'allow_download_data' => array()
					),
					'photo_count' => $album['visible'],
					'category_id' => 0
				);

				if ($album['moderation'])
				{
					$import['album_state'] = 'moderated';
				}

				if ($album['state'] == 'private')
				{
					$import['album_privacy'] = array(
						'allow_view' => 'none',
						'allow_view_data' => array(),
						'allow_comment' => 'none',
						'allow_comment_data' => array(),
						'allow_add_photo' => 'none',
						'allow_add_photo_data' => array(),
						'allow_download' => 'none',
						'allow_download_data' => array()
					);
				}

				if ($this->_config['retainKeys'])
				{
					$import['album_id'] = $album['albumid'];
				}

				$albumId = $model->importXenGalleryAlbum(0, $import);

				$model->logImportData('sonnb_xengallery_album', $album['albumid'], $albumId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	public function stepPhotos($start, array $options)
	{
		$options = array_merge(array(
			'path' => isset($this->_config['attachmentPath']) ? trim($this->_config['attachmentPath']) : '',
			'limit' => 10,
			'processed' => 0,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow("
    				SELECT MAX(pictureid) AS max, COUNT(pictureid) AS rows
    				FROM " . $prefix . "picture
    				WHERE state = 'visible'
    				AND extension IN (".$sDb->quote(array_keys(sonnb_XenGallery_Model_ContentData::$typeMap)).")
    		");

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Photos ...");
		}

		$attachments = $sDb->fetchAll($sDb->limit("
    				SELECT picture.pictureid, picture.userid, picture.caption, picture.extension,
    				picture.filesize, picture.width, picture.height, picture.idhash, picture.state,
    				album.albumid, album.dateline, user.username
    				FROM " . $prefix . "picture AS picture
    				LEFT JOIN " . $prefix . "user AS user ON (picture.userid = user.userid)
    				LEFT JOIN " . $prefix . "albumpicture AS album ON (album.pictureid = picture.pictureid)
    				WHERE picture.pictureid > " . $sDb->quote($start) . "
    				AND picture.state = 'visible'
    				AND picture.extension IN (".$sDb->quote(array_keys(sonnb_XenGallery_Model_ContentData::$typeMap)).")
    				ORDER BY picture.pictureid ASC
    			", $options['limit']
		));

		if (!$attachments)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$position = 0;
		$userIdMap = $model->getUserIdsMapFromArray($attachments, 'userid');
		$albumIdMap = $model->getAlbumIdsMapFromArray($attachments, 'albumid');

		foreach ($attachments AS $attachment)
		{
			$next = $attachment['pictureid'];

			if (!isset($userIdMap[$attachment['userid']]))
			{
				continue;
			}

			if (!isset($albumIdMap[$attachment['albumid']]))
			{
				continue;
			}

			if (!isset(sonnb_XenGallery_Model_ContentData::$typeMap[$attachment['extension']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$attachment['userid']];
				$username = $this->_convertToUtf8($attachment['username'], true);
				$username = $this->_mbTrim($username, 50, $userId);
				$albumId = $albumIdMap[$attachment['albumid']];

				$importPhotoData = array(
					'file_size' => $attachment['filesize'],
					'width' => $attachment['width'],
					'height' => $attachment['height'],
					'file_hash' => $attachment['idhash'],
					'upload_date' => $attachment['dateline'],
					'unassociated' => 1,
					'extension' => $attachment['extension']
				);

				$photoData = $model->importXenGalleryPhotoData(0, $importPhotoData);

				if ($photoData === false)
				{
					continue;
				}

				$model->logImportData('sonnb_xengallery_data', $attachment['pictureid'], $photoData['content_data_id']);
				$success = $this->_createPhotoData($options, $attachment, $photoData);

				if ($success === false)
				{
					continue;
				}

				$model->importXenGalleryContentDataConfirm($photoData);

				$import = array(
					'album_id' => $albumId,
					'title' => $this->_convertToUtf8($attachment['caption'], true),
					'content_data_id' => $photoData['content_data_id'],
					'description' => '',
					'user_id' => $userId,
					'username' => $username,
					'content_privacy' => array(
						'allow_view' => 'everyone',
						'allow_view_data' => array(),
						'allow_comment' => 'everyone',
						'allow_comment_data' => array()
					),
					'position' => $position,
					'content_state' => $attachment['state'] == 'visible' ? 'visible' : 'moderated',
					'content_date' => $attachment['dateline'],
					'content_updated_date' => $attachment['dateline']
				);

				if ($this->_config['retainKeys'])
				{
					$import['content_id'] = $attachment['pictureid'];
				}

				$photoId = $model->importXenGalleryPhoto(0, $import);
				$model->logImportData('sonnb_xengallery_photo', $attachment['pictureid'], $photoId);

				$position++;
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	public function stepComments($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow("
    				SELECT MAX(commentid) AS max, COUNT(commentid) AS rows
    				FROM " . $prefix . "picturecomment
			");

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Comments ...");
		}

		$comments = $sDb->fetchAll($sDb->limit("
    				SELECT *
    				FROM " . $prefix . "picturecomment
    				WHERE commentid > " . $sDb->quote($start) . "
    				ORDER BY commentid ASC
    			", $options['limit']
		));

		if (!$comments)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$userIdMap = $model->getUserIdsMapFromArray($comments, 'postuserid');
		$photoIdMap = $model->getPhotoIdsMapFromArray($comments, 'pictureid');

		foreach ($comments AS $comment)
		{
			$next = $comment['commentid'];

			if (!isset($userIdMap[$comment['postuserid']]))
			{
				continue;
			}

			if (!isset($photoIdMap[$comment['pictureid']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$comment['postuserid']];
				$username = $this->_convertToUtf8($comment['postusername'], true);
				$username = $this->_mbTrim($username, 50, $userId);
				$photoId = $photoIdMap[$comment['pictureid']];

				$import = array(
					'content_type' => sonnb_XenGallery_Model_Photo::$contentType,
					'content_id' => $photoId,
					'user_id' => $userId,
					'username' => $username,
					'message' => $this->_convertToUtf8($comment['pagetext'], true),
					'comment_state' => $comment['state'] == 'visible' ? 'visible' : ($comment['state'] == 'moderation' ? 'moderated' : 'deleted'),
					'comment_date' => $comment['dateline']
				);

				$commentId = $model->importXenGalleryComment(0, $import);

				$model->logImportData('sonnb_xengallery_comment', $comment['commentid'], $commentId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	protected function _createPhotoData($options, &$attachment, $data)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;;

		if (!$options['path'])
		{
			$fData = $sDb->fetchOne('
                SELECT filedata
                FROM ' . $prefix . 'picture
                WHERE pictureid = ' . $sDb->quote($attachment['pictureid'])
			);

			if ($fData === '')
			{
				return false;
			}

			$filename = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			if (!$filename || !@file_put_contents($filename, $fData))
			{
				return false;
			}
		}
		else
		{
			$attachFileOrig = "$options[path]/" . floor($attachment['pictureid'] / 1000) . "/$attachment[pictureid].picture";

			if (!is_file($attachFileOrig) || !file_exists($attachFileOrig))
			{
				return false;
			}

			$filename = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			copy($attachFileOrig, $filename);
		}

		return $this->_createPhotoFiles($filename, $data, false);
	}
}
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
class sonnb_XenGallery_Importer_VideoDirectory38 extends sonnb_XenGallery_Importer_Abstract
{
	public static function getName()
	{
		return 'Video Directory 1.3.0 for vBB 3.8.x => sonnb - XenGallery';
	}

	public function getSteps()
	{
		return array(
			'albums' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_albums')
			),
			'videos' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_videos'),
				'depends' => array('albums')
			),
			'comments' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_comments'),
				'depends' => array('videos')
			)
		);
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
				WHERE varname IN (\'videodirectory_thumbnaildir\')
			');

			if ($settings['videodirectory_thumbnaildir'])
			{
				return $controller->responseView(
					'XenForo_ViewAdmin_Import_VideoDirectory38_Config',
					'sonnb_xengallery_import_videoDirectory38_config',
					array(
						'config' => $config,
						'attachmentPath' => $settings['videodirectory_thumbnaildir'],
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
			'XenForo_ViewAdmin_Import_VideoDirectory38_Config',
			'sonnb_xengallery_import_videoDirectory38_config',
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
                SELECT MAX(videocategoryid) AS max, COUNT(videocategoryid) AS rows
                FROM ' . $prefix . 'videocategory'
			);

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Albums ...");
		}

		$albums = $sDb->fetchAll($sDb->limit('
                SELECT album.*
                FROM ' . $prefix . 'videocategory AS album
                WHERE album.videocategoryid > ' . $sDb->quote($start) . '
                ORDER BY album.videocategoryid
            ', $options['limit']
		));

		if (!$albums)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$visitor = XenForo_Visitor::getInstance();

		foreach ($albums AS $album)
		{
			$next = $album['videocategoryid'];

			if ($last <> $next)
			{
				$last = $next;
				$userId = $visitor->getUserId();
				$username = $visitor['username'];

				$import = array(
					'title' => $this->_convertToUtf8($album['title'], true),
					'description' => $this->_convertToUtf8($album['description'], true),
					'user_id' => $userId,
					'username' => $username,
					'album_date' => XenForo_Application::$time,
					'album_updated_date' => XenForo_Application::$time,
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
					'category_id' => 0
				);

				if ($this->_config['retainKeys'])
				{
					$import['album_id'] = $album['videocategoryid'];
				}

				$albumId = $model->importXenGalleryAlbum(0, $import);

				$model->logImportData('sonnb_xengallery_album', $album['videocategoryid'], $albumId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	public function stepVideos($start, array $options)
	{
		$options = array_merge(array(
			'path' => isset($this->_config['attachmentPath']) ? trim($this->_config['attachmentPath']) : '',
			'limit' => 20,
			'processed' => 0,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $sDb->fetchRow("
    				SELECT MAX(videoid) AS max, COUNT(videoid) AS rows
    				FROM " . $prefix . "video
    		");

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Videos ...");
		}

		$attachments = $sDb->fetchAll($sDb->limit("
    				SELECT video.*
    				FROM " . $prefix . "video AS video
    				WHERE video.videoid > " . $sDb->quote($start) . "
    				ORDER BY video.videoid ASC
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
		$albumIdMap = $model->getAlbumIdsMapFromArray($attachments, 'videocategoryid');

		foreach ($attachments AS $attachment)
		{
			$next = $attachment['videoid'];

			if (!isset($userIdMap[$attachment['userid']]))
			{
				continue;
			}

			if (!isset($albumIdMap[$attachment['videocategoryid']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$attachment['userid']];
				$username = $this->_convertToUtf8($attachment['username'], true);
				$username = $this->_mbTrim($username, 50, $userId);
				$albumId = $albumIdMap[$attachment['videocategoryid']];

				$importVideoData = array(
					'file_size' => 0,
					'width' => 0,
					'height' => 0,
					'file_hash' => 0,
					'upload_date' => $attachment['dateline'],
					'duration' => $attachment['timelength'],
					'unassociated' => 1,
					'extension' => 'jpg'
				);

				$videoData = $model->importXenGalleryVideoData(0, $importVideoData);

				if ($videoData === false)
				{
					continue;
				}

				$model->logImportData('sonnb_xengallery_data', $attachment['videoid'], $videoData['content_data_id']);
				$success = $this->_createVideoData($options, $attachment, $videoData);

				if ($success === false)
				{
					continue;
				}

				$model->importXenGalleryContentDataConfirm($videoData);

				$import = array(
					'video_type' => strtolower($attachment['videoservice']),
					'video_key' => $attachment['videoidservice'],
					'album_id' => $albumId,
					'title' => $this->_convertToUtf8($attachment['title'], true),
					'content_data_id' => $videoData['content_data_id'],
					'description' => $attachment['description'],
					'user_id' => $userId,
					'username' => $username,
					'content_privacy' => array(
						'allow_view' => 'everyone',
						'allow_view_data' => array(),
						'allow_comment' => 'everyone',
						'allow_comment_data' => array()
					),
					'position' => $position,
					'content_state' => 'visible',
					'content_date' => $attachment['dateline'],
					'content_updated_date' => $attachment['dateline']
				);

				if ($this->_config['retainKeys'])
				{
					$import['content_id'] = $attachment['videoid'];
				}

				$photoId = $model->importXenGalleryVideo(0, $import);
				$model->logImportData('sonnb_xengallery_video', $attachment['videoid'], $photoId);

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
    				SELECT MAX(videocommentid) AS max, COUNT(videocommentid) AS rows
    				FROM " . $prefix . "videocomment
			");

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Comments ...");
		}

		$comments = $sDb->fetchAll($sDb->limit("
    				SELECT *
    				FROM " . $prefix . "videocomment
    				WHERE videocommentid > " . $sDb->quote($start) . "
    				ORDER BY videocommentid ASC
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
		$photoIdMap = $model->getVideoIdsMapFromArray($comments, 'videoid');

		foreach ($comments AS $comment)
		{
			$next = $comment['videocommentid'];

			if (!isset($userIdMap[$comment['postuserid']]))
			{
				continue;
			}

			if (!isset($photoIdMap[$comment['videoid']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$userId = $userIdMap[$comment['postuserid']];
				$username = $this->_convertToUtf8($comment['postusername'], true);
				$username = $this->_mbTrim($username, 50, $userId);
				$photoId = $photoIdMap[$comment['videoid']];

				$import = array(
					'content_type' => sonnb_XenGallery_Model_Video::$contentType,
					'content_id' => $photoId,
					'user_id' => $userId,
					'username' => $username,
					'message' => $this->_convertToUtf8($comment['message'], true),
					'comment_state' => $comment['state'] == 'visible' ? 'visible' : ($comment['state'] == 'moderation' ? 'moderated' : 'deleted'),
					'comment_date' => $comment['dateline']
				);

				$commentId = $model->importXenGalleryComment(0, $import);

				$model->logImportData('sonnb_xengallery_comment', $comment['videocommentid'], $commentId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	protected function _createVideoData($options, &$attachment, $data)
	{
		$attachFileOrig = "$options[path]/$attachment[videoservice]/$attachment[videoidservice].jpg";
		if (!is_file($attachFileOrig) || !file_exists($attachFileOrig))
		{
			return false;
		}

		return $this->_createVideoFiles($attachFileOrig, $data);
	}
}
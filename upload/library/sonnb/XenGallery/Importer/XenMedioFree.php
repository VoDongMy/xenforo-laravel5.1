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
class sonnb_XenGallery_Importer_XenMedioFree extends sonnb_XenGallery_Importer_Abstract
{
	const XENMEDIO_IMPORT_TYPE_CATEGORY_AS_ALBUM = 1;
	const XENMEDIO_IMPORT_TYPE_USERS_AS_ALBUM = 2;

	public static function getName()
	{
		return 'XenMedio (Media) Free => sonnb - XenGallery';
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
			if (intval($config['importType']) === self::XENMEDIO_IMPORT_TYPE_USERS_AS_ALBUM)
			{
				$config['importType'] = self::XENMEDIO_IMPORT_TYPE_USERS_AS_ALBUM;
			}
			else
			{
				$config['importType'] = self::XENMEDIO_IMPORT_TYPE_CATEGORY_AS_ALBUM;
			}

			$db = XenForo_Application::getConfig()->get('db');
			$config['db'] = array(
				'host' => $db->host,
				'port' => $db->port,
				'username' => $db->username,
				'password' => $db->password,
				'dbname' => $db->dbname
			);

			$this->_bootstrap($config);

			return true;
		}
		else
		{
			$input = array(
				'importType' => self::XENMEDIO_IMPORT_TYPE_CATEGORY_AS_ALBUM
			);

			return $controller->responseView(
				'sonnb_XenGallery_ViewAdmin_Import_XenMediaFree_Config',
				'sonnb_xengallery_import_xenmediafree_config',
				array(
					'input' => $input
				)
			);
		}
	}

	protected function _bootstrap(array $config)
	{
		@set_time_limit(0);

		$this->_config = $config;
	}

	public function stepAlbums($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$model = $this->_importModel;
		$db = $this->_db;

		if ($this->_config['importType'] == self::XENMEDIO_IMPORT_TYPE_CATEGORY_AS_ALBUM)
		{
			if ($options['max'] === false)
			{
				$data = $db->fetchRow('
	                SELECT MAX(category_id) AS max, COUNT(category_id) AS rows
	                FROM EWRmedio_categories
	                WHERE category_disabled = 0
				');

				$options = array_merge($options,$data);

				return array(0, $options, "Processing Albums ...");
			}

			$albums = $db->fetchAll($db->limit('
	                SELECT category.*
	                FROM EWRmedio_categories AS category
	                WHERE category_disabled = 0 AND category_id > ' . $this->_db->quote($start) . '
	                ORDER BY category_id ASC
	            ', $options['limit']
			));

			if (!$albums)
			{
				return true;
			}

			$next = 0;
			$last = 0;
			$total = 0;
			$admin = XenForo_Visitor::getInstance();
			foreach ($albums as $album)
			{
				$next = $album['category_id'];

				if ($last <> $next)
				{
					$last = $next;
					$albumPrivacy = array(
						'allow_view' => 'everyone',
						'allow_view_data' => array(),
						'allow_comment' => 'everyone',
						'allow_comment_data' => array(),
						'allow_add_photo' => 'everyone',
						'allow_add_photo_data' => array(),
						'allow_add_video' => 'everyone',
						'allow_add_video_data' => array(),
						'allow_download' => 'none',
						'allow_download_data' => array()
					);

					$import = array(
						'title' => $album['category_name'],
						'description' => $album['category_description'],
						'user_id' => $admin['user_id'],
						'username' => $admin['username'],
						'album_state' => 'visible',
						'view_count' => 0,
						'photo_count' => 0,
						'video_count' => 0,
						'content_count' => 0,
						'album_date' => XenForo_Application::$time,
						'album_updated_date' => XenForo_Application::$time,
						'album_privacy' => serialize($albumPrivacy),
						'category_id' => 0,
						'album_type' => sonnb_XenGallery_Model_Album::ALBUM_TYPE_NORMAL
					);

					if ($this->_config['retainKeys'])
					{
						$import['album_id'] = $album['category_id'];
					}

					$albumId = $model->importXenGalleryAlbum($album['category_id'], $import);

					$model->logImportData('sonnb_xengallery_album', $album['category_id'], $albumId);
				}

				$total++;
			}
		}
		else
		{
			if ($options['max'] === false)
			{
				$data = array();
				$data['max'] = $db->fetchOne('
	                SELECT MAX(user_id) AS max
		                FROM EWRmedio_media
						GROUP BY user_id
				');
					$data['rows'] = $db->fetchOne('
	                SELECT COUNT(*) AS rows FROM
	                    (SELECT media.*
			                FROM EWRmedio_media as media
							GROUP BY user_id) AS count
				');

				$options = array_merge($options,$data);

				return array(0, $options, "Processing Albums ...");
			}

			$albums = $db->fetchAll($db->limit('
	                SELECT media.user_id, media.username
					FROM EWRmedio_media AS media
	                WHERE media.user_id > ' . $this->_db->quote($start) . '
					GROUP BY user_id
	                ORDER BY user_id ASC
	            ', $options['limit']
			));

			if (!$albums)
			{
				return true;
			}

			$next = 0;
			$last = 0;
			$total = 0;
			foreach ($albums as $album)
			{
				$next = $album['user_id'];

				if ($last <> $next)
				{
					$last = $next;
					$albumPrivacy = array(
						'allow_view' => 'everyone',
						'allow_view_data' => array(),
						'allow_comment' => 'everyone',
						'allow_comment_data' => array(),
						'allow_add_photo' => 'none',
						'allow_add_photo_data' => array(),
						'allow_add_video' => 'none',
						'allow_add_video_data' => array(),
						'allow_download' => 'none',
						'allow_download_data' => array()
					);

					$title = new XenForo_Phrase('sonnb_xengallery_xenmedio_x_videos', array('username' => $album['username']));
					$import = array(
						'title' => $title->render(true),
						'description' => '',
						'user_id' => $album['user_id'],
						'username' => $album['username'],
						'album_state' => 'visible',
						'view_count' => 0,
						'photo_count' => 0,
						'video_count' => 0,
						'content_count' => 0,
						'album_date' => XenForo_Application::$time,
						'album_updated_date' => XenForo_Application::$time,
						'album_privacy' => serialize($albumPrivacy),
						'category_id' => 0,
						'album_type' => sonnb_XenGallery_Model_Album::ALBUM_TYPE_NORMAL
					);

					if ($this->_config['retainKeys'])
					{
						//$import['album_id'] = $album['user_id'];
					}

					$albumId = $model->importXenGalleryAlbum($album['user_id'], $import);

					$model->logImportData('sonnb_xengallery_album', $album['user_id'], $albumId);
				}

				$total++;
			}
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	public function stepVideos($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$db = $this->_db;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $db->fetchRow('
    				SELECT MAX(media_id) AS max, COUNT(media_id) AS rows
    				FROM EWRmedio_media
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Videos ...");
		}

		$videos = $db->fetchAll($db->limit("
    				SELECT media.*, services.service_name
    				FROM EWRmedio_media AS media

					LEFT JOIN EWRmedio_services AS services
						ON (services.service_id = media.service_id)

    				WHERE
    				    media.media_id > " . $db->quote($start) . "
    				ORDER BY media.media_id ASC
    			", $options['limit']
		));

		if (!$videos)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$position = 0;

		if ($this->_config['importType'] == self::XENMEDIO_IMPORT_TYPE_CATEGORY_AS_ALBUM)
		{
			$albumIdMap = $model->getAlbumIdsMapFromArray($videos, 'category_id');
		}
		else
		{
			$albumIdMap = $model->getAlbumIdsMapFromArray($videos, 'user_id');
		}

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);

		foreach ($videos as $video)
		{
			$next = $video['media_id'];

			if (empty($video['user_id']))
			{
				continue;
			}

			if ($this->_config['importType'] == self::XENMEDIO_IMPORT_TYPE_CATEGORY_AS_ALBUM)
			{
				if (empty($albumIdMap[$video['category_id']]))
				{
					continue;
				}

				$albumId = $albumIdMap[$video['category_id']];
			}
			else
			{
				if (empty($albumIdMap[$video['user_id']]))
				{
					continue;
				}

				$albumId = $albumIdMap[$video['user_id']];
			}

			if ($last <> $next)
			{
				$last = $next;

				$importVideoData = array(
					'file_size' => 0,
					'width' => 0,
					'height' => 0,
					'file_hash' => 0,
					'upload_date' => $video['media_date'],
					'duration' => $video['media_duration'],
					'unassociated' => 1,
					'extension' => 'jpg'
				);

				$videoData = $model->importXenGalleryVideoData($video['media_id'], $importVideoData);

				$model->logImportData('sonnb_xengallery_data', $video['media_id'], $videoData['content_data_id']);
				$success = $this->_createPhotoData($options, $video, $videoData);

				if ($success === false)
				{
					continue;
				}

				$model->importXenGalleryContentDataConfirm($videoData);

				$import = array(
					'video_type' => strtolower($video['service_name']),
					'video_key' => $video['service_value'],
					'album_id' => $albumId,
					'content_data_id' => $videoData['content_data_id'],
					'title' => $parser->render($this->_convertToUtf8($video['media_title'], true)),
					'description' => $parser->render($this->_convertToUtf8($video['media_description'], true)),
					'user_id' => $video['user_id'],
					'username' => $video['username'],
					'content_privacy' => array(
						'allow_view' => 'everyone',
						'allow_view_data' => array(),
						'allow_comment' => 'everyone',
						'allow_comment_data' => array()
					),
					'comment_count' => 0,
					'view_count' => $video['media_views'],
					'content_date' => $video['media_date'],
					'content_updated_date' => $video['media_date'],
					'likes' => $video['media_likes'],
					'like_users' => $video['media_like_users'],
					'position' => $position,
					'content_state' => $video['media_state']
				);

				if ($this->_config['retainKeys'])
				{
					$import['content_id'] = $video['media_id'];
				}

				$videoId = $model->importXenGalleryVideo($video['media_id'], $import);
				$model->logImportData('sonnb_xengallery_video', $video['media_id'], $videoId);

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

		$db = $this->_db;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $db->fetchRow("
    				SELECT MAX(comment_id) AS max, COUNT(comment_id) AS rows
    				FROM EWRmedio_comments
			");

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Comments ...");
		}

		$comments = $db->fetchAll($db->limit("
    				SELECT comment.*, user.user_id, user.username
    				FROM EWRmedio_comments AS comment
    				LEFT JOIN xf_user AS user
    				    ON (user.user_id = comment.user_id)
    				WHERE comment.comment_id > " . $db->quote($start) . "
    				ORDER BY comment.comment_id ASC
    			", $options['limit']
		));

		if (!$comments)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$videoIdMap = $model->getVideoIdsMapFromArray($comments, 'media_id');

		foreach ($comments as $comment)
		{
			$next = $comment['comment_id'];

			if (empty($comment['user_id']))
			{
				continue;
			}

			if (empty($videoIdMap[$comment['media_id']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$import = array(
					'content_type' => sonnb_XenGallery_Model_Video::$contentType,
					'content_id' => $videoIdMap[$comment['media_id']],
					'user_id' => $comment['user_id'],
					'username' => $comment['username'],
					'message' => $comment['comment_message'],
					'comment_state' => 'visible',
					'comment_date' => $comment['comment_date']
				);

				$commentId = $model->importXenGalleryComment($comment['comment_id'], $import);

				$model->logImportData('sonnb_xengallery_comment', $comment['comment_id'], $commentId);
			}

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	protected function _createPhotoData($options, &$attachment, $data)
	{
		$file = XenForo_Helper_File::getExternalDataPath()."/media/$attachment[media_id].jpg";
		if (!is_file($file) || !file_exists($file))
		{
			$file = sonnb_XenGallery_Model_ContentData::$videoNoThumbnail;
		}

		return $this->_createVideoFiles($file, $data);
	}
}
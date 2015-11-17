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
class sonnb_XenGallery_Importer_Thread extends sonnb_XenGallery_Importer_Abstract
{
	public static function getName()
	{
		return 'Forum Threads => sonnb - XenGallery';
	}

	public function getSteps()
	{
		return array(
			'albums' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_albums')
			),
			'photos' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_photos'),
				'depends' => array('albums')
			),
			'videos' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_videos'),
				'depends' => array('albums')
			),
			'comments' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_comments'),
				'depends' => array('photos')
			)
		);
	}

	public function configure(XenForo_ControllerAdmin_Abstract $controller, array &$config)
	{
		if ($config && !empty($config['node_id']))
		{
			$this->_bootstrap($config);

			return true;
		}
		else
		{
			/* @var $nodeModel XenForo_Model_Node*/
			$nodeModel = $controller->getModelFromCache('XenForo_Model_Node');
			/* @var $categoryModel sonnb_XenGallery_Model_Category*/
			$categoryModel = $controller->getModelFromCache('sonnb_XenGallery_Model_Category');

			$db = XenForo_Application::getConfig()->get('db');

			$viewParams = array(
				'db' => array(
					'host' => $db->host,
					'port' => $db->port,
					'username' => $db->username,
					'password' => $db->password,
					'dbname' => $db->dbname
				),

				'nodes' => $nodeModel->getAllNodes(),
				'categories' => $categoryModel->getAllCachedCategories()
			);

			return $controller->responseView(
				'sonnb_XenGallery_ViewAdmin_Import_Thread_Config',
				'sonnb_xengallery_import_thread_config',
				$viewParams
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

		if ($options['max'] === false)
		{
			$data = $db->fetchRow("
                SELECT MAX(thread_id) AS max, COUNT(thread_id) AS rows
                FROM xf_thread
                WHERE node_id = ?
					AND discussion_state = 'visible'
			", $this->_config['node_id']);

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Albums ...");
		}

		$albums = $db->fetchAll($db->limit('
                SELECT thread.*, post.likes, post.like_users, post.message, post.attach_count
				FROM xf_thread AS thread
					LEFT JOIN xf_post AS post ON (thread.first_post_id = post.post_id)
				WHERE
					thread.thread_id > ' . $this->_db->quote($start) . '
					AND thread.node_id = ?
					AND thread.discussion_state = \'visible\'
                ORDER BY thread.thread_id ASC
            ', $options['limit']
		), $this->_config['node_id']);

		if (!$albums)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);

		foreach ($albums as $album)
		{
			$next = $album['thread_id'];

			if (empty($album['user_id']))
			{
				continue;
			}

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

				$description = $parser->render($album['message']);
				$description = str_replace(array('[IMG]', '[ATTACH]', '[MEDIA]'), '', $description);

				$import = array(
					'title' => $album['title'],
					'description' => $description,
					'user_id' => $album['user_id'],
					'username' => $album['username'],
					'album_state' => $album['discussion_state'],
					'view_count' => $album['view_count'],
					'comment_count' => $album['reply_count'],
					'content_count' => $album['attach_count'],
					'photo_count' => $album['attach_count'],
					'cover_content_id' => 0,
					'album_date' => $album['post_date'],
					'album_updated_date' => $album['last_post_date'],
					'likes' => $album['likes'],
					'like_users' => $album['like_users'],
					'album_privacy' => serialize($albumPrivacy),
					'category_id' => 0,
					'album_type' => sonnb_XenGallery_Model_Album::ALBUM_TYPE_NORMAL
				);

				if (!empty($this->_config['category_id']))
				{
					$import['category_id'] = $this->_config['category_id'];
				}

				if ($this->_config['retainKeys'])
				{
					$import['album_id'] = $album['thread_id'];
				}

				$albumId = $model->importXenGalleryAlbum($album['thread_id'], $import);

				$model->logImportData('sonnb_xengallery_album', $album['thread_id'], $albumId);

				$this->_importModel->importContentLike('post', $album['first_post_id'], 'sonnb_xengallery_album', $albumId, $album['user_id']);
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
			'limit' => 10,
			'processed' => 0,
			'max' => false
		), $options);

		$db = $this->_db;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $db->fetchRow('
                SELECT MAX(attachment.attachment_id) AS max, COUNT(attachment.attachment_id) AS rows
                FROM xf_attachment AS attachment
					LEFT JOIN xf_post AS post
						ON (attachment.content_id = post.post_id)
					LEFT JOIN xf_thread AS thread
						ON (thread.thread_id = post.thread_id)
				WHERE thread.node_id = ?
					AND thread.discussion_state = \'visible\'
					AND attachment.content_type = \'post\'
					AND post.message_state = \'visible\'
			', $this->_config['node_id']);

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Photos ...");
		}

		$images = $db->fetchAll($db->limit("
    				SELECT attachment.*, attachment_data.*, post.username, post.user_id, post.thread_id
					FROM xf_attachment AS attachment
						LEFT JOIN xf_attachment_data AS attachment_data
							ON (attachment.data_id = attachment_data.data_id)
						LEFT JOIN xf_post AS post
							ON (attachment.content_id = post.post_id)
						LEFT JOIN xf_thread AS thread
							ON (thread.thread_id = post.thread_id)
					WHERE thread.node_id = ?
						AND thread.discussion_state = 'visible'
						AND attachment.attachment_id > ?
						AND attachment.content_type = 'post'
						AND post.message_state = 'visible'
					ORDER BY attachment.attachment_id ASC
    			", $options['limit']
		), array($this->_config['node_id'], $start));

		if (!$images)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$position = 0;
		$albumIdMap = $model->getAlbumIdsMapFromArray($images, 'thread_id');

		foreach ($images as $image)
		{
			$next = $image['attachment_id'];

			if (empty($albumIdMap[$image['thread_id']]))
			{
				continue;
			}

			if (empty($image['user_id']))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;

				$importPhotoData = array(
					'file_size' => $image['file_size'],
					'width' => $image['width'],
					'height' => $image['height'],
					'file_hash' => $image['file_hash'],
					'upload_date' => $image['upload_date'],
					'unassociated' => 0,
					'extension' => XenForo_Helper_File::getFileExtension($image['filename'])
				);

				if (!isset(sonnb_XenGallery_Model_ContentData::$typeMap[$importPhotoData['extension']]))
				{
					continue;
				}

				$photoData = $model->importXenGalleryPhotoData($image['attachment_id'], $importPhotoData);

				$model->logImportData('sonnb_xengallery_data', $image['attachment_id'], $photoData['content_data_id']);
				$success = $this->_createPhotoData($image, $photoData);

				if ($success === false)
				{
					continue;
				}

				$model->importXenGalleryContentDataConfirm($photoData);

				$title = @pathinfo($image['filename']);
				$import = array(
					'album_id' => $albumIdMap[$image['thread_id']],
					'content_data_id' => $photoData['content_data_id'],
					'title' => $title ? $title['filename'] : $photoData['content_data_id'],
					'description' => '',
					'user_id' => $image['user_id'],
					'username' => $image['username'],
					'content_privacy' => array(
						'allow_view' => 'everyone',
						'allow_view_data' => array(),
						'allow_comment' => 'everyone',
						'allow_comment_data' => array()
					),
					'comment_count' => 0,
					'view_count' => $image['view_count'],
					'content_date' => $image['attach_date'],
					'content_updated_date' => $image['attach_date'],
					'likes' => 0,
					'like_users' => 'a:0:{}',
					'position' => $position,
					'content_state' => 'visible'
				);

				if ($this->_config['retainKeys'])
				{
					$import['content_id'] = $image['attachment_id'];
				}

				$photoId = $model->importXenGalleryPhoto($image['attachment_id'], $import);
				$model->logImportData('sonnb_xengallery_photo', $image['attachment_id'], $photoId);

				$position++;
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
			'limit' => 50,
			'processed' => 0,
			'max' => false
		), $options);

		$db = $this->_db;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$data = $db->fetchRow('
                SELECT MAX(post.post_id) AS max, COUNT(post.post_id) AS rows
                FROM xf_post AS post
					LEFT JOIN xf_thread AS thread
						ON (thread.thread_id = post.thread_id)
				WHERE thread.node_id = ?
					AND thread.discussion_state = \'visible\'
					AND post.message_state = \'visible\'
			', $this->_config['node_id']);

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Videos...");
		}

		$videos = $db->fetchAll($db->limit("
    				SELECT post.*
					FROM xf_post AS post
						LEFT JOIN xf_thread AS thread
							ON (thread.thread_id = post.thread_id)
					WHERE thread.node_id = ?
						AND thread.discussion_state = 'visible'
						AND post.post_id > ?
						AND post.message_state = 'visible'
					ORDER BY post.post_id ASC
    			", $options['limit']
		), array($this->_config['node_id'], $start));

		if (!$videos)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;

		/* @var $videoDataModel sonnb_XenGallery_Model_VideoData */
		$videoDataModel = $this->_importModel->getModelFromCache('sonnb_XenGallery_Model_VideoData');
		$albumIdMap = $model->getAlbumIdsMapFromArray($videos, 'thread_id');

		foreach ($videos as $video)
		{
			$next = $video['post_id'];

			if (empty($albumIdMap[$video['thread_id']]))
			{
				continue;
			}

			if (empty($video['user_id']))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;

				if (preg_match_all('#\[media[^\]](.*)\](.*)\[/media\]#siU', $video['message'], $matches))
				{
					foreach ($matches[0] as $_key => $match)
					{
						if (empty($matches[1][$_key]) || empty($matches[2][$_key]))
						{
							continue;
						}

						$siteId = $matches[1][$_key];
						$mediaId = $matches[2][$_key];

						$embedData = $videoDataModel->getEmbedVideoData($siteId, $mediaId);

						if (!$embedData)
						{
							continue;
						}

						$extras = array(
							'temp_hash' => '',
							'content_type' => sonnb_XenGallery_Model_Video::$contentType
						);

						$videoData = $videoDataModel->insertEmbedVideoData($embedData, $extras);

						if (!$videoData)
						{
							continue;
						}

						$model->importXenGalleryContentDataConfirm($videoData);

						$import = array(
							'album_id' => $albumIdMap[$video['thread_id']],
							'content_data_id' => $videoData['content_data_id'],
							'description' => '',
							'user_id' => $video['user_id'],
							'username' => $video['username'],
							'content_privacy' => array(
								'allow_view' => 'everyone',
								'allow_view_data' => array(),
								'allow_comment' => 'everyone',
								'allow_comment_data' => array()
							),
							'comment_count' => 0,
							'view_count' => 0,
							'content_date' => $video['post_date'],
							'content_updated_date' => $video['post_date'],
							'likes' => 0,
							'like_users' => 'a:0:{}',
							'position' => 0,
							'content_state' => 'visible',
							'video_type' => $siteId,
							'video_key' => $mediaId
						);

						if ($this->_config['retainKeys'])
						{
							$import['content_id'] = $video['post_id'];
						}

						$photoId = $model->importXenGalleryVideo($video['post_id'], $import);
						$model->logImportData('sonnb_xengallery_video', $video['post_id'], $photoId);

						$total++;
					}
				}
			}
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
    				SELECT MAX(post.post_id) AS max, COUNT(post.post_id) AS rows
    				FROM xf_post as post
						LEFT JOIN xf_thread as thread
							ON (thread.thread_id = post.thread_id)
					WHERE thread.node_id = ?
						AND post.position > 0
						AND post.message_state = 'visible'
			", $this->_config['node_id']);

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Comments ...");
		}

		$comments = $db->fetchAll($db->limit("
    				SELECT post.*
					FROM xf_post as post
						LEFT JOIN xf_thread as thread
							ON (thread.thread_id = post.thread_id)
					WHERE thread.node_id = ?
						AND post.post_id > ?
						AND post.position > 0
						AND post.message_state = 'visible'
					ORDER BY post.post_id ASC
    			", $options['limit']
		), array($this->_config['node_id'], $start));

		if (!$comments)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = new XenForo_BbCode_Parser($formatter);
		$albumIdMap = $model->getPhotoIdsMapFromArray($comments, 'thread_id');

		foreach ($comments as $comment)
		{
			$next = $comment['post_id'];

			if (empty($comment['user_id']))
			{
				continue;
			}

			if (empty($albumIdMap[$comment['thread_id']]))
			{
				continue;
			}

			$message = $parser->render($comment['message']);
			$message = preg_replace('/\[(.*?)\]\s*(.*?)\s*\[(.*?)\]/', '[$1]$2[/$3]', $message);
			if (empty($message))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$import = array(
					'content_type' => sonnb_XenGallery_Model_Album::$contentType,
					'content_id' => $albumIdMap[$comment['thread_id']],
					'user_id' => $comment['user_id'],
					'username' => $comment['username'],
					'message' => $message,
					'comment_state' => $comment['message_state'],
					'comment_date' => $comment['post_date'],
					'likes' => $comment['likes'],
					'like_users' => $comment['like_users'],
				);

				$commentId = $model->importXenGalleryComment($comment['post_id'], $import);

				$model->logImportData('sonnb_xengallery_comment', $comment['post_id'], $commentId);
			}

			$this->_importModel->importContentLike('post', $comment['post_id'], 'sonnb_xengallery_comment', $commentId, $comment['user_id']);

			$total++;
		}

		$options['processed'] += $total;
		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($options['processed'], $options['rows']));
	}

	protected function _createPhotoData(&$attachment, $data)
	{
		/* @var $attachmentModel XenForo_Model_Attachment */
		$attachmentModel = $this->_importModel->getModelFromCache('XenForo_Model_Attachment');

		$file = $attachmentModel->getAttachmentDataFilePath($attachment);
		if (!is_file($file) || !file_exists($file))
		{
			return false;
		}

		return $this->_createPhotoFiles($file, $data);
	}
}
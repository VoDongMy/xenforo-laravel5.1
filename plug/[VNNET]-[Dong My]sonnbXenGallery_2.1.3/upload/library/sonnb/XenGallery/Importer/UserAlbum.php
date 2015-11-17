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
class sonnb_XenGallery_Importer_UserAlbum extends sonnb_XenGallery_Importer_Abstract
{
	public static function getName()
	{
		return '[xfr] UserAlbum => sonnb - XenGallery';
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
			'comments' => array(
				'title' => new XenForo_Phrase('sonnb_XenGallery_import_comments'),
				'depends' => array('photos')
			)
		);
	}

	public function configure(XenForo_ControllerAdmin_Abstract $controller, array &$config)
	{
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

		/**
		 * Nothing to configure at this time.
		 *
		if ($config)
		{
		}
		else
		{
			$db = XenForo_Application::getConfig()->get('db');

			$input = array(
				'db' => array(
					'host' => $db->host,
					'port' => $db->port,
					'username' => $db->username,
					'password' => $db->password,
					'dbname' => $db->dbname
				)
			);

			return $controller->responseView(
				'sonnb_XenGallery_ViewAdmin_Import_UserAlbum_Config',
				'sonnb_xengallery_import_useralbum_config',
				array(
					'input' => $input
				)
			);
		}
		 */
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
			$data = $db->fetchRow('
                SELECT MAX(album_id) AS max, COUNT(album_id) AS rows
                FROM xfr_useralbum
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Albums ...");
		}

		$albums = $db->fetchAll($db->limit('
                SELECT album.*, user.user_id, user.username
                FROM xfr_useralbum AS album
                LEFT JOIN xf_user AS user
                    ON (album.user_id = user.user_id)
                WHERE album_id > ' . $this->_db->quote($start) . '
                ORDER BY album_id ASC
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
			$next = $album['album_id'];

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
					'allow_download' => 'everyone',
					'allow_download_data' => array()
				);

				if ($album['album_type'] === 'private')
				{
					$albumPrivacy['allow_view'] = 'none';
					$albumPrivacy['allow_comment'] = 'none';
				}
				elseif ($album['album_type'] === 'global')
				{
					$albumPrivacy['allow_add_photo'] = 'everyone';
					$albumPrivacy['allow_add_video'] = 'everyone';
				}

				if (@unserialize($album['like_users']))
				{
					$album['like_users'] = @unserialize($album['like_users']);
				}
				else
				{
					$album['like_users'] = array();
				}

				$import = array(
					'title' => $album['title'],
					'description' => $album['description'],
					'user_id' => $album['user_id'],
					'username' => $album['username'],
					'album_state' => $album['moderation'] ? 'moderated' : 'visible',
					'view_count' => $album['view_count'],
					'photo_count' => $album['image_count'],
					'content_count' => $album['image_count'],
					'cover_content_id' => $album['cover_image_id'],
					'album_date' => $album['createdate'],
					'album_updated_date' => $album['last_image_date'] ? $album['last_image_date'] : $album['createdate'],
					'likes' => $album['likes'],
					'like_users' => $album['like_users'],
					'album_privacy' => serialize($albumPrivacy),
					'category_id' => 0,
					'album_type' => sonnb_XenGallery_Model_Album::ALBUM_TYPE_NORMAL
				);

				if ($this->_config['retainKeys'])
				{
					$import['album_id'] = $album['album_id'];
				}

				$albumId = $model->importXenGalleryAlbum($album['album_id'], $import);

				$model->logImportData('sonnb_xengallery_album', $album['album_id'], $albumId);

				$this->_importModel->importContentLike('xfr_useralbum', $album['album_id'], 'sonnb_xengallery_album', $albumId, $album['user_id']);
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
    				SELECT MAX(image_id) AS max, COUNT(image_id) AS rows
    				FROM xfr_useralbum_image
			');

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Photos ...");
		}

		$images = $db->fetchAll($db->limit("
    				SELECT image.*, image_data.*, user.user_id, user.username
    				FROM xfr_useralbum_image as image

					LEFT JOIN xfr_useralbum_image_data as image_data
						ON (image.data_id = image_data.data_id)
					LEFT JOIN xf_user as user
						ON (user.user_id = image_data.user_id)

    				WHERE image.image_id > " . $db->quote($start) . "
    				ORDER BY image.image_id ASC
    			", $options['limit']
		));

		if (!$images)
		{
			return true;
		}

		$next = 0;
		$last = 0;
		$total = 0;
		$position = 0;
		$albumIdMap = $model->getAlbumIdsMapFromArray($images, 'album_id');

		foreach ($images as $image)
		{
			$next = $image['image_id'];

			if (empty($albumIdMap[$image['album_id']]))
			{
				continue;
			}

			if (empty($image['user_id']))
			{
				continue;
			}

			$ext = strtolower(XenForo_Helper_File::getFileExtension($image['filename']));
			if (!isset(sonnb_XenGallery_Model_ContentData::$typeMap[$ext]))
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
					'unassociated' => 1,
					'extension' => $ext
				);

				$photoData = $model->importXenGalleryPhotoData($image['image_id'], $importPhotoData);

				$model->logImportData('sonnb_xengallery_data', $image['image_id'], $photoData['content_data_id']);
				$success = $this->_createPhotoData($options, $image, $photoData);

				if ($success === false)
				{
					continue;
				}

				$model->importXenGalleryContentDataConfirm($photoData);

				$title = @pathinfo($image['filename']);
				$import = array(
					'album_id' => $albumIdMap[$image['album_id']],
					'content_data_id' => $photoData['content_data_id'],
					'title' => $title ? $title['filename'] : $photoData['content_data_id'],
					'description' => $image['description'],
					'user_id' => $image['user_id'],
					'username' => $image['username'],
					'content_privacy' => array(
						'allow_view' => 'everyone',
						'allow_view_data' => array(),
						'allow_comment' => 'everyone',
						'allow_comment_data' => array()
					),
					'comment_count' => $image['comment_count'],
					'view_count' => $image['view_count'],
					'content_date' => $image['image_date'],
					'content_updated_date' => $image['image_date'],
					'likes' => $image['likes'],
					'like_users' => $image['like_users'],
					'position' => $position,
					'content_state' => 'visible'
				);

				if ($this->_config['retainKeys'])
				{
					$import['content_id'] = $image['image_id'];
				}

				$photoId = $model->importXenGalleryPhoto($image['image_id'], $import);
				$model->logImportData('sonnb_xengallery_photo', $image['image_id'], $photoId);

				$this->_importModel->importContentLike('xfr_useralbum_image', $image['image_id'], 'sonnb_xengallery_photo', $photoId, $image['user_id']);

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
    				FROM xfr_useralbum_image_comment
			");

			$options = array_merge($options,$data);

			return array(0, $options, "Processing Comments ...");
		}

		$comments = $db->fetchAll($db->limit("
    				SELECT comment.*, user.user_id, user.username
    				FROM xfr_useralbum_image_comment AS comment
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
		$photoIdMap = $model->getPhotoIdsMapFromArray($comments, 'image_id');

		foreach ($comments as $comment)
		{
			$next = $comment['comment_id'];

			if (empty($comment['user_id']))
			{
				continue;
			}

			if (empty($photoIdMap[$comment['image_id']]))
			{
				continue;
			}

			if ($last <> $next)
			{
				$last = $next;
				$import = array(
					'content_type' => sonnb_XenGallery_Model_Photo::$contentType,
					'content_id' => $photoIdMap[$comment['image_id']],
					'user_id' => $comment['user_id'],
					'username' => $comment['username'],
					'message' => $comment['message'],
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
		/* @var $imageDataModel XfRu_UserAlbums_Model_Images */
		$imageDataModel = XenForo_Model::create('XfRu_UserAlbums_Model_Images');

		$file = $imageDataModel->getImageDataFilePath($attachment);
		if (!is_file($file) || !file_exists($file))
		{
			return false;
		}

		return $this->_createPhotoFiles($file, $data);
	}
}
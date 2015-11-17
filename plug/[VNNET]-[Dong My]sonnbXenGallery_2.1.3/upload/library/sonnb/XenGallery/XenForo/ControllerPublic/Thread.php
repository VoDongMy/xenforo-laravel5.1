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
class sonnb_XenGallery_XenForo_ControllerPublic_Thread extends XFCP_sonnb_XenGallery_XenForo_ControllerPublic_Thread
{
	public function actionGalleryImport()
	{
		$visitor = XenForo_Visitor::getInstance();
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		/* @var $ftpHelper XenForo_ControllerHelper_ForumThreadPost */
		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($threadFetchOptions, $forumFetchOptions) = $this->_getThreadForumFetchOptions();

		if (isset($threadFetchOptions['join']))
		{
			$threadFetchOptions['join'] |= XenForo_Model_Thread::FETCH_FIRSTPOST;
		}
		else
		{
			$threadFetchOptions['join'] = XenForo_Model_Thread::FETCH_FIRSTPOST;
		}

		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId, $threadFetchOptions, $forumFetchOptions);

		if (!$visitor->hasPermission('sonnb_xengallery', 'importAnyThread') &&
				(!$visitor->hasPermission('sonnb_xengallery', 'importOwnThread') && $visitor['user_id'] == $thread['user_id']))
		{
			return $this->responseNoPermission();
		}

		if ($thread['sonnb_xengallery_import'] && !XenForo_Application::getOptions()->sonnbXG_allowMultipleImport)
		{
			return $this->responseError(new XenForo_Phrase('sonnb_xengallery_this_thread_was_already_imported'));
		}

		if ($this->isConfirmedPost())
		{
			$targetType = $this->_input->filterSingle('target_album', XenForo_Input::STRING);

			$importIds = $this->_input->filterSingle('import_id', XenForo_Input::ARRAY_SIMPLE);
			$importIds = array_filter($importIds);
			$importIds = array_map('intval', $importIds);
			if (!count($importIds))
			{
				return $this->responseError(new XenForo_Phrase('sonnb_xengallery_there_is_no_image_selected'));
			}

			$attachments = $this->_getXenGalleryAttachmentModel()->getAttachmentsForXenGalleryByAttachmentIds($importIds);
			if (!count($attachments))
			{
				return $this->responseError(new XenForo_Phrase('sonnb_xengallery_there_is_no_image_selected'));
			}

			@set_time_limit(0);
			ignore_user_abort(true);
			$importModel = $this->_getXenGalleryImportModel();

			switch ($targetType)
			{
				case 'existing':
					$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
					foreach ($attachments as $attachment)
					{
						$this->_importXenGalleryPhoto($attachment, $importModel, $albumId);
					}
					break;
				case 'new':
					$postModel = $this->_getPostModel();

                    //TODO: limit???
					$postFetchOptions = $this->_getPostFetchOptions($thread, $forum);
					$posts = $postModel->getPostsInThread($threadId, $postFetchOptions);

					$firstPost = array(
						'likes' => 0,
						'like_users' => 'a:0:{}',
						'message' => '',
						'post_id' => 0,
					);
					if ($thread['first_post_id'] && isset($posts[$thread['first_post_id']]))
					{
						$firstPost = $posts[$thread['first_post_id']];
						unset($posts[$thread['first_post_id']]);
					}

					$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
					$importComment = $this->_input->filterSingle('import_comment', XenForo_Input::UINT);
					$title = $this->_input->filterSingle('title', XenForo_Input::STRING);

					/*
					$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
					$parser = new XenForo_BbCode_Parser($formatter);

					$description = $parser->render($firstPost['message']);
					$description = str_replace(array('[IMG]', '[ATTACH]', '[MEDIA]'), '', $description);
					*/

					$albumPrivacy = array(
						'allow_view' => 'everyone',
						'allow_comment' => 'everyone',
						'allow_download' => 'none',
						'allow_add_photo' => 'none'
					);

					$visitor = XenForo_Visitor::getInstance();
					if (!empty($visitor['xengallery']))
					{
						$albumPrivacy = array(
							'allow_view' => $visitor['xengallery']['album_allow_view'],
							'allow_comment' => $visitor['xengallery']['album_allow_comment'],
							'allow_download' => $visitor['xengallery']['album_allow_download'],
							'allow_add_photo' => $visitor['xengallery']['album_allow_add_photo']
						);
					}

					//TODO: allow to modify the description
					$album = array(
						'title' => $title,
						'description' => XenForo_Helper_String::wholeWordTrim($firstPost['message'], 1990),
						'user_id' => $thread['user_id'],
						'username' => $thread['username'],
						'album_state' => $thread['discussion_state'],
						'comment_count' => 0,
						'view_count' => $thread['view_count'],
						'cover_content_id' => 0,
						'album_date' => $thread['post_date'],
						'album_updated_date' => $thread['last_post_date'],
						'album_privacy' => serialize($albumPrivacy),
						'category_id' => $categoryId,
						'album_type' => sonnb_XenGallery_Model_Album::ALBUM_TYPE_NORMAL
					);

					$albumId = $importModel->importXenGalleryAlbum($thread['thread_id'], $album, false);

					if (!$albumId)
					{
						return $this->responseError(new XenForo_Phrase('sonnb_xengallery_cannot_import_album_please_check_your_information'));
					}

					//Import photos
					foreach ($attachments as $attachment)
					{
						$this->_importXenGalleryPhoto($attachment, $importModel, $albumId);
					}

					//Import Comments
					if ($posts && $importComment)
					{
						foreach ($posts as $postId => $post)
						{
							//$message = $parser->render($post['message']);
							//$message = preg_replace('/\[(.*?)\]\s*(.*?)\s*\[(.*?)\]/', '[$1]$2[/$3]', $message);

							$comment = array(
								'content_type' => sonnb_XenGallery_Model_Album::$contentType,
								'content_id' => $albumId,
								'user_id' => $post['user_id'],
								'username' => $post['username'],
								'message' => $post['message'],
								'comment_state' => $post['message_state'],
								'comment_date' => $post['post_date']
							);

							$importModel->importXenGalleryComment($postId, $comment, false);
						}
					}
					break;
			}

			XenForo_Application::getDb()->update(
				'xf_thread',
				array(
					'sonnb_xengallery_import' => 1
				),
				array(
					'thread_id = ?' => $thread['thread_id']
				)
			);

            if (XenForo_Application::$versionId >= 1020000)
            {
                XenForo_Application::defer('Atomic',
                    array('simple' => array('sonnb_XenGallery_Deferred_Album', 'sonnb_XenGallery_Deferred_Content')),
                    'importRebuild', false
                );
            }

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenFOro_Link::buildPublicLink('gallery/albums', array('album_id' => $albumId)),
				new XenForo_Phrase('sonnb_xengallery_your_thread_has_been_imported_successful')
			);
		}
		else
		{
			$attachments = $this->_getXenGalleryAttachmentModel()->getAttachmentsForXenGalleryByThreadId($thread['thread_id']);
			$attachments = $this->_getAttachmentModel()->prepareAttachments($attachments);

			if (!count($attachments))
			{
				return $this->responseError(new XenForo_Phrase('sonnb_xengallery_there_is_no_image_attached_to_this_thread'));
			}

			$albumConditions = array();
			$albumFetchOption = array(
				'orderDirection' => 'DESC'
			)+$this->_getXenGalleryAlbumModel()->getPermissionBasedAlbumFetchConditions();
			$albums = $this->_getXenGalleryAlbumModel()->getAlbumsByUserId($visitor['user_id'], $albumConditions, $albumFetchOption);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Thread_Import',
				'sonnb_xengallery_thread_import_index',
				array(
					'thread' => $thread,
					'forum' => $forum,

					'categories' => $this->_getXenGalleryCategoryModel()->getAllCachedCategories(),
					'albums' => $albums,

					'attachments' => $attachments,
					'totalAttachment' => count($attachments)
				)
			);
		}
	}

	protected function _importXenGalleryPhoto($attachment, sonnb_XenGallery_Model_Import $importModel, $albumId)
	{
		$attachmentModel = $this->_getAttachmentModel();
		if (is_callable(array($attachmentModel, 'bdAttachmentStore_useTempFile')))
		{
			$bdAttachmentStore_useTempFile = true;
			$attachmentModel->bdAttachmentStore_useTempFile(true);
		}

		$imagePath = $this->_getAttachmentModel()->getAttachmentDataFilePath($attachment);
		if (!empty($bdAttachmentStore_useTempFile))
		{
			$attachmentModel->bdAttachmentStore_useTempFile(false);
		}

		$photoData = array(
			'file_size' => $attachment['file_size'],
			'width' => $attachment['width'],
			'height' => $attachment['height'],
			'file_hash' => $attachment['file_hash'],
			'upload_date' => $attachment['upload_date'],
			'extension' => XenForo_Helper_File::getFileExtension($attachment['filename'])
		);

        $importToStore = false;
        if (XenForo_Application::getOptions()->sonnbXG_importThreadStore)
        {
            $importToStore = true;
        }

		$photoDataNew = $importModel->importXenGalleryPhotoData($attachment['attachment_id'], $photoData);
		$importModel->createPhotoThumbnails($imagePath, $photoDataNew, $importToStore);

		$imagePrivacy = array(
			'allow_view' => 'everyone',
			'allow_view_data' => array(),
			'allow_comment' => 'everyone',
			'allow_comment_data' => array()
		);

		$title = @pathinfo($attachment['filename'], PATHINFO_FILENAME);
		$photo = array(
			'album_id' => $albumId,
			'content_data_id' => $photoDataNew['content_data_id'],
			'title' => $title ? $title : $photoDataNew['content_data_id'],
			'description' => '',
			'user_id' => $attachment['user_id'],
			'username' => $attachment['username'],
			'content_privacy' => @serialize($imagePrivacy),
			'comment_count' => 0,
			'view_count' => $attachment['view_count'],
			'content_date' => $attachment['attach_date'],
			'content_updated_date' => $attachment['attach_date'],
			'position' => 0,
			'content_state' => 'visible'
		);

		$photoId = $importModel->importXenGalleryPhoto($attachment['attachment_id'], $photo, false);

		return $photoId;
	}

	/**
	 * @return sonnb_XenGallery_Model_Category
	 */
	protected function _getXenGalleryCategoryModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Category');
	}

	/**
	 * @return sonnb_XenGallery_Model_Attachment
	 */
	protected function _getXenGalleryAttachmentModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Attachment');
	}

	/**
	 * @return sonnb_XenGallery_Model_Album
	 */
	protected function _getXenGalleryAlbumModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Album');
	}

	/**
	 * @return sonnb_XenGallery_Model_Import
	 */
	protected function _getXenGalleryImportModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Import');
	}
}
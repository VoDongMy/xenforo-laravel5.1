<?php

class XenGallery_ControllerHelper_Media extends XenForo_ControllerHelper_Abstract
{
	/**
	 * Checks that albums are generally viewable.
	 * This doesn't need to be done for media, generally, as that is handled by _preDispatch.
	 *
	 * @return bool|error
	 */
	public function assertAlbumsAreViewable()
	{
		if (!$this->_getAlbumModel()->canViewAlbums($error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}		
	}
	
	/**
	 * Checks that an album is valid and viewable, before returning the album's info.
	 *
	 * @param integer|null $id Album ID
	 *
	 * @return array Album info
	 */
	public function assertAlbumValidAndViewable($albumId)
	{
		$album = $this->_getAlbumOrError($albumId);
		$album = $this->_getAlbumModel()->prepareAlbumWithPermissions($album);
		
		$this->assertAlbumViewable($album);

		return $album;
	}

	public function assertCanChangeAlbumOrder(array $album)
	{
		if (!$this->_getAlbumModel()->canChangeCustomOrder($album, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}
	}

	public function assertCanChangeAlbumThumbnail(array $album)
	{
		if (!$this->_getAlbumModel()->canChangeThumbnail($album, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}
	}

	public function assertCanChangeMediaThumbnail(array $media)
	{
		if (!$this->_getMediaModel()->canChangeThumbnail($media, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}
	}
	
	/**
	 * Checks that an album is valid and sharable, before returning the album's info.
	 *
	 * @param integer|null $id Album ID
	 *
	 * @return array Album info
	 */
	public function assertAlbumValidAndSharable($albumId)
	{
		$album = $this->_getAlbumOrError($albumId);
	
		$this->assertCanShareAlbum($album);
		
		return $album;
	}
	
	public function assertAlbumViewable($album)
	{
		if (!$this->_getAlbumModel()->canViewAlbum($album, $error))
		{
			if (!empty($album['media_id']))
			{
				$error = 'xengallery_no_permission_to_view_media_in_this_album';
			}

			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}
	}

	public function assertCanEditAlbum($album)
	{
		if (!$this->_getAlbumModel()->canEditAlbum($album, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}
	}

	public function assertCanDeleteAlbum($album, $type = 'soft')
	{
		if (!$this->_getAlbumModel()->canDeleteAlbum($album, $type, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}
	}
	
	public function assertCanLikeAlbum($album)
	{
		if (!$this->_getAlbumModel()->canLikeAlbum($album, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}
	}
	
	public function assertCanRateAlbum($album)
	{
		if (!$this->_getAlbumModel()->canRateAlbum($album, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}
	}
	
	public function assertCanShareAlbum($album)
	{
		if (!$this->_getAlbumModel()->canShareAlbum($album, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}
	}

	public function assertcanChangeAlbumViewPerm($album)
	{
		if (!$this->_getAlbumModel()->canChangeAlbumViewPerm($album, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}
	}
		
	/**
	 * Checks that a category is valid and viewable, before returning the category's info.
	 *
	 * @param integer|null $categoryId Category ID
	 *
	 * @return array Forum info
	 */
	public function assertCategoryValidAndViewable($categoryId)
	{
		$category = $this->_getCategoryOrError($categoryId);

		$this->assertCategoryViewable($category);

		return $category;
	}

	public function assertCategoryViewable($category)
	{
		if (!$this->_getCategoryModel()->canViewCategory($category, $error))
		{
			if (!empty($category['media_id']))
			{
				$error = 'xengallery_no_permission_to_view_media_in_this_category';
			}

			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}
	}
	
	public function assertMediaValidAndViewable($mediaId, $fetchOptions = array())
	{
		$mediaModel = $this->_getMediaModel();
		
		if (!$fetchOptions)
		{
			$fetchOptions = $this->_getMediaFetchOptions();
		}
		
		$media = $mediaModel->getMediaById($mediaId, $fetchOptions);
		
		if (!$media)
		{
			throw $this->_controller->responseException($this->_controller->responseError(new XenForo_Phrase('xengallery_requested_media_not_found'), 404));
		}
		
		if (!empty($media['album_id']))
		{
			$media = $this->_getAlbumModel()->prepareAlbumWithPermissions($media);
			$this->assertAlbumViewable($media);
		}

		if (!empty($media['category_id']))
		{
			$this->assertCategoryViewable($media);
		}
		
		if (!$mediaModel->canViewDeletedMedia($error) && $media['media_state'] == 'deleted')
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);			
		}
		
		if (!$mediaModel->canViewUnapprovedMedia($error) && $media['media_state'] == 'moderated')
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}		
		
		$media = $mediaModel->prepareMedia($media);

		if ($media['category_id'])
		{
			$media = $this->_getCategoryModel()->prepareCategory($media);
		}
		else
		{
			$media = $this->_getAlbumModel()->prepareAlbum($media);
		}
		
		return $media;
	}

	public function assertMediaValidAndMovable($mediaId, $fetchOptions = array())
	{
		$mediaModel = $this->_getMediaModel();

		$media = $this->assertMediaValidAndViewable($mediaId, $fetchOptions);
		if (!$mediaModel->canMoveMedia($media, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}

		return $media;
	}
	
	public function assertCanAddMedia()
	{
		if (!$this->_getMediaModel()->canAddMedia($error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}		
	}
	
	public function assertCanLikeMedia($media)
	{
		if (!$this->_getMediaModel()->canLikeMedia($media, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}
	}
	
	public function assertCanEditMedia($media)
	{
		if (!$this->_getMediaModel()->canEditMedia($media, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}		
	}
	
	public function assertCanRateMedia($media)
	{
		if (!$this->_getMediaModel()->canRateMedia($media, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}		
	}

	public function assertCanCropMedia($media)
	{
		if (!$this->_getMediaModel()->canCropMedia($media, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}
	}

	public function assertCanTagMedia($media)
	{
		$tagSelfOnly = $this->_getMediaModel()->canTagMedia($media, $error);
		if (!$tagSelfOnly)
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}

		return $this->_getMediaModel()->canTagMedia($media, $error);
	}
	
	public function assertCanDeleteMedia($media, $type = 'soft')
	{
		if (!$this->_getMediaModel()->canDeleteMedia($media, $type, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}		
	}

	public function assertCanDeleteTag(array $media, array $tag)
	{
		$visitor = XenForo_Visitor::getInstance()->toArray();

		$canDeleteTag = $this->_getMediaModel()->canDeleteTag($media, $error, $visitor);
		if (!$canDeleteTag)
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}

		if ($canDeleteTag === 'self')
		{
			if ($tag['user_id'] != $visitor['user_id'])
			{
				throw $this->_controller->getErrorOrNoPermissionResponseException(new XenForo_Phrase('xengallery_you_can_only_delete_tags_of_yourself'));
			}
		}

		return $canDeleteTag;
	}
	
	public function assertCanViewComments()
	{
		if (!$this->_getCommentModel()->canViewComments($error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}
	}
	
	public function assertCanAddComment()
	{
		if (!$this->_getCommentModel()->canAddComment($error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}
	}	
	
	public function assertCommentValidAndViewable($commentId, array $fetchOptions = array())
	{
		$commentModel = $this->_getCommentModel();

		$comment = $commentModel->getCommentById($commentId, $this->_getCommentFetchOptions());
		
		if (!$comment)
		{
			throw $this->_controller->responseException($this->_controller->responseError(new XenForo_Phrase('xengallery_requested_comment_not_found'), 404));
		}
		
		if (!$commentModel->canViewDeletedComment($error) && $comment['comment_state'] == 'deleted')
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);			
		}
		
		return $comment;
	}

	public function assertCommentAndContentValidAndViewable($commentId, array $fetchOptions = array())
	{
		if (!$fetchOptions)
		{
			$fetchOptions = $this->_getCommentFetchOptions();
		}

		$commentModel = $this->_getCommentModel();

		$comment = $commentModel->getCommentById($commentId, $fetchOptions);
		if (!$comment)
		{
			throw $this->_controller->responseException($this->_controller->responseError(new XenForo_Phrase('xengallery_requested_comment_not_found'), 404));
		}

		if (!$commentModel->canViewDeletedComment($error) && $comment['comment_state'] == 'deleted')
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}

		if ($comment['content_type'] == 'media')
		{
			$content = $this->assertMediaValidAndViewable($comment['content_id']);
			$content['content_title'] = $content['media_title'];
			$content['isAlbum'] = false;
		}
		else
		{
			$content = $this->assertAlbumValidAndViewable($comment['content_id']);
			$content['content_title'] = $content['album_title'];
			$content['isAlbum'] = true;
		}

		return array($comment, $content);
	}
	
	public function assertCanEditComment($comment)
	{
		if (!$this->_getCommentModel()->canEditComment($comment, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}		
	}	
	
	public function assertCanDeleteComment($comment, $type = 'soft')
	{
		if (!$this->_getCommentModel()->canDeleteComment($comment, $type, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}		
	}

	public function assertCanLikeComment($comment)
	{
		if (!$this->_getCommentModel()->canLikeComment($comment, $error))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($error);
		}
	}
	
	/**
	 * Gets the specified record or errors.
	 *
	 * @param string $albumId
	 *
	 * @return array
	 */
	protected function _getAlbumOrError($albumId)
	{
		$album = $this->_getAlbumModel()->getAlbumById($albumId, $this->_getDefaultAlbumFetchOptions());
		
		if (!$album)
		{
			throw $this->_controller->responseException($this->_controller->responseError(new XenForo_Phrase('xengallery_requested_album_not_found'), 404));
		}

		return $album;
	}

	protected function _getDefaultAlbumFetchOptions()
	{
		return array(
			'join' => XenGallery_Model_Album::FETCH_USER,
			'watchUserId' => XenForo_Visitor::getUserId()
		);
	}
		
	/**
	 * Gets the specified record or errors.
	 *
	 * @param string $categoryId
	 *
	 * @return array
	 */
	protected function _getCategoryOrError($categoryId)
	{
		$categoryModel = $this->_getCategoryModel();
		$category = $categoryModel->getCategoryById($categoryId);
		if (!$category)
		{
			throw $this->_controller->responseException($this->_controller->responseError(new XenForo_Phrase('requested_category_not_found'), 404));
		}

		return $category;
	}
	
	protected function _getMediaFetchOptions()
	{
		$visitor = XenForo_Visitor::getInstance();

		$mediaFetchOptions = array(
			'join' => XenGallery_Model_Media::FETCH_USER
				| XenGallery_Model_Media::FETCH_ATTACHMENT
				| XenGallery_Model_Media::FETCH_CATEGORY
				| XenGallery_Model_Media::FETCH_ALBUM,
			'watchUserId' => $visitor->getUserId()
		);

		if ($visitor->hasPermission('xengallery', 'viewDeleted'))
		{
			$mediaFetchOptions['join'] |= XenGallery_Model_Media::FETCH_DELETION_LOG;
		}

		return $mediaFetchOptions;
	}
	
	protected function _getCommentFetchOptions()
	{
		$commentFetchOptions = array(
			'join' => XenGallery_Model_Comment::FETCH_USER
				| XenGallery_Model_Comment::FETCH_CATEGORY
				| XenGallery_Model_Comment::FETCH_ALBUM
				| XenGallery_Model_Comment::FETCH_MEDIA
		);
		
		if (XenForo_Visitor::getInstance()->hasPermission('xengallery', 'viewDeletedComments'))
		{
			$commentFetchOptions['join'] |= XenGallery_Model_Comment::FETCH_DELETION_LOG;
		}

		return $commentFetchOptions;
	}		
	
	/**
	 * @return XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->_controller->getModelFromCache('XenGallery_Model_Album');
	}	
	
	/**
	 * @return XenGallery_Model_Category
	 */
	protected function _getCategoryModel()
	{
		return $this->_controller->getModelFromCache('XenGallery_Model_Category');
	}
	
	/**
	 * @return XenGallery_Model_Media
	 */	
	protected function _getMediaModel()
	{
		return $this->_controller->getModelFromCache('XenGallery_Model_Media');
	}	
	
	/**
	 * @return XenForo_Model_Attachment
	 */	
	protected function _getAttachmentModel()
	{
		return $this->_controller->getModelFromCache('XenForo_Model_Attachment');
	}

	/**
	 * @return XenGallery_Model_Comment
	 */	
	protected function _getCommentModel()
	{
		return $this->_controller->getModelFromCache('XenGallery_Model_Comment');
	}
}
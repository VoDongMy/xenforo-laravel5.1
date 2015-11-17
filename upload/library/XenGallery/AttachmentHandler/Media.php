<?php

class XenGallery_AttachmentHandler_Media extends XenForo_AttachmentHandler_Abstract
{
	protected $_mediaModel = null;
	protected $_albumModel = null;
	protected $_categoryModel = null;

	/**
	 * Key of primary content in content data array.
	 *
	 * @var string
	 */
	protected $_contentIdKey = 'media_id';

	/**
	 * Route to get to XenForo Media Gallery
	 *
	 * @var string
	 */
	protected $_contentRoute = 'xengallery';

	/**
	 * Name of the phrase that describes the conversation_message content type
	 *
	 * @var string
	 */
	protected $_contentTypePhraseKey = 'xengallery_media';

	/**
	 * Determines if attachments and be uploaded and managed in this context.
	 *
	 * @see XenForo_AttachmentHandler_Abstract::_canUploadAndManageAttachments()
	 */
	protected function _canUploadAndManageAttachments(array $contentData, array $viewingUser)
	{
		if (!empty($contentData['album_id']))
		{
			$albumModel = $this->_getAlbumModel();

			$album = $albumModel->getAlbumById($contentData['album_id']);
			$album = $albumModel->prepareAlbumWithPermissions($album);

			if ($album && $albumModel->canViewAlbum($album, $null, $viewingUser))
			{
				return $albumModel->canAddMediaToAlbum($album, $null, $viewingUser);
			}

			return false;
		}
		else if(!empty($contentData['category_id']))
		{
			$categoryModel = $this->_getCategoryModel();

			$category = $categoryModel->getCategoryById($contentData['category_id']);
			if ($category && $categoryModel->canViewCategory($category, $null, $viewingUser))
			{
				return $categoryModel->canAddMediaToCategory($category, $null, $viewingUser);
			}

			return false;
		}

		return $this->_getMediaModel()->canAddMedia();
	}

	/**
	 * Determines if the specified attachment can be viewed.
	 *
	 * @see XenForo_AttachmentHandler_Abstract::_canViewAttachment()
	 */
	protected function _canViewAttachment(array $attachment, array $viewingUser)
	{
		$mediaModel = $this->_getMediaModel();

		$fetchOptions = array(
			'join' => XenGallery_Model_Media::FETCH_USER
				| XenGallery_Model_Media::FETCH_ATTACHMENT
				| XenGallery_Model_Media::FETCH_CATEGORY
				| XenGallery_Model_Media::FETCH_ALBUM,
			'watchUserId' => $viewingUser['user_id']
		);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewDeleted'))
		{
			$fetchOptions['join'] |= XenGallery_Model_Media::FETCH_DELETION_LOG;
		}

		$mediaId = $mediaModel->getMediaIdByAttachmentId($attachment['attachment_id']);
		$media = $mediaModel->getMediaById($mediaId, $fetchOptions);

		if (!$media)
		{
			return false;
		}

		if (!empty($media['album_id']))
		{
			$albumModel = $this->_getAlbumModel();

			$media = $albumModel->prepareAlbumWithPermissions($media);

			if (!$albumModel->canViewAlbum($media, $null, $viewingUser))
			{
				return false;
			}
		}

		if (!empty($media['category_id']))
		{
			if (!$this->_getCategoryModel()->canViewCategory($media, $null, $viewingUser))
			{
				return false;
			}
		}

		if (!$mediaModel->canViewDeletedMedia($error, $viewingUser) && $media['media_state'] == 'deleted')
		{
			return false;
		}

		if (!$mediaModel->canViewUnapprovedMedia($error, $viewingUser) && $media['media_state'] == 'moderated')
		{
			return false;
		}

		return true;
	}

	/**
	 * Code to run after deleting an associated attachment.
	 *
	 * @see XenForo_AttachmentHandler_Abstract::attachmentPostDelete()
	 */
	public function attachmentPostDelete(array $attachment, Zend_Db_Adapter_Abstract $db)
	{
		$attachId = $db->quote($attachment['attachment_id']);		
		$db->delete('xengallery_media', "attachment_id = $attachId");
	}
    
    public function getUploadConstraints($type = 'image_upload')
    {
        return $this->_getMediaModel()->getUploadConstraints($type);
    }    
	
	/**
	 * Returns the maximum allowed attachments for this content type.
	 *
	 * @return integer|true If true, there is no limit
	 */
	public function getAttachmentCountLimit()
	{
		return true;
	}		

	/**
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		if (!$this->_mediaModel)
		{
			$this->_mediaModel = XenForo_Model::create('XenGallery_Model_Media');
		}

		return $this->_mediaModel;
	}

	/**
	 * @return XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		if (!$this->_albumModel)
		{
			$this->_albumModel = XenForo_Model::create('XenGallery_Model_Album');
		}

		return $this->_albumModel;
	}

	/**
	 * @return XenGallery_Model_Category
	 */
	protected function _getCategoryModel()
	{
		if (!$this->_categoryModel)
		{
			$this->_categoryModel = XenForo_Model::create('XenGallery_Model_Category');
		}

		return $this->_categoryModel;
	}

	/**
	 * @see XenForo_AttachmentHandler_Abstract::_getContentRoute()
	 */
	protected function _getContentRoute()
	{
		return 'xengallery';
	}
}
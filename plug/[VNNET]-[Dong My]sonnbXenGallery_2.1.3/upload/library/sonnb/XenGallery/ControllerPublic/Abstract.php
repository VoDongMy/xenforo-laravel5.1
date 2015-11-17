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
class sonnb_XenGallery_ControllerPublic_Abstract extends XenForo_ControllerPublic_Abstract
{
    /**
     *  Indicate that current request is called from API application
     * @var bool
     */
    protected $_apiRequest = false;

	/**
	 * @param $action
	 * @throws XenForo_ControllerResponse_Exception
	 */
	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);

		$xenOptions = XenForo_Application::getOptions();

		if ($styleId = $xenOptions->sonnbXG_forceStyle)
		{
			$this->setViewStateChange('styleId', $styleId);
		}
		
		if (!$this->_getGalleryModel()->canViewGallery())
		{
			if (!XenForo_Visitor::getUserId())
			{
				throw $this->responseException(
					$this->responseReroute('XenForo_ControllerPublic_Error', 'registrationRequired')
				);
			}

			throw $this->getErrorOrNoPermissionResponseException('sonnb_xengallery_you_do_not_have_permission_to_use_gallery');
		}

        if ($this->getResponseType() === 'api')
        {
            $this->_apiRequest = true;
        }
	}

	public function getContentSort(&$order, &$orderDirection, &$defaultOrder = null, &$defaultOrderDirection = null)
	{
		$xenOptions = XenForo_Application::getOptions();

		$defaultOrder = $xenOptions->sonnbXG_sortPhoto;

		switch ($defaultOrder)
		{
			case 'content_updated_date':
			case 'content_date':
			case 'comment_count':
			case 'view_count':
			case 'likes':
			case 'recently_liked':
				$defaultOrderDirection = 'desc';
				break;
			case 'position':
			default:
				$defaultOrder = 'position';
				$defaultOrderDirection = 'asc';
				break;
		}

		$orderCookie = XenForo_Helper_Cookie::getCookie('sonnbXG_content_order');

		if (empty($order))
		{
			if ($orderCookie === false)
			{
				$order = $defaultOrder;
				XenForo_Helper_Cookie::setCookie('sonnbXG_content_order', $order);
			}
			else
			{
				$order = $orderCookie;
			}
		}
		elseif ($orderCookie !== $order)
		{
			XenForo_Helper_Cookie::setCookie('sonnbXG_content_order', $order);
		}

		switch ($order)
		{
			case 'content_updated_date':
			case 'content_date':
			case 'comment_count':
			case 'view_count':
			case 'likes':
			case 'recently_liked':
				$defaultOrderDirection = 'desc';
				break;
			case 'position':
			default:
				$defaultOrderDirection = 'asc';
				break;
		}

		if (empty($orderDirection))
		{
			$orderDirection = $defaultOrderDirection;
		}
	}

	public function getAlbumSort(&$order, &$orderDirection, &$defaultOrder = null, &$defaultOrderDirection = null)
	{
		$xenOptions = XenForo_Application::getOptions();

		$defaultOrder = $xenOptions->sonnbXG_sortAlbum;

		switch ($defaultOrder)
		{
			case 'content_count':
			case 'album_date':
			case 'comment_count':
			case 'view_count':
			case 'likes':
			case 'recently_liked':
			case 'album_updated_date':
				$defaultOrderDirection = 'desc';
				break;
			default:
				$defaultOrder = 'album_updated_date';
				$defaultOrderDirection = 'desc';
				break;
		}

		$orderCookie = XenForo_Helper_Cookie::getCookie('sonnbXG_album_order');

		if (empty($order))
		{
			if ($orderCookie === false)
			{
				$order = $defaultOrder;
				XenForo_Helper_Cookie::setCookie('sonnbXG_album_order', $order);
			}
			else
			{
				$order = $orderCookie;
			}
		}
		elseif ($orderCookie !== $order)
		{
			XenForo_Helper_Cookie::setCookie('sonnbXG_album_order', $order);
		}
		switch ($order)
		{
			case 'content_count':
			case 'album_date':
			case 'comment_count':
			case 'view_count':
			case 'likes':
			case 'recently_liked':
			case 'album_updated_date':
				$defaultOrderDirection = 'desc';
				break;
			default:
				$defaultOrderDirection = 'desc';
				break;
		}

		if (empty($orderDirection))
		{
			$orderDirection = $defaultOrderDirection;
		}
	}

	/**
	 * @throws XenForo_ControllerResponse_Exception
	 */
	protected function _assertCanViewGallery()
	{
		if (!$this->_getGalleryModel()->canViewGallery())
		{
			if (!XenForo_Visitor::getUserId())
			{
				throw $this->responseException(
					$this->responseReroute('XenForo_ControllerPublic_Error', 'registrationRequired')
				);
			}

			throw $this->getErrorOrNoPermissionResponseException('sonnb_xengallery_you_do_not_have_permission_to_use_gallery');
		}
	}

	/**
	 * @param $errorPhraseKey
	 * @param bool $stringToPhrase
	 *
	 * @return XenForo_ControllerResponse_Exception
	 */
	protected function _throwFriendlyNoPermission($errorPhraseKey = null, $stringToPhrase = true)
	{
		if ($errorPhraseKey && (is_string($errorPhraseKey) || is_array($errorPhraseKey)) && $stringToPhrase)
		{
			$error = new XenForo_Phrase($errorPhraseKey);
		}
		else
		{
			$error = $errorPhraseKey;
		}

		if ($errorPhraseKey)
		{
			return $this->responseException($this->responseError($error, 403), 403);
		}
		else
		{
			return $this->responseException($this->responseNoPermission(), 403);
		}
	}

	/**
	 * @throws XenForo_ControllerResponse_Exception
	 */
	protected function _assertPhpUploadError()
	{
		if (!empty($GLOBALS['php_errormsg']))
		{
			throw $this->getErrorOrNoPermissionResponseException($GLOBALS['php_errormsg'], false);
		}
	}

	/**
	 * @return boolean
	 * @throws XenForo_ControllerResponse_Exception
	 */
	protected function _assertCanUploadContents()
	{
		$canUpload = $this->_getGalleryModel()->canUpload();

		if (!$canUpload)
		{
			throw $this->getErrorOrNoPermissionResponseException('sonnb_xengallery_you_do_not_have_permission_to_canUpload');
		}

		return $canUpload;
	}

	/**
	 * @return boolean
	 * @throws XenForo_ControllerResponse_Exception
	 */
	protected function _assertCanEmbedVideos()
	{
		$canUpload = $this->_getGalleryModel()->canEmbedVideo();

		if (!$canUpload)
		{
			throw $this->getErrorOrNoPermissionResponseException('sonnb_xengallery_you_do_not_have_permission_to_embed_videos');
		}

		return $canUpload;
	}

	/**
	 * @throws XenForo_ControllerResponse_Exception
	 */
	protected function _assertIosDevices()
	{
		if ($this->_getGalleryModel()->isMobileiOS())
		{
			throw $this->getErrorOrNoPermissionResponseException('sonnb_xengallery_currently_we_do_not_support_upload_from_ios_devices');
		}
	}

	/**
	 * @param array $activities
	 * @return mixed|XenForo_Phrase
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('sonnb_xengallery_viewing_gallery');
	}

	/**
	 * @return sonnb_XenGallery_Model_Gallery
	 */
	protected function _getGalleryModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Gallery');
	}

	/**
	 * @return sonnb_XenGallery_Model_Category
	 */
	protected function _getCategoryModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Category');
	}

	/**
	 * @return sonnb_XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Album');
	}

	/**
	 * @return sonnb_XenGallery_Model_Photo
	 */
	protected function _getPhotoModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Photo');
	}

	/**
	 * @return sonnb_XenGallery_Model_Video
	 */
	protected function _getVideoModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Video');
	}

	/**
	 * @return sonnb_XenGallery_Model_PhotoData
	 */
	protected function _getPhotoDataModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_PhotoData');
	}

	/**
	 * @return sonnb_XenGallery_Model_VideoData
	 */
	protected function _getVideoDataModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_VideoData');
	}

	/**
	 * @return sonnb_XenGallery_Model_Comment
	 */
	protected function _getCommentModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Comment');
	}

	/**
	 * @return sonnb_XenGallery_Model_Location
	 */
	protected function _getLocationModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Location');
	}

	/**
	 * @return XenForo_Model_Like
	 */
	protected function _getLikeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Like');
	}

	/**
	 * @return sonnb_XenGallery_Model_Watch
	 */
	protected function _getWatchModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Watch');
	}

	/**
	 * @return sonnb_XenGallery_Model_Tag
	 */
	protected function _getTagModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Tag');
	}

	/**
	 * @return sonnb_XenGallery_Model_Collection
	 */
	protected function _getCollectionModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Collection');
	}

	/**
	 * @return sonnb_XenGallery_Model_Stream
	 */
	protected function _getStreamModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Stream');
	}

	/**
	 * @return sonnb_XenGallery_Model_Camera
	 */
	protected function _getCameraModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Camera');
	}

	/**
	 * @return sonnb_XenGallery_Model_Content
	 */
	protected function _getContentModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Content');
	}

	/**
	 * @return XenForo_Model_BbCode
	 */
	protected function _getBbCodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_BbCode');
	}

	/**
	 * @return sonnb_XenGallery_Model_MyPlaylist
	 */
	protected function _getMyPlaylistModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_MyPlaylist');
	}

	/**
	 * @return bdAttachmentStore_Model_File
	 */
	protected function _bdAttachmentStore_getFileModel()
	{
		return $this->getModelFromCache('bdAttachmentStore_Model_File');
	}

	/**
	 * @return sonnb_XenGallery_Model_Field
	 */
	protected function _getFieldModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Field');
	}

    /**
     * @return XenForo_Model_User
     */
    protected function _getUserModel()
    {
        return $this->getModelFromCache('XenForo_Model_User');
    }
}
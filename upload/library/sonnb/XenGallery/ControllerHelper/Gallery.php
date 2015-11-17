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
class sonnb_XenGallery_ControllerHelper_Gallery extends XenForo_ControllerHelper_Abstract
{
	/**
	 * @var XenForo_Visitor
	 */
	protected $_visitor;

	protected function _constructSetup()
	{
		$this->_visitor = XenForo_Visitor::getInstance();
	}

	/**
	 * @param $albumId
	 * @param array $fetchOptions
	 * @return array
	 * @throws XenForo_ControllerResponse_Exception
	 */
	public function assertAlbumValidAndViewable($albumId, array $fetchOptions = array())
	{
		if (!isset($fetchOptions['followingUserId']))
		{
			$fetchOptions['followingUserId'] = $this->_visitor['user_id'];
		}

		$album = $this->getAlbumOrError($albumId, $fetchOptions);
        $hash = $this->_controller->getInput()->filterSingle('hash', XenForo_Input::STRING);
	
		if (!$this->_getAlbumModel()->canViewAlbum($album, $errorPhraseKey, null, $hash))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}
	
		return $album;
	}

	/**
	 * @param $photoId
	 * @param array $photoFetchOptions
	 * @param array $albumFetchOptions
	 * @return array
	 * @throws XenForo_ControllerResponse_Exception
	 */
	public function assertPhotoValidAndViewable($photoId, array $photoFetchOptions = array(), array $albumFetchOptions = array())
	{
		if (!isset($photoFetchOptions['followingUserId']))
		{
			$photoFetchOptions['followingUserId'] = $this->_visitor['user_id'];
		}

		$photo = $this->getPhotoOrError($photoId, $photoFetchOptions);
        $hash = $this->_controller->getInput()->filterSingle('hash', XenForo_Input::STRING);
		
		$album = $this->assertAlbumValidAndViewable($photo['album_id'], $albumFetchOptions);
	
		if (!$this->_getPhotoModel()->canViewContent($photo, $errorPhraseKey, null, $hash))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}
	
		return array($photo, $album);
	}

	/**
	 * @param $videoId
	 * @param array $videoFetchOptions
	 * @param array $albumFetchOptions
	 * @return array
	 * @throws XenForo_ControllerResponse_Exception
	 */
	public function assertVideoValidAndViewable($videoId, array $videoFetchOptions = array(), array $albumFetchOptions = array())
	{
		if (!isset($videoFetchOptions['followingUserId']))
		{
			$videoFetchOptions['followingUserId'] = $this->_visitor['user_id'];
		}

		$video = $this->getVideoOrError($videoId, $videoFetchOptions);
        $hash = $this->_controller->getInput()->filterSingle('hash', XenForo_Input::STRING);

		$album = $this->assertAlbumValidAndViewable($video['album_id'], $albumFetchOptions);

		if (!$this->_getVideoModel()->canViewContent($video, $errorPhraseKey, null, $hash))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		return array($video, $album);
	}

	/**
	 * @param $commentId
	 * @param array $commentFetchOptions
	 * @param array $contentFetchOptions
	 * @param array $albumFetchOptions
	 * @return array
	 * @throws XenForo_ControllerResponse_Exception
	 */
	public function assertCommentValidAndViewable($commentId, array $commentFetchOptions = array(),
			array $contentFetchOptions = array(), array $albumFetchOptions = array())
	{
		$comment = $this->getCommentOrError($commentId, $commentFetchOptions);
		
		if (!$this->_getCommentModel()->canViewComment($comment, $errorPhraseKey))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$content = $album = array();
		switch ($comment['content_type'])
		{
			case sonnb_XenGallery_Model_Album::$contentType:
				$album = $this->assertAlbumValidAndViewable($comment['content_id'], $albumFetchOptions);
				break;
			case sonnb_XenGallery_Model_Photo::$contentType:
				list($content, $album) = $this->assertPhotoValidAndViewable($comment['content_id'], $contentFetchOptions, $albumFetchOptions);
				break;
			case sonnb_XenGallery_Model_Video::$contentType:
				list($content, $album) = $this->assertVideoValidAndViewable($comment['content_id'], $contentFetchOptions, $albumFetchOptions);
				break;
		}
		
		return array($comment, $content, $album);
	}

	/**
	 * @param $albumId
	 * @param array $fetchOptions
	 * @return array
	 * @throws XenForo_ControllerResponse_Exception
	 */
	public function getAlbumOrError($albumId, array $fetchOptions = array())
	{
		$album = $this->_getAlbumModel()->getAlbumById($albumId, $fetchOptions);
	
		if (!$album)
		{
			throw $this->_controller->responseException(
					$this->_controller->responseError(new XenForo_Phrase('sonnb_xengallery_requested_album_does_not_exist'), 404)
			);
		}
	
		$album = $this->_getAlbumModel()->prepareAlbum($album, $fetchOptions);
		
		return $album;
	}

	/**
	 * @param $photoId
	 * @param array $fetchOptions
	 * @return array
	 * @throws XenForo_ControllerResponse_Exception
	 */
	public function getPhotoOrError($photoId, array $fetchOptions = array())
	{
		$photo = $this->_getPhotoModel()->getContentByContentId(sonnb_XenGallery_Model_Photo::$contentType, $photoId, $fetchOptions);

		if (!$photo)
		{
			throw $this->_controller->responseException(
					$this->_controller->responseError(new XenForo_Phrase('sonnb_xengallery_requested_photo_does_not_exist'), 404)
			);
		}
	
		$photo = $this->_getPhotoModel()->prepareContent($photo, $fetchOptions);
	
		return $photo;
	}

	/**
	 * @param $videoId
	 * @param array $fetchOptions
	 * @return array
	 * @throws XenForo_ControllerResponse_Exception
	 */
	public function getVideoOrError($videoId, array $fetchOptions = array())
	{
		$video = $this->_getVideoModel()->getContentByContentId(sonnb_XenGallery_Model_Video::$contentType, $videoId, $fetchOptions);

		if (!$video)
		{
			throw $this->_controller->responseException(
				$this->_controller->responseError(new XenForo_Phrase('sonnb_xengallery_requested_video_does_not_exist'), 404)
			);
		}

		$video = $this->_getVideoModel()->prepareContent($video, $fetchOptions);

		return $video;
	}

	/**
	 * @param $commentId
	 * @param array $fetchOptions
	 * @return array
	 * @throws XenForo_ControllerResponse_Exception
	 */
	public function getCommentOrError($commentId, array $fetchOptions = array())
	{
		$comment = $this->_getCommentModel()->getCommentById($commentId, $fetchOptions);

		if (!$comment)
		{
			throw $this->_controller->responseException(
				$this->_controller->responseError(new XenForo_Phrase('sonnb_xengallery_requested_comment_does_not_exist'), 404)
			);
		}

		$comment = $this->_getCommentModel()->prepareComment($comment, $fetchOptions);

		return $comment;
	}

	/**
	 * @return sonnb_XenGallery_Model_Photo
	 */
	protected function _getPhotoModel()
	{
		return $this->_controller->getModelFromCache('sonnb_XenGallery_Model_Photo');
	}

	/**
	 * @return sonnb_XenGallery_Model_Video
	 */
	protected function _getVideoModel()
	{
		return $this->_controller->getModelFromCache('sonnb_XenGallery_Model_Video');
	}
	
	/**
	 * @return sonnb_XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->_controller->getModelFromCache('sonnb_XenGallery_Model_Album');
	}
	
	/**
	 * @return sonnb_XenGallery_Model_Comment
	 */
	protected function _getCommentModel()
	{
		return $this->_controller->getModelFromCache('sonnb_XenGallery_Model_Comment');
	}
}
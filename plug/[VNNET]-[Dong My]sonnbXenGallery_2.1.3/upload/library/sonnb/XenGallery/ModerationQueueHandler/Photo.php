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
class sonnb_XenGallery_ModerationQueueHandler_Photo extends XenForo_ModerationQueueHandler_Abstract
{
	/**
	 * @var sonnb_XenGallery_Model_Photo
	 */
	protected $_photoModel = null;
	/**
	 * @var sonnb_XenGallery_Model_Album
	 */
	protected $_albumModel = null;

	/**
	 * @param array $contentIds
	 * @param array $viewingUser
	 * @return array
	 */
	public function getVisibleModerationQueueEntriesForUser(array $contentIds, array $viewingUser)
	{
		$photoModel = $this->_getPhotoModel();

		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Photo::FETCH_ALBUM | sonnb_XenGallery_Model_Photo::FETCH_DATA,
			'followingUserId' => XenForo_Visitor::getUserId()
		);
		
		$photos = $photoModel->getContentsByIds($contentIds, $fetchOptions);
		$photos = $photoModel->prepareContents($photos, $fetchOptions, $viewingUser);
		
		$output = array();
		foreach ($photos AS $photo)
		{
			if ($photoModel->canViewContentAndContainer($photo, $photo['album'], $null, $viewingUser))
			{
				$output[$photo['content_id']] = array(
					'message' => $photo['description'],
					'user' => array(
							'user_id' => $photo['user_id'],
							'username' => $photo['username']
					),
					'title' => $photo['title'],
					'link' => XenForo_Link::buildPublicLink('gallery/photos', $photo),
					'contentTypeTitle' => new XenForo_Phrase('sonnb_xengallery_photo'),
					'titleEdit' => false
				);
			}
		}

		return $output;
	}

	/**
	 * @param int $contentId
	 * @param string $message
	 * @param string $title
	 * @return bool
	 */
	public function approveModerationQueueEntry($contentId, $message, $title)
	{
		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Photo', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('content_state', 'visible');
		$dw->set('description', $message);

		if ($dw->save())
		{
			XenForo_Model_Log::logModeratorAction(sonnb_XenGallery_Model_Photo::$xfContentType, $dw->getMergedData(), 'approve');
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @param int $contentId
	 * @return bool
	 */
	public function deleteModerationQueueEntry($contentId)
	{
		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Photo', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('content_state', 'deleted');

		if ($dw->save())
		{
			XenForo_Model_Log::logModeratorAction(sonnb_XenGallery_Model_Photo::$xfContentType, $dw->getMergedData(), 'delete_soft', array('reason' => ''));
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * 
	 * @return sonnb_XenGallery_Model_Photo
	 */
	protected function _getPhotoModel()
	{
		if (!$this->_photoModel)
		{
			$this->_photoModel = XenForo_Model::create('sonnb_XenGallery_Model_Photo');
		}

		return $this->_photoModel;
	}

	/**
	 *
	 * @return sonnb_XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		if (!$this->_albumModel)
		{
			$this->_albumModel = XenForo_Model::create('sonnb_XenGallery_Model_Album');
		}

		return $this->_albumModel;
	}
}
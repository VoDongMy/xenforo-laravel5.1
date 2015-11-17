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
class sonnb_XenGallery_ModerationQueueHandler_Video extends XenForo_ModerationQueueHandler_Abstract
{
	/**
	 * @var sonnb_XenGallery_Model_Video
	 */
	protected $_videoModel = null;
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
		$videoModel = $this->_getVideoModel();

		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Video::FETCH_ALBUM | sonnb_XenGallery_Model_Video::FETCH_DATA,
			'followingUserId' => XenForo_Visitor::getUserId()
		);
		
		$videos = $videoModel->getContentsByIds($contentIds, $fetchOptions);
		$videos = $videoModel->prepareContents($videos, $fetchOptions, $viewingUser);
		
		$output = array();
		foreach ($videos AS $video)
		{
			if ($videoModel->canViewContentAndContainer($video, $video['album'], $null, $viewingUser))
			{
				$output[$video['content_id']] = array(
					'message' => $video['description'],
					'user' => array(
							'user_id' => $video['user_id'],
							'username' => $video['username']
					),
					'title' => $video['title'],
					'link' => XenForo_Link::buildPublicLink('gallery/videos', $video),
					'contentTypeTitle' => new XenForo_Phrase('sonnb_xengallery_video'),
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
		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Video', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('content_state', 'visible');
		$dw->set('description', $message);

		if ($dw->save())
		{
			XenForo_Model_Log::logModeratorAction(sonnb_XenGallery_Model_Video::$xfContentType, $dw->getMergedData(), 'approve');
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
		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Video', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('content_state', 'deleted');

		if ($dw->save())
		{
			XenForo_Model_Log::logModeratorAction(sonnb_XenGallery_Model_Video::$xfContentType, $dw->getMergedData(), 'delete_soft', array('reason' => ''));
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * 
	 * @return sonnb_XenGallery_Model_Video
	 */
	protected function _getVideoModel()
	{
		if (!$this->_videoModel)
		{
			$this->_videoModel = XenForo_Model::create('sonnb_XenGallery_Model_Video');
		}

		return $this->_videoModel;
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
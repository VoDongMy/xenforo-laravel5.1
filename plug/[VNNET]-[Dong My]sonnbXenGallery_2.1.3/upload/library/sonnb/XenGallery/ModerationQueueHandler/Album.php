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
class sonnb_XenGallery_ModerationQueueHandler_Album extends XenForo_ModerationQueueHandler_Abstract
{
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
		$albumModel = $this->_getAlbumModel();
		
		$fetchOptions = array('followingUserId' => XenForo_Visitor::getUserId());
		
		$albums = $albumModel->getAlbumsByIds($contentIds, $fetchOptions);
		$albums = $albumModel->prepareAlbums($albums, $fetchOptions, $viewingUser);

		$output = array();
		foreach ($albums AS $album)
		{
			if ($album['canView'])
			{
				$output[$album['album_id']] = array(
					'message' => $album['description'],
					'user' => array(
						'user_id' => $album['user_id'],
						'username' => $album['username']
					),
					'title' => $album['title'],
					'link' => XenForo_Link::buildPublicLink('galley/albums', $album),
					'contentTypeTitle' => new XenForo_Phrase('sonnb_xengallery_album'),
					'titleEdit' => true
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
		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('album_state', 'visible');
		$dw->set('title', $title);

		if ($dw->save())
		{
			XenForo_Model_Log::logModeratorAction(sonnb_XenGallery_Model_Album::$xfContentType, $dw->getMergedData(), 'approve');
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
		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('album_state', 'deleted');

		if ($dw->save())
		{
			XenForo_Model_Log::logModeratorAction(sonnb_XenGallery_Model_Album::$xfContentType, $dw->getMergedData(), 'delete_soft', array('reason' => ''));
			return true;
		}
		else
		{
			return false;
		}
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
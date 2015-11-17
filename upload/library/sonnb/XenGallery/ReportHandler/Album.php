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
class sonnb_XenGallery_ReportHandler_Album extends XenForo_ReportHandler_Abstract
{
	/**
	 * @var sonnb_XenGallery_Model_Album
	 */
	protected $_albumModel = null;

	/**
	 * @param array $content
	 * @return array
	 */
	public function getReportDetailsFromContent(array $content)
	{
		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Album::FETCH_USER | sonnb_XenGallery_Model_Album::FETCH_COVER_PHOTO,
			'followingUserId' => XenForo_Visitor::getUserId()
		);
        
		$album = $this->_getAlbumModel()->getAlbumById($content['album_id'], $fetchOptions);
        
		if (!$album)
		{
			return array(false, false, false);
		}

        $album = $this->_getAlbumModel()->prepareAlbum($album, $fetchOptions);
		$album = $this->_getAlbumModel()->attachCoverToAlbum($album, $fetchOptions);
        
		return array(
			$album['album_id'],
			$album['user_id'],
			array(
                'album_id' => $album['album_id'],
                'title' => $album['title'],
					
				'username' => $album['username'],
					
				'description' => $album['description'],
				'thumbnailUrl' => isset($album['cover']['thumbnailUrl']) ? $album['cover']['thumbnailUrl'] : ''
			)
		);
	}

	/**
	 * @param array $reports
	 * @param array $viewingUser
	 * @return array
	 */
	public function getVisibleReportsForUser(array $reports, array $viewingUser)
	{
		return $reports;
	}

	/**
	 * @param array $report
	 * @param array $contentInfo
	 * @return string|XenForo_Phrase
	 */
	public function getContentTitle(array $report, array $contentInfo)
	{
		return new XenForo_Phrase('sonnb_xengallery_report_for_album_x', array('title' => $contentInfo['title']));
	}

	/**
	 * @param array $report
	 * @param array $contentInfo
	 * @return string
	 */
	public function getContentLink(array $report, array $contentInfo)
	{
		return XenForo_Link::buildPublicLink('gallery/albums', $contentInfo);
	}

	/**
	 * @param XenForo_View $view
	 * @param array $report
	 * @param array $contentInfo
	 * @return string|XenForo_Template_Abstract
	 */
	public function viewCallback(XenForo_View $view, array &$report, array &$contentInfo)
	{
		return $view->createTemplateObject('sonnb_xengallery_album_report_content', array(
			'report' => $report,
			'content' => $contentInfo
		));
	}

	/**
	 * @param array $contentInfo
	 * @return array
	 */
	public function prepareExtraContent(array $contentInfo)
	{
		$contentInfo['title'] = XenForo_Helper_String::censorString($contentInfo['title']);
	
		return $contentInfo;
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
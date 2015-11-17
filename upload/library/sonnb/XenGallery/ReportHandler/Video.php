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
class sonnb_XenGallery_ReportHandler_Video extends XenForo_ReportHandler_Abstract
{
	/**
	 * @var sonnb_XenGallery_Model_Video
	 */
	protected $_videoModel = null;

	/**
	 * @param array $content
	 * @return array
	 */
	public function getReportDetailsFromContent(array $content)
	{
		$videoModel = $this->_getVideoModel();

		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Video::FETCH_USER
						| sonnb_XenGallery_Model_Video::FETCH_ALBUM
						| sonnb_XenGallery_Model_Video::FETCH_DATA,
			'followingUserId' => XenForo_Visitor::getUserId()
		);
		$video = $videoModel->getContentById($content['content_id'], $fetchOptions);
		$video = $videoModel->prepareContent($video, $fetchOptions);
        
		if (!$video)
		{
			return array(false, false, false);
		}

		return array(
			$video['content_id'],
			$video['user_id'],
			array(
                'content_id' => $video['content_id'],
				'title' => $video['title'],
				'description' => $video['description'],
				'username' => $video['username'],
				'album_id' => $video['album']['album_id'],
				'album_title' => $video['album']['title'],
				'thumbnailUrl' => $video['thumbnailUrl']
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
		return new XenForo_Phrase('sonnb_xengallery_report_for_video_x', array('title' => $contentInfo['title']));
	}

	/**
	 * @param array $report
	 * @param array $contentInfo
	 * @return string
	 */
	public function getContentLink(array $report, array $contentInfo)
	{
		return XenForo_Link::buildPublicLink('gallery/videos', $contentInfo);
	}

	/**
	 * @param XenForo_View $view
	 * @param array $report
	 * @param array $contentInfo
	 * @return string|XenForo_Template_Abstract
	 */
	public function viewCallback(XenForo_View $view, array &$report, array &$contentInfo)
	{
		return $view->createTemplateObject('sonnb_xengallery_video_report_content', array(
			'report' => $report,
			'content' => $contentInfo
		));
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
}
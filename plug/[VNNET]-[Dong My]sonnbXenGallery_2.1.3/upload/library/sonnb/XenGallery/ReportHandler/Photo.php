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
class sonnb_XenGallery_ReportHandler_Photo extends XenForo_ReportHandler_Abstract
{
	/**
	 * @var sonnb_XenGallery_Model_Photo
	 */
	protected $_photoModel = null;

	/**
	 * @param array $content
	 * @return array
	 */
	public function getReportDetailsFromContent(array $content)
	{
		$photoModel = $this->_getPhotoModel();

		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Photo::FETCH_USER
						| sonnb_XenGallery_Model_Photo::FETCH_ALBUM
						| sonnb_XenGallery_Model_Photo::FETCH_DATA,
			'followingUserId' => XenForo_Visitor::getUserId()
		);
		$photo = $photoModel->getContentById($content['content_id'], $fetchOptions);
		$photo = $photoModel->prepareContent($photo, $fetchOptions);
        
		if (!$photo)
		{
			return array(false, false, false);
		}

		return array(
			$photo['content_id'],
			$photo['user_id'],
			array(
                'content_id' => $photo['content_id'],
				'title' => $photo['title'],
				'description' => $photo['description'],
				'username' => $photo['username'],
				'album_id' => $photo['album']['album_id'],
				'album_title' => $photo['album']['title'],
				'thumbnailUrl' => $photo['thumbnailUrl']
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
		$title = (isset($contentInfo['title']) ? $contentInfo['title'] : (isset($contentInfo['content_id']) ? $contentInfo['content_id'] : $contentInfo['photo_id']));

		return new XenForo_Phrase('sonnb_xengallery_report_for_photo_x', array('id' => $title));
	}

	/**
	 * @param array $report
	 * @param array $contentInfo
	 * @return string
	 */
	public function getContentLink(array $report, array $contentInfo)
	{
		return XenForo_Link::buildPublicLink('gallery/photos', $contentInfo);
	}

	/**
	 * @param XenForo_View $view
	 * @param array $report
	 * @param array $contentInfo
	 * @return string|XenForo_Template_Abstract
	 */
	public function viewCallback(XenForo_View $view, array &$report, array &$contentInfo)
	{
		return $view->createTemplateObject('sonnb_xengallery_photo_report_content', array(
			'report' => $report,
			'content' => $contentInfo
		));
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
}
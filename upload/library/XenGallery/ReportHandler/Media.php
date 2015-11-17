<?php

class XenGallery_ReportHandler_Media extends XenForo_ReportHandler_Abstract
{
	/**
	 * Gets report details from raw array of content (eg, a post record).
	 *
	 * @see XenForo_ReportHandler_Abstract::getReportDetailsFromContent()
	 */
	public function getReportDetailsFromContent(array $content)
	{
		/* @var $mediaModel XenGallery_Model_Media */
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		$media = $mediaModel->getMediaById($content['media_id'], array(
			'join' => XenGallery_Model_Media::FETCH_USER
				| XenGallery_Model_Media::FETCH_ALBUM
				| XenGallery_Model_Media::FETCH_CATEGORY
				| XenGallery_Model_Media::FETCH_ATTACHMENT
		));
		if (!$media)
		{
			return array(false, false, false);
		}
		
		$content = $mediaModel->prepareMedia($media);

		return array(
			$content['media_id'],
			$content['user_id'],
			array(
				'username' => $content['username'],
				'media' => $content,
			)
		);
	}

	/**
	 * Gets the visible reports of this content type for the viewing user.
	 *
	 * @see XenForo_ReportHandler_Abstract:getVisibleReportsForUser()
	 */
	public function getVisibleReportsForUser(array $reports, array $viewingUser)
	{
		/* @var $mediaModel XenGallery_Model_Media */
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		foreach ($reports AS $reportId => $report)
		{
			$content = unserialize($report['content_info']);

			if (!$mediaModel->canManageReportedMedia($content))
			{
				unset($reports[$reportId]);
			}
		}

		return $reports;
	}

	/**
	 * Gets the title of the specified content.
	 *
	 * @see XenForo_ReportHandler_Abstract:getContentTitle()
	 */
	public function getContentTitle(array $report, array $contentInfo)
	{
		if (!empty($contentInfo['media']['album_id']) && isset($contentInfo['media']['album_title']))
		{
			return new XenForo_Phrase('xengallery_media_x_in_album_y', array('title' => XenForo_Helper_String::censorString($contentInfo['media']['media_title']), 'album' => $contentInfo['media']['album_title']));
		}
		else if (!empty($contentInfo['media']['category_id']) && isset($contentInfo['media']['category_title']))
		{
			return new XenForo_Phrase('xengallery_media_x_in_category_y', array('title' => XenForo_Helper_String::censorString($contentInfo['media']['media_title']), 'category' => $contentInfo['media']['category_title']));
		}

		return new XenForo_Phrase('xengallery_media');
	}

	/**
	 * Gets the link to the specified content.
	 *
	 * @see XenForo_ReportHandler_Abstract::getContentLink()
	 */
	public function getContentLink(array $report, array $contentInfo)
	{
		return XenForo_Link::buildPublicLink('xengallery', $contentInfo['media']);
	}

	/**
	 * A callback that is called when viewing the full report.
	 *
	 * @see XenForo_ReportHandler_Abstract::viewCallback()
	 */
	public function viewCallback(XenForo_View $view, array &$report, array &$contentInfo)
	{
		return $view->createTemplateObject('xengallery_report_media_content', array(
			'report' => $report,
			'content' => $contentInfo
		));
	}
}
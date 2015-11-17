<?php

class XenGallery_ReportHandler_Album extends XenForo_ReportHandler_Abstract
{
	/**
	 * Gets report details from raw array of content (eg, a post record).
	 *
	 * @see XenForo_ReportHandler_Abstract::getReportDetailsFromContent()
	 */
	public function getReportDetailsFromContent(array $content)
	{
		/* @var $albumModel XenGallery_Model_Album */
		$albumModel = XenForo_Model::create('XenGallery_Model_Album');

		$album = $albumModel->getAlbumById($content['album_id'], array('join' => XenGallery_Model_Album::FETCH_USER));
		if (!$album)
		{
			return array(false, false, false);
		}

		$content = $albumModel->prepareAlbum($album);

		return array(
			$content['album_id'],
			$content['album_user_id'],
			array(
				'username' => $content['album_username'],
				'album' => $content,
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
		/* @var $albumModel XenGallery_Model_Album */
		$albumModel = XenForo_Model::create('XenGallery_Model_Album');

		foreach ($reports AS $reportId => $report)
		{
			$content = unserialize($report['content_info']);

			if (!$albumModel->canManageReportedAlbum($content))
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
		return new XenForo_Phrase('xengallery_album_x', array('album' => XenForo_Helper_String::censorString($contentInfo['album']['album_title'])));
	}

	/**
	 * Gets the link to the specified content.
	 *
	 * @see XenForo_ReportHandler_Abstract::getContentLink()
	 */
	public function getContentLink(array $report, array $contentInfo)
	{
		return XenForo_Link::buildPublicLink('xengallery/albums', $contentInfo['album']);
	}

	/**
	 * A callback that is called when viewing the full report.
	 *
	 * @see XenForo_ReportHandler_Abstract::viewCallback()
	 */
	public function viewCallback(XenForo_View $view, array &$report, array &$contentInfo)
	{
		return $view->createTemplateObject('xengallery_report_album_content', array(
			'report' => $report,
			'content' => $contentInfo
		));
	}
}
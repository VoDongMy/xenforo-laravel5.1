<?php

class XenGallery_ReportHandler_Comment extends XenForo_ReportHandler_Abstract
{
	/**
	 * Gets report details from raw array of content (eg, a post record).
	 *
	 * @see XenForo_ReportHandler_Abstract::getReportDetailsFromContent()
	 */
	public function getReportDetailsFromContent(array $content)
	{
		/* @var $commentModel XenGallery_Model_Comment */
		$commentModel = XenForo_Model::create('XenGallery_Model_Comment');

		/* @var $mediaModel XenGallery_Model_Media */
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		/* @var $albumModel XenGallery_Model_Album */
		$albumModel = XenForo_Model::create('XenGallery_Model_Album');
		
		$album = array();
		$media = array();

		$comment = $commentModel->getCommentById($content['comment_id'], array('join' => XenGallery_Model_Comment::FETCH_USER));
		if (!$comment)
		{
			return array(false, false, false);
		}

		if ($comment['content_type'] == 'album')
		{
			$album = $albumModel->getAlbumById($comment['content_id']);
		}
		else
		{
			$media = $mediaModel->getMediaById($comment['content_id']);
		}

		return array(
			$content['comment_id'],
			$content['user_id'],
			array(
				'username' => $content['username'],
				'comment' => $content,
				'album' => $album,
				'media' => $media
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
		/* @var $commentModel XenGallery_Model_Comment */
		$commentModel = XenForo_Model::create('XenGallery_Model_Comment');

		foreach ($reports AS $reportId => $report)
		{
			$content = unserialize($report['content_info']);

			if (!$commentModel->canManageReportedComment($content))
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
		if (!empty($contentInfo['album']))
		{
			return new XenForo_Phrase('xengallery_comment_by_x_in_album_y', array('user' => $contentInfo['comment']['username'], 'title' => $contentInfo['album']['album_title']));
		}
		elseif (!empty($contentInfo['media']))
		{
			return new XenForo_Phrase('xengallery_comment_by_x_in_media_y', array('user' => $contentInfo['comment']['username'], 'title' => $contentInfo['media']['media_title']));
		}
		// For BC
		elseif (!empty($contentInfo['comment']['media_title']))
		{
			return new XenForo_Phrase('xengallery_comment_by_x_in_media_y', array('user' => $contentInfo['comment']['username'], 'title' => $contentInfo['comment']['media_title']));
		}

		return new XenForo_Phrase('xengallery_comment');
	}

	/**
	 * Gets the link to the specified content.
	 *
	 * @see XenForo_ReportHandler_Abstract::getContentLink()
	 */
	public function getContentLink(array $report, array $contentInfo)
	{
		return XenForo_Link::buildPublicLink('xengallery/comments', $contentInfo['comment']);
	}

	/**
	 * A callback that is called when viewing the full report.
	 *
	 * @see XenForo_ReportHandler_Abstract::viewCallback()
	 */
	public function viewCallback(XenForo_View $view, array &$report, array &$contentInfo)
	{
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $view)));
		
		$contentInfo['comment']['messageHtml'] = new XenForo_BbCode_TextWrapper($contentInfo['comment']['message'], $bbCodeParser);
		$contentInfo['comment']['message'] = $contentInfo['comment']['messageHtml']; // sanity check in case template not update
		
		return $view->createTemplateObject('xengallery_report_comment_content', array(
			'report' => $report,
			'content' => $contentInfo
		));
	}
}
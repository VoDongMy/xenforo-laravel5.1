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
class sonnb_XenGallery_ReportHandler_Comment extends XenForo_ReportHandler_Abstract
{
	/**
	 * @var sonnb_AddonStore_Model_Comment
	 */
	protected $_commentModel = null;

	/**
	 * @param array $content
	 * @return array
	 */
	public function getReportDetailsFromContent(array $content)
	{
        $fetchOptions = array('join' => sonnb_XenGallery_Model_Comment::FETCH_USER);
        
		$comment = $this->_getCommentModel()->getCommentById($content['comment_id'], $fetchOptions);
        
		if (!$comment)
		{
			return array(false, false, false);
		}

        $comment = $this->_getCommentModel()->prepareComment($comment, $fetchOptions);
        
		return array(
			$comment['comment_id'],
			$comment['user_id'],
			array(
                'comment_id' => $comment['comment_id'],
                'username' => $comment['username'],
				'message' => $comment['message'],
					
				'content_id' => $comment['content_id'],
				'content_type' => $comment['content_type']
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
		return new XenForo_Phrase('sonnb_xengallery_report_for_comment_x', array('id' => $contentInfo['comment_id']));
	}

	/**
	 * @param array $report
	 * @param array $contentInfo
	 * @return string
	 */
	public function getContentLink(array $report, array $contentInfo)
	{
		return XenForo_Link::buildPublicLink('gallery/comments', $contentInfo);
	}

	/**
	 * @param XenForo_View $view
	 * @param array $report
	 * @param array $contentInfo
	 * @return string|XenForo_Template_Abstract
	 */
	public function viewCallback(XenForo_View $view, array &$report, array &$contentInfo)
	{
		return $view->createTemplateObject('sonnb_xengallery_comment_report_content', array(
			'report' => $report,
			'content' => $contentInfo
		));
	}

	/**
	 * 
	 * @return sonnb_XenGallery_Model_Comment
	 */
	protected function _getCommentModel()
	{
		if (!$this->_commentModel)
		{
			$this->_commentModel = XenForo_Model::create('sonnb_XenGallery_Model_Comment');
		}

		return $this->_commentModel;
	}
}
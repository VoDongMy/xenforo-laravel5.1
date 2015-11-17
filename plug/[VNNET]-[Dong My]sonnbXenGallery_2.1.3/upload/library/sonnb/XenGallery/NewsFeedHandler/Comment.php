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
class sonnb_XenGallery_NewsFeedHandler_Comment extends XenForo_NewsFeedHandler_Abstract
{
	protected $_commentModel = null;
	protected $_galleryModel = null;

	public function getContentByIds(array $contentIds, $model, array $viewingUser)
	{
		$galleryModel = $this->_getGalleryModel();
		$commentModel = $this->_getCommentModel();

		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Comment::FETCH_USER
		);
		
		$comments = $commentModel->getCommentsByIds($contentIds, $fetchOptions);
		$comments = $commentModel->prepareComments($comments,$fetchOptions);
		$comments = $galleryModel->groupContentsContentType($comments);
		
		return $comments;
	}

	public function canViewNewsFeedItem(array $item, $content, array $viewingUser)
	{
		return $this->_getCommentModel()->canViewCommentAndContainer($content, $errorPhraseKey, $viewingUser);
	}
	
	protected function _getDefaultTemplateTitle($contentType, $action)
	{
		return 'sonnb_xengallery_news_feed_comment_' . $action;
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

	/**
	 *
	 * @return sonnb_XenGallery_Model_Gallery
	 */
	protected function _getGalleryModel()
	{
		if (!$this->_galleryModel)
		{
			$this->_galleryModel = XenForo_Model::create('sonnb_XenGallery_Model_Gallery');
		}

		return $this->_galleryModel;
	}
}
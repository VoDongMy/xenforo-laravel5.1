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
class sonnb_XenGallery_AlertHandler_Comment extends XenForo_AlertHandler_Abstract
{
	/**
	 * @var sonnb_XenGallery_Model_Comment
	 */
	protected $_commentModel = null;
	/**
	 * @var sonnb_XenGallery_Model_Gallery
	 */
	protected $_galleryModel = null;

	/**
	 * @param array $contentIds
	 * @param XenForo_Model $model
	 * @param int $userId
	 * @param array $viewingUser
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, $userId, array $viewingUser)
	{
		$galleryModel = $this->_getGalleryModel();
		$commentModel = $this->_getCommentModel();

		$fetchOptions = array('join' => sonnb_XenGallery_Model_Comment::FETCH_USER);

		$comments = $commentModel->getCommentsByIds($contentIds, $fetchOptions);
		$comments = $commentModel->prepareComments($comments, $fetchOptions);
		$comments = $galleryModel->groupContentsContentType($comments);

		return $comments;
	}

	/**
	 * @param array $alert
	 * @param mixed $content
	 * @param array $viewingUser
	 * @return bool
	 */
	public function canViewAlert(array $alert, $content, array $viewingUser)
	{
		return $this->_getCommentModel()->canViewCommentAndContainer($content, $errorPhraseKey, $viewingUser);
	}

	/**
	 * @param array $item
	 * @param $content
	 * @param array $viewingUser
	 * @return array
	 */
	protected function _prepareAlertBeforeAction(array $item, $content, array $viewingUser)
	{
		$item = parent::_prepareAlertBeforeAction($item, $content, $viewingUser);
		
		$item['extra_data'] = @unserialize($item['extra_data']);

		return $item;
	}

	/**
	 * @param string $contentType
	 * @param string $action
	 * @return string
	 */
	protected function _getDefaultTemplateTitle($contentType, $action)
	{
		return 'sonnb_xengallery_comment_alert_' . $action;
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
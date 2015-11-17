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
class sonnb_XenGallery_ContentHandler_Content extends sonnb_XenGallery_ContentHandler_Abstract
{
	/**
	 * @var sonnb_XenGallery_Model_Content
	 */
	protected $_contentModel = null;

	/**
	 * @param $contentId
	 * @param array $viewingUser
	 * @return array
	 */
	public function getContentById($contentId, array $viewingUser = null)
	{
		if (!$contentId)
		{
			return array();
		}

		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Content::FETCH_ALBUM
				| sonnb_XenGallery_Model_Content::FETCH_DATA
				| sonnb_XenGallery_Model_Content::FETCH_USER,
			'followingUserId' => XenForo_Visitor::getUserId()
		);

		$content = $this->_getContentModel()->getContentById($contentId, $fetchOptions);
		$content = $this->_getContentModel()->prepareContent($content, $fetchOptions, $viewingUser);

		return $content;
	}

	/**
	 * @param array $contentIds
	 * @param array $viewingUser
	 * @return array
	 */
	public function getContentsByIds(array $contentIds, array $viewingUser = null)
	{
		if (!$contentIds)
		{
			return array();
		}

		$conditions = $this->_getContentModel()->getPermissionBasedContentFetchConditions()+array(
			'content_id' => $contentIds
		);
		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Content::FETCH_ALBUM
					| sonnb_XenGallery_Model_Content::FETCH_DATA
					| sonnb_XenGallery_Model_Content::FETCH_USER,
			'followingUserId' => XenForo_Visitor::getUserId()
		);

		$contents = $this->_getContentModel()->getContents($conditions, $fetchOptions);
		$contents = $this->_getContentModel()->prepareContents($contents, $fetchOptions, $viewingUser);

		return $contents;
	}

	/**
	 * @param array $content
	 * @param array $viewingUser
	 * @return bool
	 */
	public function canViewContent(array $content, array $viewingUser = null)
	{
		$album = null;
		if (isset($content['album']))
		{
			$album = $content['album'];
		}

		return $this->_getContentModel()->canViewContentAndContainer($content, $album, $null, $viewingUser);
	}

	/**
	 * @param array $content
	 * @return string
	 */
	public function getContentLink(array $content)
	{
		return XenForo_Link::buildPublicLink('gallery/'.$content['content_type'].'s', $content);
	}

	/**
	 * @param array $content
	 * @param XenForo_View $view
	 * @return XenForo_Template_Abstract
	 */
	public function renderHtml(array $content, XenForo_View $view)
	{
		$album = null;
		if (isset($content['album']))
		{
			$album = $content['album'];
		}

		return $view->createTemplateObject(
			'sonnb_xengallery_'. $content['content_type'] .'_list_item',
			array(
				'content' => $content,
				'album' => $album
			)
		);
	}

	/**
	 * @return sonnb_XenGallery_Model_Content
	 */
	protected function _getContentModel()
	{
		if ($this->_contentModel === null)
		{
			$this->_contentModel = XenForo_Model::create('sonnb_XenGallery_Model_Content');
		}

		return $this->_contentModel;
	}
}
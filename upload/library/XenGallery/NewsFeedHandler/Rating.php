<?php

/**
 * News feed handler for media rating actions
 *
 */
class XenGallery_NewsFeedHandler_Rating extends XenForo_NewsFeedHandler_Abstract
{
	protected $_ratingModel;

	protected $_mediaModel;

	/**
	 * Just returns a value for each requested ID
	 * but does no actual DB work
	 *
	 * @param array $contentIds
	 * @param XenForo_Model_NewsFeed $model
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, array $viewingUser)
	{
		$rating = $this->_getRatingModel()->getRatingsByIds($contentIds, array(
			'join' => XenGallery_Model_Rating::FETCH_USER | XenGallery_Model_Rating::FETCH_CONTENT
		));
		$rating = $this->_getMediaModel()->prepareMediaItems($rating);

		return $rating;
	}

	/**
	 * Determines if the given news feed item is viewable.
	 *
	 * @param array $item
	 * @param mixed $content
	 * @param array $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewNewsFeedItem(array $item, $content, array $viewingUser)
	{
		return XenForo_Model::create('XenGallery_Model_Media')->canViewMedia($null, $viewingUser);
	}

	/**
	 * @return XenGallery_Model_Rating
	 */
	protected function _getRatingModel()
	{
		if (!$this->_ratingModel)
		{
			$this->_ratingModel = XenForo_Model::create('XenGallery_Model_Rating');
		}

		return $this->_ratingModel;
	}

	/**
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		if (!$this->_mediaModel)
		{
			$this->_mediaModel = XenForo_Model::create('XenGallery_Model_Media');
		}

		return $this->_mediaModel;
	}
}
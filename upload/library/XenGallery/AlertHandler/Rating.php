<?php

class XenGallery_AlertHandler_Rating extends XenForo_AlertHandler_Abstract
{
	protected $_ratingModel;

	/**
	 * Fetches the content required by alerts.
	 *
	 * @param array $contentIds
	 * @param XenForo_Model_Alert $model Alert model invoking this
	 * @param integer $userId User ID the alerts are for
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, $userId, array $viewingUser)
	{
		$ratingModel = $this->_getRatingModel();
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');
		
		$conditions = array(
			'rating_id' => $contentIds
		);
		
		$fetchOptions = array(
			'join' => XenGallery_Model_Rating::FETCH_USER | XenGallery_Model_Rating::FETCH_CONTENT
		);
		
		$ratings = $ratingModel->getRatings($conditions, $fetchOptions);

		foreach ($ratings AS $key => &$rating)
		{
			if (!$mediaModel->canViewMedia($null, $viewingUser))
			{
				unset($ratings[$key]);
			}
		}
	
		return $ratings;
	}

	/**
	* Determines if the rating is viewable.
	* @see XenForo_AlertHandler_Abstract::canViewAlert()
	*/
	public function canViewAlert(array $alert, $content, array $viewingUser)
	{	
		return true;
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
}

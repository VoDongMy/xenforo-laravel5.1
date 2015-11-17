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
class sonnb_XenGallery_Search_DataHandler_Video extends XenForo_Search_DataHandler_Abstract
{
	protected $_videoModel;
	protected $_galleryModel;

	/**
	 * @param XenForo_Search_Indexer $indexer
	 * @param array $data
	 * @param array $parentData
	 */
	protected function _insertIntoIndex(XenForo_Search_Indexer $indexer, array $data, array $parentData = null)
	{
		$metadata = array();
		$metadata['album'] = $data['album_id'];

		if (!empty($data['collection_id']))
		{
			$metadata['collection'] = $data['collection_id'];
		}

		if (utf8_strlen($data['title']) > 250)
		{
			$data['title'] = utf8_substr($data['title'], 0, 249);
		}

		$indexer->insertIntoIndex(
			'sonnb_xengallery_video', $data['content_id'],
			$data['title'], $data['description'],
			$data['content_date'], $data['user_id'], 0, $metadata
		);
	}

	/**
	 * Updates a record in the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_updateIndex()
	 *
	 * @param XenForo_Search_Indexer $indexer
	 * @param array $data
	 * @param array $fieldUpdates
	 */
	protected function _updateIndex(XenForo_Search_Indexer $indexer, array $data, array $fieldUpdates)
	{
		$indexer->updateIndex('sonnb_xengallery_video', $data['content_id'], $fieldUpdates);
	}

	/**
	 * Deletes one or more records from the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_deleteFromIndex()
	 *
	 * @param XenForo_Search_Indexer $indexer
	 * @param array $dataList
	 */
	protected function _deleteFromIndex(XenForo_Search_Indexer $indexer, array $dataList)
	{
		$videoIds = array();
		foreach ($dataList AS $data)
		{
			if (!is_array($data))
			{
				$videoIds[] = $data['content_id'];
			}
			else
			{
				$videoIds[] = $data;
			}
		}

		$indexer->deleteFromIndex('sonnb_xengallery_video', $videoIds);
	}

	/**
	 * Rebuilds the index for a batch.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::rebuildIndex()
	 *
	 * @param XenForo_Search_Indexer $indexer
	 * @param int $lastId
	 * @param int $batchSize
	 * @return bool|false|int|mixed
	 */
	public function rebuildIndex(XenForo_Search_Indexer $indexer, $lastId, $batchSize)
	{
		$videoIds = $this->_getVideoModel()->getContentIdsInRange($lastId, $batchSize);
		if (!$videoIds)
		{
			return false;
		}

		$this->quickIndex($indexer, $videoIds);

		return max($videoIds);
	}

	/**
	 * Rebuilds the index for the specified content.

	 * @see XenForo_Search_DataHandler_Abstract::quickIndex()
	 */
	public function quickIndex(XenForo_Search_Indexer $indexer, array $contentIds)
	{
		$videos = $this->_getVideoModel()->getContentsByContentIds(sonnb_XenGallery_Model_Video::$contentType, $contentIds);
		if (!$videos)
		{
			return false;
		}

		foreach ($videos AS $video)
		{
			$this->insertIntoIndex($indexer, $video);
		}

		return true;
	}

	/**
	 * Gets the type-specific data for a collection of results of this content type.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getDataForResults()
	 */
	public function getDataForResults(array $ids, array $viewingUser, array $resultsGrouped)
	{
		return $this->_getVideoModel()->getContentsByContentIds(
			sonnb_XenGallery_Model_Video::$contentType,
			$ids,
			array(
				'join' => sonnb_XenGallery_Model_Video::FETCH_DATA |
					sonnb_XenGallery_Model_Video::FETCH_USER |
					sonnb_XenGallery_Model_Video::FETCH_ALBUM
			)
		);
	}

	/**
	 * Determines if this result is viewable.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::canViewResult()
	 */
	public function canViewResult(array $result, array $viewingUser)
	{
		if (!$this->_getGalleryModel()->canViewGallery())
		{
			return false;
		}

		$result = $this->_getVideoModel()->prepareContent($result, array(
			'join' => sonnb_XenGallery_Model_Video::FETCH_DATA |
				sonnb_XenGallery_Model_Video::FETCH_USER |
				sonnb_XenGallery_Model_Video::FETCH_ALBUM
		));

		return $this->_getVideoModel()->canViewContentAndContainer($result, $result['album'], $null, $viewingUser);
	}

	/**
	 * Prepares a result for display.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::prepareResult()
	 */
	public function prepareResult(array $result, array $viewingUser)
	{
		return $this->_getVideoModel()->prepareContent($result, array(
			'join' => sonnb_XenGallery_Model_Video::FETCH_DATA |
				sonnb_XenGallery_Model_Video::FETCH_USER |
				sonnb_XenGallery_Model_Video::FETCH_ALBUM
		), $viewingUser);
	}

	/**
	 * Gets the date of the result (from the result's content).
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getResultDate()
	 */
	public function getResultDate(array $result)
	{
		return $result['content_date'];
	}

	/**
	 * Renders a result to HTML.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::renderResult()
	 */
	public function renderResult(XenForo_View $view, array $result, array $search)
	{
		return $view->createTemplateObject('sonnb_xengallery_search_result_video', array(
			'content' => $result,
			'album' => $result['album'],
			'search' => $search
		));
	}

	/**
	 * Returns an array of content types handled by this class
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getSearchContentTypes()
	 */
	public function getSearchContentTypes()
	{
		return array('sonnb_xengallery_video');
	}

	/**
	 * Process a type-specific constraint.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::processConstraint()
	 */
	public function processConstraint(XenForo_Search_SourceHandler_Abstract $sourceHandler, $constraint, $constraintInfo, array $constraints)
	{
		switch ($constraint)
		{
			case 'album':
				if ($constraintInfo)
				{
					return array(
						'metadata' => array('album', preg_split('/\D+/', strval($constraintInfo))),
					);
				}
				break;
			case 'collection':
				if ($constraintInfo)
				{
					return array(
						'metadata' => array('collection', preg_split('/\D+/', strval($constraintInfo))),
					);
				}
				break;
		}

		return false;
	}

	/**
	 * Gets the search form controller response for this type.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getSearchFormControllerResponse()
	 */
	public function getSearchFormControllerResponse(XenForo_ControllerPublic_Abstract $controller, XenForo_Input $input, array $viewParams)
	{
		$params = $input->filterSingle('c', XenForo_Input::ARRAY_SIMPLE);

		return $controller->responseView(
			'sonnb_XenGallery_ViewPublic_Search_Form_Video',
			'sonnb_xengallery_search_form_video',
			$viewParams
		);
	}

	/**
	 * @return sonnb_XenGallery_Model_Video
	 */
	protected function _getVideoModel()
	{
		if (!$this->_videoModel)
		{
			$this->_videoModel = XenForo_Model::create('sonnb_XenGallery_Model_Video');
		}

		return $this->_videoModel;
	}

	/**
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
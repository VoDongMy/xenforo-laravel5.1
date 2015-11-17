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
class sonnb_XenGallery_Search_DataHandler_Album extends XenForo_Search_DataHandler_Abstract
{
	/**
	 * @var sonnb_XenGallery_Model_Album
	 */
	protected $_albumModel;
	/**
	 * @var sonnb_XenGallery_Model_Gallery
	 */
	protected $_galleryModel;

	/**
	 * @param XenForo_Search_Indexer $indexer
	 * @param array $data
	 * @param array $parentData
	 */
	protected function _insertIntoIndex(XenForo_Search_Indexer $indexer, array $data, array $parentData = null)
	{
		$metadata = array();

		if (!empty($data['category_id']))
		{
			$metadata['category'] = $data['category_id'];
		}

		if (!empty($data['collection_id']))
		{
			$metadata['collection'] = $data['collection_id'];
		}

		if (utf8_strlen($data['title']) > 250)
		{
			$data['title'] = utf8_substr($data['title'], 0, 249);
		}

		$indexer->insertIntoIndex(
			'sonnb_xengallery_album', $data['album_id'],
			$data['title'], $data['description'],
			$data['album_date'], $data['user_id'], 0, $metadata
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
		$indexer->updateIndex('sonnb_xengallery_album', $data['album_id'], $fieldUpdates);
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
		$albumIds = array();
		foreach ($dataList AS $data)
		{
			if (!is_array($data))
			{
				$albumIds[] = $data['album_id'];
			}
			else
			{
				$albumIds[] = $data;
			}
		}

		$indexer->deleteFromIndex('sonnb_xengallery_album', $albumIds);
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
		$albumIds = $this->_getAlbumModel()->getAlbumIdsInRange($lastId, $batchSize);
		if (!$albumIds)
		{
			return false;
		}

		$this->quickIndex($indexer, $albumIds);

		return max($albumIds);
	}

	/**
	 * Rebuilds the index for the specified content.

	 * @see XenForo_Search_DataHandler_Abstract::quickIndex()
	 *
	 * @param XenForo_Search_Indexer $indexer
	 * @param array $contentIds
	 * @return array|bool
	 */
	public function quickIndex(XenForo_Search_Indexer $indexer, array $contentIds)
	{
		$albums = $this->_getAlbumModel()->getAlbumsByIds($contentIds);
		if (!$albums)
		{
			return false;
		}

		foreach ($albums AS $album)
		{
			$this->insertIntoIndex($indexer, $album);
		}

		return true;
	}

	/**
	 * Gets the type-specific data for a collection of results of this content type.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getDataForResults()
	 *
	 * @param array $ids
	 * @param array $viewingUser
	 * @param array $resultsGrouped
	 * @return array
	 */
	public function getDataForResults(array $ids, array $viewingUser, array $resultsGrouped)
	{
		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Album::FETCH_USER
		);

		$albums = $this->_getAlbumModel()->getAlbumsByIds($ids, $fetchOptions);
		$albums = $this->_getAlbumModel()->prepareAlbums($albums, $fetchOptions, $viewingUser);
		$albums = $this->_getAlbumModel()->attachContentsToAlbums($albums);

		return $albums;
	}

	/**
	 * Determines if this result is viewable.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::canViewResult()
	 *
	 * @param array $result
	 * @param array $viewingUser
	 * @return bool
	 */
	public function canViewResult(array $result, array $viewingUser)
	{
		if (!$this->_getGalleryModel()->canViewGallery())
		{
			return false;
		}

		return $this->_getAlbumModel()->canViewAlbum($result, $null, $viewingUser);
	}

	/**
	 * Prepares a result for display.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::prepareResult()
	 *
	 * @param array $result
	 * @param array $viewingUser
	 * @return array
	 */
	public function prepareResult(array $result, array $viewingUser)
	{
		return $this->_getAlbumModel()->prepareAlbum($result, array(), $viewingUser);
	}

	/**
	 * Gets the date of the result (from the result's content).
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getResultDate()
	 *
	 * @param array $result
	 * @return int
	 */
	public function getResultDate(array $result)
	{
		return $result['album_date'];
	}

	/**
	 * Renders a result to HTML.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::renderResult()
	 *
	 * @param XenForo_View $view
	 * @param array $result
	 * @param array $search
	 * @return string|XenForo_Template_Abstract
	 */
	public function renderResult(XenForo_View $view, array $result, array $search)
	{
		return $view->createTemplateObject('sonnb_xengallery_search_result_album', array(
			'album' => $result,
			'search' => $search
		));
	}

	/**
	 * Returns an array of content types handled by this class
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getSearchContentTypes()
	 *
	 * @return array
	 */
	public function getSearchContentTypes()
	{
		return array('sonnb_xengallery_album');
	}

	/**
	 * Get type-specific constraints from input.
	 *
	 * @param XenForo_Input $input
	 *
	 * @return array
	 */
	public function getTypeConstraintsFromInput(XenForo_Input $input)
	{
		$constraints = array();

		$categories = $input->filterSingle('categories', XenForo_Input::UINT, array('array' => true));
		if ($categories && !in_array(0, $categories))
		{
			if ($input->inRequest('child_categories'))
			{
				$includeChildren = $input->filterSingle('child_categories', XenForo_Input::UINT);
			}
			else
			{
				$includeChildren = true;
			}

			if ($includeChildren)
			{
				$descendants = array_keys(XenForo_Model::create('sonnb_XenGallery_Model_Category')->getDescendantsOfCategoryIds($categories));
				$categories = array_merge($categories, $descendants);
			}

			$categories = array_unique($categories);
			$constraints['category'] = implode(' ', $categories);
			if (!$constraints['category'])
			{
				unset($constraints['category']); // just 0
			}
		}

		return $constraints;
	}

	/**
	 * Process a type-specific constraint.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::processConstraint()
	 *
	 * @param XenForo_Search_SourceHandler_Abstract $sourceHandler
	 * @param string $constraint
	 * @param mixed $constraintInfo
	 * @param array $constraints
	 * @return array|bool|false
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
			case 'category':
				if ($constraintInfo)
				{
					return array(
						'metadata' => array('category', preg_split('/\D+/', strval($constraintInfo))),
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
	 *
	 * @param XenForo_ControllerPublic_Abstract $controller
	 * @param XenForo_Input $input
	 * @param array $viewParams
	 * @return false|XenForo_ControllerResponse_Abstract|XenForo_ControllerResponse_View
	 */
	public function getSearchFormControllerResponse(XenForo_ControllerPublic_Abstract $controller, XenForo_Input $input, array $viewParams)
	{
		$params = $input->filterSingle('c', XenForo_Input::ARRAY_SIMPLE);

		if (!empty($params['category']))
		{
			$viewParams['search']['categories'] = array_fill_keys(explode(' ', $params['category']), true);
		}
		else
		{
			$viewParams['search']['categories'] = array();
		}

		$viewParams['search']['child_categories'] = true;

		$viewParams['categories'] = XenForo_Model::create('sonnb_XenGallery_Model_Category')->getAllCachedCategories();

		return $controller->responseView(
			'sonnb_XenGallery_ViewPublic_Search_Form_Album',
			'sonnb_xengallery_search_form_album',
			$viewParams
		);
	}

	/**
	 * @return sonnb_XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		if (!$this->_albumModel)
		{
			$this->_albumModel = XenForo_Model::create('sonnb_XenGallery_Model_Album');
		}

		return $this->_albumModel;
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
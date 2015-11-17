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
class sonnb_XenGallery_Search_DataHandler_Photo extends XenForo_Search_DataHandler_Abstract
{
	/**
	 * @var sonnb_XenGallery_Model_Photo
	 */
	protected $_photoModel;
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
		$metadata['album'] = $data['album_id'];

		if (!empty($data['photo_exif']) && !is_array($data['photo_exif']))
		{
			$data['photo_exif'] = @unserialize($data['photo_exif']);
		}

		if (!empty($data['photo_exif']))
		{
			if (isset($data['photo_exif']['Make']) && isset($data['photo_exif']['Model']))
			{
				$metadata['camera'] = $data['photo_exif']['Model'];
			}

			if (isset($data['photo_exif']['ExposureTime']))
			{
				$metadata['exposure'] = str_replace('/', '_', $data['photo_exif']['ExposureTime']);
			}

			if (isset($data['photo_exif']['FNumber']))
			{
				$f = explode('/', $data['photo_exif']['FNumber']);
				$metadata['aperture'] = str_replace('.', '_', $f[1]);
			}

			if (isset($data['photo_exif']['FocalLength']))
			{
				$metadata['focal'] = str_replace('.', '_', (str_replace('mm', '', $data['photo_exif']['FocalLength'])));
			}

			if (isset($data['photo_exif']['ISOSpeedRatings']))
			{
				$metadata['iso'] = intval($data['photo_exif']['ISOSpeedRatings']);
			}
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
			'sonnb_xengallery_photo', $data['content_id'],
			$data['title'], $data['description'],
			$data['content_date'], $data['user_id'], 0, $metadata
		);
	}

	/**
	 * Updates a record in the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_updateIndex()
	 */
	protected function _updateIndex(XenForo_Search_Indexer $indexer, array $data, array $fieldUpdates)
	{
		$indexer->updateIndex('sonnb_xengallery_photo', $data['content_id'], $fieldUpdates);
	}

	/**
	 * Deletes one or more records from the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_deleteFromIndex()
	 */
	protected function _deleteFromIndex(XenForo_Search_Indexer $indexer, array $dataList)
	{
		$photoIds = array();
		foreach ($dataList AS $data)
		{
			if (!is_array($data))
			{
				$photoIds[] = $data['content_id'];
			}
			else
			{
				$photoIds[] = $data;
			}
		}

		$indexer->deleteFromIndex('sonnb_xengallery_photo', $photoIds);
	}

	/**
	 * Rebuilds the index for a batch.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::rebuildIndex()
	 */
	public function rebuildIndex(XenForo_Search_Indexer $indexer, $lastId, $batchSize)
	{
		$photoIds = $this->_getPhotoModel()->getContentIdsInRange($lastId, $batchSize);
		if (!$photoIds)
		{
			return false;
		}

		$this->quickIndex($indexer, $photoIds);

		return max($photoIds);
	}

	/**
	 * Rebuilds the index for the specified content.

	 * @see XenForo_Search_DataHandler_Abstract::quickIndex()
	 */
	public function quickIndex(XenForo_Search_Indexer $indexer, array $contentIds)
	{
		$photos = $this->_getPhotoModel()->getContentsByContentIds(sonnb_XenGallery_Model_Photo::$contentType, $contentIds);
		if (!$photos)
		{
			return false;
		}

		foreach ($photos AS $photo)
		{
			$this->insertIntoIndex($indexer, $photo);
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
		return $this->_getPhotoModel()->getContentsByContentIds(
			sonnb_XenGallery_Model_Photo::$contentType,
			$ids,
			array(
				'join' => sonnb_XenGallery_Model_Photo::FETCH_DATA |
					sonnb_XenGallery_Model_Photo::FETCH_USER |
					sonnb_XenGallery_Model_Photo::FETCH_ALBUM
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

		$result = $this->_getPhotoModel()->prepareContent($result, array(
			'join' => sonnb_XenGallery_Model_Photo::FETCH_DATA |
				sonnb_XenGallery_Model_Photo::FETCH_USER |
				sonnb_XenGallery_Model_Photo::FETCH_ALBUM
		));

		return $this->_getPhotoModel()->canViewContentAndContainer($result, $result['album'], $null, $viewingUser);
	}

	/**
	 * Prepares a result for display.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::prepareResult()
	 */
	public function prepareResult(array $result, array $viewingUser)
	{
		return $this->_getPhotoModel()->prepareContent($result, array(
			'join' => sonnb_XenGallery_Model_Photo::FETCH_DATA |
				sonnb_XenGallery_Model_Photo::FETCH_USER |
				sonnb_XenGallery_Model_Photo::FETCH_ALBUM
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
		return $view->createTemplateObject('sonnb_xengallery_search_result_photo', array(
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
		return array('sonnb_xengallery_photo');
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
			case 'camera':
				if ($constraintInfo)
				{
					$constraintInfo = preg_replace('/[^a-z0-9_]/i', '', strval($constraintInfo));

					return array(
						'metadata' => array('camera', $constraintInfo),
					);
				}
				break;
			case 'exposure':
				if ($constraintInfo)
				{
					$constraintInfo = preg_replace('/[^a-z0-9_\/]/i', '', strval($constraintInfo));

					return array(
						'metadata' => array('exposure', str_replace('/', '_', $constraintInfo)),
					);
				}
				break;
			case 'aperture':
				if ($constraintInfo)
				{
					$constraintInfo = preg_replace('/[^a-z0-9_.]/i', '', strval($constraintInfo));

					return array(
						'metadata' => array('aperture', str_replace('.', '_', $constraintInfo)),
					);
				}
				break;
			case 'focal':
				if ($constraintInfo)
				{
					$constraintInfo = preg_replace('/[^a-z0-9_.]/i', '', strval($constraintInfo));

					return array(
						'metadata' => array('focal', str_replace('.', '_', $constraintInfo)),
					);
				}
				break;
			case 'iso':
				if ($constraintInfo)
				{
					$constraintInfo = preg_replace('/[^a-z0-9_]/i', '', strval($constraintInfo));

					return array(
						'metadata' => array('iso', $constraintInfo),
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
	 * Get type-specific constrints from input.
	 *
	 * @param XenForo_Input $input
	 *
	 * @return array
	 */
	public function getTypeConstraintsFromInput(XenForo_Input $input)
	{
		$constraints = $input->filter(array(
			'camera' => XenForo_Input::STRING,
			'exposure' => XenForo_Input::STRING,
			'focal' => XenForo_Input::UINT,
			'iso' => XenForo_Input::UINT,
			'aperture' => XenForo_Input::FLOAT,
		));

		return $constraints;
	}

	/**
	 * Gets the search form controller response for this type.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getSearchFormControllerResponse()
	 */
	public function getSearchFormControllerResponse(XenForo_ControllerPublic_Abstract $controller, XenForo_Input $input, array $viewParams)
	{
		$params = $input->filterSingle('c', XenForo_Input::ARRAY_SIMPLE);

		if (!empty($params['camera']))
		{
			$viewParams['search']['camera'] = $params['camera'];
		}
		if (!empty($params['exposure']))
		{
			$viewParams['search']['exposure'] = $params['exposure'];
		}
		if (!empty($params['focal']))
		{
			$viewParams['search']['focal'] = $params['focal'];
		}
		if (!empty($params['iso']))
		{
			$viewParams['search']['iso'] = $params['iso'];
		}
		if (!empty($params['aperture']))
		{
			$viewParams['search']['aperture'] = $params['aperture'];
		}

		return $controller->responseView(
			'sonnb_XenGallery_ViewPublic_Search_Form_Photo',
			'sonnb_xengallery_search_form_photo',
			$viewParams
		);
	}

	/**
	 * @return sonnb_XenGallery_Model_Photo
	 */
	protected function _getPhotoModel()
	{
		if (!$this->_photoModel)
		{
			$this->_photoModel = XenForo_Model::create('sonnb_XenGallery_Model_Photo');
		}

		return $this->_photoModel;
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
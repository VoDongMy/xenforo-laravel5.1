<?php

/**
 * Handles searching of media items.
 */
class XenGallery_Search_DataHandler_Media extends XenForo_Search_DataHandler_Abstract
{
	public static $findNew = false;

	protected $_mediaModel;
	protected $_albumModel;
	protected $_categoryModel;
	
	/**
	 * Inserts into (or replaces a record) in the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_insertIntoIndex()
	 */
	protected function _insertIntoIndex(XenForo_Search_Indexer $indexer, array $data, array $parentData = null)
	{
		$metadata = array();
		$metadata['media_id'] = $data['media_id'];

		if ($parentData)
		{
			$metadata['mediacat'] = $parentData['category_id'];
			$userId = $parentData['user_id'];
		}
		else
		{
			$userId = 0;
		}

		if (!empty($data['tags']))
		{
			$tags = @unserialize($data['tags']);
			if ($tags)
			{
				$tagIds = array();
				foreach ($tags AS $tagId => $tag)
				{
					$data['media_title'] .= " $tag[tag]";
					$tagIds[] = $tagId;
				}

				$metadata['tag'] = $tagIds;
			}
		}

		$indexer->insertIntoIndex(
			'xengallery_media', $data['media_id'],
			utf8_substr($data['media_title'], 0, 250), $data['media_description'],
			$data['media_date'], $userId, 0, $metadata
		);
	}

	/**
	 * Updates a record in the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_updateIndex()
	 */
	protected function _updateIndex(XenForo_Search_Indexer $indexer, array $data, array $fieldUpdates)
	{
		$indexer->updateIndex('xengallery_media', $data['media_id'], $fieldUpdates);
	}

	/**
	 * Deletes one or more records from the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_deleteFromIndex()
	 */
	protected function _deleteFromIndex(XenForo_Search_Indexer $indexer, array $dataList)
	{
		$mediaIds = array();
		foreach ($dataList AS $data)
		{
			if (!empty($data['media_id']))
			{
				$mediaIds[] = $data['media_id'];
			}
		}

		$indexer->deleteFromIndex('xengallery_media', $mediaIds);
	}

	/**
	 * Rebuilds the index for a batch.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::rebuildIndex()
	 */
	public function rebuildIndex(XenForo_Search_Indexer $indexer, $lastId, $batchSize)
	{
		$mediaIds = $this->_getMediaModel()->getMediaIdsInRange($lastId, $batchSize, 'all');
		if (!$mediaIds)
		{
			return false;
		}

		$this->quickIndex($indexer, $mediaIds);

		return max($mediaIds);
	}

	/**
	 * Rebuilds the index for the specified content.

	 * @see XenForo_Search_DataHandler_Abstract::quickIndex()
	 */
	public function quickIndex(XenForo_Search_Indexer $indexer, array $contentIds)
	{
		$media = $this->_getMediaModel()->getMediaByIds($contentIds, array());

		foreach ($media AS $item)
		{
			$this->insertIntoIndex($indexer, $item, $item);
		}

		return true;
	}

	public function getInlineModConfiguration()
	{
		return array(
			'name' => new XenForo_Phrase('xengallery_media'),
			'route' => 'xengallery/inline-mod/switch',
			'cookie' => 'media',
			'template' => 'inline_mod_controls_media_search'
		);
	}

	/**
	 * Gets the type-specific data for a collection of results of this content type.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getDataForResults()
	 */
	public function getDataForResults(array $ids, array $viewingUser, array $resultsGrouped)
	{
		$mediaModel = $this->_getMediaModel();

		$fetchOptions = array(
			'join' => XenGallery_Model_Media::FETCH_ATTACHMENT
				| XenGallery_Model_Media::FETCH_CATEGORY
				| XenGallery_Model_Media::FETCH_ALBUM
				| XenGallery_Model_Media::FETCH_USER
				| XenGallery_Model_Media::FETCH_PRIVACY
		);
		$conditions = array(
			'media_id' => $ids,
			'deleted' => $mediaModel->canViewDeletedMedia($null, $viewingUser),
			'privacyUserId' => $viewingUser['user_id'],
			'viewAlbums' => XenForo_Model::create('XenGallery_Model_Album')->canViewAlbums($null, $viewingUser),
			'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($viewingUser),
			'media_state' => 'visible'
		);

		if (self::$findNew === true)
		{
			$fetchOptions['join'] |= XenGallery_Model_Media::FETCH_LAST_VIEW;
			$conditions = $conditions + array('view_user_id' => $viewingUser['user_id']);
		}

		$media = $mediaModel->getMedia($conditions, $fetchOptions);
		$media = $mediaModel->prepareMediaItems($media);

		return $media;
	}

	/**
	 * Determines if this result is viewable.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::canViewResult()
	 */
	public function canViewResult(array $result, array $viewingUser)
	{
		$mediaModel = $this->_getMediaModel();

		if (!$mediaModel->canViewMediaItem($result, $null, $viewingUser))
		{
			return false;
		}

		if ($result['album_id'] > 0)
		{
			$albumModel = $this->_getAlbumModel();

			$result = $albumModel->prepareAlbum($result);
			$result['albumPermissions']['view'] = array(
				'permission' => 'view',
				'access_type' => $result['access_type'],
				'share_users' => $result['share_users']
			);

			if (!$albumModel->canViewAlbum($result, $null, $viewingUser))
			{
				return false;
			}
		}

		if ($result['category_id'] > 0)
		{
			if (!$this->_getCategoryModel()->canViewCategory($result, $null, $viewingUser))
			{
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Prepares a result for display.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::prepareResult()
	 */
	public function prepareResult(array $result, array $viewingUser)
	{
		$result['findNewPage'] = self::$findNew;
		$result = $this->_getMediaModel()->prepareMedia($result);
		
		return $result;
	}

	public function addInlineModOption(array &$result)
	{
		return $this->_getMediaModel()->addInlineModOptionToMedia($result, $result);
	}

	/**
	 * Gets the date of the result (from the result's content).
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getResultDate()
	 */
	public function getResultDate(array $result)
	{
		return $result['media_date'];
	}

	/**
	 * Renders a result to HTML.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::renderResult()
	 */
	public function renderResult(XenForo_View $view, array $result, array $search)
	{
		if ($result['media_type'] == 'video_embed')
		{
			$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $view)));
			
			$html = new XenForo_BbCode_TextWrapper($result['media_tag'], $bbCodeParser);
			$result['videoHtml'] = $html;
		}		
		
		return $view->createTemplateObject('xengallery_search_result_media', array(
			'item' => $result,
			'search' => $search,
			'showCategory' => true,
			'enableInlineMod' => isset($this->_inlineModEnabled) ? $this->_inlineModEnabled : false
		));
	}

	/**
	 * Returns an array of content types handled by this class
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getSearchContentTypes()
	 */
	public function getSearchContentTypes()
	{
		return array('xengallery_media');
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
			$categories = array_unique($categories);
			$constraints['mediacat'] = implode(' ', $categories);
			if (!$constraints['mediacat'])
			{
				unset($constraints['mediacat']); // just 0
			}
		}

		return $constraints;
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
			case 'mediacat':
				if ($constraintInfo)
				{
					return array(
						'metadata' => array('mediacat', preg_split('/\D+/', strval($constraintInfo))),
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
		$controller->getRouteMatch()->setSections('xengallery');

		$options = XenForo_Application::getOptions();
		if ($options->xengalleryOverrideStyle)
		{
			$controller->setViewStateChange('styleId', $options->xengalleryOverrideStyle);
		}
		
		$params = $input->filterSingle('c', XenForo_Input::ARRAY_SIMPLE);

		if (!empty($params['mediacat']))
		{
			$viewParams['search']['categories'] = array_fill_keys(explode(' ', $params['mediacat']), true);
		}
		else
		{
			$viewParams['search']['categories'] = array();
		}

		$viewParams['categories'] = XenForo_Model::create('XenGallery_Model_Category')->getCategoryStructure();

		return $controller->responseView('XenGallery_ViewPublic_Search_Form_Media', 'xengallery_search_form_media', $viewParams);
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

	/**
	 * @return XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		if (!$this->_albumModel)
		{
			$this->_albumModel = XenForo_Model::create('XenGallery_Model_Album');
		}

		return $this->_albumModel;
	}

	/**
	 * @return XenGallery_Model_Category
	 */
	protected function _getCategoryModel()
	{
		if (!$this->_categoryModel)
		{
			$this->_categoryModel = XenForo_Model::create('XenGallery_Model_Category');
		}

		return $this->_categoryModel;
	}
}
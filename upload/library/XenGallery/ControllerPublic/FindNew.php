<?php

class XenGallery_ControllerPublic_FindNew extends XFCP_XenGallery_ControllerPublic_FindNew
{
	/**
	 * Finds new/unread media.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionMedia()
	{
		$mediaModel = $this->_getMediaModel();

		if (!$mediaModel->canViewMedia($error))
		{
			throw $this->getErrorOrNoPermissionResponseException($error);
		}

		$this->_routeMatch->setSections('xengallery');

		$options = XenForo_Application::getOptions();
		if ($options->xengalleryOverrideStyle)
		{
			$this->setViewStateChange('styleId', $options->xengalleryOverrideStyle);
		}
		
		$searchId = $this->_input->filterSingle('search_id', XenForo_Input::UINT);
		if (!$searchId)
		{
			return $this->findNewMedia();
		}

		/** @var $searchModel XenForo_Model_Search */
		$searchModel = $this->_getSearchModel();

		$search = $searchModel->getSearchById($searchId);
		if (!$search
			|| $search['user_id'] != XenForo_Visitor::getUserId()
		)
		{
			return $this->findNewMedia();
		}		
		
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$perPage = $options->xengalleryMediaMaxPerPage;

		XenGallery_Search_DataHandler_Media::$findNew = true;

		$pageResultIds = $searchModel->sliceSearchResultsToPage($search, $page, $perPage);
		$results = $searchModel->getSearchResultsForDisplay($pageResultIds);
		if (!$results)
		{
			return $this->getNoMediaResponse();
		}

		$resultStartOffset = ($page - 1) * $perPage + 1;
		$resultEndOffset = ($page - 1) * $perPage + count($results['results']);

		$media = array();
		foreach ($results['results'] AS $result)
		{
			$media[$result[XenForo_Model_Search::CONTENT_ID]] = $result['content'];
		}

		$ignoredNames = array();
		foreach ($media AS $item)
		{
			if (!empty($item['isIgnored']))
			{
				$ignoredNames[] = $item['username'];
			}
		}

		$inlineModOptions = $mediaModel->prepareInlineModOptions($media, false);
		
		$viewParams = array(
			'search' => $search,		
			'findNewPage' => 'media',
			'media' => $media,
			'ignoredNames' => array_unique($ignoredNames),
			'canViewComments' => $this->getModelFromCache('XenGallery_Model_Comment')->canViewComments(),
			'threadStartOffset' => $resultStartOffset,
			'threadEndOffset' => $resultEndOffset,
			
			'page' => $page,
			'perPage' => $perPage,
			'totalMedia' => $search['result_count'],
			'nextPage' => ($resultEndOffset < $search['result_count'] ? ($page + 1) : 0),
			'inlineModOptions' => $inlineModOptions
		);

		return $this->getFindNewWrapper(
			$this->responseView('XenGallery_ViewPublic_FindNew_Media', 'xengallery_find_new_media', $viewParams),
			'media'
		);
	}
	
	public function findNewMedia()
	{
		$mediaModel = $this->_getMediaModel();

		/** @var $searchModel XenForo_Model_Search */
		$searchModel = $this->_getSearchModel();
		
		$visitor = XenForo_Visitor::getInstance();
		
		$limitOptions = array(
			'limit' => XenForo_Application::getOptions()->maximumSearchResults,
			'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($visitor->toArray()),
			'viewAlbums' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewAlbums'),
			'privacyUserId' => $visitor->user_id
		);
		
		if ($visitor->user_id)
		{
			$mediaIds = $mediaModel->getUnviewedMediaIds($visitor->user_id, $limitOptions);
		}				
		else
		{
			$conditions = $limitOptions + array(
				'media_date' => array('>', XenForo_Application::$time - 86400 * 7),
				'deleted' => false,
				'moderated' => false
			);

			$fetchOptions = $limitOptions + array(
				'order' => 'media_date',
				'orderDirection' => 'desc',
				'join' =>
					XenGallery_Model_Media::FETCH_USER
						| XenGallery_Model_Media::FETCH_ATTACHMENT
						| XenGallery_Model_Media::FETCH_CATEGORY
						| XenGallery_Model_Media::FETCH_ALBUM
						| XenGallery_Model_Media::FETCH_PRIVACY
			);

			$mediaIds = array_keys($mediaModel->getMedia($conditions, $fetchOptions));
		}
		
		if ($mediaIds)
		{
			$media = $mediaModel->getMedia(
				array(
					'media_id' => $mediaIds,
					'view_user_id' => $visitor->getUserId()
				),
				array(
					'join' =>
						XenGallery_Model_Media::FETCH_USER
							| XenGallery_Model_Media::FETCH_ATTACHMENT
							| XenGallery_Model_Media::FETCH_CATEGORY
							| XenGallery_Model_Media::FETCH_ALBUM
							| XenGallery_Model_Media::FETCH_LAST_VIEW
				)
			);
			$media = $mediaModel->prepareMedia($media);
		}

		$results = array();
		foreach ($mediaIds AS $mediaId)
		{
			if (isset($media[$mediaId]))
			{
				$results[] = array(
					XenForo_Model_Search::CONTENT_TYPE => 'xengallery_media',
					XenForo_Model_Search::CONTENT_ID => $mediaId
				);
			}
		}

		$search = $searchModel->insertSearch($results, 'xengallery_media', '', array('findNew'), 'date', false);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('find-new/media', $search)
		);		
	}
	
	public function getNoMediaResponse()
	{
		$this->_routeMatch->setSections('xengallery');
	
		return $this->getFindNewWrapper($this->responseView('XenGallery_ViewPublic_FindNew_MediaNoResults', 'xengallery_find_new_no_response', array()), 'media');
	}
	
	protected function _getWrapperTabs()
	{
		$parent = parent::_getWrapperTabs();

		if ($this->_getMediaModel()->canViewMedia())
		{
			$parent['media'] = array(
				'href' => XenForo_Link::buildPublicLink('find-new/media'),
				'title' => new XenForo_Phrase('xengallery_new_media')
			);
		}

		return $parent;
	}	
	
	/**
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Media');
	}
}
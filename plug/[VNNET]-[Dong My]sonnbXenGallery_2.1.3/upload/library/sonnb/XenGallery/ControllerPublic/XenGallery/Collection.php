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
class sonnb_XenGallery_ControllerPublic_XenGallery_Collection extends sonnb_XenGallery_ControllerPublic_Abstract
{
	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);

		if (XenForo_Application::getOptions()->sonnbXG_disableCollection)
		{
			throw $this->responseException($this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('gallery')
			));
		}
	}

	public function actionIndex()
	{
		if ($this->_input->inRequest('collection_id'))
		{
			return $this->responseReroute(__CLASS__, 'view');
		}

		$isGalleryIndex = false;
		$linkPrefix = 'gallery/collections';
		if (XenForo_Application::getOptions()->sonnbXG_loadedContent === 'collection' &&
			$this->_routeMatch->getControllerName() === 'sonnb_XenGallery_ControllerPublic_XenGallery')
		{
			$isGalleryIndex = true;
			$linkPrefix = 'gallery';
		}

        $xenOptions = XenForo_Application::getOptions();
        $page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
        $itemsPerPage = $xenOptions->sonnbXG_photoPerPage;

        $order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => 'collection_id'));
        $orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => 'asc'));

        $conditions = array(

        );
        $fetchOptions = array(
            'perPage' => $itemsPerPage,
            'page' => $page,
            'order' => $order,
            'orderDirection' => $orderDirection
        );

		$collections = $this->_getCollectionModel()->getCollections($conditions, $fetchOptions);

        //TODO: add pagination to the XML.
		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Collection_Index',
			'sonnb_xengallery_collection_index',
			array(
				'collections' => $collections,
				'breadCrumbs' => array(),

                'totalItems' => $this->_getCollectionModel()->countCollections(),
                'page' => $page,
                'itemsPerPage' => $itemsPerPage,

				'linkPrefix' => $linkPrefix,
				'isGalleryIndex' => $isGalleryIndex,

				'canCreateAlbum' => $this->_getGalleryModel()->canUpload(),
				'canEmbedVideos' => $this->_getGalleryModel()->canEmbedVideo(),
			)
		);
	}

	public function actionView()
	{
		if (!$this->_input->inRequest('collection_id'))
		{
			return $this->responseReroute(__CLASS__, 'index');
		}

		$collection = $this->_getCollectionOrError();
		$xenOptions = XenForo_Application::getOptions();
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$itemsPerPage = $xenOptions->sonnbXG_photoPerPage;
		$collectionModel = $this->_getCollectionModel();

		$isGalleryIndex = false;
		$linkPrefix = 'gallery/collections';
		if ($xenOptions->sonnbXG_loadedContent === 'collection' &&
			$this->_routeMatch->getControllerName() === 'sonnb_XenGallery_ControllerPublic_XenGallery')
		{
			$isGalleryIndex = true;
			$linkPrefix = 'gallery';
		}
		else
		{
			$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink($linkPrefix, $collection+array('page' => $page)));
		}

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => 'collection_date'));
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => 'desc'));

		$conditions = array(
			'collection_id' => $collection['collection_id']
		);
		$fetchOptions = array(
			'perPage' => $itemsPerPage,
			'page' => $page,
			'order' => $order,
			'orderDirection' => $orderDirection
		);

		$totalItems = $collectionModel->countCollectedItems($collection['collection_id']);
		$items = $collectionModel->getCollectedItems($conditions, $fetchOptions);
		$itemsGrouped = $this->_getGalleryModel()->groupContentsContentType($items);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Collection_View',
			'sonnb_xengallery_collection_view',
			array(
				'collection' => $collection,

				'items' => $items,
				'totalItems' => $totalItems,
				'itemsGrouped' => $itemsGrouped,

				'page' => $page,
				'itemsPerPage' => $itemsPerPage,

				'breadCrumbs' => $this->_getCollectionModel()->getCollectionBreadCrumb($collection),

				'linkPrefix' => $linkPrefix,
				'isGalleryIndex' => $isGalleryIndex,

				'canCreateAlbum' => $this->_getGalleryModel()->canUpload(),
				'canEmbedVideos' => $this->_getGalleryModel()->canEmbedVideo(),
			)
		);
	}

	protected function _getCollectionOrError($collectionId = null)
	{
		if ($collectionId === null)
		{
			$collectionId = $this->_input->filterSingle('collection_id', XenForo_Input::UINT);
		}

		$collection = $this->_getCollectionModel()->getCollectionById($collectionId);
		$collection = $this->_getCollectionModel()->prepareCollection($collection);

		if (!$collectionId || !$collection)
		{
			throw $this->_throwFriendlyNoPermission(new XenForo_Phrase('sonnb_xengallery_requested_collection_could_not_be_found'));
		}

		return $collection;
	}
}

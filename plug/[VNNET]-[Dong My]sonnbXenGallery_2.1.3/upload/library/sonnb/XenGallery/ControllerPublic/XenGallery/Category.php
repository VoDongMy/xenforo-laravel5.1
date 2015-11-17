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
class sonnb_XenGallery_ControllerPublic_XenGallery_Category extends sonnb_XenGallery_ControllerPublic_Abstract
{
	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);

		if (XenForo_Application::getOptions()->sonnbXG_disableCategory)
		{
			throw $this->responseException($this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('gallery')
			));
		}
	}

	public function actionIndex()
	{
		if ($this->_input->inRequest('category_id'))
		{
			return $this->responseReroute(__CLASS__, 'view');
		}

		$isGalleryIndex = false;
		$linkPrefix = 'gallery/categories';
		$xenOptions = XenForo_Application::getOptions();
		if ($xenOptions->sonnbXG_loadedContent === 'category' &&
				$this->_routeMatch->getControllerName() === 'sonnb_XenGallery_ControllerPublic_XenGallery')
		{
			$isGalleryIndex = true;
			$linkPrefix = 'gallery';
		}


		$topCategories = $this->_getCategoryModel()->getTopCategories();
		$topCategories = $this->_getCategoryModel()->getLatestAlbumsForCategories($topCategories);

		$viewParams = array(
			'categories' => $topCategories,

			'linkPrefix' => $linkPrefix,
			'isGalleryIndex' => $isGalleryIndex,

			'canCreateAlbum' => $this->_getGalleryModel()->canUpload(),
			'canEmbedVideos' => $this->_getGalleryModel()->canEmbedVideo(),
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Category_Index',
			'sonnb_xengallery_category_index',
			$viewParams
		);
	}

	public function actionPopup()
	{
		$categories = $this->_getCategoryModel()->getAllCachedCategories();

		$viewParams = array(
			'categories' => $categories
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Category_ListPopup',
			'sonnb_xengallery_category_popup',
			$viewParams
		);
	}

	public function actionView()
	{
		if (!$this->_input->inRequest('category_id'))
		{
			return $this->responseReroute(__CLASS__, 'index');
		}

		$categoryModel = $this->_getCategoryModel();
		$albumModel = $this->_getAlbumModel();
		$category = $this->_getCategoryOrError();
		$childrenCategory = $categoryModel->getChildrenOfCategoryId($category['category_id']);

		$xenOptions = XenForo_Application::getOptions();;
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$albumPerPage = $xenOptions->sonnbXG_albumPerPage;

		$isGalleryIndex = false;
		$linkPrefix = 'gallery/categories';
		if ($xenOptions->sonnbXG_loadedContent === 'category' &&
			$this->_routeMatch->getControllerName() === 'sonnb_XenGallery_ControllerPublic_XenGallery')
		{
			$isGalleryIndex = true;
			$linkPrefix = 'gallery';
		}
		else
		{
			$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink($linkPrefix, $category+array('page' => $page)));
		}

		list($defaultOrder, $defaultOrderDirection) = $this->_getDefaultAlbumSort();

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => $defaultOrderDirection));

		$albumConditions = $this->_getDefaultAlbumConditions($category);
		$albumFetchElements = $this->_getAlbumFetchElements($albumConditions);

		$albumFetchConditions = $albumFetchElements['conditions'];
		$albumFetchOptions = $albumFetchElements['options'] + array(
			'perPage' => $albumPerPage,
			'page' => $page,
			'order' => $order,
			'orderDirection' => $orderDirection
		);

		$totalAlbums = $albumModel->countAlbums($albumFetchConditions, $albumFetchOptions);
		$this->canonicalizePageNumber($page, $albumPerPage, $totalAlbums, 'gallery/categories', $category);

		$albums = $albumModel->getAlbums($albumFetchConditions, $albumFetchOptions);
		$albums = $albumModel->prepareAlbums($albums, $albumFetchOptions);

		foreach ($albums as $albumId=>$album)
		{
			if (!$albumModel->canViewAlbum($album))
			{
				unset($albums[$albumId]);
			}
		}

		$albums = $albumModel->attachCoversToAlbums($albums, $albumFetchOptions);

		$pageNavParams = array();
		$pageNavParams['order'] = ($order != $defaultOrder ? $order : false);
		$pageNavParams['direction'] = ($orderDirection != $defaultOrderDirection ? $orderDirection : false);

		$viewParams = array(
			'albums' => $albums,

			'canCreateAlbum' => $this->_getGalleryModel()->canUpload() && $categoryModel->canUploadToCategory($category),

			'category' => $category,
			'childrenCategory' => $childrenCategory,

			'order' => $order,
			'orderDirection' => $orderDirection,

			'pageNavParams' => $pageNavParams,
			'page' => $page,

			'albumsPerPage' => $albumPerPage,
			'totalAlbums' => $totalAlbums,

			'breadCrumbs' => $categoryModel->getCategoryBreadCrumbs($category),

			'linkPrefix' => $linkPrefix,
			'isGalleryIndex' => $isGalleryIndex,

			'canEmbedVideos' => $this->_getGalleryModel()->canEmbedVideo(),
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Category_View',
			'sonnb_xengallery_category_view',
			$viewParams
		);
	}

	protected function _getDefaultAlbumConditions(array $category)
	{
		$categories = $this->_getCategoryModel()->getDescendantsOfCategory($category);
		$conditionIds = array_keys($categories);
		$conditionIds[] = $category['category_id'];

		return array(
			'category_id' => $conditionIds,
			'album_type' => sonnb_XenGallery_Model_Album::ALBUM_TYPE_NORMAL,
			'content_count' => array('>', 0)
		);
	}

	protected function _getDefaultAlbumSort()
	{
		return array('album_updated_date',  'desc');
	}
	
	protected function _getAlbumFetchElements(array $conditions)
	{
		$albumModel = $this->_getAlbumModel();
		$visitor = XenForo_Visitor::getInstance();
	
		$albumFetchConditions = $conditions + $albumModel->getPermissionBasedAlbumFetchConditions();
		$albumFetchOptions = array(
			'join' => sonnb_XenGallery_Model_Album::FETCH_USER | 
						sonnb_XenGallery_Model_Album::FETCH_COVER_PHOTO,
			'likeUserId' => $visitor['user_id'],
			'watchUserId' => $visitor['user_id'],
			'followingUserId' => $visitor['user_id'],
			'coverPhoto' => true
		);
	
		return array(
			'conditions' => $albumFetchConditions,
			'options' => $albumFetchOptions
		);
	}

	protected function _getCategoryOrError($categoryId = null)
	{
		if ($categoryId === null)
		{
			$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
		}

		$category = $this->_getCategoryModel()->getCategoryById($categoryId);
		$category = $this->_getCategoryModel()->prepareCategory($category);

		if (!$categoryId || !$category)
		{
			throw $this->_throwFriendlyNoPermission(new XenForo_Phrase('sonnb_xengallery_requested_category_could_not_be_found'));
		}

		if (!$this->_getCategoryModel()->canViewCategory($category, $errorPhrase))
		{
			throw $this->_throwFriendlyNoPermission($errorPhrase);
		}

		return $category;
	}
}
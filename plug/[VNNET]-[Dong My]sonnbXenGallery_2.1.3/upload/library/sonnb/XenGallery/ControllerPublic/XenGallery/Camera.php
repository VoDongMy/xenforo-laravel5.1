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
class sonnb_XenGallery_ControllerPublic_XenGallery_Camera extends sonnb_XenGallery_ControllerPublic_Abstract
{
	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);

		if (XenForo_Application::getOptions()->sonnb_XG_disableCamera)
		{
			throw $this->responseException($this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('gallery')
			));
		}
	}

	public function actionIndex()
	{
		$cameraPortion = $this->_input->filterSingle('camera_url', XenForo_Input::STRING);
		$cameraPortion = sonnb_XenGallery_Model_Gallery::getTitleForUrl($cameraPortion);
		
		if ($cameraPortion)
		{
			return $this->responseReroute(__CLASS__, 'view');
		}

		$cameraModel = $this->_getCameraModel();
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$camerasPerPage = XenForo_Application::getOptions()->sonnbXG_albumPerPage;
		
		$conditions = array();
		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Camera::FETCH_CAMERA,
			'perPage' => $camerasPerPage,
			'page' => $page,
			'order' => 'photo_count',
			'orderDirection' => 'desc'
		);
		
		$cameras = $cameraModel->getUniqueCameras($conditions, $fetchOptions);
		$cameras = $cameraModel->prepareCameras($cameras);

		$totalUniqueCamera = $cameraModel->countUniqueCameras($conditions, $fetchOptions);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Camera_Index',
			'sonnb_xengallery_camera_index',
			array(
				'cameras' => $cameras,
					
				'page' => $page,
				
				'perPage' => $camerasPerPage,
				'totalCameras' => $totalUniqueCamera,
				
				'breadCrumbs' => $cameraModel->getCameraBreadCrumbs()
			)		
		);
	}
	
	public function actionView()
	{
		$cameraPortion = $this->_input->filterSingle('camera_url', XenForo_Input::STRING);
		$cameraPortion = sonnb_XenGallery_Model_Gallery::getTitleForUrl($cameraPortion);
		
		if (!$cameraPortion)
		{
			return $this->responseReroute(__CLASS__, 'index');
		}

		$cameraModel = $this->_getCameraModel();
		$photoModel = $this->_getPhotoModel();
		$xenOptions = XenForo_Application::getOptions();;
		
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$photosPerPage = $xenOptions->sonnbXG_photoPerPage;

		list($defaultOrder, $defaultOrderDirection) = $this->_getDefaultPhotoSort();

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => $defaultOrderDirection));

		$conditions = array(
			'camera_url' => $cameraPortion
		);
		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Camera::FETCH_CAMERA,
			'perPage' => $photosPerPage,
			'page' => $page,
			'order' => 'camera_id',
			'orderDirection' => 'desc'
		);

		$cameras = $cameraModel->getCameras($conditions, $fetchOptions);

		$camera = reset($cameras);

		if ($camera)
		{
			$camera = $cameraModel->prepareCamera($camera);
		}

		$photoFetchOptions = array(
			'join' => sonnb_XenGallery_Model_Photo::FETCH_USER |
				sonnb_XenGallery_Model_Photo::FETCH_DATA | sonnb_XenGallery_Model_Photo::FETCH_ALBUM,
			'likeUserId' => XenForo_Visitor::getUserId(),
			'followingUserId' => XenForo_Visitor::getUserId()
		);
		
		$photos = $cameraModel->getPhotosByCameras($cameras, $photoFetchOptions);
		$totalPhotos = $cameraModel->countCameras($conditions, $fetchOptions);

		foreach ($photos as $_photoId => $_photo)
		{
			if (!$photoModel->canViewContentAndContainer($_photo, $_photo['album'], $errorKey))
			{
				unset($photos[$_photoId]);
			}
		}

		$pageNavParams = array();
		$pageNavParams['order'] = ($order != $defaultOrder ? $order : false);
		$pageNavParams['direction'] = ($orderDirection != $defaultOrderDirection ? $orderDirection : false);

		return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Camera_View',
				'sonnb_xengallery_camera_view',
				array(
					'camera' => $camera,
					'contents' => $photos,

					'page' => $page,
					'photosPerPage' => $photosPerPage,
					'order' => $order,
					'orderDirection' => $orderDirection,
					'pageNavParams' => $pageNavParams,

					'totalPhotos' => $totalPhotos,

					'breadCrumbs' => $cameraModel->getCameraBreadCrumbs($camera)
				)
		);
	}

	public function actionFind()
	{
		$q = $this->_input->filterSingle('q', XenForo_Input::STRING);

		if ($q !== '' && utf8_strlen($q) >= 2)
		{
			$cameras = $this->_getCameraModel()->getCameras(
				array('camera_name_search' => array($q , 'r')),
				array('limit' => 10)
			);
		}
		else
		{
			$cameras = array();
		}

		$viewParams = array(
			'cameras' => $cameras
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Camera_Find',
			'sonnb_xengallery_camera_autocomplete',
			$viewParams
		);
	}

	public function actionShopping()
	{
		$xenOptions = XenForo_Application::getOptions();;
		$cameraPortion = $this->_input->filterSingle('camera_url', XenForo_Input::STRING);
		$cameraPortion = sonnb_XenGallery_Model_Gallery::getTitleForUrl($cameraPortion);

		if (!$cameraPortion || !$xenOptions->sonnbXG_amazonEnable || !class_exists('SoapClient'))
		{
			return $this->responseReroute(__CLASS__, 'index');
		}

		$cameraModel = $this->_getCameraModel();

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$camera = $cameraModel->getCamerasByCameraUrl($cameraPortion);
		$camera = reset($camera);

		if ($camera)
		{
			$camera = $cameraModel->getDataCameras(array('camera_name' => $camera['camera_name']));
			$camera = reset($camera);
		}

		$response = array();
		if ($camera)
		{
			$camera['camera_url'] = $cameraPortion;

			$amazonEcs = new sonnb_XenGallery_Model_AmazonECS(
				$xenOptions->sonnbXG_amazonAccessKey,
				$xenOptions->sonnbXG_amazonSecretKey,
				$xenOptions->sonnbXG_amazonSite,
				$xenOptions->sonnbXG_amazonAssociateTag
			);
			$amazonEcs->returnType(sonnb_XenGallery_Model_AmazonECS::RETURN_TYPE_ARRAY);
			$amazonEcs->page($page);

			try
			{
				$response = $amazonEcs->category('Electronics')->responseGroup('Large')->search($camera['camera_name']);
			}
			catch(Exception $e)
			{}
		}

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Camera_Shopping',
			'sonnb_xengallery_camera_shopping',
			array(
				'camera' => $camera,
				'items' => empty($response['Items']['Item']) ? array() : $response['Items']['Item'],

				'page' => $page,

				'perPage' => 10,
				'totalItems' => empty($response['Items']['TotalResults']) ? 0 : $response['Items']['TotalResults'],
				'totalPages' => empty($response['Items']['TotalPages']) ? 0 : $response['Items']['TotalPages'],

				'breadCrumbs' => $cameraModel->getCameraBreadCrumbs()
			)
		);
	}

	protected function _getDefaultPhotoSort()
	{
		return array('position',  'asc');
	}
}
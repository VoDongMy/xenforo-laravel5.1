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
class sonnb_XenGallery_ControllerPublic_XenGallery_Location extends sonnb_XenGallery_ControllerPublic_Abstract
{
	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);

		if (XenForo_Application::getOptions()->sonnb_XG_disableLocation)
		{
			throw $this->responseException($this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('gallery')
			));
		}
	}

	public function actionIndex()
	{
		$locationPortion = $this->_input->filterSingle('location_url', XenForo_Input::STRING);
		$locationPortion = sonnb_XenGallery_Model_Gallery::getTitleForUrl($locationPortion);
		
		if ($locationPortion)
		{
			return $this->responseReroute(__CLASS__, 'view');
		}

		$locationModel = $this->_getLocationModel();

		$conditions = array();
		$fetchOptions = array(
			'order' => 'content_count',
			'orderDirection' => 'desc',
			'limit' => 1000
		);
		$locations = $locationModel->getUniqueLocations($conditions, $fetchOptions);
		$locations = $locationModel->prepareLocations($locations);

		$countPhrase = new XenForo_Phrase('sonnb_xengallery_item_count');
		foreach ($locations as &$location)
		{
			$link = XenForo_Link::buildPublicLink('gallery/locations', $location);

			$location['url'] = sprintf(
				"<div class=\"windowInfo\">
					<h2 class=\"title\">
						<a href=\"%s\">%s</a>
					</h2>
					%s: <a href=\"%s\">%s</a>
				</div>",
				$link,
				$location['location_name'],
				$countPhrase,
				$link,
				$location['content_count']
			);
		}

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Location_Index',
			'sonnb_xengallery_location_index',
			array(
				'locations' => $locations,
				'locationGrouped' => json_encode($locations),
				'firstLocation' => json_encode(reset($locations)),

				'breadCrumbs' => $locationModel->getLocationBreadCrumbs()
			)
		);
	}
	
	public function actionView()
	{
		$locationPortion = $this->_input->filterSingle('location_url', XenForo_Input::STRING);
		$locationPortion = sonnb_XenGallery_Model_Gallery::getTitleForUrl($locationPortion);
	
		if (!$locationPortion)
		{
			return $this->responseReroute(__CLASS__, 'index');
		}
	
		$xenOptions = XenForo_Application::getOptions();;
		$itemsPerPage = $xenOptions->sonnbXG_photoPerPage;
		$locationModel = $this->_getLocationModel();

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
	
		$conditions = array(
			'location_url' => $locationPortion
		);
		$fetchOptions = array(
			'page' => $page,
			'perPage' => $itemsPerPage,
			'order' => 'location_id',
			'orderDirection' => 'asc'
		);

		$totalItems = $locationModel->countLocations($conditions, $fetchOptions);

		if (!$totalItems)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('gallery/locations')
			);
		}

		$locations = $locationModel->getLocations($conditions, $fetchOptions);
		$locations = $locationModel->prepareLocations($locations);
		$itemsGrouped = $this->_getGalleryModel()->groupContentsContentType($locations);

		$location = reset($locations);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Location_View',
			'sonnb_xengallery_location_view',
			array(
				'location' => $location,

				'locations' => $locations,
				'totalItems' => $totalItems,
				'itemsGrouped' => $itemsGrouped,

				'page' => $page,
				'itemsPerPage' => $itemsPerPage,

				'breadCrumbs' => $locationModel->getLocationBreadCrumbs($location)
			)
		);
	}
}
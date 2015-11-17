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
class sonnb_XenGallery_ControllerPublic_XenGallery_Stream extends sonnb_XenGallery_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		if ($this->_input->inRequest('stream_name'))
		{
			return $this->responseReroute(__CLASS__, 'view');
		}

		$streamModel = $this->_getStreamModel();

		$conditions = array();
		$fetchOptions = array(
			'limit' => 100,
			'order' => 'item_count',
			'orderDirection' => 'desc'
		);

		$totalStreams = $streamModel->countStreams($conditions, $fetchOptions);
		$streams = $streamModel->getUniqueStreams($conditions, $fetchOptions);
		$streams = $streamModel->prepareStreamsForDisplay($streams, $totalStreams);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Stream_Index',
			'sonnb_xengallery_stream_index',
			array(
				'streams' => $streams,
				'breadCrumbs' => $streamModel->getStreamBreadCrumb(),
				'totalItems' => count($streams),

				'limit' => $this->_getGalleryModel()->getMaximumStreamCount()
			)
		);
	}

	public function actionView()
	{
		$streams = $this->_input->filterSingle('stream_name', XenForo_Input::STRING);

		if (!$this->_input->inRequest('stream_name') || !$streams)
		{
			return $this->responseReroute(__CLASS__, 'index');
		}

		$xenOptions = XenForo_Application::getOptions();;
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$itemsPerPage = $xenOptions->sonnbXG_photoPerPage;
		$streamModel = $this->_getStreamModel();

		$conditions = array(
			'stream_name' => $streams
		);
		$fetchOptions = array(
			'perPage' => $itemsPerPage,
			'page' => $page
		);

		//TODO: FIx duplicate count
		$totalItems = $streamModel->countStreams($conditions, $fetchOptions);
		$items = $streamModel->getStreams($conditions, $fetchOptions);

		$filterItems = array();
		foreach ($items as $item)
		{
			$filterItems[$item['stream_id']] = $item;
		}

		$itemsGrouped = $this->_getGalleryModel()->groupContentsContentType($filterItems);
		$stream = reset($filterItems);

		$this->canonicalizePageNumber($page, $itemsPerPage, $totalItems, 'gallery/streams', $stream);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Stream_View',
			'sonnb_xengallery_stream_view',
			array(
				'stream' => $stream,

				'items' => $filterItems,
				'totalItems' => $totalItems,
				'itemsGrouped' => $itemsGrouped,

				'page' => $page,
				'itemsPerPage' => $itemsPerPage,

				'breadCrumbs' => $streamModel->getStreamBreadCrumb($stream)
			)
		);
	}

	public function actionJump()
	{
		$stream = $this->_input->filterSingle('stream_name', XenForo_Input::STRING);

		if ($this->_noRedirect())
		{
			$streamModel = $this->_getStreamModel();
			$conditions = array(
				'stream_name' => $stream
			);

			$totalItems = $streamModel->countStreams($conditions);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Stream_Jump',
				'',
				array (
					'totalItems' => $totalItems,
					'stream_name' => $stream
				)
			);
		}
		else
		{
			return $this->responseReroute(__CLASS__, 'view');
		}
	}

	public function actionFind()
	{
		$q = $this->_input->filterSingle('q', XenForo_Input::STRING);

		if (!$this->_noRedirect())
		{
			$this->_request->setParam('stream_name', $q);
			return $this->responseReroute(__CLASS__, 'jump');
		}

		if ($q !== '' && utf8_strlen($q) >= 2)
		{
			$streams = $this->_getStreamModel()->getStreams(
				array('stream_name_search' => array($q , 'r')),
				array('limit' => 10)
			);
		}
		else
		{
			$streams = array();
		}

		$viewParams = array(
			'streams' => $streams
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Stream_Find',
			'sonnb_xengallery_stream_autocomplete',
			$viewParams
		);
	}
}

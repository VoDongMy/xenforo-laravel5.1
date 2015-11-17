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
class sonnb_XenGallery_ControllerAdmin_Collection extends sonnb_XenGallery_ControllerAdmin_Abstract
{
	/**
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionIndex()
	{
        $page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
        $perPage = 50;

        $conditions = array(

        );
        $fetchOptions = array(
            'perPage' => $perPage,
            'page' => $page
        );

        $collections = $this->_getCollectionModel()->getCollections($conditions, $fetchOptions);

		$viewParams = array(
			'collections' => $collections,

            'page' => $page,
            'perPage' => $perPage,
            'totalItems' => $this->_getCollectionModel()->countCollections()
		);
		
		return $this->responseView(
			'sonnb_XenGallery_ViewAdmin_Collection_List',
			'sonnb_xengallery_collection_list',
			$viewParams
		);
	}

	/**
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionView()
	{
		//TODO: List all items within this collection
	}

	/**
	 * @param array $collection
	 * @return XenForo_ControllerResponse_View
	 */
	protected function _getCollectionAddEditResponse(array $collection)
	{
		$viewParams = array(
			'collection' => $collection
		);
		return $this->responseView(
			'sonnb_XenGallery_ViewAdmin_Collection_Edit',
			'sonnb_xengallery_collection_edit',
			$viewParams
		);
	}

	/**
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionAdd()
	{
		return $this->_getCollectionAddEditResponse(array(
			'collection_id' => 0,
			'title' => '',
			'description' => '',
			'thumbnail' => '',
			'item_count' => 0
		));
	}

	/**
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionEdit()
	{
		$category = $this->_getCollectionOrError();

		return $this->_getCollectionAddEditResponse($category);
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$collectionId = $this->_input->filterSingle('collection_id', XenForo_Input::STRING);

		$dwInput = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'description' => XenForo_Input::STRING,
			'thumbnail' => XenForo_Input::STRING
		));

		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Collection');
		if ($collectionId)
		{
			$dw->setExistingData($collectionId);
		}
		$dw->bulkSet($dwInput);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('gallery/collections') . $this->getLastHash($dw->get('collection_id'))
		);
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect|XenForo_ControllerResponse_View
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			$collectionId = $this->_input->filterSingle('collection_id', XenForo_Input::STRING);
			
			$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Collection');
			$dw->setExistingData($collectionId);
			$dw->delete();
			
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS, 
				XenForo_Link::buildAdminLink('gallery/collections')
			);
		}
		else
		{
			$viewParams = array(
				'collection' => $this->_getCollectionOrError()
			);
			
			return $this->responseView(
				'sonnb_XenGallery_ViewAdmin_Collection_Delete',
				'sonnb_xengallery_collection_delete',
				$viewParams
			);
		}
	}

	/**
	 * @param null $id
	 * @return array|mixed
	 * @throws XenForo_ControllerResponse_Exception
	 */
	protected function _getCollectionOrError($id = null)
	{
		if ($id === null)
		{
			$id = $this->_input->filterSingle('collection_id', XenForo_Input::UINT);
		}

		$info = $this->_getCollectionModel()->getCollectionById($id);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('sonnb_xengallery_requested_collection_not_found'), 404));
		}

		return $info;
	}
}
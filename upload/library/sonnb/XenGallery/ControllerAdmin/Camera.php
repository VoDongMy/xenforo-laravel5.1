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
class sonnb_XenGallery_ControllerAdmin_Camera extends sonnb_XenGallery_ControllerAdmin_Abstract
{
	/**
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionIndex()
	{
		$cameras = $this->_getCameraModel()->getDataCameras();

		foreach ($cameras as &$camera)
		{
			$camera['camera_data'] = unserialize($camera['camera_data']);
		}

		$viewParams = array(
			'cameras' => $cameras
		);
		
		return $this->responseView(
			'sonnb_XenGallery_ViewAdmin_Camera_List',
			'sonnb_xengallery_camera_list',
			$viewParams
		);
	}

	/**
	 * @param array $camera
	 * @return XenForo_ControllerResponse_View
	 */
	protected function _getCameraAddEditResponse(array $camera)
	{
		$viewParams = array(
			'camera' => $camera
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewAdmin_Camera_Edit',
			'sonnb_xengallery_camera_edit',
			$viewParams
		);
	}

	/**
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionAdd()
	{
		return $this->_getCameraAddEditResponse(array(
			'unique_id' => 0,
			'camera_id' => '',
			'camera_name' => '',
			'camera_thumbnail' => '',
			'camera_vendor' => '',
			'camera_data' => array(
				'camera_type' => '',
				'pixel' => '',
				'lcd_screen_size' => '',
				'memory_type' => '',
				'weight' => '',
				'len_type' => ''
			)
		));
	}

	/**
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionEdit()
	{
		$camera = $this->_getCameraOrError();
		$camera['camera_data'] = @unserialize($camera['camera_data']);

		return $this->_getCameraAddEditResponse($camera);
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$cameraId = $this->_input->filterSingle('camera_id', XenForo_Input::STRING);
		$newCameraId = $this->_input->filterSingle('new_camera_id', XenForo_Input::STRING);

		$dwInput = $this->_input->filter(array(
			'camera_name' => XenForo_Input::STRING,
			'camera_thumbnail' => XenForo_Input::STRING,
			'camera_vendor' => XenForo_Input::STRING,
		));

		$inputCameraData = $this->_input->filterSingle('camera_data', XenForo_Input::ARRAY_SIMPLE);
		$cameraDataHandler = new XenForo_Input($inputCameraData);
		$cameraData = $cameraDataHandler->filter(array(
			'key_value' => array(XenForo_Input::STRING, array('array' => true)),
			'key_name' => array(XenForo_Input::STRING, array('array' => true))
		));

		if (!empty($cameraData['key_value']))
		{
			foreach ($cameraData['key_value'] as $_index => $_value)
			{
				if (isset($cameraData['key_name'][$_index]))
				{
					$dwInput['camera_data'][$cameraData['key_name'][$_index]] = $_value;
				}
			}
		}

		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Camera');

		if ($cameraId)
		{
			$dw->setExistingData($cameraId);
		}

		$dw->set('camera_id', $newCameraId);
		$dw->bulkSet($dwInput);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('gallery/cameras') . $this->getLastHash($dw->get('camera_id'))
		);
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect|XenForo_ControllerResponse_View
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			$cameraId = $this->_input->filterSingle('camera_id', XenForo_Input::STRING);
			
			$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Camera');
			$dw->setExistingData($cameraId);
			$dw->delete();
			
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS, 
				XenForo_Link::buildAdminLink('gallery/cameras')
			);
		}
		else
		{
			$viewParams = array(
				'camera' => $this->_getCameraOrError()
			);
			
			return $this->responseView(
					'sonnb_XenGallery_ViewAdmin_Camera_Delete',
					'sonnb_xengallery_camera_delete',
					$viewParams
				);
		}
	}

	/**
	 * @param null $id
	 * @return array|mixed
	 * @throws XenForo_ControllerResponse_Exception
	 */
	protected function _getCameraOrError($id = null)
	{
		if ($id === null)
		{
			$id = $this->_input->filterSingle('camera_id', XenForo_Input::STRING);
		}

		$info = $this->_getCameraModel()->getDataCameraById($id);

		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('sonnb_xengallery_requested_camera_not_found'), 404));
		}

		return $info;
	}
}
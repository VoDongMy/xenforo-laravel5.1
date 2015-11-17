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
class sonnb_XenGallery_DataWriter_Camera extends sonnb_XenGallery_DataWriter_Abstract
{
	protected function _getFields()
	{
		return array(
			'sonnb_xengallery_camera' => array(
				'unique_id' => array(
					'type' => self::TYPE_UINT,
					'autoIncrement' => true
				),
				'camera_id' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 50,
					'verification' => array('$this', '_verifyCameraId'),
				),
				'camera_name' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 100,
					'default' => '',
				),
				'camera_vendor' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 100,
					'default' => '',
				),
				'camera_thumbnail' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 100,
					'default' => '',
				),
				'camera_data' => array(
					'type' => self::TYPE_SERIALIZED,
					'default' => 'a:0:{}',
				),
				'updated_date' => array(
					'type' => self::TYPE_UINT,
					'default' => XenForo_Application::$time,
				),
			)
		);
	}

	protected function _preSave()
	{
		if ($this->isUpdate())
		{
			$this->set('updated_date', XenForo_Application::$time);
		}
	}

	protected function _verifyCameraId(&$cameraId)
	{
		if (!empty($cameraId))
		{
			$camera = $this->_getCameraModel()->getDataCameraById($cameraId);

			if ((!$this->get('camera_id') && $camera) ||
					($this->get('camera_id') && $camera['unique_id'] != $this->get('unique_id')))
			{
				$this->error('sonnb_xengallery_this_camera_id_already_exist', 'camera_id');
				return false;
			}
		}

		return true;
	}
	
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'camera_id'))
		{
			return false;
		}
	
		return array('sonnb_xengallery_camera' => $this->_getCameraModel()->getDataCameraById($id));
	}
	
	protected function _getUpdateCondition($tableName)
	{
		return 'camera_id = ' . $this->_db->quote($this->getExisting('camera_id'));
	}
	
	/**
	 * @return sonnb_XenGallery_Model_Camera
	 */
	protected function _getCameraModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Camera');
	}
}
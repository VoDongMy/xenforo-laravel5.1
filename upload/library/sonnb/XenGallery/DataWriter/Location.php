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
class sonnb_XenGallery_DataWriter_Location extends sonnb_XenGallery_DataWriter_Abstract
{
	protected function _getFields()
	{
		return array(
			'sonnb_xengallery_location' => array(
				'location_id' => array(
					'type' => self::TYPE_UINT,
					'autoIncrement' => true
				),
				'content_type' => array(
					'type' => self::TYPE_STRING,
					'default' => 'album',
					'allowedValues' => array('album', 'photo', 'video', 'audio'),
				),
				'content_id' => array(
					'type' => self::TYPE_UINT,
					'default' => 0
				),
				'location_name' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 255,
					'default' => '',
				),
				'location_url' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 255,
					'default' => '',
				),
			)
		);
	}
	
	protected function _preSave()
	{
		
	}
	
	protected function _postSave()
	{
		
	}
	
	protected function _preDelete()
	{
		
	}
	
	protected function _postDelete()
	{
		
	}
	
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'location_id'))
		{
			return false;
		}
	
		return array('sonnb_xengallery_location' => $this->_getLocationModel()->getLocationById($id));
	}
	
	protected function _getUpdateCondition($tableName)
	{
		return 'location_id = ' . $this->_db->quote($this->getExisting('location_id'));
	}
}
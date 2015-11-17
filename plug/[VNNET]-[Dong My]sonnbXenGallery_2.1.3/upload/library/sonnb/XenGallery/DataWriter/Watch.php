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
class sonnb_XenGallery_DataWriter_Watch extends sonnb_XenGallery_DataWriter_Abstract
{
	protected function _getFields()
	{
		return array(
			'sonnb_xengallery_watch' => array(
				'watch_id' => array(
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
				'user_id' => array(
					'type' => self::TYPE_UINT,
				),
				'username' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 50,
					'default' => ''
				),
				'watch_date' => array(
					'type' => self::TYPE_UINT,
					'default' => XenForo_Application::$time,
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'watch_id'))
		{
			return false;
		}
	
		return array('sonnb_xengallery_watch' => $this->_getWatchModel()->getWatchById($id));
	}
	
	protected function _getUpdateCondition($tableName)
	{
		return 'watch_id = ' . $this->_db->quote($this->getExisting('watch_id'));
	}
}
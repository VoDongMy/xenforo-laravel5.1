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
class sonnb_XenGallery_DataWriter_History extends sonnb_XenGallery_DataWriter_Abstract
{
	protected function _getFields()
	{
		return array(
			'sonnb_xengallery_history' => array(
				'history_id' => array(
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
				'history_type' => array(
					'type' => self::TYPE_STRING,
					'default' => 'insert',
					'allowedValues' => array('insert', 'update', 'delete'),
				),
				'history_sub_type' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'history_old_data' => array(
					'type' => self::TYPE_SERIALIZED,
					'default' => 'a:0:{}',
				),
				'history_new_data' => array(
					'type' => self::TYPE_SERIALIZED,
					'default' => 'a:0:{}',
				),
				'history_date' => array(
					'type' => self::TYPE_UINT,
					'default' => XenForo_Application::$time,
				)
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'history_id'))
		{
			return false;
		}
	
		return array('sonnb_xengallery_history' => $this->_getHistoryModel()->getHistoryById($id));
	}
	
	protected function _getUpdateCondition($tableName)
	{
		return 'history_id = ' . $this->_db->quote($this->getExisting('history_id'));
	}
}
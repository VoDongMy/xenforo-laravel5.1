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
class sonnb_XenGallery_DataWriter_Stream extends sonnb_XenGallery_DataWriter_Abstract
{
	protected function _getFields()
	{
		return array(
			'sonnb_xengallery_stream' => array(
				'stream_id' => array(
					'type' => self::TYPE_UINT,
					'autoIncrement' => true
				),
				'stream_name' => array(
					'type' => self::TYPE_STRING,
					'default' => '',
					'required' => true
				),
				'content_type' => array(
					'type' => self::TYPE_STRING,
					'default' => 'photo',
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
				'stream_date' => array(
					'type' => self::TYPE_UINT,
					'default' => XenForo_Application::$time,
				),
			)
		);
	}

	protected function _postDelete()
	{
		//TODO: Remove information from photos/albums contains this stream
		//This is currently not needed as we do not delete stream directly.
	}
	
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'stream_id'))
		{
			return false;
		}
	
		return array('sonnb_xengallery_stream' => $this->_getStreamModel()->getStreamById($id));
	}
	
	protected function _getUpdateCondition($tableName)
	{
		return 'stream_id = ' . $this->_db->quote($this->getExisting('stream_id'));
	}
}
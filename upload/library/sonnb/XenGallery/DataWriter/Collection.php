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
class sonnb_XenGallery_DataWriter_Collection extends sonnb_XenGallery_DataWriter_Abstract
{
	protected function _getFields()
	{
		return array(
			'sonnb_xengallery_collection' => array(
				'collection_id' => array(
					'type' => self::TYPE_UINT,
					'autoIncrement' => true
				),
				'title' => array(
					'type' => self::TYPE_STRING,
					'required' => true,
					'default' => '',
					'maxLength' => 255
				),
				'description' => array(
					'type' => self::TYPE_STRING,
					'default' => ''
				),
				'thumbnail' => array(
					'type' => self::TYPE_STRING,
					'default' => '',
					'maxLength' => 255
				),
				'item_count' => array(
					'type' => self::TYPE_UINT,
					'default' => 0
				),
                'last_content_date' => array(
                    'type' => self::TYPE_UINT,
                    'default' => 0
                )
			)
		);
	}

	protected function _postSave()
	{
		XenForo_Application::setSimpleCacheData(sonnb_XenGallery_Model_Collection::$allCacheKey, false);
	}
	
	protected function _postDelete()
	{
		$this->_getCollectionModel()->deleteCollection($this->get('collection_id'));
		XenForo_Application::setSimpleCacheData(sonnb_XenGallery_Model_Collection::$allCacheKey, false);
	}
	
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'collection_id'))
		{
			return false;
		}
	
		return array('sonnb_xengallery_collection' => $this->_getCollectionModel()->getCollectionById($id));
	}
	
	protected function _getUpdateCondition($tableName)
	{
		return 'collection_id = ' . $this->_db->quote($this->getExisting('collection_id'));
	}
	
	/**
	 * @return sonnb_XenGallery_Model_Collection
	 */
	protected function _getCollectionModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Collection');
	}
}
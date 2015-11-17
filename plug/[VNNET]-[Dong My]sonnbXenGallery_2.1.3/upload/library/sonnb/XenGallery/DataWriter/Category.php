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
class sonnb_XenGallery_DataWriter_Category extends sonnb_XenGallery_DataWriter_Abstract
{
	protected function _getFields()
	{
		return array(
			'sonnb_xengallery_category' => array(
				'category_id' => array(
					'type' => self::TYPE_UINT,
					'autoIncrement' => true
				),
				'title' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 255,
					'required' => true,
				),
				'description' => array(
					'type' => self::TYPE_STRING,
					'default' => ''
				),
				'parent_category_id' => array(
					'type' => self::TYPE_UINT,
					'default' => 0
				),
				'category_breadcrumb' => array(
					'type' => self::TYPE_SERIALIZED,
					'default' => 'a:0:{}'
				),
				'album_count' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'display_order' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'lft' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'rgt' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'depth' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'category_privacy' => array(
					'type' => self::TYPE_SERIALIZED,
					'verification' => array('$this', '_verifyPrivacy')
				)
			),
		);
	}

	protected function _verifyPrivacy(&$privacy)
	{
		if (!is_array($privacy))
		{
			$privacy = @unserialize($privacy);
		}

		if ($privacy === null)
		{
			$privacy = array(
				'view' => array(-1),
				'post' => array(-1)
			);

			return true;
		}

		return XenForo_DataWriter_Helper_Denormalization::verifySerialized($privacy, $this, 'category_privacy');
	}
	
	protected function _preSave()
	{
		if ($this->get('title') && $this->isChanged('title'))
		{
			$conflict = $this->_getCategoryModel()->getCategoryByName($this->get('title'));
			if ($conflict && $conflict['category_id'] != $this->get('category_id'))
			{
				$this->error(new XenForo_Phrase('sonnb_xengallery_category_name_must_be_unique'), 'title');
			}
		}
	}
	
	protected function _postSave()
	{
		XenForo_Application::setSimpleCacheData(sonnb_XenGallery_Model_Category::$allCacheKey, false);

		if ($this->isInsert() || $this->isChanged('parent_category_id')
				|| $this->isChanged('display_order'))
		{
			$this->_getCategoryModel()->rebuildCategoryStructure();
		}
	}
	
	protected function _postDelete()
	{
		$this->_getCategoryModel()->deleteCategory($this->get('category_id'));

		XenForo_Application::setSimpleCacheData(sonnb_XenGallery_Model_Category::$allCacheKey, false);

		$this->_getCategoryModel()->rebuildCategoryStructure();
	}
	
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'category_id'))
		{
			return false;
		}
	
		return array('sonnb_xengallery_category' => $this->_getCategoryModel()->getCategoryById($id));
	}
	
	protected function _getUpdateCondition($tableName)
	{
		return 'category_id = ' . $this->_db->quote($this->getExisting('category_id'));
	}
}
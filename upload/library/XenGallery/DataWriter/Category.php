<?php

/**
* Data writer for categories
*/
class XenGallery_DataWriter_Category extends XenForo_DataWriter
{
	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'xengallery_requested_category_not_found';

	const DATA_FIELD_IDS = 'fieldIds';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xengallery_category' => array(
				'category_id'		   => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'category_title'       => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 100,
					'requiredError' => 'please_enter_valid_title'
				),
				'category_description' => array('type' => self::TYPE_STRING, 'default' => 0),
				'upload_user_groups'   => array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}'),
				'view_user_groups'	   => array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}'),
				'allowed_types'		   => array('type' => self::TYPE_SERIALIZED, 'default' => 'a:1:{i:0;s:3:"all";}'),
				'parent_category_id'   => array('type' => self::TYPE_UINT, 'default' => 0),
				'display_order'        => array('type' => self::TYPE_UINT, 'default' => 1),
				'category_breadcrumb'  => array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}'),
				'depth'                => array('type' => self::TYPE_UINT, 'default' => 0),
				'category_media_count' => array('type' => self::TYPE_UINT, 'default' => 0),
				'field_cache'		   => array('type' => self::TYPE_SERIALIZED, 'default' => ''),
				'min_tags' 			   => array('type' => self::TYPE_UINT_FORCED, 'default' => 0, 'max' => 100),
			)
		);
	}

	/**
	* Gets the actual existing data out of data that was passed in. See parent for explanation.
	*
	* @param mixed
	*
	* @return array|false
	*/
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xengallery_category' => $this->_getCategoryModel()->getCategoryById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'category_id = ' . $this->_db->quote($this->getExisting('category_id'));
	}
	
	protected function _postSave()
	{
		if ($this->isInsert()
			|| $this->isChanged('display_order')
			|| $this->isChanged('parent_category_id')
			|| $this->isChanged('category_title')
		)
		{
			$this->_getCategoryModel()->rebuildCategoryStructure();
		}

		$newFieldIds = $this->getExtraData(self::DATA_FIELD_IDS);
		if (is_array($newFieldIds))
		{
			$this->_updateFieldAssociations($newFieldIds);
			$this->_getFieldModel()->rebuildFieldCategoryAssociationCache(array($this->get('category_id')));
		}
	}

	protected function _updateFieldAssociations(array $fieldIds)
	{
		$fieldIds = array_unique($fieldIds);

		$db = $this->_db;
		$categoryId = $this->get('category_id');

		$db->delete('xengallery_field_category', 'category_id = ' . $db->quote($categoryId));

		foreach ($fieldIds AS $fieldId)
		{
			$db->insert('xengallery_field_category', array(
				'field_id' => $fieldId,
				'category_id' => $categoryId
			));
		}

		return $fieldIds;
	}
	
	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$db = $this->_db;
		
		$db->update('xengallery_category',
			array('parent_category_id' => $this->get('parent_category_id')),
			'parent_category_id = ' . $this->_db->quote($this->get('category_id'))
		);
		
		$media = $db->fetchAll('
			SELECT *
			FROM xengallery_media
			WHERE category_id = ?
		', $this->get('category_id'));
		
		$mediaIds = array();
		foreach ($media AS $_media)
		{
			$mediaIds[] = $_media['media_id'];

			$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
			$mediaWriter->setExistingData($_media);
			$mediaWriter->delete();
		}

		$mediaIdsQuoted = $db->quote($mediaIds);
		if ($mediaIdsQuoted)
		{
			$indexer = new XenForo_Search_Indexer();
			$dataHandler = XenForo_Search_DataHandler_Abstract::create('XenGallery_Search_DataHandler_Media');
			
			$dataHandler->deleteFromIndex($indexer, $media);
			
			$db->update('xf_attachment', array('unassociated' => 1), 'content_type = \'xengallery_media\' AND content_id IN (' . $mediaIdsQuoted . ')');
		}

		$this->_getCategoryModel()->rebuildCategoryStructure();
	}

	public function rebuildCategoryStructure()
	{
		$this->_getCategoryModel()->rebuildCategoryStructure();
	}

	/**
	 * @return XenGallery_Model_Category
	 */
	protected function _getCategoryModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Category');
	}

	/**
	 * @return XenGallery_Model_Field
	 */
	protected function _getFieldModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Field');
	}
}
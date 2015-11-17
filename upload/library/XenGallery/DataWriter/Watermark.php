<?php

class XenGallery_DataWriter_Watermark extends XenForo_DataWriter
{
	/**
	 * Gets the fields that are defined for the table. See parent for explanation.
	 *
	 * @return array
	 */
	protected function _getFields()
	{
		return array(
			'xengallery_watermark' => array(
				'watermark_id'		=> array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'watermark_user_id'	=> array('type' => self::TYPE_UINT, 'required' => true),
				'watermark_date'	=> array('type' => self::TYPE_UINT, 'required' => true, 'default' => XenForo_Application::$time),
				'is_site'			=> array('type' => self::TYPE_UINT, 'default' => 0)
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'watermark_id'))
		{
			return false;
		}

		return array('xengallery_watermark' => $this->_getWatermarkModel()->getWatermarkById($id));
	}

	/**
	 * Gets SQL condition to update the existing record.
	 *
	 * @return string
	 */
	protected function _getUpdateCondition($tableName)
	{
		return 'watermark_id = ' . $this->_db->quote($this->getExisting('watermark_id'));
	}

	protected function _postDelete()
	{
		$watermarkPath = $this->_getWatermarkModel()->getWatermarkFilePath($this->get('watermark_id'));
		@unlink($watermarkPath);
	}

	/**
	 * @return XenGallery_Model_Watermark
	 */
	protected function _getWatermarkModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Watermark');
	}

	/**
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Media');
	}
}
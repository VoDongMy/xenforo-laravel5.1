<?php

class XenGallery_DataWriter_Exif extends XenForo_DataWriter
{
	/**
	 * Gets the fields that are defined for the table. See parent for explanation.
	 *
	 * @return array
	 */
	protected function _getFields()
	{
		return array(
			'xengallery_exif' => array(
				'media_id' => array('type' => self::TYPE_UINT),
				'exif_name' => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 200),
				'exif_value' => array('type' => self::TYPE_STRING, 'default' => 'n/a', 'maxLength' => 200),
				'exif_format' => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50),
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
		if (!is_array($data))
		{
			return false;
		}
		else if (isset($data['media_id'], $data['exif_name']))
		{
			$mediaId = $data['media_id'];
			$exifName = $data['exif_name'];
		}
		else
		{
			return false;
		}

		return array('xengallery_exif' => $this->_getExifModel()->getExifByMediaIdAndName($mediaId, $exifName));
	}

	/**
	 * Gets SQL condition to update the existing record.
	 *
	 * @return string
	 */
	protected function _getUpdateCondition($tableName)
	{
		return 'media_id = ' . $this->_db->quote($this->getExisting('media_id')) .
		' AND exif_name = ' . $this->_db->quote($this->getExisting('exif_name'));
	}

	/**
	 * @return XenGallery_Model_Exif
	 */
	protected function _getExifModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Exif');
	}

	/**
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Media');
	}
}
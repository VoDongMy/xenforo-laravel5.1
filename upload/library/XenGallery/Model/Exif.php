<?php

class XenGallery_Model_Exif extends XenForo_Model
{
	public function getExifByMediaIdAndName($mediaId, $propertyNames)
	{
		return array();
	}

	public function getExifByMediaId($mediaId)
	{
		return $this->_getDb()->fetchAll('
			SELECT *
			FROM xengallery_exif
			WHERE media_id = ?
		', $mediaId);
	}

	public function deleteExifByMediaId($mediaId)
	{
		$exif = $this->getExifByMediaId($mediaId);

		foreach ($exif AS $_exif)
		{
			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Exif', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($_exif);
			$dw->delete();
		}

		return true;
	}
}
<?php

class XenGallery_DataWriter_AttachmentData extends XFCP_XenGallery_DataWriter_AttachmentData
{
	const DATA_XMG_DATA = 'XMG';

	const DATA_XMG_FILE_IS_VIDEO = 'FILE_IS_VIDEO';

	/**
	 * Writes out the specified attachment file. The temporary file
	 * will be moved to the new position!
	 *
	 * @param string $tempFile Temporary (source file)
	 * @param array $data Information about this attachment data (for dest path)
	 * @param boolean $thumbnail True if writing out thumbnail.
	 *
	 * @return boolean
	 */
	protected function _writeAttachmentFile($tempFile, array $data, $thumbnail = false)
	{
		if ($this->getExtraData(self::DATA_XMG_DATA))
		{
			if ($tempFile && is_readable($tempFile))
			{
				/** @var $mediaModel XenGallery_Model_Media */
				$mediaModel = $this->getModelFromCache('XenGallery_Model_Media');

				if ($thumbnail)
				{
					$filePath = $mediaModel->getMediaThumbnailFilePath($data);
				}
				else
				{
					$filePath = $this->_getAttachmentModel()->getAttachmentDataFilePath($data);
				}

				$directory = dirname($filePath);

				if (XenForo_Helper_File::createDirectory($directory, true))
				{
					$success = $this->_copyFile($tempFile, $filePath);

					if ($success)
					{
						return parent::_writeAttachmentFile($tempFile, $data, $thumbnail);
					}

					return false;
				}
			}
		}

		return parent::_writeAttachmentFile($tempFile, $data, $thumbnail);
	}

	/**
	 * Copies the specified file.
	 *
	 * @param string $source
	 * @param string $destination
	 *
	 * @return boolean
	 */
	protected function _copyFile($source, $destination)
	{
		$success = copy($source, $destination);
		if ($success)
		{
			XenForo_Helper_File::makeWritableByFtpUser($destination);
		}

		return $success;
	}
}
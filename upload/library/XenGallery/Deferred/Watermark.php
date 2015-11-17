<?php

class XenGallery_Deferred_Watermark extends XenForo_Deferred_Abstract
{
	protected $_db = null;

	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 10
		), $data);
		$data['batch'] = max(1, $data['batch']);

		/* @var $mediaModel XenGallery_Model_Media */
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		/* @var $attachmentModel XenForo_Model_Attachment */
		$attachmentModel = XenForo_Model::create('XenForo_Model_Attachment');

		$watermarkModel = $this->_getWatermarkModel();

		if (!$this->_db)
		{
			$this->_db = XenForo_Application::getDb();
		}

		$mediaIds = $mediaModel->getMediaIdsInRange($data['position'], $data['batch']);
		if (sizeof($mediaIds) == 0)
		{
			return true;
		}

		$options = XenForo_Application::getOptions();

		$fetchOptions = array(
			'join' => XenGallery_Model_Media::FETCH_ATTACHMENT
		);

		$media = $mediaModel->getMediaByIds($mediaIds, $fetchOptions);
		$media = $mediaModel->prepareMediaItems($media);

		foreach ($media AS $item)
		{
			$data['position'] = $item['media_id'];

			if (empty($item['watermark_id']))
			{
				continue;
			}

			try
			{
				$attachment = $attachmentModel->getAttachmentById($item['attachment_id']);

				$originalPath = $mediaModel->getOriginalDataFilePath($attachment, true);
				$filePath = $attachmentModel->getAttachmentDataFilePath($attachment);
				$watermarkPath = $watermarkModel->getWatermarkFilePath($item['watermark_id']);

				if (XenForo_Helper_File::createDirectory(dirname($originalPath), true))
				{
					$image = new XenGallery_Helper_Image($originalPath);
					$watermark = new XenGallery_Helper_Image($watermarkPath);

					$watermark->resize(
						($image->getWidth() / 100) * $options->xengalleryWatermarkDimensions['width'],
						($image->getHeight() / 100) * $options->xengalleryWatermarkDimensions['height'], 'fit'
					);

					$image->addWatermark($watermark->tmpFile);
					$image->writeWatermark(
						$options->xengalleryWatermarkOpacity,
						$options->xengalleryWatermarkMargin['h'],
						$options->xengalleryWatermarkMargin['v'],
						$options->xengalleryWatermarkHPos,
						$options->xengalleryWatermarkVPos
					);

					$image->saveToPath($filePath);

					unset($watermark);
					unset($image);

					clearstatcache();
					$this->_db->update('xf_attachment_data', array('file_size' => filesize($filePath)), 'data_id = ' . $attachment['data_id']);

					$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
					$mediaWriter->setExistingData($item['media_id']);

					$mediaWriter->set('last_edit_date', XenForo_Application::$time);

					$mediaWriter->save();
				}
			}
			catch (Exception $e) {}
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('xengallery_rebuild_watermarks');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}

	/**
	 * @return XenGallery_Model_Watermark
	 */
	protected function _getWatermarkModel()
	{
		return XenForo_Model::create('XenGallery_Model_Watermark');
	}
}
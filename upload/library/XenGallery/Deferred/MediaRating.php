<?php

class XenGallery_Deferred_MediaRating extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 50
		), $data);
		$data['batch'] = max(1, $data['batch']);

		/* @var $mediaModel XenGallery_Model_Media */
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		$mediaIds = $mediaModel->getMediaIdsInRange($data['position'], $data['batch'], 'all');
		if (sizeof($mediaIds) == 0)
		{
			return true;
		}

		foreach ($mediaIds AS $mediaId)
		{
			/** @var XenGallery_DataWriter_Media $mediaWriter */
			$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media', XenForo_DataWriter::ERROR_SILENT);
			$mediaWriter->setExistingData($mediaId);

			$mediaWriter->updateRating();
			$mediaWriter->save();

			$data['position'] = $mediaId;
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('xengallery_rebuilding_media_ratings');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}
<?php

class XenGallery_Deferred_AlbumRating extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 50
		), $data);
		$data['batch'] = max(1, $data['batch']);

		/* @var $albumModel XenGallery_Model_Album */
		$albumModel = XenForo_Model::create('XenGallery_Model_Album');

		$albumIds = $albumModel->getAlbumIdsInRange($data['position'], $data['batch']);
		if (sizeof($albumIds) == 0)
		{
			return true;
		}

		foreach ($albumIds AS $albumId)
		{
			/** @var XenGallery_DataWriter_Album $albumWriter */
			$albumWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Album', XenForo_DataWriter::ERROR_SILENT);
			$albumWriter->setExistingData($albumId);

			$albumWriter->updateRating();
			$albumWriter->save();

			$data['position'] = $albumId;
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('xengallery_rebuilding_album_ratings');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}
<?php

class XenGallery_Deferred_AlbumMediaCount extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 5
		), $data);
		$data['batch'] = max(1, $data['batch']);

		/* @var $albumModel XenGallery_Model_Album */
		$albumModel = XenForo_Model::create('XenGallery_Model_Album');

		/* @var $mediaModel XenGallery_Model_Media */
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		$albumIds = $albumModel->getAlbumIdsInRange($data['position'], $data['batch']);
		if (sizeof($albumIds) == 0)
		{
			return true;
		}


		foreach ($albumIds AS $albumId)
		{
			$count = $mediaModel->countMedia(array('album_id' => $albumId));

			$albumWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
			$albumWriter->setExistingData($albumId);

			$albumWriter->set('album_media_count', $count);

			$albumWriter->save();

			$data['position'] = $albumId;
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('xengallery_rebuilding_album_media_counts');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}
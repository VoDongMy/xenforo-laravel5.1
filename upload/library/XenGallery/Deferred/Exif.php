<?php

class XenGallery_Deferred_Exif extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 10
		), $data);
		$data['batch'] = max(1, $data['batch']);

		/* @var $mediaModel XenGallery_Model_Media */
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		$mediaIds = $mediaModel->getMediaIdsInRange($data['position'], $data['batch'], 'all');
		if (sizeof($mediaIds) == 0)
		{
			return true;
		}
		$mediaModel->deleteExifDataByMediaIds($mediaIds);

		$fetchOptions = array(
			'join' => XenGallery_Model_Media::FETCH_ATTACHMENT
		);

		$media = $mediaModel->getMediaByIds($mediaIds, $fetchOptions);
		foreach ($media AS $item)
		{
			$data['position'] = $item['media_id'];

			$mediaModel->rebuildExifDataForMedia($item);
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('xengallery_rebuild_exif_data');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}
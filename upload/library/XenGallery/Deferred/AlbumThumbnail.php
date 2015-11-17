<?php

class XenGallery_Deferred_AlbumThumbnail extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 10
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
			$data['position'] = $albumId;

			$writer = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
			$writer->setExistingData($albumId);

			if (!$writer->get('manual_media_cache') && !$writer->get('album_thumbnail_date'))
			{
				$media = $mediaModel->getMediaForAlbumCache($albumId);

				$writer->bulkSet(array(
					'media_cache' => serialize($media)
				));

				$writer->save();
			}
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('xengallery_rebuild_album_thumbnails');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}
}
<?php

class sonnb_XenGallery_Deferred_Album extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'batch' => 10,
			'position' => 0,
			'positionRebuild' => false
		), $data);

		/* @var $albumModel sonnb_XenGallery_Model_Album */
		$albumModel = XenForo_Model::create('sonnb_XenGallery_Model_Album');

		$albumIds = $albumModel->getAlbumIdsInRange($data['position'], $data['batch']);
		if (count($albumIds) < 1)
		{
			return false;
		}

		foreach ($albumIds AS $albumId)
		{
			$data['position'] = $albumId;

			/* @var $dw sonnb_XenGallery_DataWriter_Album */
			$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album', XenForo_DataWriter::ERROR_SILENT);
			if ($dw->setExistingData($albumId))
			{
				$dw->setImportMode(true);
				$dw->rebuildCounters($data);
				$dw->save();
			}
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('sonnb_xengallery_album');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}
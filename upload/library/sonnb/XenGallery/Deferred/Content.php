<?php

class sonnb_XenGallery_Deferred_Content extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'batch' => 10,
			'position' => 0,
			'positionRebuild' => false
		), $data);

		/* @var sonnb_XenGallery_Model_Content $contentModel */
		$contentModel = XenForo_Model::create('sonnb_XenGallery_Model_Content');

		$contents = $contentModel->getContentTypeIdsInRange($data['position'], $data['batch']);
		if (count($contents) < 1)
		{
			return false;
		}

        /**
         * TODO - FIX ME: This will remove current information???
         *
        if ($data['position'] === 0 && !empty($options['exif']))
		{
			XenForo_Application::getDb()->delete('sonnb_xengallery_photo_camera');
		}
         */

		foreach ($contents AS $contentId => $contentType)
		{
			$data['position'] = $contentId;

			$dwClass = null;
			if ($contentType === sonnb_XenGallery_Model_Photo::$contentType)
			{
				$dwClass = 'sonnb_XenGallery_DataWriter_Photo';
			}
			elseif ($contentType === sonnb_XenGallery_Model_Video::$contentType)
			{
				$dwClass = 'sonnb_XenGallery_DataWriter_Video';
			}

			if ($dwClass)
			{
				/* @var $dw sonnb_XenGallery_DataWriter_Content */
				$dw = XenForo_DataWriter::create($dwClass, XenForo_DataWriter::ERROR_SILENT);
				if ($dw->setExistingData($contentId))
				{
					$dw->setImportMode(true);
					$dw->rebuildCounters($data);
					$dw->save();
				}
			}
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('sonnb_xengallery_rebuild_contents');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}
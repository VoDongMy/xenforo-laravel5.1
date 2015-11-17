<?php

/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
class sonnb_XenGallery_CacheRebuilder_Content extends XenForo_CacheRebuilder_Abstract
{
	/**
	 * @return string|XenForo_Phrase
	 */
	public function getRebuildMessage()
	{
		return new XenForo_Phrase('sonnb_xengallery_rebuild_contents');
	}

	/**
	 * @return bool
	 */
	public function showExitLink()
	{
		return true;
	}

	/**
	 * @param int $position
	 * @param array $options
	 * @param string $detailedMessage
	 * @return bool|int|string|true
	 */
	public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
	{
		$options['batch'] = max(1, isset($options['batch']) ? $options['batch'] : 10);

		/* @var sonnb_XenGallery_Model_Content $contentModel */
		$contentModel = XenForo_Model::create('sonnb_XenGallery_Model_Content');

		$contents = $contentModel->getContentTypeIdsInRange($position, $options['batch']);
		if (count($contents) < 1)
		{
			return true;
		}

		XenForo_Db::beginTransaction();

        /**
         * TODO - FIX ME: This will remove current information???
         *
        if ($position === 0 && !empty($options['exif']))
		{
			XenForo_Application::getDb()->delete('sonnb_xengallery_photo_camera');
		}
        */

		foreach ($contents AS $contentId => $contentType)
		{
			$position = $contentId;

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
					$dw->rebuildCounters($options);
					$dw->save();
				}
			}
		}

		XenForo_Db::commit();

		$detailedMessage = XenForo_Locale::numberFormat($position);

		return $position;
	}
}
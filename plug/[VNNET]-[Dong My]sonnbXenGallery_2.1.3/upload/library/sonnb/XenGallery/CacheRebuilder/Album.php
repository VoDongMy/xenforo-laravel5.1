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
class sonnb_XenGallery_CacheRebuilder_Album extends XenForo_CacheRebuilder_Abstract
{
	/**
	 * @return string|XenForo_Phrase
	 */
	public function getRebuildMessage()
	{
		return new XenForo_Phrase('sonnb_xengallery_stats_albums');
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
	 * @return bool|int|true
	 */
	public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
	{
		$options['batch'] = max(1, isset($options['batch']) ? $options['batch'] : 10);

		/* @var $albumModel sonnb_XenGallery_Model_Album */
		$albumModel = XenForo_Model::create('sonnb_XenGallery_Model_Album');

		$albumIds = $albumModel->getAlbumIdsInRange($position, $options['batch']);
		if (count($albumIds) < 1)
		{
			return true;
		}

		XenForo_Db::beginTransaction();

		foreach ($albumIds AS $albumId)
		{
			$position = $albumId;

			/* @var $dw sonnb_XenGallery_DataWriter_Album */
			$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album', XenForo_DataWriter::ERROR_SILENT);
			if ($dw->setExistingData($albumId))
			{
				$dw->setImportMode(true);
				$dw->rebuildCounters($options);
				$dw->save();
			}
		}

		XenForo_Db::commit();

		$detailedMessage = XenForo_Locale::numberFormat($position);

		return $position;
	}
}
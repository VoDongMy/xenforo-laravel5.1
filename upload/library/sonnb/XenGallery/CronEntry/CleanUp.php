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
class sonnb_XenGallery_CronEntry_CleanUp
{

	public static function runHourlyCleanUp()
	{
		// delete unassociated Content Data
		$unassociatedCutOff = XenForo_Application::$time - 86400;

		/* @var $contentDataModel sonnb_XenGallery_Model_ContentData */
		$contentDataModel = XenForo_Model::create('sonnb_XenGallery_Model_ContentData');

		$contentDataModel->deleteUnusedContentData($unassociatedCutOff);
	}
}
<?php

/**
 * Cron entry for updating XenForo Media Gallery view counts.
 */
class XenGallery_CronEntry_Views
{
	/**
	 * Updates view counters for XenForo Media Gallery.
	 */
	public static function runViewUpdate()
	{
		XenForo_Model::create('XenGallery_Model_Media')->updateMediaViews();
		XenForo_Model::create('XenGallery_Model_Album')->updateAlbumViews();
	}
}
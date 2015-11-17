<?php

/**
 * Cron entry for updating XenForo Media Gallery statistics.
 */
class XenGallery_CronEntry_Statistics
{
	/**
	 * Updates statistics for XenForo Media Gallery.
	 */
	public static function runStatisticsUpdate()
	{
		/** @var  $categoryModel XenGallery_Model_Category */
		$categoryModel = XenForo_Model::create('XenGallery_Model_Category');

		/** @var  $albumModel XenGallery_Model_Album */
		$albumModel = XenForo_Model::create('XenGallery_Model_Album');

		/** @var  $mediaModel XenGallery_Model_Media */
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		/** @var  $commentModel XenGallery_Model_Comment */
		$commentModel = XenForo_Model::create('XenGallery_Model_Comment');

		$statisticsCache = array(
			'category_count' => $categoryModel->getCategoryCount(),
			'album_count' => $albumModel->countAlbums(),
			'upload_count' => $mediaModel->countMedia(
				array('media_type' => array('image_upload', 'video_upload')),
				array('join' => XenGallery_Model_Media::FETCH_ALBUM)
			),
			'embed_count' => $mediaModel->countMedia(
				array('media_type' => 'video_embed'),
				array('join' => XenGallery_Model_Media::FETCH_ALBUM)
			),
			'comment_count' => $commentModel->countComments(),
			'disk_usage' => $mediaModel->calculateMediaDiskUsage()
		);

		XenForo_Application::setSimpleCacheData('xengalleryStatisticsCache', $statisticsCache);

		return true;
	}
}
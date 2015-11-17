<?php

class XenGallery_CronEntry_RandomMediaCache
{
	public static function buildCache()
	{
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');
		XenForo_Application::setSimpleCacheData('xengalleryRandomMediaCache', $mediaModel->generateRandomMediaCache());
	}
}
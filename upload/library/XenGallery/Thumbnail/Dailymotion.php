<?php

class XenGallery_Thumbnail_Dailymotion extends XenGallery_Thumbnail_Abstract
{
	public function getThumbnailUrl($videoId)
	{
		$this->_videoId = $videoId;
		$this->_mediaSiteId = 'dailymotion';

		$urlVideoId = rawurlencode($videoId);

		$thumbnailUrl = "https://api.dailymotion.com/video/$urlVideoId?fields=thumbnail_url";

		try
		{
			$client = XenForo_Helper_Http::getClient($thumbnailUrl);

			$response = $client->request('GET');

			$body = $response->getBody();
			if (preg_match('#^[{\[]#', $body))
			{
				$videoData = json_decode($body, true);

				if (!empty($videoData['thumbnail_url']))
				{
					$this->_thumbnailUrl = $videoData['thumbnail_url'];
				}
			}
		}
		catch (Zend_Http_Client_Exception $e) {}

		return $this->verifyThumbnailUrl($this->_thumbnailUrl);
	}
}
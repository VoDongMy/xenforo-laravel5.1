<?php

class XenGallery_Thumbnail_Vimeo extends XenGallery_Thumbnail_Abstract
{
	public function getThumbnailUrl($videoId)
	{
		$this->_videoId = $videoId;
		$this->_mediaSiteId = 'vimeo';

		$urlVideoId = rawurlencode($videoId);

		$thumbnailData = "http://vimeo.com/api/v2/video/$urlVideoId.json";

		try
		{
			$client = XenForo_Helper_Http::getClient($thumbnailData);

			$response = $client->request('GET');

			$body = $response->getBody();
			if (preg_match('#^[{\[]#', $body))
			{
				$videoData = json_decode($body, true);

				if (!empty($videoData[0]['thumbnail_large']))
				{
					$this->_thumbnailUrl = $videoData[0]['thumbnail_large'];
				}
			}
		}
		catch (Zend_Http_Client_Exception $e) {}

		return $this->verifyThumbnailUrl($this->_thumbnailUrl);
	}
}
<?php

class XenGallery_Thumbnail_YouTube extends XenGallery_Thumbnail_Abstract
{
	public function getThumbnailUrl($videoId)
	{
		$this->_videoId = $videoId;
		$this->_mediaSiteId = 'youtube';

		$urlVideoId = rawurlencode($videoId);

		$preferredThumbnail = "https://i.ytimg.com/vi/{$urlVideoId}/maxresdefault.jpg";
		$fallbackThumbnail = "https://i.ytimg.com/vi/{$urlVideoId}/hqdefault.jpg";

		try
		{

			$client = XenForo_Helper_Http::getClient($preferredThumbnail);

			$response = $client->request('GET');

			if ($response->isSuccessful())
			{
				$this->_thumbnailUrl = $preferredThumbnail;
			}
			else
			{
				$this->_thumbnailUrl = $fallbackThumbnail;
			}
		}
		catch (Zend_Http_Client_Exception $e) {}

		return $this->verifyThumbnailUrl($this->_thumbnailUrl);
	}
}
<?php

class XenGallery_Thumbnail_Metacafe extends XenGallery_Thumbnail_Abstract
{
	public function getThumbnailUrl($videoId)
	{
		$this->_videoId = $videoId;
		$this->_mediaSiteId = 'metacafe';

		$urlVideoId = rawurlencode($videoId);

		$videoUrl = "http://www.metacafe.com/watch/$urlVideoId";

		try
		{
			$client = XenForo_Helper_Http::getClient($videoUrl);

			$response = $client->request('GET');

			$body = $response->getBody();

			if ($body)
			{
				$dom = new Zend_Dom_Query($body);

				$thumbnailUrl = $dom->query('meta[property="og:image"]');

				if ($thumbnailUrl->count())
				{
					$this->_thumbnailUrl = $thumbnailUrl->current()->getAttribute('content');
				}
			}
		}
		catch (Zend_Http_Client_Exception $e) {}

		return $this->verifyThumbnailUrl($this->_thumbnailUrl);
	}
}
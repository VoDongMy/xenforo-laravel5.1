<?php

class XenGallery_Thumbnail_NoThumb extends XenGallery_Thumbnail_Abstract
{
	public function getNoThumbnailUrl($videoId, $mediaSiteId)
	{
		$this->_mediaSiteId = $mediaSiteId;

		return $this->getThumbnailUrl($videoId);
	}

	public function getThumbnailUrl($videoId)
	{
		$this->_videoId = $videoId;

		$options = XenForo_Application::getOptions();

		$this->_thumbnailUrl = $options->boardUrl . '/' . $options->xengalleryDefaultNoThumb;

		return $this->verifyThumbnailUrl($this->_thumbnailUrl);
	}
}
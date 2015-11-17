<?php

class XenGallery_ViewPublic_Media_Download extends XenForo_ViewPublic_Base
{
	public function renderRaw()
	{
		$media = $this->_params['media'];

		$this->_response->setHeader('Content-type', 'application/octet-stream', true);
		$this->setDownloadFileName($media['filename']);
		
		$this->_response->setHeader('ETag', $media['attach_date'], true);
		$this->_response->setHeader('Content-Length', $media['file_size'], true);
		$this->_response->setHeader('X-Content-Type-Options', 'nosniff');

		return new XenForo_FileOutput($this->_params['mediaFile']);		
	}
}

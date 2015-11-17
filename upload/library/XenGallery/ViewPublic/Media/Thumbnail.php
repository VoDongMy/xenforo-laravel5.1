<?php

class XenGallery_ViewPublic_Media_Thumbnail extends XenForo_ViewPublic_Base
{
	public function renderRaw()
	{
		$extension = XenForo_Helper_File::getFileExtension($this->_params['thumbnailPath']);
		$imageTypes = array(
			'gif' => 'image/gif',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpe' => 'image/jpeg',
			'png' => 'image/png'
		);

		$this->_response->setHeader('Content-type', $imageTypes[$extension], true);
		$this->setDownloadFileName($this->_params['thumbnailPath'], true);

		$this->_response->setHeader('X-Content-Type-Options', 'nosniff');

		if (!is_readable($this->_params['thumbnailPath']) || !file_exists($this->_params['thumbnailPath']))
		{
			$this->_params['thumbnailPath'] = XenGallery_Template_Helper_Core::helperDummyImage('visible', '', '', true);
		}

		return new XenForo_FileOutput($this->_params['thumbnailPath']);
	}
}
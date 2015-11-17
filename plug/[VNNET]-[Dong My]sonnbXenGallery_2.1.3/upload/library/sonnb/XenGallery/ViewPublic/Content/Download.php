<?php

/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
class sonnb_XenGallery_ViewPublic_Content_Download extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderRaw()
	{
		$photo = $this->_params['content'];

		$extension = $photo['extension'];
		
		$fileName = explode('/', $this->_params['fileUrl']);
		$fileName = end($fileName);

		$this->_response->setHeader('Content-type', sonnb_XenGallery_Model_ContentData::$imageMimes[$extension], true);
		$this->setDownloadFileName($fileName);

		$this->_response->setHeader('ETag', $photo['upload_date'], true);
		$this->_response->setHeader('Content-Length', filesize($this->_params['filePath']), true);
		$this->_response->setHeader('X-Content-Type-Options', 'nosniff');
		
		return new XenForo_FileOutput($this->_params['filePath']);
	}
}
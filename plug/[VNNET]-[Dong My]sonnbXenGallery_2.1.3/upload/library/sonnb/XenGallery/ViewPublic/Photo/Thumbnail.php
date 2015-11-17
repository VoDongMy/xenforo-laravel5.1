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
class sonnb_XenGallery_ViewPublic_Photo_Thumbnail extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderRaw()
	{
		$url = $this->_params['url'];
		$width = $this->_params['width'];
		$height = $this->_params['height'];
		$crop = $this->_params['crop'];

        $extension = XenForo_Helper_File::getFileExtension($url);
		
		$imageInfo = @getimagesize($url);
		
		if (!$imageInfo || !in_array($imageInfo[2], array_values(sonnb_XenGallery_Model_PhotoData::$typeMap))
				|| !in_array(strtolower($extension), array_keys(sonnb_XenGallery_Model_PhotoData::$imageMimes)))
		{
			$url = XenForo_Template_Helper_Core::getAvatarUrl(array(), 'l');
            $extension = XenForo_Helper_File::getFileExtension($url);
			$imageInfo = @getimagesize($url);
		}

		$this->_response->setHeader('Content-type', sonnb_XenGallery_Model_PhotoData::$imageMimes[$extension], true);
		$this->_response->setHeader('ETag', XenForo_Application::$time, true);
		$this->_response->setHeader('X-Content-Type-Options', 'nosniff');
		$this->setDownloadFileName($url, true);		
		
		$image = XenForo_Image_Abstract::createFromFile($url, sonnb_XenGallery_Model_PhotoData::$typeMap[$extension]);
		
		if ($image)
		{			
			if (XenForo_Image_Abstract::canResize($width, $height))
			{
				if ($crop)
				{
					$image->thumbnail($width*2, $height*2);
					$image->crop(0, 0, $width, $height);
				}
				else
				{
					$image->thumbnail($width, $height);
				}
			}
			else
			{
				$image->output(sonnb_XenGallery_Model_PhotoData::$typeMap[$extension]);
			}
		}
	}
}
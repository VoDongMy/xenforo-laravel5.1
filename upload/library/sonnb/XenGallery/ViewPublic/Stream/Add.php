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
class sonnb_XenGallery_ViewPublic_Stream_Add extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderJson()
	{
		$results = array();
		foreach ($this->_params['newStreams'] AS $stream)
		{
			$results[$stream['stream_name']] = $this->createTemplateObject('sonnb_xengallery_photo_stream_item', array(
				'stream' => $stream,
				'photo' => $this->_params['photo']
			));
		}

		return array(
			'streams' => $results
		);
	}
}
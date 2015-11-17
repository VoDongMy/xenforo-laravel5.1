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
class sonnb_XenGallery_ViewPublic_Stream_Find extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderJson()
	{
		$results = array();
		foreach ($this->_params['streams'] AS $stream)
		{
			$results[$stream['stream_name']] = array(
				'username' => htmlspecialchars($stream['stream_name'])
			);
		}

		return array(
			'results' => $results
		);
	}
}
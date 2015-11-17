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
class sonnb_XenGallery_ViewPublic_Stream_Jump extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderJson()
	{
		return array(
			'valid' => $this->_params['totalItems'],
			'errorPhrase' => new XenForo_Phrase('sonnb_xengallery_there_is_no_content_for_stream_x', array(
				'stream' => $this->_params['stream_name']
			)),
			'link' => XenForo_Link::buildPublicLink('gallery/streams', array('stream_name' => $this->_params['stream_name']))
		);
	}
}
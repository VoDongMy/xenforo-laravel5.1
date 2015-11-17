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
class sonnb_XenGallery_XenForo_DataWriter_Discussion_Thread extends XFCP_sonnb_XenGallery_XenForo_DataWriter_Discussion_Thread
{
	protected function _getFields()
	{
		$fields = parent::_getFields();
		
		$fields['xf_thread']['sonnb_xengallery_import'] = array(
			'type' => self::TYPE_UINT,
			'default' => 0
		);

		return $fields;
	}
}

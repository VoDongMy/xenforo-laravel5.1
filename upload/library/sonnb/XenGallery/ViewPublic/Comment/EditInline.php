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
class sonnb_XenGallery_ViewPublic_Comment_EditInline extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderJson()
	{
		$bbCodeFormatter = XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this));
		$parser = new XenForo_BbCode_Parser($bbCodeFormatter);

		$output = array();

		$this->_params['comment']['message'] = sonnb_XenGallery_ViewPublic_Helper::renderGalleryComment($parser, $this->_params['comment']['message']);

		$output['messagesTemplateHtml'] = $this->createTemplateObject('sonnb_xengallery_comment', $this->_params);

		$output['_message'] = nl2br($this->_params['comment']['message']);
		$output['css'] = $output['messagesTemplateHtml']->getRequiredExternals('css');
		$output['js'] = $output['messagesTemplateHtml']->getRequiredExternals('js');

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}
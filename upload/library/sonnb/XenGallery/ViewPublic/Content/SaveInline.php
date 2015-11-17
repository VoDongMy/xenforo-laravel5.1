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
class sonnb_XenGallery_ViewPublic_Content_SaveInline extends sonnb_XenGallery_ViewPublic_Abstract
{
    public function renderJson()
    {
	    $bbCodeFormatter = XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this));
	    $parser = new XenForo_BbCode_Parser($bbCodeFormatter);

		$output = array();

	    $output['message'] = new XenForo_Phrase('changes_saved');
	    $output['contentId'] = $this->_params['content']['content_id'];
	    $output['description'] = sonnb_XenGallery_ViewPublic_Helper::renderGalleryComment($parser, $this->_params['content']['description']);
	    $output['fields'] = '';
	    if (!empty($this->_params['fields']))
	    {
		    $fields = sonnb_XenGallery_ViewPublic_Helper::addFieldsValueHtml($this, $this->_params['fields']);

		    foreach ($fields as $field)
		    {
			    $output['fields'] .= $this->createTemplateObject(
				    'sonnb_xengallery_custom_field_view',
				    array(
					    'field' => $field
				    )
			    );
		    }
	    }

	    return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
    }
}
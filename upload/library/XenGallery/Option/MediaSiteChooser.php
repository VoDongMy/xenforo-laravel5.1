<?php

/**
 * Helper for choosing media sites.
 *
 * @package XenForo_Options
 */
class XenGallery_Option_MediaSiteChooser
{
    public static function renderCheckbox(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {
        return self::_render('option_list_option_checkbox', $view, $fieldPrefix, $preparedOption, $canEdit);
    }

    protected static function _render($templateName, XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {
        $preparedOption['formatParams'] = XenForo_Model::create('XenGallery_Model_Media')->getUserGroupOptions(
            $preparedOption['option_value']
        );

		foreach ($preparedOption['formatParams'] AS &$param)
		{
			$param['label'] .= " ($param[value])";
		}

        return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
            $templateName, $view, $fieldPrefix, $preparedOption, $canEdit
        );
    }    
}
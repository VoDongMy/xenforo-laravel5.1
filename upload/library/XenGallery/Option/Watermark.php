<?php

class XenGallery_Option_Watermark
{
	public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$preparedOption['noImagick'] = !class_exists('Imagick');

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			'option_template_xengallery_watermark_enable',
			$view, $fieldPrefix, $preparedOption, $canEdit
		);
	}

	public static function verifyOption(&$optionValue, XenForo_DataWriter $dw, $fieldName)
	{
		if ($dw->isInsert())
		{
			return true;
		}

		if ($optionValue == 'enabled' && !class_exists('Imagick'))
		{
			$dw->error(new XenForo_Phrase('must_have_imagick_pecl_extension'), $fieldName);
			return false;
		}

		return true;
	}
}
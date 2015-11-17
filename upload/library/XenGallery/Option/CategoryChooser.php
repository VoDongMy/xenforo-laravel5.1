<?php

class XenGallery_Option_CategoryChooser
{
	/**
	* Gallery Category chooser. Displays a list of nodes. Rendered in a multiple choice select element
	*/
	public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$preparedOption['options'] = array();
		
		$categories = XenForo_Model::create('XenGallery_Model_Category')->getCategoryStructure();

		$preparedOption['options'][0] = array(
			'category_id' => 0,
			'title' => sprintf('(%s)', new XenForo_Phrase('xengallery_no_categories')),
			'depth' => '',
			'selected' => in_array('0', $preparedOption['option_value'])
		);
		foreach ($categories AS $key => $category)
		{
			$preparedOption['options'][$category['category_id']] = array(
				'category_id' => $category['category_id'],
				'title' => $category['category_title'],
				'depth' => str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $category['depth']),
				'selected' => in_array($categories[$key]['category_id'], $preparedOption['option_value'])
			);
		}
		
		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			'xengallery_category_chooser', $view, $fieldPrefix, $preparedOption, $canEdit
		);		
	}
}
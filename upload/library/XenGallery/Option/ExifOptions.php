<?php

class XenGallery_Option_ExifOptions
{
	/**
	 * Renders the exif options.
	 *
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$value = $preparedOption['option_value'];

		$choices = array();
		if (isset($value['FILE']))
		{
			foreach ($value['FILE'] AS $option)
			{
				$choices[] = array('group' => $option['group'], 'name' => is_string($option['name']) ? $option['name'] : '', 'format' => $option['format']);
			}
		}

		if (isset($value['COMPUTED']))
		{
			foreach ($value['COMPUTED'] AS $option)
			{
				$choices[] = array('group' => $option['group'], 'name' => is_string($option['name']) ? $option['name'] : '', 'format' => $option['format']);
			}
		}

		if (isset($value['IFD0']))
		{
			foreach ($value['IFD0'] AS $option)
			{
				$choices[] = array('group' => $option['group'], 'name' => is_string($option['name']) ? $option['name'] : '', 'format' => $option['format']);
			}
		}

		if (isset($value['EXIF']))
		{
			foreach ($value['EXIF'] AS $option)
			{
				$choices[] = array('group' => $option['group'], 'name' => is_string($option['name']) ? $option['name'] : '', 'format' => $option['format']);
			}
		}

		$editLink = $view->createTemplateObject('option_list_option_editlink', array(
			'preparedOption' => $preparedOption,
			'canEditOptionDefinition' => $canEdit
		));

		return $view->createTemplateObject('option_template_xengallery_exif_options', array(
			'fieldPrefix' => $fieldPrefix,
			'listedFieldName' => $fieldPrefix . '_listed[]',
			'preparedOption' => $preparedOption,
			'formatParams' => $preparedOption['formatParams'],
			'editLink' => $editLink,

			'choices' => $choices,
			'nextCounter' => count($choices)
		));
	}

	public static function verifyOption(array &$options, XenForo_DataWriter $dw, $fieldName)
	{
		if (!$dw->isInsert())
		{
			$grouped = array();

			foreach ($options AS $option)
			{
				if (!isset($option['group']) || strval($option['group']) === '')
				{
					continue;
				}

				$grouped[strval($option['group'])][] = array(
					'group' => strval($option['group']),
					'name' => strval($option['name']),
					'format' => strval($option['format'])
				);
			}

			$options = $grouped;
		}

		return true;
	}
}
<?php

class XenGallery_Option_MediaSiteThumbs
{
	/**
	 * Renders the media site thumbs options.
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
		foreach ($value AS $site => $url)
		{
			$choices[] = array('id' => $site, 'url' => is_string($url) ? $url : '');
		}

		$editLink = $view->createTemplateObject('option_list_option_editlink', array(
			'preparedOption' => $preparedOption,
			'canEditOptionDefinition' => $canEdit
		));

			return $view->createTemplateObject('option_template_xengallery_media_thumbnails', array(
			'fieldPrefix' => $fieldPrefix,
			'listedFieldName' => $fieldPrefix . '_listed[]',
			'preparedOption' => $preparedOption,
			'formatParams' => $preparedOption['formatParams'],
			'editLink' => $editLink,

			'mediaSites' => self::getBbCodeMediaSites(),

			'choices' => $choices,
			'nextCounter' => count($choices)
		));
	}

	public static function verifyOption(array &$sites, XenForo_DataWriter $dw, $fieldName)
	{
		$output = array();

		foreach ($sites AS $site)
		{
			if (!isset($site['id']) || strval($site['id']) === '')
			{
				continue;
			}

			if (isset($site['url']) && strval($site['url']) !== '')
			{
				$output[strval($site['id'])] = strval($site['url']);
			}
			else
			{
				$output[strval($site['id'])] = utf8_strlen($site['id']);
			}
		}

		$sites = $output;

		return true;
	}

	public static function getBbCodeMediaSites()
	{
		$mediaSites = array();
		foreach (XenForo_Model::create('XenForo_Model_BbCode')->getAllBbCodeMediaSites() AS $mediaSite)
		{
			$mediaSites[] = array(
				'label' => $mediaSite['site_title'],
				'value' => $mediaSite['media_site_id']
			);
		}

		return $mediaSites;
	}
}
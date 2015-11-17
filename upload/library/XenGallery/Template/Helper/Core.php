<?php

class XenGallery_Template_Helper_Core
{
	/**
	 * Array to cache model objects
	 *
	 * @var array
	 */
	protected static $_modelCache = array();

	/**
	 * Stores the JS Cache Buster
	 *
	 * @var string
	 */
	protected static $_jsCacheBuster = '';

	/**
	 * Helper to fetch the title of a custom media field from its ID
	 *
	 * @param string $field
	 *
	 * @return XenForo_Phrase
	 */
	public static function getMediaFieldTitle($fieldId)
	{
		return new XenForo_Phrase("xengallery_field_$fieldId");
	}

	/**
	 * Gets the HTML value of the media field.
	 *
	 * @param array|string $field If string, field ID
	 * @param mixed $value Value of the field; if null, pulls from field_value in field
	 * @param XenForo_BbCode_Parser $parser
	 *
	 * @return string
	 */
	public static function getMediaFieldValueHtml($field, $value = null, $parser = null)
	{
		if (!is_array($field))
		{
			$fields = self::_getModelFromCache('XenGallery_Model_Field')->getGalleryFieldCache();
			if (!isset($fields[$field]))
			{
				return '';
			}

			$field = $fields[$field];
		}

		if ($value === null && isset($field['field_value']))
		{
			$value = $field['field_value'];
		}

		if ($value === '' || $value === null)
		{
			return '';
		}

		$multiChoice = false;
		$choice = '';

		switch ($field['field_type'])
		{
			case 'radio':
			case 'select':
				$choice = $value;
				$value = new XenForo_Phrase("xengallery_field_$field[field_id]_choice_$value");
				$value->setPhraseNameOnInvalid(false);
				break;

			case 'checkbox':
			case 'multiselect':
				$multiChoice = true;
				if (!is_array($value) || count($value) == 0)
				{
					return '';
				}

				$newValues = array();
				foreach ($value AS $id => $choice)
				{
					$phrase = new XenForo_Phrase("xengallery_field_$field[field_id]_choice_$choice");
					$phrase->setPhraseNameOnInvalid(false);

					$newValues[$choice] = $phrase;
				}
				$value = $newValues;
				break;

			case 'bbcode':

				if (!($parser instanceof XenForo_BbCode_Parser))
				{
					trigger_error('BB code parser not specified correctly.', E_USER_WARNING);
					break;
				}

				$value = $parser->render($value, array());
				break;

			case 'textbox':
			case 'textarea':
			default:
				$value = XenForo_Template_Helper_Core::callHelper('bodytext', array($value));
		}

		if (!empty($field['display_template']))
		{
			if ($multiChoice && is_array($value))
			{
				foreach ($value AS $choice => &$thisValue)
				{
					$thisValue = strtr($field['display_template'], array(
						'{$fieldId}' => $field['field_id'],
						'{$value}' => $thisValue,
						'{$valueUrl}' => urlencode($thisValue),
						'{$choice}' => $choice,
					));
				}
			}
			else
			{
				$value = strtr($field['display_template'], array(
					'{$fieldId}' => $field['field_id'],
					'{$value}' => $value,
					'{$valueUrl}' => urlencode($value),
					'{$choice}' => $choice,
				));
			}
		}

		if (is_array($value))
		{
			if (empty($value))
			{
				return '';
			}
			return '<ul class="plainList"><li>' . implode('</li><li>', $value) . '</li></ul>';
		}

		return $value;
	}

	public static function helperWatermarkUrl($watermarkId)
	{
		$watermarkModel = self::_getModelFromCache('XenGallery_Model_Watermark');

		return $watermarkModel->getWatermarkUrl($watermarkId);
	}

	protected static $_uniqueId = 0;
	public static function helperGalleryUniqueId($prefix = '')
	{
		return $prefix ? $prefix : '' . (++self::$_uniqueId);
	}

	/**
	 * Dynamically creates a dummy image based on the configured aspect ratio (if required).
	 * Can be useful for spacing and as a placeholder.
	 *
	 * @return string
	 */
	public static function helperDummyImage($visibility = 'hidden', $title = '', $classes = '', $pathOnly = false)
	{
		$dimensions = XenForo_Application::getOptions()->get('xengalleryThumbnailDimension');
		$imagePath = XenForo_Template_Helper_Core::styleProperty('imagePath');

		$dummyPath = "{$imagePath}/xengallery/nothumb.jpg";
		if (!file_exists($dummyPath))
		{
			$dummyPath = 'styles/default/xengallery/nothumb.jpg';
		}

		$writePath = XenForo_Application::$externalDataPath . '/xengallery/dummy/dummy.jpg';
		$writeUrl = XenForo_Application::$externalDataUrl . '/xengallery/dummy/dummy.jpg';
		XenForo_Helper_File::createDirectory(dirname($writePath));
		if (is_readable($dummyPath))
		{
			$baseImage = new XenGallery_Helper_Image($dummyPath);

			$newDummyImage = false;
			if (is_readable($writePath))
			{
				$currentImage = new XenGallery_Helper_Image($writePath);

				if ($currentImage->getWidth() != $dimensions['width'] || $currentImage->getHeight() != $dimensions['height'])
				{
					$newDummyImage = true;
				}
			}

			if ($newDummyImage || !file_exists($writePath))
			{
				$baseImage->resize($dimensions['width'], $dimensions['height'], 'crop');
				$baseImage->saveToPath($writePath);
			}
		}

		if ($pathOnly)
		{
			return $writePath;
		}
		$writeUrl .= '?' . XenForo_Application::$time;

		return "<img src=\"{$writeUrl}\" style=\"width: 100%; visibility: {$visibility}\" class=\"dummyImage {$classes}\" title=\"{$title}\" alt=\"{$title}\" />";
	}

	/**
	 * Helper to abbreviate a number
	 */
	public static function helperShortNumber($number)
	{
		if ($number >= 1000000000) // 1B
		{
			$number = number_format($number / 1000000000);
			$phrase = 'xengallery_x_b';
		}
		elseif ($number >= 100000000) // 100M
		{
			$number = number_format($number / 1000000);
			$phrase = 'xengallery_x_m';
		}
		elseif ($number >= 10000000) // 10M
		{
			$number = number_format($number / 1000000);
			$phrase = 'xengallery_x_m';
		}
		elseif ($number >= 1000000) // 1M
		{
			$number = number_format($number / 1000000);
			$phrase = 'xengallery_x_m';
		}
		elseif ($number >= 100000) // 100K
		{
			$number = number_format($number / 1000);
			$phrase = 'xengallery_x_k';
		}
		elseif ($number >= 10000) // 10K
		{
			$number = number_format($number / 1000);
			$phrase = 'xengallery_x_k';
		}
		elseif ($number >= 1000) // 1K
		{
			$number = number_format($number / 1000);
			$phrase = 'xengallery_x_k';
		}
		elseif (intval($number) < 1)
		{
			return 0;
		}
		else
		{
			return $number;
		}

		return new XenForo_Phrase($phrase, array('number' => $number));
	}

	public static function helperXmgJs($jsPath = '')
	{
		if (!$jsPath)
		{
			return "js/xenforo/xenforo.js";
		}

		switch (XenForo_Application::getOptions()->uncompressedJs)
		{
			case 0:
			case 2:
				$min = 'min/';
				break;

			default:
				$min = '';
		}

		return "js/xengallery/$min$jsPath?_v=" . self::_getJsCacheBuster();
	}

	protected static function _getJsCacheBuster()
	{
		if (!self::$_jsCacheBuster)
		{
			// Fallback in case XFMG version ID unavailable for whatever reason.
			$versionId = XenForo_Application::$versionId;

			if (XenForo_Application::isRegistered('addOns'))
			{
				$addOns = XenForo_Application::get('addOns');
				if (!empty($addOns['XenGallery']))
				{
					$versionId = $addOns['XenGallery'];
				}
			}

			self::$_jsCacheBuster = substr(md5($versionId . XenForo_Application::$jsVersion), 0, 8);
		}

		return self::$_jsCacheBuster;
	}

	/**
	 * Fetches a model object from the local cache
	 *
	 * @param string $modelName
	 *
	 * @return XenForo_Model
	 */
	protected static function _getModelFromCache($modelName)
	{
		if (!isset(self::$_modelCache[$modelName]))
		{
			self::$_modelCache[$modelName] = XenForo_Model::create($modelName);
		}

		return self::$_modelCache[$modelName];
	}
}
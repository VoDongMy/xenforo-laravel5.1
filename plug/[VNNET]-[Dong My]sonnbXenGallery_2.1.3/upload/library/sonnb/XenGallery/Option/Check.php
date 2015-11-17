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
class sonnb_XenGallery_Option_Check
{
	public static function watermark(array &$optionValues, XenForo_DataWriter $optionDw, $optionId)
	{
		if ($optionId !== 'sonnbXG_watermark')
		{
			return true;
		}

		if (!empty($optionValues['enabled']))
		{
			switch ($optionValues['overlay'])
			{
				case 'image':
					if (!Zend_Uri::check($optionValues['url']) && !is_file($optionValues['url']))
					{
						$optionDw->error(new XenForo_Phrase('sonnb_xengallery_watermark_image_not_valid'), $optionId);
						return false;
					}
					break;
				case 'text':
					if (!self::isTextColorValid($optionValues['textColor']))
					{
						$optionDw->error(new XenForo_Phrase('sonnb_xengallery_watermark_text_color_not_valid'), $optionId);
						return false;
					}

					if (!self::isBgColorValid($optionValues['bgColor']))
					{
						$optionDw->error(new XenForo_Phrase('sonnb_xengallery_watermark_bg_color_not_valid'), $optionId);
						return false;
					}

					if (!empty($optionValues['font']) && !is_file($optionValues['font']))
					{
						$optionDw->error(new XenForo_Phrase('sonnb_xengallery_watermark_font_path_not_valid'), $optionId);
						return false;
					}
					break;
			}
		}

		return true;
	}

	public static function isTextColorValid($textColor)
	{
		if (empty($textColor))
		{
			return false;
		}

		$textColor = str_split($textColor);

		if ($textColor[0] !== '#')
		{
			return false;
		}

		if (count($textColor) !== 4 && count($textColor) !== 7)
		{
			return false;
		}

		return true;
	}

	public static function isBgColorValid($bgColor)
	{
		if (empty($bgColor))
		{
			return false;
		}

		if ($bgColor === 'transparent')
		{
			return true;
		}

		return self::isTextColorValid($bgColor);
	}
}
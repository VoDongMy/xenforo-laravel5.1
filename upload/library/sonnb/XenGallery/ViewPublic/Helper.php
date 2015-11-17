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
class sonnb_XenGallery_ViewPublic_Helper
{
	protected static $_tags = array(
		'photo', 'xengallery', 'album', 'user', 'url', 'video'
	);

	public static function renderGalleryComment(XenForo_BbCode_Parser $parser, &$message)
	{
		if (XenForo_Application::getOptions()->sonnbXG_extraBbcode)
		{
			self::$_tags = array_merge(self::$_tags, array('img', 'media'));
		}

		$message = preg_replace(array('/(\[url\]\[url\])/i', '/(\[\/url\]\[\/url\])/i'), array('[url]', '[/url]'), $message);
		$message = XenForo_Helper_String::censorString($message);
		$message = self::linkTaggedPlainText($message);

		$tags = $parser->parse($message);

		if (!empty($tags))
		{
			$tags = self::_processTags($tags);
		}

		$extraStates = array(
			'stopLineBreakConversion' => 1
		);

		$message = new XenForo_BbCode_TextWrapper($tags, $parser, $extraStates);

		return $message;
	}

	protected static function _processTags(array $tags)
	{
		foreach ($tags as &$tag)
		{
			if (is_array($tag) && isset($tag['tag']))
			{
				if (!in_array(strtolower($tag['tag']), self::$_tags))
				{
					$tag['tag'] = md5(rand());
				}
				else
				{
					if (in_array($tag, array('video', 'photo', 'xengallery', 'album')))
					{
						$tag['option'] = sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_SMALL;
					}
				}
			}

			if (!empty($tag['children']) && is_array($tag['children']))
			{
				$tag['children'] = self::_processTags($tag['children']);
			}
		}

		return $tags;
	}

	public static function renderContentByContentGrouped($itemsGrouped, XenForo_View $view)
	{
		$itemRendered = array();

		if ($itemsGrouped)
		{
			foreach ($itemsGrouped as $item)
			{
				$itemRendered[] = sonnb_XenGallery_ContentHandler_Abstract::create($item['content_type'])
					->renderHtml($item['content'], $view);
			}
		}

		return $itemRendered;
	}

	public static function getWatchViewParams($watched)
	{
		$output = array();

		if ($watched)
		{
			$output['term'] = new XenForo_Phrase('sonnb_xengallery_unwatch');

			$output['cssClasses'] = array(
				'watch' => '-',
				'unwatch' => '+'
			);
		}
		else
		{
			$output['term'] = new XenForo_Phrase('sonnb_xengallery_watch');

			$output['cssClasses'] = array(
				'watch' => '+',
				'unwatch' => '-'
			);
		}

		return $output;
	}

	public static function getFieldValueHtml(array $field, $value = null)
	{
		if ($value === null && isset($field['field_value']))
		{
			$value = $field['field_value'];
		}

		if ($value === '' || $value === null)
		{
			return '';
		}

		switch ($field['field_type'])
		{
			case 'radio':
			case 'select':
				$fieldChoices = unserialize($field['field_choices']);

				if (isset($fieldChoices[$value]))
				{
					$value = $fieldChoices[$value];
				}
				break;

			case 'checkbox':
			case 'multiselect':
				if (!is_array($value) || count($value) == 0)
				{
					return '';
				}

				$fieldChoices = unserialize($field['field_choices']);
				$newValues = array();
				foreach ($value AS $choice)
				{
					$val = '';
					if (isset($fieldChoices[$choice]))
					{
						$val = $fieldChoices[$choice];
					}

					$newValues[$choice] = $val;
				}
				$value = $newValues;
				break;

			case 'textbox':
			case 'textarea':
			default:
				$value = nl2br(XenForo_Helper_String::censorString($value));
		}

		return $value;
	}

	public static function addFieldsValueHtml(XenForo_View $view, array $fields, array $values = array())
	{
		foreach ($fields AS &$field)
		{
			$field['fieldValueHtml'] = self::getFieldValueHtml(
				$field,
				isset($values[$field['field_id']]) ? $values[$field['field_id']] : null
			);
		}

		return $fields;
	}

	public static function linkTaggedPlainText($message)
	{
		return preg_replace_callback(
				'#(?<=^|\s|[\](,]|--|@)@\[(\d+):(\'|"|&quot;|)(.*)\\2\]#iU',
				array('self', '_linkTaggedPlainTextCallback'),
				$message
			);
	}

	protected static function _linkTaggedPlainTextCallback(array $match)
	{
		$userId = intval($match[1]);
		$username = htmlspecialchars($match[3], null, 'utf-8', false);

		return "[USER={$userId}]{$username}[/USER]";
	}
}
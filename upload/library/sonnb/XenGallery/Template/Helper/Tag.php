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
class sonnb_XenGallery_Template_Helper_Tag
{

	/**
	 * @param $users
	 * @param $tagsUrl
	 * @return XenForo_Phrase
	 */
	public static function helperTags ($users, $tagsUrl)
	{
		if (!is_array($users) || empty($users))
		{
			return;
		}

		$number = count($users);
		
		if (empty($users))
		{
			return new XenForo_Phrase('sonnb_xengallery_tag_with_x_people', 
				array(
						'tags' => XenForo_Template_Helper_Core::numberFormat($number),
						'tagsLink' => $tagsUrl
				));
		}
		
		$user1 = $user2 = '';
		$hasMe = false;

		$users = array_values($users);

		/*
		foreach ($users as $key => $_user)
		{
			if ($_user['user_id'] == XenForo_Visitor::getUserId())
			{
				$hasMe = true;
				unset($users[$key]);
				sort($users);
				$number = count($users);
				break;
			}
		}
		*/

		if ($users[0])
		{
			$user1 = XenForo_Template_Helper_Core::helperUserNameHtml($users[0], '', false, array(
				'class' => 'photoTag-tag_'.$users[0]['tag_id'],
				'href' => XenForo_Link::buildPublicLink('gallery/authors', $users[0])
			));
			
			if (!empty($users[1]))
			{
				$user2 = XenForo_Template_Helper_Core::helperUserNameHtml($users[1], '', false, array(
					'class' => 'photoTag-tag_'.$users[1]['tag_id'],
					'href' => XenForo_Link::buildPublicLink('gallery/authors', $users[1])
				));
			}
		}

		$phraseParams = array(
			'user1' => $user1,
			'user2' => $user2,
			'others' => XenForo_Template_Helper_Core::numberFormat($number - 2),
			'tagsLink' => $tagsUrl
		);

		if ($hasMe === false)
		{
			switch ($number)
			{
				case 1:
					return new XenForo_Phrase('sonnb_xengallery_tag_user1', $phraseParams, false);
					break;
				case 2:
					return new XenForo_Phrase('sonnb_xengallery_tag_user1_and_user2', $phraseParams, false);
					break;
				default:
					return new XenForo_Phrase('sonnb_xengallery_tag_user1_user2_and_x_others_people', $phraseParams, false);
					break;
			}
		}
		else
		{
			switch ($number)
			{
				case 0:
					return new XenForo_Phrase('sonnb_xengallery_tag_you', $phraseParams, false);
					break;
				case 1:
					return new XenForo_Phrase('sonnb_xengallery_tag_you_and_user1', $phraseParams, false);
					break;
				case 2:
					return new XenForo_Phrase('sonnb_xengallery_tag_you_and_user1_and_user2', $phraseParams, false);
					break;
				default:
					return new XenForo_Phrase('sonnb_xengallery_tag_you_and_user1_user2_and_x_others_people', $phraseParams, false);
					break;
			}
		}
	}
}
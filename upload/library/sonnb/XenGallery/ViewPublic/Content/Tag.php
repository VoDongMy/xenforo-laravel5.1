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
class sonnb_XenGallery_ViewPublic_Content_Tag extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderJson()
	{
		$tag = $this->_params['tag'];
		$content = $this->_params['content'];

		$output = array(
			'image' => array(
				$content['content_id'] => array(
					'content_id' => $content['content_id'],
					'tags' => array()
				)
			)
		);

		if (!empty($content['tagUsers']))
		{
			$output['tagList'] = sonnb_XenGallery_Template_Helper_Tag::helperTags(
				$content['tagUsers'],
				XenForo_Link::buildPublicLink("gallery/{$content['content_type']}s/tags", $content)
			);

			foreach ($content['tagUsers'] as $_tag)
			{
				$output['image'][$content['content_id']]['tags'][] = array(
					'tag_id' => $_tag['tag_id'],
					'username' => $_tag['username'],
					'left' => $_tag['tag_x'],
					'top' => $_tag['tag_y'],
					'url' => XenForo_Link::buildPublicLink('gallery/authors', $_tag),
					'isDeleteEnable' => $content['canEdit'] || $_tag['user_id'] == XenForo_Visitor::getUserId()
				);
			}
		}
		else
		{
			$output['tagList'] = '';
		}

		if ($tag)
		{
			$output['tag'] = array(
				'tag_id' => $tag['tag_id'],
				'username' => $tag['username'],
				'left' => $tag['tag_x'],
				'top' => $tag['tag_y'],
				'url' => XenForo_Link::buildPublicLink('gallery/authors', $tag),
				'isDeleteEnable' => $content['canEdit'] || $tag['user_id'] == XenForo_Visitor::getUserId()
			);
		}

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}
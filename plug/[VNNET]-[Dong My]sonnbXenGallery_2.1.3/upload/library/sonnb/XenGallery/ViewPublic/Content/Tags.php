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
class sonnb_XenGallery_ViewPublic_Content_Tags extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderJson()
	{
		if ($this->_params['fetchTags'])
		{
			$tags = $this->_params['tags'];
			$content = $this->_params['content'];

			$output = array(
				'image' => array(
					$content['content_id'] => array(
						'content_id' => $content['content_id'],
						'tags' => array()
					)
				)
			);

			if ($tags)
			{
				foreach ($tags as $_tagId => $_tag)
				{
					$output['image'][$content['content_id']]['tags'][] = array(
						'tag_id' => $_tagId,
						'username' => $_tag['username'],
						'left' => $_tag['tag_x'],
						'top' => $_tag['tag_y'],
						'url' => XenForo_Link::buildPublicLink('gallery/authors', $_tag),
						'isDeleteEnable' => $content['canEdit'] || $_tag['user_id'] == XenForo_Visitor::getUserId()
					);
				}
			}

			return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
		}
	}
}
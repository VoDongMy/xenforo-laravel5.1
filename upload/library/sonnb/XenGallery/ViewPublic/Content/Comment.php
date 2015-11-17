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
class sonnb_XenGallery_ViewPublic_Content_Comment extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderJson()
	{
		$bbCodeFormatter = XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this));
		$parser = new XenForo_BbCode_Parser($bbCodeFormatter);

		$output = array();
		$comments = $this->_params['comments'];
		$lastComment = end($comments);

		foreach ($comments as &$comment)
		{
			$comment['message'] = sonnb_XenGallery_ViewPublic_Helper::renderGalleryComment($parser, $comment['message']);
			$output[] = $this->createTemplateObject('sonnb_xengallery_comment', array('comment' => $comment) + $this->_params);
		}

		return array(
			'comments' => $output,
			'lastShownCommentDate' => $lastComment['comment_date']
		);
	}
}
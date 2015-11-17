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
class sonnb_XenGallery_ViewPublic_Content_View extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderHtml()
	{
		$bbCodeFormatter = XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this));
		$parser = new XenForo_BbCode_Parser($bbCodeFormatter);

		$this->_params['content']['descriptionHtml'] = sonnb_XenGallery_ViewPublic_Helper::renderGalleryComment($parser, $this->_params['content']['description']);

		if (isset($this->_params['content']['video_type']))
		{
			$this->_params['content']['embed'] = $parser->render('[MEDIA='.$this->_params['content']['video_type'].']'.$this->_params['content']['video_key'].'[/MEDIA]');
		}

		if (!empty($this->_params['content']['comments']))
		{
			foreach ($this->_params['content']['comments'] as &$comment)
			{
				$comment['message'] = sonnb_XenGallery_ViewPublic_Helper::renderGalleryComment($parser, $comment['message']);
			}
		}

		if (!empty($this->_params['fields']))
		{
			$this->_params['fields'] = sonnb_XenGallery_ViewPublic_Helper::addFieldsValueHtml($this, $this->_params['fields']);
		}
	}
}
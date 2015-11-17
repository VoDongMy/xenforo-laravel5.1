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
class sonnb_XenGallery_ViewPublic_Album_Edit extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderHtml()
	{
		$editorOptions = array(
			'disable' => true,
			'height' => '150px',
			'templateName' => 'editor',
			'extraClass' => 'UserTagger'
		);
		
		$this->_params['editorTemplate'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
				$this, 'description', $this->_params['album']['description'], $editorOptions);
	}
}
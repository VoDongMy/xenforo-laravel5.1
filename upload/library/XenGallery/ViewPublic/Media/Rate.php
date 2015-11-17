<?php

class XenGallery_ViewPublic_Media_Rate extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		if (!empty($this->_params['canAddComment']))
		{
			$this->_params['commentsEditor'] = XenForo_ViewPublic_Helper_Editor::getQuickReplyEditor(
				$this, 'message', '',
				array(
					'json' => array(
						'placeholder' => new XenForo_Phrase('xengallery_write_a_comment') . '...'
					),
					'extraClass' => 'SetHeight'
				)
			);
		}
	}
}
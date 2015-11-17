<?php

class XenGallery_ViewPublic_Media_View extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));

		$this->_params['parser'] = $bbCodeParser;
		$this->_params['videoHtml'] = new XenForo_BbCode_TextWrapper($this->_params['media']['media_tag'], $bbCodeParser);

		if (!empty($this->_params['canAddComment']))
		{
			$this->_params['commentsEditor'] = XenForo_ViewPublic_Helper_Editor::getQuickReplyEditor(
				$this, 'message', !empty($this->_params['draft']) ? $this->_params['draft']['message'] : '',
				array(
					'autoSaveUrl' => XenForo_Link::buildPublicLink('xengallery/save-draft', $this->_params['media']),
					'json' => array(
						'placeholder' => new XenForo_Phrase('xengallery_write_a_comment') . '...'
					)
				)
			);
		}

		foreach ($this->_params['comments'] AS &$comment)
		{
			$comment['messageHtml'] = new XenForo_BbCode_TextWrapper($comment['message'], $bbCodeParser);
			$comment['message'] = $comment['messageHtml']; // sanity check in case template not updated
		}
	}
}
<?php

class XenGallery_ViewPublic_Media_CommentEdit extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['commentsEditor'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
			$this, 'message',
			$this->_params['comment']['message'],
			array(
				'editorId' =>
					'message' . $this->_params['comment']['comment_id'] . '_' . substr(md5(microtime(true)), -8),
				'json' => array(
					'enableXmgButton' => false
				)
			)
		);		
	}
}
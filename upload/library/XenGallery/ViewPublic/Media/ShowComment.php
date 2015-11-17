<?php

class XenGallery_ViewPublic_Media_ShowComment extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		
		$this->_params['comment']['messageHtml'] = new XenForo_BbCode_TextWrapper($this->_params['comment']['message'], $bbCodeParser);
		$this->_params['comment']['message'] = $this->_params['comment']['messageHtml']; // sanity check in case template not updated
		$this->_params['comment']['comment_state'] = 'visible';
		
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array(
			'templateHtml' => $this->createTemplateObject('xengallery_comment', $this->_params),
			'commentId' => $this->_params['comment']['comment_id']
		));
	}
}
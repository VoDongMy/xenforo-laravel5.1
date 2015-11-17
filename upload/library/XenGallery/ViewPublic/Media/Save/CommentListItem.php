<?php

class XenGallery_ViewPublic_Media_Save_CommentListItem extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));

		$this->_params['comment']['messageHtml'] = new XenForo_BbCode_TextWrapper($this->_params['comment']['message'], $bbCodeParser);
		$this->_params['comment']['message'] = $this->_params['comment']['messageHtml']; // sanity check in case template not update

		$params = $this->_params;
		$params['comment'] = XenForo_Model::create('XenGallery_Model_Comment')->prepareComments($params['comment']);

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array(
			'templateHtml' => $this->createTemplateObject('xengallery_comment', $params),
			'commentId' => $this->_params['comment']['comment_id'],
			'date' => XenForo_Application::$time
		));
	}
}
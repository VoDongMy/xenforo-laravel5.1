<?php

class XenGallery_ViewPublic_Media_LatestComments extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));

		foreach ($this->_params['comments'] AS &$comment)
		{
			$comment['messageHtml'] = new XenForo_BbCode_TextWrapper($comment['message'], $bbCodeParser);
			$comment['message'] = $comment['messageHtml']; // sanity check in case template not update
		}

		$output = $this->_renderer->getDefaultOutputArray(get_class($this), $this->_params, $this->_templateName);
		$output['date'] = $this->_params['date'];

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}
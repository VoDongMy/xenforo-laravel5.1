<?php

class XenGallery_ViewPublic_Media_Preview extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		if ($this->_params['media']['media_type'] == 'video_embed')
		{
			$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
			
			$html = new XenForo_BbCode_TextWrapper($this->_params['media']['media_tag'], $bbCodeParser);
			$this->_params['videoHtml'] = $html;
		}
	}

	public function renderRaw()
	{
		if ($this->_params['media']['media_type'] == 'video_embed')
		{
			$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));

			$html = new XenForo_BbCode_TextWrapper($this->_params['media']['media_tag'], $bbCodeParser);
			$this->_params['videoHtml'] = $html;
		}

		$this->_params['jQuerySource'] = XenForo_Dependencies_Public::getJquerySource();

		$template = $this->createTemplateObject($this->_templateName, $this->_params);
		return $template;
	}
}
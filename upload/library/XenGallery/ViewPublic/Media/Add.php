<?php

class XenGallery_ViewPublic_Media_Add extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$template = $this->createTemplateObject($this->_templateName, $this->_params);
		$output = array(
			'templateHtml' => $template,
			'css' => $template->getRequiredExternals('css'),
			'js' => $template->getRequiredExternals('js')
		);

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}
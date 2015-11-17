<?php

class XenGallery_ViewPublic_Media_Fetch extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$output = array(
			'prevTemplateHtml' => $this->createTemplateObject('xengallery_media_next_prev', array('prevMedia' => $this->_params['prevMedia'], 'noMorePrev' => $this->_params['noMorePrev'])),
			'nextTemplateHtml' => $this->createTemplateObject('xengallery_media_next_prev', array('nextMedia' => $this->_params['nextMedia'], 'noMoreNext' => $this->_params['noMoreNext']))
		);

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}
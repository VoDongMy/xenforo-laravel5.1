<?php

class XenGallery_ViewPublic_Media_LoadUserAlbums extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$template = $this->createTemplateObject('xengallery_media_load_user_albums', $this->_params);
		$output = array(
			'templateHtml' => $template,
			'css' => $template->getRequiredExternals('css'),
			'js' => $template->getRequiredExternals('js')
		);

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}
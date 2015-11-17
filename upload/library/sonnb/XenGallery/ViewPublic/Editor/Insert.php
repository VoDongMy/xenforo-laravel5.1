<?php

class sonnb_XenGallery_ViewPublic_Editor_Insert extends sonnb_XenGallery_ViewPublic_Abstract
{
	public function renderJson()
	{
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($this->_params);
	}
}
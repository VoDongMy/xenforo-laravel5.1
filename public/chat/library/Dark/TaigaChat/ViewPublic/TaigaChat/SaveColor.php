<?php

class Dark_TaigaChat_ViewPublic_TaigaChat_SaveColor extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($this->_params);
	}
}
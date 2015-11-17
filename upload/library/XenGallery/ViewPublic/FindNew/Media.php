<?php

class XenGallery_ViewPublic_FindNew_Media extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		if (!empty($this->_params['media']))
		{
			foreach ($this->_params['media'] AS &$media)
			{
				if (isset($media['media_type']) && $media['media_type'] == 'video_embed')
				{
					$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
					
					$html = new XenForo_BbCode_TextWrapper($media['media_tag'], $bbCodeParser);
					$media['videoHtml'] = $html;
				}
			}
		}
	}
}

<?php

class XenGallery_ViewPublic_Helper_VideoHtml
{
	public static function addVideoHtml(array &$media, XenForo_BbCode_Parser $bbCodeParser)
	{
		if ($media['media_type'] != 'video_embed')
		{
			return;
		}

		$html = new XenForo_BbCode_TextWrapper($media['media_tag'], $bbCodeParser);
		$media['videoHtml'] = $html;
	}
}
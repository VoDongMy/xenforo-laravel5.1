<?php

class XenGallery_ViewPublic_Album_PrivacyDescription extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		return array(
			'description' => $this->_params['descPhrase']
		);
	}
}
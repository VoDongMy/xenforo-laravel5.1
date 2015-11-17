<?php

/**
 * View for displaying a form to upload more attachments, and listing those that already exist
 *
 * @package XenForo_Attachment
 */
class XenGallery_ViewPublic_Media_Upload extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$this->_templateName = 'xengallery_media_file_upload_overlay';
	}
}
<?php

/**
 * View for displaying a form to upload more attachments, and listing those that already exist
 *
 * @package XenForo_Attachment
 */
class XenGallery_ViewPublic_Media_DoUpload extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$attach = $this->_prepareAttachmentForJson($this->_params['media']);
		$attach['key'] = (isset($this->_params['key']) ? $this->_params['key'] : '');
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($attach);
	}

	/**
	 * Reduces down an array of attachment data into information we don't mind exposing,
	 * and includes the attachment_editor_attachment template for each attachment.
	 *
	 * @param array $attachment
	 *
	 * @return array
	 */
	protected function _prepareAttachmentForJson(array $attachment)
	{
		$attachment['mediaType'] = $this->_params['upload_type'];

		if (!empty($this->_params['customFields']))
		{
			foreach ($this->_params['customFields'] AS $fieldId => &$fields)
			{
				foreach ($fields AS &$field)
				{
					if ($field['field_type'] == 'bbcode')
					{
						$field['editorTemplateHtml'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
							$this, "$attachment[mediaType][$attachment[attachment_id]][custom_fields][$field[field_id]]",
							isset($field['field_value']) ? $field['field_value'] : '',
							array(
								'height' => '90px',
								'extraClass' => 'NoAttachment NoAutoComplete'
							)
						);
					}
				}
			}
		}

		$template = $this->createTemplateObject($this->_templateName, array(
			'media' => $attachment
		) + $this->_params);

		$keys = array('attachment_id', 'attach_date', 'filename', 'thumbnailUrl', 'deleteUrl', 'mediaType');
		$attachment = XenForo_Application::arrayFilterKeys($attachment, $keys);
		$attachment['templateHtml'] = $template;

		return $attachment;
	}
}

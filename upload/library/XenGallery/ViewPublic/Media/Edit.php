<?php

class XenGallery_ViewPublic_Media_Edit extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		if (isset($this->_params['media']['media_id']))
		{
			if ($this->_params['media']['media_type'] == 'video_embed')
			{
				$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
				$html = new XenForo_BbCode_TextWrapper($this->_params['media']['media_tag'], $bbCodeParser);
				
				$this->_params['media']['videoHtml'] = $html;
				
				$this->_params += array(
					'mediaTag' => $this->_params['media']['media_tag']
				);				
			}				
		}

		$media = $this->_params['media'];

		foreach ($this->_params['customFields'] AS $fieldId => &$fields)
		{
			foreach ($fields AS &$field)
			{
				if ($field['field_type'] == 'bbcode')
				{
					$field['editorTemplateHtml'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
						$this, "$media[media_type][$media[media_id]][custom_fields][$field[field_id]]",
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
}

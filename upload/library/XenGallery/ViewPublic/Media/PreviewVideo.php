<?php

class XenGallery_ViewPublic_Media_PreviewVideo extends XenForo_ViewPublic_Base
{
	protected $_charset = 'utf-8';

	public function renderJson()
	{
		if (isset($this->_params['mediaTag']))
		{
			$mediaTag = $this->_params['mediaTag'];

			$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
			$html = new XenForo_BbCode_TextWrapper($mediaTag, $bbCodeParser);

			$this->_params['media']['videoHtml'] = $html;
			$this->_params['media']['mediaType'] = 'video_embed';

			$media = $this->_params['media'];

			foreach ($this->_params['customFields'] AS $fieldId => &$fields)
			{
				foreach ($fields AS &$field)
				{
					if ($field['field_type'] == 'bbcode')
					{
						$field['editorTemplateHtml'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
							$this, "video_embed[$media[attachment_id]][custom_fields][$field[field_id]]",
							isset($field['field_value']) ? $field['field_value'] : '',
							array(
								'height' => '90px',
								'extraClass' => 'NoAttachment NoAutoComplete'
							)
						);
					}
				}
			}

			$this->_templateName = 'xengallery_media_add_item';

			$options = XenForo_Application::getOptions();
			if ($options->xengalleryAutoGenerateVideoTitles)
			{
				list ($urlTitle, $urlDescription) = $this->_getTitleAndDescription($this->_params['embedUrl']);

				if ($urlTitle)
				{
					$this->_params['media']['media_title'] = utf8_substr($urlTitle, 0, $options->xengalleryMaxTitleLength);
				}

				if ($urlDescription)
				{
					$this->_params['media']['media_description'] = utf8_substr($urlDescription, 0, $options->xengalleryMaxDescLength);
				}
			}
		}

		if (!empty($this->_params['throwError']))
		{
			if (!empty($this->_params['notValid']))
			{
				$this->_params = array(
					'error' => new XenForo_Phrase('xengallery_not_a_valid_media_site')
				);
			}

			if (!empty($this->_params['notAllowed']))
			{
				$this->_params = array(
					'error' => new XenForo_Phrase('xengallery_use_of_this_media_site_not_allowed')
				);
			}

			return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array('error' => $this->_params['error']));
		}
	}

	/**
	 * Internal function to get title and page description etc.
	 * Slightly amended from XenForo_Helper_Url::getTitle.
	 *
	 * @return string
	 */
	protected function _getTitleAndDescription($url)
	{
		if (preg_match('#^https?://#i', $url))
		{
			try
			{
				$client = XenForo_Helper_Http::getClient($url, array(
					'timeout' => 10
				));

				$request = $client->request();

				if ($request->isSuccessful())
				{
					$html = $request->getBody();

					preg_match('#<title[^>]*>(.*?)</title>#simU', $html, $urlTitleMatches);
					preg_match('#<[\s]*meta[\s]*(name|property)="(og:|twitter:|)description"?[\s]*content="?([^>"]*)"?[\s]*[\/]?[\s]*>#simU', $html, $urlDescriptionMatches);

					$urlTitle = isset($urlTitleMatches[1]) ? $urlTitleMatches[1] : '';
					$urlTitle = $this->_prepareString($urlTitle);

					$urlDescription = isset($urlDescriptionMatches[3]) ? $urlDescriptionMatches[3] : '';
					$urlDescription = $this->_prepareString($urlDescription);

					return array($urlTitle, $urlDescription);
				}
			}
			catch (Zend_Http_Client_Exception $e)
			{
				return false;
			}
		}

		return false;
	}

	protected function _prepareString($string)
	{
		$string = htmlspecialchars_decode($string, ENT_QUOTES);
		$string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
		return $string;
	}
}
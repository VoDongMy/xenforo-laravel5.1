<?php

class XenGallery_ControllerPublic_Account extends XFCP_XenGallery_ControllerPublic_Account
{
	public function actionPreferencesSave()
	{
		if ($this->_request->isPost())
		{
			$session = false;
			if (XenForo_Application::isRegistered('session'))
			{
				/** @var $session XenForo_Session */
				$session = XenForo_Application::get('session');
			}

			$preferencesForm = $this->_input->filterSingle('xengallery_preferences_form', XenForo_Input::BOOLEAN);
			if (!$preferencesForm || !$session)
			{
				if ($session)
				{
					$session->remove('xengalleryAccountPreferences');
				}

				return parent::actionPreferencesSave();
			}

			$formData = $this->_input->filter(array(
				'xengallery_default_media_watch_state' => XenForo_Input::BOOLEAN,
				'xengallery_default_media_watch_state_email' => XenForo_Input::BOOLEAN,
				'xengallery_default_album_watch_state' => XenForo_Input::BOOLEAN,
				'xengallery_default_album_watch_state_email' => XenForo_Input::BOOLEAN,
				'xengallery_default_category_watch_state' => XenForo_Input::BOOLEAN,
				'xengallery_default_category_watch_state_email' => XenForo_Input::BOOLEAN,
				'xengallery_unviewed_media_count' => XenForo_Input::BOOLEAN
			));

			$inputData = array(
				'xengallery_default_media_watch_state' => '',
				'xengallery_default_album_watch_state' => '',
				'xengallery_default_category_watch_state' => '',
				'xengallery_unviewed_media_count' => $formData['xengallery_unviewed_media_count']
			);

			if ($formData['xengallery_default_media_watch_state'])
			{
				$inputData['xengallery_default_media_watch_state'] = 'watch_no_email';
				if ($formData['xengallery_default_media_watch_state_email'])
				{
					$inputData['xengallery_default_media_watch_state'] = 'watch_email';
				}
			}

			if ($formData['xengallery_default_album_watch_state'])
			{
				$inputData['xengallery_default_album_watch_state'] = 'watch_no_email';
				if ($formData['xengallery_default_album_watch_state_email'])
				{
					$inputData['xengallery_default_album_watch_state'] = 'watch_email';
				}
			}

			if ($formData['xengallery_default_category_watch_state'])
			{
				$inputData['xengallery_default_category_watch_state'] = 'watch_no_email';
				if ($formData['xengallery_default_category_watch_state_email'])
				{
					$inputData['xengallery_default_category_watch_state'] = 'watch_email';
				}
			}

			$session->set('xengalleryAccountPreferences', $inputData);
		}

		return parent::actionPreferencesSave();
	}
}
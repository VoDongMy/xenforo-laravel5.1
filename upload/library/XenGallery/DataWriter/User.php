<?php

class XenGallery_DataWriter_User extends XFCP_XenGallery_DataWriter_User
{
	/**
	 * Gets the fields that are defined for the table. See parent for explanation.
	 *
	 * @return array
	 */
	protected function _getFields()
	{
		$parent = parent::_getFields();

		$parent['xf_user']['xengallery_media_count'] = array(
			'type' => self::TYPE_UINT, 'default' => 0
		);

		$parent['xf_user']['xengallery_album_count'] = array(
			'type' => self::TYPE_UINT, 'default' => 0
		);

		$parent['xf_user_option']['xengallery_default_media_watch_state'] = array(
			'type' => self::TYPE_STRING, 'default' => 'watch_no_email'
		);

		$parent['xf_user_option']['xengallery_default_album_watch_state'] = array(
			'type' => self::TYPE_STRING, 'default' => 'watch_no_email'
		);

		$parent['xf_user_option']['xengallery_default_category_watch_state'] = array(
			'type' => self::TYPE_STRING, 'default' => 'watch_no_email'
		);

		$parent['xf_user_option']['xengallery_unviewed_media_count'] = array(
			'type' => self::TYPE_BOOLEAN, 'default' => true
		);

		return $parent;
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		$session = false;
		if (XenForo_Application::isRegistered('session'))
		{
			/** @var $session XenForo_Session */
			$session = XenForo_Application::get('session');
		}

		if ($session && $session->get('xengalleryAccountPreferences'))
		{
			$this->bulkSet($session->get('xengalleryAccountPreferences'));

			$session->remove('xengalleryAccountPreferences');
		}

		return parent::_preSave();
	}
}
<?php

class XenGallery_SpamHandler_Album extends XenForo_SpamHandler_Abstract
{
	/**
	 * Checks that the options array contains a non-empty 'action_threads' key
	 *
	 * @param array $user
	 * @param array $options
	 *
	 * @return boolean
	 */
	public function cleanUpConditionCheck(array $user, array $options)
	{
		return !empty($options['action_threads']);
	}

	/**
	 * @see XenForo_SpamHandler_Abstract::cleanUp()
	 */
	public function cleanUp(array $user, array &$log, &$errorKey)
	{
		/** @var $albumModel XenGallery_Model_Album */
		$albumModel = $this->getModelFromCache('XenGallery_Model_Album');
		$albums = $albumModel->getAlbums(array(
			'album_user_id' => $user['user_id'],
			'deleted' => true,
			'moderated' => true
		));

		if ($albums)
		{
			$albumIds = array_keys($albums);

			$deleteType = (XenForo_Application::getOptions()->spamMessageAction == 'delete' ? 'hard' : 'soft');

			$log['xengallery_album'] = array(
				'deleteType' => $deleteType,
				'albumIds' => $albumIds
			);

			$inlineModModel = $this->getModelFromCache('XenGallery_Model_InlineMod_Album');
			$inlineModModel->enableLogging = false;

			$ret = $inlineModModel->deleteAlbum(
				$albumIds, array('deleteType' => $deleteType, 'skipPermissions' => true), $errorKey
			);
			if (!$ret)
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * @see XenForo_SpamHandler_Abstract::restore()
	 */
	public function restore(array $log, &$errorKey = '')
	{
		if ($log['deleteType'] == 'soft')
		{
			$inlineModModel = $this->getModelFromCache('XenGallery_Model_InlineMod_Album');
			$inlineModModel->enableLogging = false;

			return $inlineModModel->undeleteAlbum(
				$log['albumIds'], array('skipPermissions' => true), $errorKey
			);
		}

		return true;
	}
}
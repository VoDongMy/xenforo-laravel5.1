<?php

class XenGallery_SpamHandler_Media extends XenForo_SpamHandler_Abstract
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
		/** @var $mediaModel XenGallery_Model_Media */
		$mediaModel = $this->getModelFromCache('XenGallery_Model_Media');
		$media = $mediaModel->getMedia(array(
			'user_id' => $user['user_id'],
			'deleted' => true,
			'moderated' => true
		));

		if ($media)
		{
			$mediaIds = array_keys($media);

			$deleteType = (XenForo_Application::getOptions()->spamMessageAction == 'delete' ? 'hard' : 'soft');

			$log['xengallery_media'] = array(
				'deleteType' => $deleteType,
				'mediaIds' => $mediaIds
			);

			$inlineModModel = $this->getModelFromCache('XenGallery_Model_InlineMod_Media');
			$inlineModModel->enableLogging = false;

			$ret = $inlineModModel->deleteMedia(
				$mediaIds, array('deleteType' => $deleteType, 'skipPermissions' => true), $errorKey
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
			$inlineModModel = $this->getModelFromCache('XenGallery_Model_InlineMod_Media');
			$inlineModModel->enableLogging = false;

			return $inlineModModel->undeleteMedia(
				$log['mediaIds'], array('skipPermissions' => true), $errorKey
			);
		}

		return true;
	}
}
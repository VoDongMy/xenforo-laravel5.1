<?php

class XenGallery_SpamHandler_Comment extends XenForo_SpamHandler_Abstract
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
		return !empty($options['delete_messages']);
	}

	/**
	 * @see XenForo_SpamHandler_Abstract::cleanUp()
	 */
	public function cleanUp(array $user, array &$log, &$errorKey)
	{
		/** @var $commentModel XenGallery_Model_Comment */
		$commentModel = $this->getModelFromCache('XenGallery_Model_Comment');
		$comments = $commentModel->getComments(array(
			'user_id' => $user['user_id'],
			'deleted' => true,
			'moderated' => true
		));

		if ($comments)
		{
			$commentIds = array_keys($comments);

			$deleteType = (XenForo_Application::getOptions()->spamMessageAction == 'delete' ? 'hard' : 'soft');

			$log['xengallery_comment'] = array(
				'deleteType' => $deleteType,
				'commentIds' => $commentIds
			);

			$inlineModModel = $this->getModelFromCache('XenGallery_Model_InlineMod_Comment');
			$inlineModModel->enableLogging = false;

			$ret = $inlineModModel->deleteComment(
				$commentIds, array('deleteType' => $deleteType, 'skipPermissions' => true), $errorKey
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
			$inlineModModel = $this->getModelFromCache('XenGallery_Model_InlineMod_Comment');
			$inlineModModel->enableLogging = false;

			return $inlineModModel->undeleteComment(
				$log['commentIds'], array('skipPermissions' => true), $errorKey
			);
		}

		return true;
	}
}
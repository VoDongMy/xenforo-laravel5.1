<?php

class XenGallery_ModerationQueueHandler_Media extends XenForo_ModerationQueueHandler_Abstract
{
	/**
	 * Gets visible moderation queue entries for specified user.
	 *
	 * @see XenForo_ModerationQueueHandler_Abstract::getVisibleModerationQueueEntriesForUser()
	 */
	public function getVisibleModerationQueueEntriesForUser(array $contentIds, array $viewingUser)
	{
		/* @var $mediaModel XenGallery_Model_Media */
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');
		$media = $mediaModel->getMediaByIds($contentIds, array('join' => XenGallery_Model_Media::FETCH_CATEGORY));

		$output = array();
		foreach ($media AS $item)
		{
			$canManage = $mediaModel->canManageModeratedMedia($item);

			if ($canManage)
			{
				$output[$item['media_id']] = array(
					'message' => $item['media_description'],
					'user' => array(
						'user_id' => $item['user_id'],
						'username' => $item['username']
					),
					'title' => $item['media_title'],
					'link' => XenForo_Link::buildPublicLink('xengallery', $item),
					'contentTypeTitle' => new XenForo_Phrase('xengallery_moderated_media'),
					'titleEdit' => false
				);
			}
		}

		return $output;
	}

	/**
	 * Approves the specified moderation queue entry.
	 *
	 * @see XenForo_ModerationQueueHandler_Abstract::approveModerationQueueEntry()
	 */
	public function approveModerationQueueEntry($contentId, $message, $title)
	{
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Media', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('media_state', 'visible');

		if ($dw->save())
		{
			XenForo_Model_Log::logModeratorAction('xengallery_media', $dw->getMergedData(), 'approve');

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Deletes the specified moderation queue entry.
	 *
	 * @see XenForo_ModerationQueueHandler_Abstract::deleteModerationQueueEntry()
	 */
	public function deleteModerationQueueEntry($contentId)
	{
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Media', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('media_state', 'deleted');

		if ($dw->save())
		{
			XenForo_Model_Log::logModeratorAction('xengallery_media', $dw->getMergedData(), 'delete_soft', array('reason' => ''));

			return true;
		}
		else
		{
			return false;
		}
	}
}
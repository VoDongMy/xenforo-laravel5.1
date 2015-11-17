<?php

/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
class sonnb_XenGallery_ModeratorLogHandler_Comment extends XenForo_ModeratorLogHandler_Abstract
{
	/**
	 * @param array $logUser
	 * @param array $content
	 * @param $action
	 * @param array $actionParams
	 * @param null $parentContent
	 * @return mixed
	 */
	protected function _log(array $logUser, array $content, $action, array $actionParams = array(), $parentContent = null)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ModeratorLog');
		$dw->bulkSet(array(
			'user_id' => $logUser['user_id'],
			'content_type' => sonnb_XenGallery_Model_Comment::$xfContentType,
			'content_id' => $content['comment_id'],
			'content_user_id' => $content['user_id'],
			'content_username' => $content['username'],
			'content_title' => '',
			'content_url' => XenForo_Link::buildPublicLink('gallery/comments', $content),
			'discussion_content_type' => sonnb_XenGallery_Model_Comment::$xfContentType,
			'discussion_content_id' => $content['comment_id'],
			'action' => $action,
			'action_params' => $actionParams
		));
		$dw->save();

		return $dw->get('moderator_log_id');
	}

	/**
	 * @param array $entry
	 * @return array
	 */
	protected function _prepareEntry(array $entry)
	{
		$elements = json_decode($entry['action_params'], true);

		$entry['content_title'] = 'Comment id: '.$entry['content_id'];

		$entry['actionText'] = new XenForo_Phrase(
			'sonnb_xengallery_moderator_log_comment_'.$entry['action'],
			array('elements' => implode(', ', array_keys($elements)))
		);
			
		return $entry;
	}
}
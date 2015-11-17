<?php

class XenGallery_ModeratorLogHandler_Comment extends XenForo_ModeratorLogHandler_Abstract
{
	protected function _log(array $logUser, array $content, $action, array $actionParams = array(), $parentContent = null)
	{
		$contentType = $content['content_type'];

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ModeratorLog');
		$dw->bulkSet(array(
			'user_id' => $logUser['user_id'],
			'content_type' => 'xengallery_comment',
			'content_id' => $content['comment_id'],
			'content_user_id' => $content['user_id'],
			'content_username' => $content['username'],
			'content_title' => utf8_substr($content[$contentType . '_title'], 0, 100) . " ($contentType)",
			'content_url' => XenForo_Link::buildPublicLink('xengallery/comments', $content),
			'discussion_content_type' => 'xengallery_' . $contentType,
			'discussion_content_id' => $contentType . '_id',
			'action' => $action,
			'action_params' => $actionParams
		));
		$dw->save();

		return $dw->get('moderator_log_id');
	}
}
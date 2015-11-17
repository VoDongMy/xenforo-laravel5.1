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
class sonnb_XenGallery_Model_Attachment extends XenForo_Model_Attachment
{
	public function getAttachmentsForXenGalleryByThreadId($threadId)
	{
		$attachments = $this->fetchAllKeyed("
				SELECT attachment.*, attachment_data.*, post.username, post.user_id
				FROM xf_attachment AS attachment
					LEFT JOIN xf_attachment_data AS attachment_data
						ON (attachment.data_id = attachment_data.data_id)
					LEFT JOIN xf_post AS post
						ON (attachment.content_id = post.post_id)
				WHERE post.thread_id = ?
					AND attachment.content_type = 'post'
					AND post.message_state = 'visible'
				ORDER BY attachment.attachment_id ASC", 'attachment_id', $threadId);

		if ($attachments)
		{
			foreach ($attachments as $attachId => $attachment)
			{
				$extension = XenForo_Helper_File::getFileExtension($attachment['filename']);
				if (!in_array($extension, array('gif','png','jpg', 'jpeg', 'jpe')))
				{
					unset($attachments[$attachId]);
				}
			}
		}

		return $attachments;
	}

	public function getAttachmentsForXenGalleryByAttachmentIds($attachmentIds)
	{
		$db = $this->_getDb();

		$attachments = $this->fetchAllKeyed("
				SELECT attachment.*, attachment_data.*, post.username, post.user_id
				FROM xf_attachment AS attachment
					LEFT JOIN xf_attachment_data AS attachment_data
						ON (attachment.data_id = attachment_data.data_id)
					LEFT JOIN xf_post AS post
						ON (attachment.content_id = post.post_id)
				WHERE attachment.attachment_id IN (". $db->quote($attachmentIds) .")
					AND attachment.content_type = 'post'
					AND post.message_state = 'visible'
				ORDER BY attachment.attachment_id ASC", 'attachment_id');

		if ($attachments)
		{
			foreach ($attachments as $attachId => $attachment)
			{
				$extension = XenForo_Helper_File::getFileExtension($attachment['filename']);
				if (!in_array($extension, array('gif','png','jpg', 'jpeg', 'jpe')))
				{
					unset($attachments[$attachId]);
				}
			}
		}

		return $attachments;
	}
}
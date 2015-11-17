<?php

class XenGallery_Model_Moderator extends XFCP_XenGallery_Model_Moderator
{
	public function getGeneralModeratorInterfaceGroupIds()
	{
		$ids = parent::getGeneralModeratorInterfaceGroupIds();

		$moderatorInterfaceGroupIds = array(
			'xengalleryMediaModeratorPermissions', 'xengalleryAlbumModeratorPermissions',
			'xengalleryWatermarkModeratorPermissions', 'xengalleryCommentModeratorPermissions'
		);

		foreach ($moderatorInterfaceGroupIds AS $groupId)
		{
			$ids[] = $groupId;
		}

		return $ids;
	}
}
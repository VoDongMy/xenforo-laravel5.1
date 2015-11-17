<?php

class XenGallery_ControllerPublic_Member extends XFCP_XenGallery_ControllerPublic_Member
{
	protected function _getNotableMembers($type, $limit)
	{
		if ($type == 'xengallery_media' && XenForo_Visitor::getInstance()->hasPermission('xengallery', 'view'))
		{
			$userModel = $this->_getUserModel();

			$notableCriteria = array(
				'is_banned' => 0
			);

			$users = $userModel->getUsers($notableCriteria, array(
				'join' => XenForo_Model_User::FETCH_USER_FULL,
				'limit' => $limit,
				'order' => 'xengallery_media_count',
				'direction' => 'desc'
			));

			foreach ($users AS $userId => $user)
			{
				if ($user['xengallery_media_count'] < 1)
				{
					unset($users[$userId]);
				}
			}

			return array($users, 'xengallery_media_count');
		}

		if ($type == 'xengallery_album' && XenForo_Visitor::getInstance()->hasPermission('xengallery', 'view'))
		{
			$userModel = $this->_getUserModel();

			$notableCriteria = array(
				'is_banned' => 0
			);

			$users = $userModel->getUsers($notableCriteria, array(
				'join' => XenForo_Model_User::FETCH_USER_FULL,
				'limit' => $limit,
				'order' => 'xengallery_album_count',
				'direction' => 'desc'
			));

			foreach ($users AS $userId => $user)
			{
				if ($user['xengallery_album_count'] < 1)
				{
					unset($users[$userId]);
				}
			}

			return array($users, 'xengallery_album_count');
		}

		return parent::_getNotableMembers($type, $limit);
	}
}
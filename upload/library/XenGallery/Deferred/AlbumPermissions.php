<?php

class XenGallery_Deferred_AlbumPermissions extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 30
		), $data);

		$db = XenForo_Application::getDb();

		$tableExists = $db->fetchOne('SHOW TABLES LIKE ' . XenForo_Db::quoteLike('xengallery_album', ''));
		if (!$tableExists)
		{
			return true;
		}

		/* @var $albumModel XenGallery_Model_Album */
		$albumModel = XenForo_Model::create('XenGallery_Model_Album');

		$albumIds = $albumModel->getAlbumIdsInRange($data['position'], $data['batch']);
		if (sizeof($albumIds) == 0)
		{
			return true;
		}

		foreach ($albumIds AS $albumId)
		{
			$data['position'] = $albumId;
			$album = $albumModel->getAlbumByIdSimple($albumId);

			$viewPermission = $albumModel->getUserAlbumPermission($albumId, 'view');
			if ($viewPermission)
			{
				switch ($viewPermission['access_type'])
				{
					case 'private':

						if (!$albumModel->isUserMappedToAlbum($album['album_id'], $album['album_user_id'], 'private'))
						{
							$albumModel->mapUserToAlbum($album['album_id'], $album['album_user_id'], 'private');
						}
						break;

					case 'shared':

						if (!$albumModel->isUserMappedToAlbum($album['album_id'], $album['album_user_id'], 'shared'))
						{
							$albumModel->mapUserToAlbum($album['album_id'], $album['album_user_id'], 'shared');
						}
						break;

					default:

						break;
				}
			}

			if (!$album || $viewPermission)
			{
				continue;
			}

			if ((!array_key_exists('album_privacy', $album) || !array_key_exists('album_share_users', $album))
				&& !$viewPermission
			)
			{
				$album['album_privacy'] = 'private';
				$album['album_share_users'] = array();
			}

			$albumPermissionData = array(
				'album_id' => $album['album_id'],
				'permission' => 'view',
				'access_type' => $album['album_privacy'],
				'share_users' => $this->_prepareShareUsers($album['album_share_users'])
			);

			$albumPermissionDw = XenForo_DataWriter::create('XenGallery_DataWriter_AlbumPermission', XenForo_DataWriter::ERROR_SILENT);
			$albumPermissionDw->setExtraData(XenGallery_DataWriter_AlbumPermission::DATA_ALBUM_USER_ID, $album['album_user_id']);
			$albumPermissionDw->setOption(XenGallery_DataWriter_AlbumPermission::OPTION_SKIP_ALERTS, true);
			$albumPermissionDw->bulkSet($albumPermissionData);
			$albumPermissionDw->save();

			if ($albumModel->getUserAlbumPermission($albumId, 'add'))
			{
				continue;
			}

			$albumPermissionData = array(
				'album_id' => $album['album_id'],
				'permission' => 'add',
				'access_type' => 'private',
				'share_users' => array()
			);

			$albumPermissionDw = XenForo_DataWriter::create('XenGallery_DataWriter_AlbumPermission', XenForo_DataWriter::ERROR_SILENT);
			$albumPermissionDw->setOption(XenGallery_DataWriter_AlbumPermission::OPTION_SKIP_ALERTS, true);
			$albumPermissionDw->bulkSet($albumPermissionData);
			$albumPermissionDw->save();
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = 'Album permissions';
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	protected function _prepareShareUsers($shareUsers)
	{
		$array = @unserialize($shareUsers);
		if (!is_array($array))
		{
			$array = array();
		}

		return $array;
	}

	public function canTriggerManually()
	{
		return true;
	}
}
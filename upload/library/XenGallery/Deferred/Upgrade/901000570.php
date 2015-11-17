<?php

class XenGallery_Deferred_Upgrade_901000570 extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 10
		), $data);
		$data['batch'] = max(1, $data['batch']);

		/* @var $albumModel XenGallery_Model_Album */
		$albumModel = XenForo_Model::create('XenGallery_Model_Album');

		$albumIds = $albumModel->getSharedAlbumIdsInRange($data['position'], $data['batch']);
		if (sizeof($albumIds) == 0)
		{
			return true;
		}

		$db = XenForo_Application::getDb();
		XenForo_Db::beginTransaction($db);

		foreach ($albumIds AS $albumId)
		{
			$data['position'] = $albumId;

			$album = $albumModel->getAlbumByIdSimple($albumId);
			$bind = array(
				$album['album_id'],
				$album['album_user_id']
			);
			$ownerShared = $db->fetchOne(
				'SELECT shared_user_id FROM xengallery_shared_map WHERE album_id = ? AND shared_user_id = ?', $bind
			);
			if (!$ownerShared)
			{
				$db->query('
					INSERT IGNORE INTO xengallery_shared_map
						(album_id, shared_user_id)
					VALUES
						(?, ?)
				', $bind);
			}
		}

		XenForo_Db::commit($db);

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('xengallery_album_permissions');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canTriggerManually()
	{
		return false;
	}
}
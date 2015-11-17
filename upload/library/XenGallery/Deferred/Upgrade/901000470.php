<?php

class XenGallery_Deferred_Upgrade_901000470 extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'queryKeys' => array(
				'xengallery_media_drop',
				'xengallery_media_add',
				'xengallery_album',
				'xengallery_comment',
				'xengallery_user_tag',
				'xf_user'
			)
		), $data);

		if (!$data['queryKeys'])
		{
			return true;
		}

		$s = microtime(true);
		$db = XenForo_Application::getDb();
		$status = sprintf('%s... %s %s', 'Adding', 'XFMG Table Indexes', str_repeat(' . ', $data['position']));

		foreach ($data['queryKeys'] AS $key => $name)
		{
			$data['position']++;

			$query = $this->_getQueryToExecute($name);
			if (!$query)
			{
				continue;
			}

			try
			{
				$db->query($query);
				unset ($data['queryKeys'][$key]);
			}
			catch (Zend_Db_Exception $e)
			{
				if ($name != 'xengallery_media_drop') // skip logging an error about this as an error here may be expected.
				{
					XenForo_Error::logException($e, false, "XenForo Media Gallery: Error adding index(es) ($name): ");
				}

				unset ($data['queryKeys'][$key]);
				continue;
			}

			if ($targetRunTime && microtime(true) - $s > $targetRunTime)
			{
				break;
			}
		}

		return $data;
	}

	protected function _getQueryToExecute($name)
	{
		$queries = array(
			'xengallery_media_drop' => 'ALTER TABLE xengallery_media DROP INDEX user_id',
			'xengallery_media_add' => 'ALTER TABLE xengallery_media ADD INDEX user_id_media_date (user_id, media_date), ADD INDEX album_id_media_date (album_id, media_date), ADD INDEX category_id_media_date (category_id, media_date)',
			'xengallery_album' => 'ALTER TABLE xengallery_album ADD INDEX album_create_date (album_create_date), ADD INDEX album_user_id_album_create_date (album_user_id, album_create_date)',
			'xengallery_comment' => 'ALTER TABLE xengallery_comment ADD INDEX comment_date (comment_date), ADD INDEX content_type_content_id_comment_date (content_type, content_id, comment_date)',
			'xengallery_user_tag' => 'ALTER TABLE xengallery_user_tag ADD INDEX media_id (media_id)',
			'xf_user' => 'ALTER TABLE xf_user ADD INDEX xengallery_media_count (xengallery_media_count), ADD INDEX xengallery_album_count (xengallery_album_count)'
		);

		return isset($queries[$name]) ? $queries[$name] : null;
	}

	public function canTriggerManually()
	{
		return false;
	}
}
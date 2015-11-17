<?php

class XenGallery_Deferred_Upgrade_901010070 extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 10
		), $data);
		$data['batch'] = max(1, $data['batch']);

		/* @var $mediaModel XenGallery_Model_Media */
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		/** @var XenForo_Model_Tag $tagModel */
		$tagModel = XenForo_Model::create('XenForo_Model_Tag');

		$tagIds = $mediaModel->getTagIdsInRange($data['position'], $data['batch']);
		if (sizeof($tagIds) == 0)
		{
			return true;
		}

		$db = XenForo_Application::getDb();

		foreach ($tagIds AS $oldTagId)
		{
			$data['position'] = $oldTagId;

			$xfmgTag = $db->fetchRow('SELECT * FROM xengallery_content_tag WHERE tag_id = ?', $oldTagId);
			$tagMap = $db->fetchAll('SELECT * FROM xengallery_content_tag_map WHERE tag_id = ?', $oldTagId);

			if (!$xfmgTag || !$tagMap)
			{
				continue;
			}

			$tagId = $tagModel->createTag($xfmgTag['tag_name']);

			if (!$tagId)
			{
				continue;
			}

			$tag = $tagModel->getTagById($tagId);

			$mediaIds = array();
			$media = array();

			foreach ($tagMap AS $tagUse)
			{
				$mediaIds[] = $tagUse['media_id'];
			}

			if ($mediaIds)
			{
				$media = $mediaModel->getMediaByIds($mediaIds);
			}

			foreach ($tagMap AS $tagUse)
			{
				if (!isset($media[$tagUse['media_id']]))
				{
					continue;
				}

				$item = $media[$tagUse['media_id']];

				try
				{
					$db->insert('xf_tag_content', array(
						'content_type' => 'xengallery_media',
						'content_id' => $tagUse['media_id'],
						'tag_id' => $tag['tag_id'],
						'add_user_id' => $item['user_id'],
						'add_date' => $item['media_date'],
						'visible' => ($item['media_state'] == 'visible'),
						'content_date' => $item['media_date']
					));
				}
				catch (Zend_Db_Exception $e) { continue; }

				$tagModel->recalculateTagUsageByContentTagged('xengallery_media', $item['media_id']);
				$tagModel->rebuildTagCache('xengallery_media', $item['media_id']);
			}
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('tags');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canTriggerManually()
	{
		return false;
	}
}
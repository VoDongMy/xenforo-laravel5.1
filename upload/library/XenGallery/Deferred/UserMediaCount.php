<?php

class XenGallery_Deferred_UserMediaCount extends XenForo_Deferred_Abstract
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

		/* @var $userModel XenForo_Model_User */
		$userModel = XenForo_Model::create('XenForo_Model_User');

		$userIds = $userModel->getUserIdsInRange($data['position'], $data['batch']);
		if (sizeof($userIds) == 0)
		{
			return true;
		}

		$mediaModel->rebuildUserMediaCounts($userIds);

		$data['position'] = end($userIds);

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('xengallery_rebuilding_user_media_counts');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}
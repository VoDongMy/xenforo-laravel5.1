<?php

class XenGallery_Deferred_MediaCommentCount extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 5,
			'positionRebuild' => false
		), $data);
		$data['batch'] = max(1, $data['batch']);

		/* @var $mediaModel XenGallery_Model_Media */
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		/* @var $commentModel XenGallery_Model_Comment */
		$commentModel = XenForo_Model::create('XenGallery_Model_Comment');

		$mediaIds = $mediaModel->getMediaIdsInRange($data['position'], $data['batch'], 'all');
		if (sizeof($mediaIds) == 0)
		{
			return true;
		}

		foreach ($mediaIds AS $mediaId)
		{
			$conditions = array(
				'media_id' => $mediaId
			);
			$count = $commentModel->countComments($conditions);

			$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
			$mediaWriter->setExistingData($mediaId);

			$mediaWriter->set('comment_count', $count);

			$mediaWriter->save();

			if ($data['positionRebuild'])
			{
				// $mediaWriter->rebuildCommentPositions();
			}

			$data['position'] = $mediaId;
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('xengallery_rebuilding_media_comment_counts');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}
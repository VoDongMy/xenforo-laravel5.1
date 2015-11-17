<?php

class XenGallery_Deferred_AlbumCommentCount extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 5,
			'positionRebuild' => false
		), $data);
		$data['batch'] = max(1, $data['batch']);

		/* @var $albumModel XenGallery_Model_Album */
		$albumModel = XenForo_Model::create('XenGallery_Model_Album');

		/* @var $commentModel XenGallery_Model_Comment */
		$commentModel = XenForo_Model::create('XenGallery_Model_Comment');

		$albumIds = $albumModel->getAlbumIdsInRange($data['position'], $data['batch']);
		if (sizeof($albumIds) == 0)
		{
			return true;
		}


		foreach ($albumIds AS $albumId)
		{
			$conditions = array(
				'album_id' => $albumId
			);
			$count = $commentModel->countComments($conditions);

			$albumWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
			$albumWriter->setExistingData($albumId);

			$albumWriter->set('album_comment_count', $count);

			$albumWriter->save();

			if ($data['positionRebuild'])
			{
				// $albumWriter->rebuildCommentPositions();
			}

			$data['position'] = $albumId;
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('xengallery_rebuilding_album_comment_counts');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}
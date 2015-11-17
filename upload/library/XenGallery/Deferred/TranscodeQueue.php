<?php

class XenGallery_Deferred_TranscodeQueue extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		/* @var $queueModel XenGallery_Model_Transcode */
		$queueModel = XenForo_Model::create('XenGallery_Model_Transcode');

		if (!$queueModel->countQueue())
		{
			return false; // no more work to do
		}

		$options = XenForo_Application::getOptions();
		$transcodingLimit = $options->get('xengalleryVideoTranscoding', 'limit');

		$count = $queueModel->countQueue('processing');

		if ($count >= $transcodingLimit)
		{
			XenForo_Application::defer(
				'XenGallery_Deferred_TranscodeQueue', array(), 'TranscodeQueue', false, time() + 30
			);

			return false; // Currently busy. Re-queue and check again in half a minute.
		}

		$hasMore = $queueModel->runTranscodeQueue($transcodingLimit - $count);
		if ($hasMore)
		{
			// wait a little bit as our queue is likely full
			XenForo_Application::defer(
				'XenGallery_Deferred_TranscodeQueue', array(), 'TranscodeQueue', false, time() + 30
			);

			return false;
		}
		else
		{
			return false; // no more work to do
		}
	}
}
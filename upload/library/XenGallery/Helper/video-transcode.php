<?php

// CLI only
if (PHP_SAPI != 'cli')
{
	die('This script may only be run at the command line.');
}

$fileDir = realpath(dirname(__FILE__) . '/../../../');
chdir($fileDir);

require_once($fileDir . '/library/XenForo/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');

XenForo_Application::initialize($fileDir . '/library', $fileDir);

$dependencies = new XenForo_Dependencies_Public();
$dependencies->preLoadData();

set_time_limit(0);

if ($argc < 2)
{
	if (empty($argv[1]))
	{
		die('No queue ID specified.');
	}
}

/** @var XenGallery_Model_Transcode $model */
$model = XenForo_Model::create('XenGallery_Model_Transcode');
$queueRecord = $model->getTranscodeQueueItem($argv[1]);

if (!$queueRecord)
{
	die('Queue record no longer exists.');
}

if (!$model->setQueueItemProcessing($queueRecord['transcode_queue_id']))
{
	die('Queue record already set to processing by another process');
}

$queueRecord['queue_data'] = @unserialize($queueRecord['queue_data']);
$video = new XenGallery_Helper_Video($queueRecord['queue_data']['filename']);

$outputFile = $video->transcodeProcess();
$video->finalizeTranscode($queueRecord, $outputFile);
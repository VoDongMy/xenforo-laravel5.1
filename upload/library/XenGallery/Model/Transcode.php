<?php

class XenGallery_Model_Transcode extends XenForo_Model
{
	public function insertTranscodeQueue(array $data)
	{
		XenForo_Application::getDb()->insert('xengallery_transcode_queue', array(
			'queue_data' => @serialize($data),
			'queue_date' => XenForo_Application::$time
		));

		if (!$this->isDeferredQueued())
		{
			try
			{
				XenForo_Application::defer('XenGallery_Deferred_TranscodeQueue', array(), 'TranscodeQueue');
			}
			catch (Exception $e)
			{
				// need to just ignore this and let it get picked up later
				XenForo_Error::logException($e, false);
			}
		}

		return true;
	}

	public function getTranscodeQueue($limit = 20)
	{
		return $this->fetchAllKeyed($this->limitQueryResults('
			SELECT *
			FROM xengallery_transcode_queue
			ORDER BY queue_date
		', $limit), 'transcode_queue_id');
	}

	public function getRunnableTranscodeQueue($limit = 20)
	{
		return $this->fetchAllKeyed($this->limitQueryResults('
			SELECT *
			FROM xengallery_transcode_queue
			WHERE queue_state = \'pending\'
			ORDER BY queue_date
		', $limit), 'transcode_queue_id');
	}

	public function getTranscodeQueueItem($queueId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xengallery_transcode_queue
			WHERE transcode_queue_id = ?
		', $queueId);
	}

	public function setQueueItemProcessing($queueId)
	{
		$result = $this->_getDb()->query("
			UPDATE xengallery_transcode_queue
			SET queue_state = 'processing'
			WHERE transcode_queue_id = ?
				AND queue_state = 'pending'
		", $queueId);

		return $result->rowCount() > 0;
	}

	public function countQueue($state = null)
	{
		$db = $this->_getDb();

		$where = '1=1';
		if ($state !== null)
		{
			if (!is_array($state))
			{
				$state = array($state);
			}

			$where = 'queue_state IN(' . $db->quote($state) . ')';
		}

		return $db->fetchOne('
			SELECT COUNT(*)
			FROM xengallery_transcode_queue
			WHERE ' . $where . '
		');
	}

	public function runTranscodeQueue($limit)
	{
		$pending = $this->countQueue('pending');

		$queue = $this->getRunnableTranscodeQueue($limit);
		if (!$queue)
		{
			return false;
		}

		$video = new XenGallery_Helper_Video();
		$db = $this->_getDb();

		foreach ($queue AS $id => $record)
		{
			$pending--;

			$data = @unserialize($record['queue_data']);
			if ($video->setFilename($data['filename']))
			{
				$video->beginTranscode($record);
			}
			else
			{
				$this->sendTranscodeAlert($data['media'], false);
				$db->delete(
					'xengallery_transcode_queue',
					'transcode_queue_id = ' . $db->quote($id)
				);
			}
		}

		return ($pending > 0);
	}

	public function findPhpExecutable()
	{
		if (defined('PHP_BINARY') && PHP_BINARY && is_file(PHP_BINARY))
		{
			return PHP_BINARY;
		}

		if ($php = getenv('PHP_PATH'))
		{
			if (!is_executable($php))
			{
				return false;
			}
			return $php;
		}

		if ($php = getenv('PHP_PEAR_PHP_BIN'))
		{
			if (is_executable($php))
			{
				return $php;
			}
		}

		$binDir = PHP_BINDIR;

		$suffixes = array('');
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
		{
			$typicalSuffixes = array('.exe', '.bat', '.cmd', '.com');

			$pathExt = getenv('PATHEXT');
			$suffixes = $pathExt ? explode(PATH_SEPARATOR, $pathExt) : $typicalSuffixes;
		}

		foreach ($suffixes AS $suffix)
		{
			if (is_file($file = $binDir . DIRECTORY_SEPARATOR . 'php' . $suffix)
				&& ('\\' === DIRECTORY_SEPARATOR || is_executable($file))
			)
			{
				return $file;
			}
		}

		return false;
	}

	public function sendTranscodeAlert(array $media, $success)
	{
		if (!$media['user_id'])
		{
			return;
		}

		if ($success)
		{
			$contentType = 'xengallery_media';
			$contentId = $media['media_id'];
			$action = 'video_transcode_success';
		}
		else
		{
			$contentType = 'user';
			$contentId = $media['user_id'];
			$action = 'xfmg_video_transcode_failed';
		}

		XenForo_Model_Alert::alert(
			$media['user_id'],
			0, '',
			$contentType,
			$contentId,
			$action,
			array('media' => $media)
		);
	}

	public function isDeferredQueued()
	{
		/** @var XenForo_Model_Deferred $deferredModel */
		$deferredModel = $this->getModelFromCache('XenForo_Model_Deferred');

		return (bool)$deferredModel->getDeferredByKey('TranscodeQueue');
	}
}
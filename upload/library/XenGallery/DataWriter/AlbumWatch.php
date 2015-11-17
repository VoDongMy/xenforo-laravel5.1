<?php

class XenGallery_DataWriter_AlbumWatch extends XenForo_DataWriter
{
	protected function _getFields()
	{
		return array(
			'xengallery_album_watch' => array(
				'user_id'    => array('type' => self::TYPE_UINT,    'required' => true),
				'album_id'    => array('type' => self::TYPE_UINT,    'required' => true),
				'notify_on'  => array('type' => self::TYPE_STRING, 'default' => '',
					'allowedValues' => array('', 'media', 'comment', 'media_comment')
				),
				'send_alert' => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'send_email' => array('type' => self::TYPE_BOOLEAN, 'default' => 0)
			)
		);
	}

	/**
	 * Gets the actual existing data out of data that was passed in. See parent for explanation.
	 *
	 * @param mixed
	 *
	 * @return array|false
	 */
	protected function _getExistingData($data)
	{
		if (!is_array($data))
		{
			return false;
		}
		else if (isset($data['user_id'], $data['album_id']))
		{
			$userId = $data['user_id'];
			$albumId = $data['album_id'];
		}
		else if (isset($data[0], $data[1]))
		{
			$userId = $data[0];
			$albumId = $data[1];
		}
		else
		{
			return false;
		}

		return array('xengallery_album_watch' => $this->_getAlbumWatchModel()->getUserAlbumWatchByAlbumId($userId, $albumId));
	}

	/**
	 * Gets SQL condition to update the existing record.
	 *
	 * @return string
	 */
	protected function _getUpdateCondition($tableName)
	{
		return 'user_id = ' . $this->_db->quote($this->getExisting('user_id'))
		. ' AND album_id = ' . $this->_db->quote($this->getExisting('album_id'));
	}

	/**
	 * @return XenGallery_Model_AlbumWatch
	 */
	protected function _getAlbumWatchModel()
	{
		return $this->getModelFromCache('XenGallery_Model_AlbumWatch');
	}
}
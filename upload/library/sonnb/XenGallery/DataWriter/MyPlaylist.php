<?php

/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
class sonnb_XenGallery_DataWriter_MyPlaylist extends sonnb_XenGallery_DataWriter_Abstract
{
	protected function _getFields()
	{
		return array(
			'sonnb_xengallery_myplaylist' => array(
				'playlist_id' => array(
					'type' => self::TYPE_UINT,
					'autoIncrement' => true
				),
				'title' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 255,
					'required' => true
				),
				'description' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 1000,
					'default' => '',
				),
				'user_id' => array(
					'type' => self::TYPE_UINT,
					'required' => true
				),
				'username' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 50,
					'required' => true
				),
				'content_count' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'icon_date' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'added_date' => array(
					'type' => self::TYPE_UINT,
					'default' => XenForo_Application::$time,
				),
				'updated_date' => array(
					'type' => self::TYPE_UINT,
					'default' => XenForo_Application::$time,
				),
			)
		);
	}

	protected function _getExistingData($data)
	{
		$id = $this->_getExistingPrimaryKey($data, 'playlist_id');
		if (!$id)
		{
			return false;
		}

		$playlist = $this->_getMyPlaylistModel()->getPlaylistById($id);
		if (!$playlist)
		{
			return false;
		}

		return $this->getTablesDataFromArray($playlist);
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'playlist_id = ' . $this->_db->quote($this->getExisting('playlist_id'));
	}

	protected function _preSave()
	{
		if ($this->isInsert())
		{
			$visitor = XenForo_Visitor::getInstance();

			$this->bulkSet(array(
				'user_id' => $visitor['user_id'],
				'username' => $visitor['username'],
				'added_date' => XenForo_Application::$time,
				'updated_date' => XenForo_Application::$time
			));
		}

		if ($this->isUpdate())
		{
			$this->set('updated_date', XenForo_Application::$time);
		}
	}

	protected function _postDelete()
	{
		$db = XenForo_Application::getDb();
		$db->delete(
			'sonnb_xengallery_myplaylist_item',
			array(
				'playlist_id = '. $this->get('playlist_id')
			)
		);
	}

	public function insertPlaylistItem($contentType, $contentId, array $viewingUser)
	{
		if (!$contentType || !$contentId)
		{
			return false;
		}

		$db = XenForo_Application::getDb();
		$exist = $db->fetchRow("
			SELECT *
			FROM sonnb_xengallery_myplaylist_item AS myplaylist_item
			LEFT JOIN sonnb_xengallery_myplaylist AS myplaylist
				ON (myplaylist.playlist_id = myplaylist_item.playlist_id)
			WHERE myplaylist_item.content_type = ?
				AND myplaylist_item.content_id = ?
				AND myplaylist.user_id = ?",
			array($contentType, $contentId, $viewingUser['user_id']));

		if ($exist)
		{
			return new XenForo_Phrase(
				'sonnb_xengallery_this_'.$contentType.'_already_in_your_playlist_x',
				array(
					'playlist' => $exist['title']
				)
			);
		}
		else
		{
			$success = $db->insert(
				'sonnb_xengallery_myplaylist_item',
				array(
					'playlist_id' => $this->get('playlist_id'),
					'content_id' => $contentId,
					'content_type' => $contentType,
					'added_date' => XenForo_Application::$time
				)
			);

			if ($success)
			{
				$this->set('content_count', $this->get('content_count') + 1);
			}
		}

		return true;
	}

	public function deletePlaylistItem($contentType, $contentId, array $viewingUser)
	{
		if (!$contentType || !$contentId)
		{
			return false;
		}

		$db = XenForo_Application::getDb();
		$success = $db->delete('sonnb_xengallery_myplaylist_item', array(
			'playlist_id = ' . $this->get('playlist_id'),
			'content_id = ' . $db->quote($contentId),
			'content_type = ' . $db->quote($contentType)
		));

		if ($success)
		{
			$this->set('content_count', $this->get('content_count') - 1);
		}
	}
}
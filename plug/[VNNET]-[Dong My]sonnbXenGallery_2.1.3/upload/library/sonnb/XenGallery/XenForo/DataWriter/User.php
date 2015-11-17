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
class sonnb_XenGallery_XenForo_DataWriter_User extends XFCP_sonnb_XenGallery_XenForo_DataWriter_User
{
	protected function _getFields()
	{
		$fields = parent::_getFields();
		
		$fields['xf_user']['sonnb_xengallery_album_count'] = array(
			'type' => self::TYPE_UINT,
			'default' => 0
		);

		$fields['xf_user']['sonnb_xengallery_photo_count'] = array(
			'type' => self::TYPE_UINT,
			'default' => 0
		);

		$fields['xf_user']['sonnb_xengallery_video_count'] = array(
			'type' => self::TYPE_UINT,
			'default' => 0
		);

		$fields['xf_user']['sonnb_xengallery_cover'] = array(
			'type' => self::TYPE_SERIALIZED,
			'default' => 'a:0:{}'
		);

        $fields['xf_user_option']['sonnb_xengallery_watermark'] = array(
            'type' => self::TYPE_SERIALIZED,
            'default' => 'a:0:{}'
        );

		$fields['xf_user_option']['xengallery'] = array(
			'type' => self::TYPE_SERIALIZED,
			'default' => 'a:0:{}'
		);

		return $fields;
	}

	protected function _postDelete()
	{
		parent::_postDelete();

		$xenOptions = XenForo_Application::getOptions();
		$postDelete = $xenOptions->sonnbXG_userPostDelete;

		if (!empty($postDelete['action']))
		{
			switch($postDelete['action'])
			{
				case 'nothing':
					break;
				case 'delete':
					$db = XenForo_Application::getDb();
					$userId = $this->get('user_id');

					$albums = $this->_getAlbumModel()->getAlbumsByUserId($userId);

					if ($albums)
					{
						foreach ($albums as $album)
						{
							/* @var $albumDw sonnb_XenGallery_DataWriter_Album */
							$albumDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album', XenForo_DataWriter::ERROR_SILENT);
							$albumDw->setExistingData($album, true);
							$albumDw->delete();
						}
					}

					$db->delete(
						'sonnb_xengallery_comment',
						'user_id = '.$userId
					);
					$db->delete(
						'sonnb_xengallery_stream',
						'user_id = '.$userId
					);
					$db->delete(
						'sonnb_xengallery_tag',
						'user_id = '.$userId
					);
					$db->delete(
						'sonnb_xengallery_tag',
						'tagger_user_id = '.$userId
					);
					$db->delete(
						'sonnb_xengallery_watch',
						'user_id = '.$userId
					);
					break;
				case 'move':
					if (!empty($postDelete['user_id']))
					{
						$userId = $this->get('user_id');
						$targetUserId = intval($postDelete['user_id']);
						$user = $this->_getUserModel()->getUserById($targetUserId);

						$db = $this->_db;
						$db->update(
							'sonnb_xengallery_album',
							array(
								'user_id' => $user['user_id'],
								'username' => $user['username']
							),
							'user_id = '.$userId
						);
						$db->update(
							'sonnb_xengallery_comment',
							array(
								'user_id' => $user['user_id'],
								'username' => $user['username']
							),
							'user_id = '.$userId
						);
						$db->update(
							'sonnb_xengallery_content',
							array(
								'user_id' => $user['user_id'],
								'username' => $user['username']
							),
							'user_id = '.$userId
						);
						$db->update(
							'sonnb_xengallery_stream',
							array(
								'user_id' => $user['user_id'],
								'username' => $user['username']
							),
							'user_id = '.$userId
						);
						$db->update(
							'sonnb_xengallery_tag',
							array(
								'user_id' => $user['user_id'],
								'username' => $user['username']
							),
							'user_id = '.$userId
						);
						$db->update(
							'sonnb_xengallery_tag',
							array(
								'tagger_user_id' => $user['user_id'],
								'tagger_username' => $user['username']
							),
							'tagger_user_id = '.$userId
						);
						$db->update(
							'sonnb_xengallery_watch',
							array(
								'user_id' => $user['user_id'],
								'username' => $user['username']
							),
							'user_id = '.$userId
						);
					}
					break;
				default:
					break;
			}
		}
	}

	public function rebuildIgnoreCache()
	{
		parent::rebuildIgnoreCache();

		$this->rebuildXenGalleryCount();
	}

	public function rebuildXenGalleryCount()
	{
		$userId = $this->get('user_id');

		if (!$userId)
		{
			return false;
		}

		$albumCount = $this->_getAlbumModel()->countAlbumsByUserId(
			$userId,
			array(
				'album_state' => 'visible'
			)
		);
		$photoCount = $this->_getPhotoModel()->countPhotosByUserId(
			$userId,
			array(
				'content_state' => 'visible'
			)
		);
		$videoCount = $this->_getVideoModel()->countVideosByUserId(
			$userId,
			array(
				'content_state' => 'visible'
			)
		);

		$this->_setPostSave('sonnb_xengallery_album_count', $albumCount);
		$this->_setPostSave('sonnb_xengallery_photo_count', $photoCount);
		$this->_setPostSave('sonnb_xengallery_video_count', $videoCount);

		$db = $this->_db;
		$db->update(
			'xf_user',
			array(
				'sonnb_xengallery_album_count' => $albumCount,
				'sonnb_xengallery_photo_count' => $photoCount,
				'sonnb_xengallery_video_count' => $videoCount
			),
			'user_id = ' . $userId
		);
	}

	/**
	 *
	 * @return sonnb_XenGallery_Model_Photo
	 */
	protected function _getPhotoModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Photo');
	}

	/**
	 *
	 * @return sonnb_XenGallery_Model_Video
	 */
	protected function _getVideoModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Video');
	}

	/**
	 *
	 * @return sonnb_XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Album');
	}
}

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
class sonnb_XenGallery_ControllerPublic_XenGallery_Video extends sonnb_XenGallery_ControllerPublic_XenGallery_Content
{
    public function actionIndex()
    {
        $parent = parent::actionIndex();

        if ($parent instanceof XenForo_ControllerResponse_View && !$this->_noRedirect())
        {
            $myPlaylistModel = $this->_getMyPlaylistModel();
            $myPlaylist = $myPlaylistModel->getPlaylistByContentUserId(sonnb_XenGallery_Model_Video::$contentType, $parent->params['content']['content_id'], XenForo_Visitor::getUserId());
            $myPlaylistItems = array();

            if ($myPlaylist)
            {
                $myPlaylistConditions = array();
                $myPlaylistFetchOptions = array(
                    'order' => 'random',
                    'limit' => XenForo_Application::getOptions()->sonnbXG_relatedPhotos,
                    'join' => sonnb_XenGallery_Model_MyPlaylist::FETCH_CONTENT
                );
                $myPlaylistItems = $myPlaylistModel->getPlaylistItemsByPlaylistId($myPlaylist['playlist_id'], $myPlaylistConditions, $myPlaylistFetchOptions);
                $myPlaylistItems = $this->_getContentModel()->prepareContents(
                    $myPlaylistItems,
                    array(
                        'join' => sonnb_XenGallery_Model_Video::FETCH_DATA | sonnb_XenGallery_Model_Video::FETCH_ALBUM,
                    )
                );

                foreach ($myPlaylistItems AS $playlistId => $myPlaylistItem)
                {
                    if (!$this->_getContentModel()->canViewContentAndContainer($myPlaylistItem, $myPlaylistItem['album']))
                    {
                        unset($myPlaylistItems[$playlistId]);
                    }
                }
            }

            $parent->params['myPlaylist'] = $myPlaylist;
            $parent->params['myPlaylistItems'] = $myPlaylistItems;
        }

        return $parent;
    }

	public function actionAlbumSelect()
	{
        $conditions = array();
		$maxVideos = $this->_getVideoModel()->getVideoCountLimit();
		if ($maxVideos)
		{
			$conditions['video_count'] = array('<', $maxVideos);
		}

        return $this->_actionAlbumSelect(sonnb_XenGallery_Model_Video::$contentType, $conditions);
	}

	public function actionPlayer()
	{
		//TODO: Show embed player for external playing (Twitter Player Card).
	}

	public function actionThumbnail()
	{
		list($content, $album) = $this->_getVideoOrError();

		$contentDataModel = $this->_getVideoDataModel();

		if (!$content['canEdit'])
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_change_thumbnail_this_video');
		}

		if ($this->isConfirmedPost())
		{
			$thumbnail = sonnb_XenGallery_Model_PhotoUpload::getUploadedFile('thumbnail');

			$contentDataModel->uploadVideoThumbnail($thumbnail, $content);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('gallery/videos', $content),
				new XenForo_Phrase('redirect_changes_saved_successfully')
			);
		}
		else
		{
			$viewParams = array(
				'content' => $content,
				'album' => $album
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Video_Thumbnail',
				'sonnb_xengallery_video_thumbnail',
				$viewParams
			);
		}
	}

	public function actionPlaylist()
	{
		list($content, $album) = $this->_getVideoOrError();

		$playlistId = $this->_input->filterSingle('playlist_id', XenForo_Input::UINT);

		if ($this->isConfirmedPost() && !empty($playlistId))
		{
			$visitor = XenForo_Visitor::getInstance()->toArray();

			$success = $this->_getMyPlaylistModel()->insertPlaylistItem($playlistId, $content['content_type'], $content['content_id'], $visitor);

			if ($success === false)
			{
				return $this->responseNoPermission();
			}
			elseif ($success instanceof XenForo_Phrase)
			{
				return $this->responseError($success);
			}

			if ($this->_noRedirect())
			{
				return $this->responseMessage(new XenForo_Phrase('changes_saved'));
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
					$this->_buildLink('gallery/videos', $content),
					new XenForo_Phrase('changes_saved')
				);
			}
		}
		else
		{
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Video_Playlist',
				'sonnb_xengallery_video_playlist',
				array(
					'playlists' => $this->_getMyPlaylistModel()->getPlaylistsByUserId(XenForo_Visitor::getUserId()),
					'content' => $content,
					'album' => $album
				)
			);
		}
	}
	
	protected function _getVideoOrError($contentId = null)
	{
		if ($contentId === null)
		{
			$contentId = $this->_input->filterSingle('content_id', XenForo_Input::UINT);
		}
		
		$visitor = XenForo_Visitor::getInstance();

		/* @var $galleryHelper sonnb_XenGallery_ControllerHelper_Gallery */
		$galleryHelper = $this->getHelper('sonnb_XenGallery_ControllerHelper_Gallery');
		
		$contentFetchOptions = array(
			'join' => sonnb_XenGallery_Model_Video::FETCH_DATA |
							sonnb_XenGallery_Model_Video::FETCH_USER |
							sonnb_XenGallery_Model_Video::FETCH_VIDEO,
			'likeUserId' => $visitor['user_id'],
			'watchUserId' => $visitor['user_id'],
			'followingUserId' => $visitor['user_id']
		);
		
		list($content, $album) = $galleryHelper->assertVideoValidAndViewable($contentId, $contentFetchOptions);

		if (!$this->_getVideoModel()->isVideo($content))
		{
			throw $this->responseException($this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->_getControllerContentLink($content)
			));
		}

		return array($content, $album);
	}

	public function actionEmbed()
	{
		$input = $this->_input->filter(array(
			'hash' => XenForo_Input::STRING,
			'album_id' => XenForo_Input::UINT
		));

		if (!$input['hash'])
		{
			$input['hash'] = $this->_input->filterSingle('temp_hash', XenForo_Input::STRING);
		}

		$this->_assertCanUploadContents();

		$contentModel = $this->_getVideoModel();
		$maxVideoCounts = $contentModel->getVideoCountLimit();

		$viewParams = array(
			'contentDataConstraints' => $contentModel->getVideoDataConstraints(),

			'sites' => $this->_getBbCodeModel()->getAllBbCodeMediaSites(),

			'remainingUploads' => $maxVideoCounts,

			'hash' => $input['hash'],
			'album_id' => $input['album_id'],
			'contentDataParams' => array(
				'hash' => $input['hash'],
				'album_id' => $input['album_id']
			)
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Video_Embed',
			'sonnb_xengallery_video_embed_upload',
			$viewParams
		);
	}

	public function actionEmbedVideo()
	{
		$this->_assertPostOnly();

		$url = $this->_input->filterSingle('url', XenForo_Input::STRING);
		$input = $this->_input->filter(array(
			'content_data_hash' => XenForo_Input::STRING,
			'album_id' => XenForo_Input::UINT
		));

		if (!isset($input['album_id']))
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_your_upload_data_is_invalid');
		}

		$contentDataModel = $this->_getVideoDataModel();
		$conditions = array('content_type' => sonnb_XenGallery_Model_Video::$contentType);
		$existingVideoCount = ($input['album_id'] ? $this->_getVideoModel()->countContentsByAlbumId($input['album_id'], $conditions) : 0);
		$newVideoData = $contentDataModel->countDataByHash($input['content_data_hash'], $conditions);

		$maxVideoCounts = $this->_getVideoModel()->getVideoCountLimit();

		if ($maxVideoCounts > 0 && ($existingVideoCount + $newVideoData >= $maxVideoCounts))
		{
			return $this->responseError(new XenForo_Phrase(
				'sonnb_xengallery_you_may_upload_maximum_x_videos_to_this_album_only',
				array('total' => $maxVideoCounts)
			));
		}

		$matchBbCode = $contentDataModel->parseMediaUrl($url);

		if (!$matchBbCode)
		{
			return $this->responseError(new XenForo_Phrase('sonnb_xengallery_your_url_is_not_supported_by_xengallery'));
		}

		$extras = array(
			'temp_hash' => $input['content_data_hash'],
			'content_type' => sonnb_XenGallery_Model_Video::$contentType
		);

		$contentData = $contentDataModel->insertEmbedVideoData($matchBbCode, $extras);

		$message = new XenForo_Phrase('sonnb_xengallery_your_video_has_been_added');

		if ($this->_noRedirect())
		{
			$viewParams = array(
				'album' => $this->_getAlbumModel()->getAlbumById($input['album_id']),
				'content' => $contentDataModel->prepareDataSingle($contentData),
				'message' => $message
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Video_EmbedVideo',
				'',
				$viewParams
			);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->_buildLink('gallery/videos/upload', false, array(
					'hash' => $input['temp_hash'],
					'album_id' => $input['album_id']
				)),
				$message
			);
		}
	}

	public static function getSessionActivityDetailsForList(array $activities)
	{
		$xenOptions = XenForo_Application::getOptions();

		if ($xenOptions->sonnbXG_showActivity)
		{
			$contentIds = array();
			foreach ($activities AS $activity)
			{
				if (!empty($activity['params']['content_id']))
				{
					$contentIds[$activity['params']['content_id']] = intval($activity['params']['content_id']);
				}
			}

			$contentData = array();
			if ($contentIds)
			{
				/* @var $contentModel sonnb_XenGallery_Model_Video */
				$contentModel = XenForo_Model::create('sonnb_XenGallery_Model_Video');
				$fetchOptions = array(
					'join' => sonnb_XenGallery_Model_Video::FETCH_ALBUM
								| sonnb_XenGallery_Model_Video::FETCH_USER
								| sonnb_XenGallery_Model_Video::FETCH_VIDEO
				);

				$contents = $contentModel->getContentsByContentIds(sonnb_XenGallery_Model_Video::$contentType, $contentIds, $fetchOptions);
				$contents = $contentModel->prepareContents($contents, $fetchOptions);

				foreach ($contents AS $content)
				{
					if ($contentModel->canViewContentAndContainer($content, $content['album']))
					{
						$content['title'] = XenForo_Helper_String::censorString($content['title']);

						$contentData[$content['content_id']] = array(
							'title' => $content['title'],
							'url' => XenForo_Link::buildPublicLink('gallery/videos', $content),
							'album_title' => XenForo_Helper_String::censorString($content['album']['title']),
							'album_id' => $content['album_id'],
							'user' => $content['album']['username']
						);
					}
				}
			}

			$output = array();
			foreach ($activities AS $key => $activity)
			{
				$content = false;
				if (!empty($activity['params']['content_id']))
				{
					$contentId = $activity['params']['content_id'];

					if (isset($contentData[$contentId]))
					{
						$content = $contentData[$contentId];
					}
				}

				if ($content)
				{
					$output[$key] = array(
						' ',
						new XenForo_Phrase(
							'sonnb_xengallery_viewing_video_x_in_album_y_by_z',
							array(
								'title' => $content['title'],
								'link' => $content['url'],
								'album_title' => $content['album_title'],
								'user' => $content['user']
							)
						),
						$content['url'],
						''
					);
				}
				else
				{
					$output[$key] = new XenForo_Phrase('sonnb_xengallery_viewing_gallery_video');
				}
			}
		}
		else
		{
			$output = array();
			foreach ($activities AS $key => $activity)
			{
				$output[$key] = new XenForo_Phrase('sonnb_xengallery_viewing_gallery_video');
			}
		}

		return $output;
	}

    protected function _getControllerContentLink(array $content)
    {
        return $this->_buildLink("gallery/videos", $content);
    }

    protected function _getControllerContentData()
    {
        return $this->_getVideoOrError();
    }

    protected function _getControllerContentDwClass()
    {
        return "sonnb_XenGallery_DataWriter_Video";
    }

    protected function _getControllerContentModel()
    {
        return $this->_getVideoModel();
    }

    protected function _getControllerContentDataModel()
    {
        return $this->_getVideoDataModel();
    }

    protected function _getControllerContentType()
    {
        return sonnb_XenGallery_Model_Video::$contentType;
    }

    protected function _getControllerContentXfType()
    {
        return sonnb_XenGallery_Model_Video::$xfContentType;
    }
}
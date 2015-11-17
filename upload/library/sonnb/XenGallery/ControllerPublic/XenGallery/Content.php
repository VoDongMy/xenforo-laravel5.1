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
abstract class sonnb_XenGallery_ControllerPublic_XenGallery_Content extends sonnb_XenGallery_ControllerPublic_Abstract
{
	public function actionIndex()
	{
        list($content, $album) = $this->_getControllerContentData();

		$xenOptions = XenForo_Application::getOptions();
		$visitor = XenForo_Visitor::getInstance();
		$source = $this->_input->filterSingle('_source', XenForo_Input::STRING);
        $contentModel = $this->_getControllerContentModel();

        $content['canDownloadFull'] = $this->_getAlbumModel()->canDownloadOriginalContent($content, $album);

		$this->canonicalizeRequestUrl($this->_getControllerContentLink($content));

		//TODO: Check for more source: collection, stream, camera, location... to show related contents
		if ($source === 'widget' && $this->_noRedirect())
		{
			$relatedContents = array();
			$nextContent = $prevContent = array();
		}
		else
		{
			$relatedContentLimit = $xenOptions->sonnbXG_relatedPhotos;

			if ($xenOptions->sonnbXG_relatedPhotos < 1)
			{
				$relatedContentLimit = 2;
			}

			$relatedContentLimit = $relatedContentLimit + 1;
			$this->getContentSort($order, $orderDirection);
			list($relatedContents, $nextContent, $prevContent) = $contentModel->getRelatedContents($content, $album, $relatedContentLimit, $order, $orderDirection);
			if ($xenOptions->sonnbXG_relatedPhotos < 1)
			{
				$relatedContents = array();
			}
		}

		$commentOnLoad = $xenOptions->sonnbXG_commentPerPage;

		if ($commentOnLoad > 0)
		{
			//Fetch latest comments
			$commentConditions = array(
					'content_id' => $content['content_id'],
					'content_type' => $content['content_type']
			)+$this->_getCommentModel()->getPermissionBasedCommentFetchConditions();

			$commentFetchOptions = array(
				'join' => sonnb_XenGallery_Model_Comment::FETCH_USER,
				'likeUserId' => $visitor['user_id'],
				'limit' => $commentOnLoad,
				'order' => 'comment_date',
				'orderDirection' => 'desc'
			);
            $content['comments'] = $this->_getCommentModel()->getComments($commentConditions, $commentFetchOptions);
            $content['comments'] = $this->_getCommentModel()->prepareComments($content['comments'], $commentFetchOptions);
            $content['comments'] = array_reverse($content['comments'], true);
		}

		if (!empty($content['comments']))
		{
			$firstShownComment = reset($content['comments']);
			$firstShownCommentDate = $firstShownComment['comment_date'];
			$lastShownComment = end($content['comments']);
			$lastShownCommentDate = $lastShownComment['comment_date'];
		}
		else
		{
			$firstShownCommentDate = 0;
			$lastShownCommentDate = 0;
		}

		if (!empty($content['tagUsers']))
		{
            $content['tagUsers'] = $this->_getTagModel()->getTagsByContentId(
                $content['content_type'],
                $content['content_id'],
				array(
					'tag_state' => 'accepted'
				)
			);
		}

        $contentModel->logContentView($content['content_id']);

		$inlineModOptions = $contentModel->getInlineModeration();

		$category = null;
		$categories = $this->_getCategoryModel()->getAllCachedCategories();
		if (isset($categories[$album['category_id']]))
		{
			$category = $categories[$album['category_id']];
		}
		$fields = $this->_getFieldModel()->getApplicableFieldsByContentId(
			$content['content_type'],
            $content['content_id'],
			$category,
			true,
			false
		);

		$viewParams = array(
			'album' => $album,
			'content' => $content,
			'category' => $category,
			'fields' => $fields,
			'inlineModOptions' => $inlineModOptions,

			'nextContent' => $nextContent,
			'prevContent' => $prevContent,

			'relatedContents' => $relatedContents,

			'breadCrumbs' => $this->_getAlbumModel()->getAlbumBreadCrumbs($album),

			'commentOnLoad' => $commentOnLoad,
			'firstShownCommentDate' => $firstShownCommentDate,
			'lastShownCommentDate' => $lastShownCommentDate,
			'commentShownCount' => $content['comment_count'] > $commentOnLoad ? $commentOnLoad : $content['comment_count'],

            'includeTaggerJs' => $this->_getGalleryModel()->includeJsTagger()
		);

		if ($this->_noRedirect())
		{
			$template = "sonnb_xengallery_$content[content_type]_view_overlay";
		}
		else
		{
			$template = "sonnb_xengallery_$content[content_type]_view";
			if (!$this->_input->inRequest('regular'))
			{
				$viewParams['triggerFullscreen'] = true;
			}
		}

		return $this->responseView(
			"sonnb_XenGallery_ViewPublic_Content_View",
			$template,
			$viewParams
		);
	}
	
	public function actionCreate()
	{
		$this->_assertRegistrationRequired();
		$this->_assertCanUploadContents();
		$this->_assertIosDevices();

        $contentType = $this->_getControllerContentType();
		
		return $this->responseView(
            "sonnb_XenGallery_ViewPublic_Content_Create",
            "sonnb_xengallery_{$contentType}_create"
        );
	}

    protected function _actionAlbumSelect($contentType, $conditions = array())
	{
		$this->_assertRegistrationRequired();
		$this->_assertCanUploadContents();
		$this->_assertIosDevices();
		
		$visitor = XenForo_Visitor::getInstance();
		
		$conditions += $this->_getControllerContentModel()->getPermissionBasedContentFetchConditions();
		$conditions += array(
			'album_type' => sonnb_XenGallery_Model_Album::ALBUM_TYPE_NORMAL
		);
		
		$fetchOptions = array(
			'limit' => 20,
			'order' => 'album_updated_date',
			'orderDirection' => 'desc'
		);
		
		$albums = $this->_getAlbumModel()->getAlbumsByUserId($visitor['user_id'], $conditions, $fetchOptions);

		if (empty($albums))
		{
			return $this->responseReroute('sonnb_XenGallery_ControllerPublic_XenGallery_'. ucfirst($this->_getControllerContentType()), 'add');
		}
		else
		{
			$viewParams = array(
				'breadCrumbs' => array(),
				'albums' => $albums
			);

			return $this->responseView(
				"sonnb_XenGallery_ViewPublic_Content_AlbumSelect",
				"sonnb_xengallery_{$contentType}_album_select",
				$viewParams
			);
		}
	}

    public function actionAdd()
	{
		$this->_assertRegistrationRequired();
		$this->_assertCanUploadContents();
		$this->_assertIosDevices();

		$albumModel = $this->_getAlbumModel();
		$visitor = XenForo_Visitor::getInstance();
        $contentType = $this->_getControllerContentType();
		
		if ($this->_getGalleryModel()->isMobileDevice($visitor))
		{
			$defaultAlbum = $albumModel->getMobileAlbumByUserId($visitor['user_id']);
			if (!$defaultAlbum)
			{
				$defaultAlbum = $albumModel->createDefaultAlbumForUser($visitor->toArray(), 'mobile', $errorPhraseKey);
				
				if ($defaultAlbum === false)
				{
					throw $this->_throwFriendlyNoPermission($errorPhraseKey);
				}
			}
		}
		else
		{
			$defaultAlbum = $albumModel->getProfileAlbumByUserId($visitor['user_id']);
			if (!$defaultAlbum)
			{
				$defaultAlbum = $albumModel->createDefaultAlbumForUser($visitor->toArray(), 'profile', $errorPhraseKey);
			
				if ($defaultAlbum === false)
				{
					throw $this->_throwFriendlyNoPermission($errorPhraseKey);
				}
			}
		}
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->_buildLink("gallery/albums/add-{$contentType}", $defaultAlbum)
		);
	}

    public function actionEditInline()
    {
        $this->_assertRegistrationRequired();

        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();

        if (!$contentModel->canEditContent($content))
        {
            throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_edit_this_'. $content['content_type']);
        }

        $category = null;
        $categories = $this->_getCategoryModel()->getAllCachedCategories();
        if (isset($categories[$album['category_id']]))
        {
            $category = $categories[$album['category_id']];
        }
        $fields = $this->_getFieldModel()->getApplicableFieldsByContentId(
            $content['content_type'],
            $content['content_id'],
            $category
        );

        $viewParams = array(
            'album' => $album,
            'content' => $content,
            'fields' => $fields,

            'disableLocation' => XenForo_Application::getOptions()->sonnb_XG_disableLocation,

            'breadCrumbs' => $contentModel->getContentBreadCrumbs($content, $album)
        );

        return $this->responseView(
            'sonnb_XenGallery_ViewPublic_Content_EditInline',
            'sonnb_xengallery_'. $content['content_type'] .'_edit_overlay',
            $viewParams
        );
    }

    public function actionSaveInline()
    {
	    $this->_assertPostOnly();
        $this->_assertRegistrationRequired();

        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();
        $dwClass = $this->_getControllerContentDwClass();

        if (!$contentModel->canEditContent($content))
        {
            throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_edit_this_'. $content['content_type']);
        }

	    if ($this->_input->inRequest('more_options'))
	    {
		    return $this->responseReroute('sonnb_XenGallery_ControllerPublic_XenGallery_'. ucfirst($content['content_type']), 'edit');
	    }

        $description = $this->getHelper('Editor')->getMessageText('description', $this->_input);

        $contentDw = XenForo_DataWriter::create($dwClass);
        $contentDw->setExistingData($content);

        $contentDw->set('description', $description);

        $categories = $this->_getCategoryModel()->getAllCachedCategories();
        $category = null;
        if (isset($categories[$album['category_id']]))
        {
            $category = $categories[$album['category_id']];
        }
        $customFields = $this->_input->filterSingle('custom_fields', XenForo_Input::ARRAY_SIMPLE);
        $contentDw->setCustomFields($customFields, $category);

        $contentDw->preSave();

        if ($errors = $contentDw->getErrors())
        {
            return $this->responseError($errors);
        }

        $contentDw->save();
        $content = $contentDw->getMergedData();

        if ($this->_noRedirect())
        {
            $category = null;
            $categories = $this->_getCategoryModel()->getAllCachedCategories();
            if (isset($categories[$album['category_id']]))
            {
                $category = $categories[$album['category_id']];
            }
            $fields = $this->_getFieldModel()->getApplicableFieldsByContentId(
                $content['content_type'],
                $content['content_id'],
                $category,
	            true,
	            false
            );

            return $this->responseView(
                'sonnb_XenGallery_ViewPublic_Content_SaveInline',
                '',
                array(
                    'content' => $content,
                    'album' => $album,
                    'fields' => $fields
                )
            );
        }
        else
        {
            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                $this->_getControllerContentLink($content),
                new XenForo_Phrase('changes_saved')
            );
        }
    }

    public function actionEdit()
	{
		$this->_assertRegistrationRequired();

        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();

        if ($this->_noRedirect() && !$this->_input->inRequest('more_options'))
        {
            return $this->responseReroute('sonnb_XenGallery_ControllerPublic_XenGallery_'. ucfirst($content['content_type']), 'edit-inline');
        }

		if ($this->_input->inRequest('more_options'))
		{
			$content['description'] = $this->getHelper('Editor')->getMessageText('description', $this->_input);
		}

		if (!$contentModel->canEditContent($content))
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_edit_this_'. $content['content_type']);
		}

		if (!empty($content['contentStreams']))
		{
            $content['stream_name'] = implode(', ', $content['contentStreams']);
		}

        if (!empty($content['tagUsers']))
        {
            foreach ($content['tagUsers'] as $user)
            {
                $content['content_people'][] = $user['username'];
            }

            $content['content_people'] = implode(',', $content['content_people']);
        }

		$category = null;
		$categories = $this->_getCategoryModel()->getAllCachedCategories();
		if (isset($categories[$album['category_id']]))
		{
			$category = $categories[$album['category_id']];
		}
		if (!empty($content['content_id']))
		{
			$fields = $this->_getFieldModel()->getApplicableFieldsByContentId(
                $content['content_type'],
                $content['content_id'],
				$category
			);
		}
		else
		{
			$fields = $this->_getFieldModel()->getApplicableFieldsByContentId(
                $content['content_type'],
				null,
				$category,
				false
			);
		}
		
		$viewParams = array(
			'album' => $album,
			'content' => $content,
			'fields' => $fields,

			'disableLocation' => XenForo_Application::getOptions()->sonnb_XG_disableLocation,
	
			'breadCrumbs' => $contentModel->getContentBreadCrumbs($content, $album)
		);
		
		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Content_Edit',
            'sonnb_xengallery_'. $content['content_type'] .'_edit',
			$viewParams
		);
	}

    public function actionSave()
	{
		$this->_assertRegistrationRequired();
		$xenOptions = XenForo_Application::getOptions();
        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();
        $dwClass = $this->_getControllerContentDwClass();

		if (!$contentModel->canEditContent($content))
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_edit_this_'. $content['content_type']);
		}

		$input = array();

		if (!$xenOptions->sonnb_XG_disableLocation)
		{
			$input = $this->_input->filter(array(
				'content_location' => XenForo_Input::STRING,
			));
			$locationLatLng = $this->_input->filter(array(
				'location_lat' => XenForo_Input::STRING,
				'location_lng' => XenForo_Input::STRING
			));
			$locationLatLng['location_name'] = $input['content_location'];
		}
		
		$privacy = $this->_input->filter(array(
			'allow_view_username' => XenForo_Input::STRING,
			'allow_comment_username' => XenForo_Input::STRING,
		));

		$streams = $this->_input->filterSingle('stream_name', XenForo_Input::STRING);

		//TODO: Save rotate

		$input['title'] = $this->_input->filterSingle('title', XenForo_Input::STRING);
		$input['description'] = $this->getHelper('Editor')->getMessageText('description', $this->_input);

		$contentDw = XenForo_DataWriter::create($dwClass);
        $contentDw->setExistingData($content);

        $contentDw->bulkSet($input);
        $contentDw->insertCustomPrivacy($privacy);
		$contentDw->setExtraData(sonnb_XenGallery_DataWriter_Content::CHECK_CONTENT, true);

		if ($streams)
		{
            $contentDw->setExtraData(sonnb_XenGallery_DataWriter_Content::DATA_CONTENT_STREAMS, $streams);
		}

		$categories = $this->_getCategoryModel()->getAllCachedCategories();
		$category = null;
		if (isset($categories[$album['category_id']]))
		{
			$category = $categories[$album['category_id']];
		}
		$customFields = $this->_input->filterSingle('custom_fields', XenForo_Input::ARRAY_SIMPLE);
        $contentDw->setCustomFields($customFields, $category);

        $contentDw->preSave();

		if ($errors = $contentDw->getErrors())
		{
			return $this->responseError($errors);
		}

        $contentDw->save();
		$content = $contentDw->getMergedData();

        $tagInput = $this->_input->filterSingle('content_people', XenForo_Input::STRING);
        $this->_getTagModel()->addTagUsers(
            $tagInput,
            $content['content_type'],
            $content['content_id'],
	        true
        );
			
		if (!$xenOptions->sonnb_XG_disableLocation && $contentDw->isChanged('content_location'))
		{
			$this->_getLocationModel()->insertLocation($content['content_type'], $content['content_id'], $locationLatLng);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->_getControllerContentLink($content),
			new XenForo_Phrase('changes_saved')
		);
	}

    public function actionMove()
	{
		$this->_assertRegistrationRequired();
        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();
        $dwClass = $this->_getControllerContentDwClass();

		if (!$contentModel->canEditContent($content))
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_edit_this_'. $content['content_type']);
		}
		
		if ($this->_request->isPost())
		{
			$targetAlbumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
			
			if ($targetAlbumId == $album['album_id'])
			{
				return $this->responseError(new XenForo_Phrase('sonnb_xengallery_you_could_not_move_to_current_album'), 403);
			}

			/* @var $galleryHelper sonnb_XenGallery_ControllerHelper_Gallery */
			$galleryHelper = $this->getHelper('sonnb_XenGallery_ControllerHelper_Gallery');
			$targetAlbum = $galleryHelper->assertAlbumValidAndViewable($targetAlbumId);

            $funcAdd = "canAdd" . ucfirst($content['content_type']);
			if (!$this->_getAlbumModel()->canEditAlbum($targetAlbum) && !$this->_getAlbumModel()->$funcAdd($targetAlbum))
			{
				throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_edit_target_album');
			}
			
			$contentDw = XenForo_DataWriter::create($dwClass);
            $contentDw->setExistingData($content);
            $contentDw->set('album_id', $targetAlbum['album_id']);
            $contentDw->save();
			
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->_getControllerContentLink($content),
				new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			//TODO: Limit album
			$visitor = XenForo_Visitor::getInstance();
			$albumConditions = $this->_getAlbumModel()->getPermissionBasedAlbumFetchConditions();
			$albumFetchOptions = array(
				//'limit' => 50
			);
			$targetAlbums = $this->_getAlbumModel()->getAlbumsByUserId($visitor['user_id'], $albumConditions, $albumFetchOptions);
			$targetAlbums = $this->_getAlbumModel()->prepareAlbums($targetAlbums, $albumFetchOptions);
			
			foreach ($targetAlbums as $index=>$target)
			{
				if (!$target['canView'] || $content['album_id'] == $target['album_id'])
				{
					unset($targetAlbums[$index]);
				}
			}
			
			$viewParams = array(
				'album' => $album,
				'content' => $content,

				'targetAlbums' => $targetAlbums,

				'breadCrumbs' => $contentModel->getContentBreadCrumbs($content, $album)
			);
				
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Content_Move',
				'sonnb_xengallery_'. $content['content_type'] .'_move',
				$viewParams
			);
		}
	}
	
	public function actionTag()
	{
		$this->_assertRegistrationRequired();
		$this->_assertPostOnly();

        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();

        if (!$contentModel->canEditContent($content))
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_tag_people_to_this_'. $content['content_type']);
		}
		
		$username = $this->_input->filterSingle('username', XenForo_Input::STRING);

		$tagPosition = $this->_input->filter(array(
			'tag_x' => XenForo_Input::UINT,
			'tag_y' => XenForo_Input::UINT
		));

		$tag = $this->_getTagModel()->addTagUser(
			$username,
            $content['content_type'],
            $content['content_id'],
			$tagPosition
		);

		if ($this->_noRedirect())
		{
            $content['tagUsers'] = $this->_getTagModel()->getTagsByContentId(
                $content['content_type'],
                $content['content_id'],
				array(
					'tag_state' => 'accepted'
				)
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Content_Tag',
				'',
				array(
					'album' => $album,
					'content' => $content,

					'tag' => $tag
				)
			);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				$this->_getControllerContentLink($content),
				new XenForo_Phrase('changes_saved')
			);
		}
	}

    public function actionTags()
	{
        list($content, $album) = $this->_getControllerContentData();

		$tagConditions = array(
			'content_type' => $content['content_type'],
			'content_id' => $content['content_id'],
			'tag_state' => 'accepted'	
		);
		
		$tagFetchOptions = array(
			'join' => sonnb_XenGallery_Model_Tag::FETCH_USER,
			'order' => 'tag_date',
			'orderDirection' => 'asc'		
		);
		
		$tags = $this->_getTagModel()->getTags($tagConditions, $tagFetchOptions);

		if ($this->_request->isXmlHttpRequest() && !$this->_noRedirect())
		{
			$this->_routeMatch->setResponseType('json');
		}
		
		if (!$tags && !$this->_request->isXmlHttpRequest())
		{
			return $this->responseError(new XenForo_Phrase('sonnb_xengallery_no_one_was_tagged_in_this_'. $content['content_type'] .'_yet'));
		}
		
		$viewParams = array(
			'album' => $album,
			'content' => $content,
	
			'breadCrumbs' => $this->_getControllerContentModel()->getContentBreadCrumbs($content, $album),

			'fetchTags' => $this->_request->isXmlHttpRequest() && !$this->_noRedirect(),
	
			'tags' => $tags
		);
		
		return $this->responseView(
            'sonnb_XenGallery_ViewPublic_Content_Tags',
            'sonnb_xengallery_'. $content['content_type'] .'_tags',
            $viewParams
		);
	}
	
	public function actionTagConfirm()
	{
		$this->_assertRegistrationRequired();

		$visitor = XenForo_Visitor::getInstance();
		$tagModel = $this->_getTagModel();
        list($content, $album) = $this->_getControllerContentData();

		$tagId = $this->_input->filterSingle('tag_id', XenForo_Input::UINT);

		if (!$tagId)
		{
			return $this->responseNoPermission();
		}

		$tag = $tagModel->getTagById($tagId);
		
		if (!$tag || $tag['user_id'] != $visitor['user_id'])
		{
			return $this->responseNoPermission();
		}
	
		if ($tag['tag_state'] != 'awaiting')
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_have_'.$tag['tag_state'].'_this_tag_already');
		}
		
		if ($this->isConfirmedPost())
		{
			$action = $this->_input->filterSingle('tag_state', XenForo_Input::UINT);
			
			$tagDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Tag');
			$tagDw->setExistingData($tag);
			$tagDw->set('tag_state', $action);
			$tagDw->save();
		
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				$this->_getControllerContentLink($content),
				new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			$viewParams = array(
				'tag' => $tag,
				'content' => $content,
				'album' => $album
			);
			
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Content_TagConfirm',
				'sonnb_xengallery_'. $content['content_type']. '_tag_confirm',
				$viewParams
			);
		}
	}

    public function actionShare()
	{
        list($content, $album) = $this->_getControllerContentData();

		$viewParams = array(
			'album' => $album,
			'content' => $content,

			'breadCrumbs' => $this->_getControllerContentModel()->getContentBreadCrumbs($content, $album)
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Content_Share',
			'sonnb_xengallery_'. $content['content_type'] .'_share',
			$viewParams
		);
	}

    public function actionPrivacy()
	{
		$this->_assertRegistrationRequired();

        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();
        $dwClass = $this->_getControllerContentDwClass();

		if (!$contentModel->canEditContent($content, $album))
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_edit_privacy_of_this_'. $content['content_type']);
		}
		
		if ($this->_request->isPost())
		{
			$input = $this->_input->filter(array(
				'allow_view' => XenForo_Input::STRING,
				'allow_comment' => XenForo_Input::STRING
			));
		
			$privacy = $this->_input->filter(array(
				'allow_view_username' => XenForo_Input::STRING,
				'allow_comment_username' => XenForo_Input::STRING
			));

			$contentDw = XenForo_DataWriter::create($dwClass);
            $contentDw->setExistingData($content);

			$contentPrivacy = $contentDw->get('content_privacy');

			if (!is_array($contentPrivacy))
			{
                $contentPrivacy = @unserialize($contentPrivacy);
			}

            $contentPrivacy['allow_view'] = $input['allow_view'];
            $contentPrivacy['allow_comment'] = $input['allow_comment'];

            $contentDw->set('content_privacy', $contentPrivacy);
            $contentDw->insertCustomPrivacy($privacy);
            $contentDw->save();
				
			return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
					$this->_getControllerContentLink($content),
					new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			if (!empty($content['content_privacy']['allow_view_data']))
			{
                $content['allow_view_username'] = array();
				foreach ($content['content_privacy']['allow_view_data'] as $user)
				{
                    $content['allow_view_username'][] = $user['username'];
				}

                $content['allow_view_username'] = implode(',', $content['allow_view_username']);
			}
		
			if (!empty($content['content_privacy']['allow_comment_data']))
			{
                $content['allow_comment_username'] = array();
				foreach ($content['content_privacy']['allow_comment_data'] as $user)
				{
                    $content['allow_comment_username'][] = $user['username'];
				}

                $content['allow_comment_username'] = implode(',', $content['allow_comment_username']);
			}
		
			$viewParams = array(
				'album' => $album,
				'content' => $content,
	
				'breadCrumbs' => $this->_getControllerContentModel()->getContentBreadCrumbs($content, $album)
			);
		
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Content_Privacy',
				'sonnb_xengallery_'. $content['content_type'] .'_edit_privacy',
				$viewParams
			);
		}
	}

    public function actionStreamAdd()
	{
		$this->_assertRegistrationRequired();

        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();
        $dwClass = $this->_getControllerContentDwClass();

        if (!$contentModel->canEditContent($content, $album))
        {
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_edit_streams_this_'. $content['content_type']);
		}

		$streams = $this->_input->filterSingle('stream_name', XenForo_Input::STRING);
		$streams = explode(',', $streams);
		$streams = array_filter($streams, 'utf8_trim');
		$streams = array_filter($streams);
		$streams = array_unique($streams);

		if ($streams)
		{
			$streamProcessed = $this->_getStreamModel()->publishStream(
                $content['content_type'],
                $content['content_id'],
				$streams
			);

			if ($streamProcessed === false)
			{

			}
			elseif ($streamProcessed === -1)
			{
				return $this->responseError(
					new XenForo_Phrase(
						'sonnb_xengallery_you_are_allowed_to_add_x_streams_to_a_single_'. $content['content_type'] .'_album',
						array(
							'limit' => $this->_getGalleryModel()->getMaximumStreamCount()
						)
					), 403);
			}
			else
			{
				$dw = XenForo_DataWriter::create($dwClass);
				$dw->setExistingData($content);

				if ($content['contentStreams'])
				{
                    $content['contentStreams'] = array_merge($content['contentStreams'], $streamProcessed);
				}
				else
				{
                    $content['contentStreams'] = $streamProcessed;
				}

				$dw->set('content_streams', $content['contentStreams']);
				$dw->save();
			}
		}

		if ($this->_noRedirect())
		{
			$viewParams = array(
				'album' => $album,
				'content' => $content,

				'newStreams' => is_array($streamProcessed) ? $streamProcessed : array()
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Content_StreamAdd',
				'sonnb_xengallery_'. $content['content_type'] .'_stream_add',
				$viewParams
			);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
			$this->_getControllerContentLink($content),
			new XenForo_Phrase('sonnb_xengallery_streams_have_been_added')
		);
	}

    public function actionStreamDelete()
	{
		$this->_assertRegistrationRequired();

        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();
        $dwClass = $this->_getControllerContentDwClass();

        if (!$contentModel->canEditContent($content, $album))
        {
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_delete_streams_of_this_'. $content['content_type']);
		}

		$stream = $this->_input->filterSingle('stream_name', XenForo_Input::STRING);
		$contentType = $content['content_type'];
		$contentId = $content['content_id'];

		if (!$stream || (!$return = $this->_getStreamModel()->removeStream($contentType, $contentId,$stream)))
		{
			return $this->responseError(new XenForo_Phrase('sonnb_xengallery_invalid_stream_name_specified'), 404);
		}

		$dw = XenForo_DataWriter::create($dwClass);
		$dw->setExistingData($content);

		if ($content['contentStreams'])
		{
			foreach ($content['contentStreams'] as $_index => $_stream)
			{
				if ($_stream == $return)
				{
					unset($content['contentStreams'][$_index]);
				}
			}

			$dw->set('content_streams', $content['contentStreams']);
			$dw->save();
		}

		if ($this->_noRedirect())
		{
			$viewParams = array(
				'album' => $album,
				'content' => $content,

				'stream' => $return
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Content_StreamDelete',
				'sonnb_xengallery_'. $content['content_type'] .'_stream_delete',
				$viewParams
			);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
			$this->_getControllerContentLink($content),
			new XenForo_Phrase('sonnb_xengallery_stream_has_been_removed')
		);
	}

    public function actionLocation()
	{
		$this->_assertRegistrationRequired();

        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();
        $dwClass = $this->_getControllerContentDwClass();

		if (!$contentModel->canEditContent($content, $album) || XenForo_Application::getOptions()->sonnb_XG_disableLocation)
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_edit_location_of_this_'. $content['content_type']);
		}
		
		if ($this->_request->isPost())
		{
			$locationLatlng = $this->_input->filter(array(
				'location_lat' => XenForo_Input::STRING,
				'location_lng' => XenForo_Input::STRING
			));

			$location = $this->_input->filterSingle('content_location', XenForo_Input::STRING);
			$location = trim($location);

			$locationLatlng['location_name'] = $location;
				
			$contentDw = XenForo_DataWriter::create($dwClass);
            $contentDw->setExistingData($content);
            $contentDw->set('content_location', $location);
            $contentDw->save();
			
			if ($contentDw->isChanged('content_location'))
			{
				$this->_getLocationModel()->insertLocation($content['content_type'], $content['content_id'], $locationLatlng);
			}

			if ($this->_noRedirect())
			{
				return $this->responseMessage(new XenForo_Phrase('changes_saved'));
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
					$this->_getControllerContentLink($content),
					new XenForo_Phrase('changes_saved')
				);
			}
		}
		else
		{
			$location = $this->_getLocationModel()->getLocationByContentId($content['content_type'], $content['content_id']);

			$viewParams = array(
				'album' => $album,
				'content' => $content,
				'location' => $location,
	
				'breadCrumbs' => $contentModel->getContentBreadCrumbs($content, $album)
			);
				
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Content_Location',
				'sonnb_xengallery_'. $content['content_type'] .'_edit_location',
				$viewParams
			);
		}
	}

    public function actionDownload()
	{
		$this->_assertRegistrationRequired();

        list($content, $album) = $this->_getControllerContentData();

		$full = $this->_input->inRequest('fullsize');

		if ($full && !$this->_getAlbumModel()->canDownloadOriginalContent($content, $album))
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_no_permission_to_download_original_'. $content['content_type']);
		}

		$forceLocal = false;
		$engine = $content['bdattachmentstore_engine'];
		$engineOptions = @unserialize($content['bdattachmentstore_options']);
		$contentDataModel = $this->_getControllerContentDataModel();

		if (!empty($engine) && !empty($engineOptions['keepLocalCopy']))
		{
			$forceLocal = true;
		}

		if ($full)
		{
			$filePath = $contentDataModel->getContentDataFile($content, $forceLocal);
			$fileUrl = $contentDataModel->getContentDataUrl($content, $forceLocal);

			if ($forceLocal && !is_file($filePath))
			{
				$filePath = $contentDataModel->getContentDataFile($content);
			}
		}
		else
		{
			$filePath = $contentDataModel->getContentDataLargeThumbnailFile($content, $forceLocal);
			$fileUrl = $contentDataModel->getContentDataLargeThumbnailUrl($content, $forceLocal);

			if ($forceLocal && !is_file($filePath))
			{
				$filePath = $contentDataModel->getContentDataLargeThumbnailFile($content);
			}
		}

		if (!is_file($filePath))
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_some_thing_went_wrong_with_this_'. $content['content_type']);
		}
		
		$this->_routeMatch->setResponseType('raw');
		
		$viewParams = array(
			'album' => $album,
			'content' => $content,
				
			'filePath' => $filePath,
			'fileUrl' => $fileUrl,
	
			'breadCrumbs' => $this->_getControllerContentModel()->getContentBreadCrumbs($content, $album)
		);
		
		return $this->responseView('sonnb_XenGallery_ViewPublic_Content_Download','', $viewParams);
	}

    public function actionDelete()
	{
		$this->_assertRegistrationRequired();

        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();
        $dwClass = $this->_getControllerContentDwClass();

        if (!$contentModel->canDeleteContent($content, 'soft'))
        {
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_delete_this_'. $content['content_type']);
		}

		if ($this->_input->inRequest('undo_delete'))
		{
			return $this->responseReroute('sonnb_XenGallery_ControllerPublic_XenGallery_'. ucfirst($content['content_type']), 'undo-delete');
		}
		
		if ($this->isConfirmedPost())
		{
			$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::UINT);
			$reason = $this->_input->filterSingle('reason', XenForo_Input::STRING);

            $contentDw = XenForo_DataWriter::create($dwClass);
            $contentDw->setExistingData($content);
				
			if ($hardDelete)
			{
				if (!$this->_getControllerContentModel()->canDeleteContent($content, 'hard'))
				{
					return $this->responseNoPermission();
				}

                $contentDw->setExtraData(sonnb_XenGallery_DataWriter_Content::DATA_DELETE_REASON, $reason);
                $contentDw->delete();

				$target = $this->_buildLink('gallery/albums', $album);
				$message = new XenForo_Phrase('sonnb_xengallery_your_'. $content['content_type'] .'_has_been_deleted');
			}
			else
			{
                $contentDw->set('content_state', 'deleted');
                $contentDw->save();

				$target = $this->_buildLink('gallery/albums', $album);

				if ($this->_getControllerContentModel()->canViewDeletedContent($content))
				{
					$target = $this->_getControllerContentLink($content);
				}

				$message = new XenForo_Phrase('changes_saved');
			}
				
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				$target,
				$message
			);
		}
		else
		{
			$viewParams = array(
				'album' => $album,
				'content' => $content,
	
				'canHardDelete' => 	$this->_getControllerContentModel()->canDeleteContent($content, 'hard'),
					
				'breadCrumbs' => $this->_getControllerContentModel()->getContentBreadCrumbs($content, $album)
			);
				
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Content_Delete',
				'sonnb_xengallery_'. $content['content_type'] .'_delete',
				$viewParams
			);
		}
	}

    public function actionUndoDelete()
	{
		$this->_assertRegistrationRequired();

        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();
        $dwClass = $this->_getControllerContentDwClass();

        if (!$contentModel->canDeleteContent($content, 'soft'))
        {
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_delete_this_'. $content['content_type']);
		}

        $contentDw = XenForo_DataWriter::create($dwClass);
        $contentDw->setExistingData($content);
        $contentDw->set('content_state', 'visible');
        $contentDw->save();
		$message = new XenForo_Phrase('changes_saved');

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
			$this->_getControllerContentLink($content),
			$message
		);
	}

    public function actionReport()
	{	
		$this->_assertRegistrationRequired();

        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();
        $xfContentType = $this->_getControllerContentXfType();

		if (!$contentModel->canReportContent($content, $album, $errorPhraseKey))
		{
			throw $this->_throwFriendlyNoPermission($errorPhraseKey);
		}

		if ($this->_request->isPost())
		{
			$message = $this->_input->filterSingle('message', XenForo_Input::STRING);
			if (!$message)
			{
				return $this->responseError(new XenForo_Phrase('please_enter_reason_for_reporting_this_message'));
			}

			$this->assertNotFlooding('report');

			$reportModel = XenForo_Model::create('XenForo_Model_Report');
			$reportModel->reportContent($xfContentType, $content, $message);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS, 
				$this->_getControllerContentLink($content),
				new XenForo_Phrase('thank_you_for_reporting_this_message')
			);
		}
		else
		{
			$viewParams = array(
				'content' => $content,
				'album' => $album,
					
				'breadCrumbs' => $this->_getControllerContentModel()->getContentBreadCrumbs($content, $album),
			);

			return $this->responseView(
                'sonnb_XenGallery_ViewPublic_Content_Report',
                'sonnb_xengallery_'. $content['content_type'] .'_report',
                $viewParams
            );
		}
	}
	
	public function actionComment()
	{
		$this->_assertRegistrationRequired();
		
		$visitor = XenForo_Visitor::getInstance();

        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();

        if (!$contentModel->canCommentContent($content, $errorPhraseKey))
        {
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_leave_an_comment_here');
		}
		
		if ($this->_request->isPost())
		{
			$message = $this->_input->filterSingle('message', XenForo_Input::STRING);
			$message = XenForo_Helper_String::autoLinkBbCode($message);

			$commentDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Comment');
			
			$commentDw->bulkSet(array(
				'user_id' => $visitor['user_id'],
				'username' => $visitor['username'],
				'content_type' => $content['content_type'],
				'content_id' => $content['content_id'],
				'message' => $message	
			));
			
			$commentDw->setExtraData(sonnb_XenGallery_DataWriter_Comment::DATA_CONTENT, $content);
			
			$commentDw->preSave();

			if (!$commentDw->hasErrors())
			{
				$this->assertNotFlooding('post');
			}

			$commentDw->save();
			
			if ($this->_noRedirect())
			{
				$lastShown = $this->_input->filterSingle('after', XenForo_Input::UINT);

				$commentConditions = array(
					'comment_date' => array('>', $lastShown),
					'content_type' => $content['content_type'],
					'content_id' => $content['content_id']
				);
				$commentFetch = array('join' => sonnb_XenGallery_Model_Comment::FETCH_USER);
				$comments = $this->_getCommentModel()->getComments($commentConditions, $commentFetch);
				$comments = $this->_getCommentModel()->prepareComments($comments, $commentFetch);
			
				$viewParams = array(
					'comments' => $comments,
					'content' => $content,
					'album' => $album
				);
			
				return $this->responseView(
                    'sonnb_XenGallery_ViewPublic_Content_Comment',
                    '',
                    $viewParams
                );
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS, 
					$this->_getControllerContentLink($content),
					new XenForo_Phrase('changes_saved')
				);
			}
		}
		else
		{
			$viewParams = array(
				'album' => $album,
				'content' => $content,
				
				'breadCrumbs' => $this->_getControllerContentModel()->getContentBreadCrumbs($content, $album),
			);
			
			return $this->responseView(
                'sonnb_XenGallery_ViewPublic_Content_CommentPost',
                'sonnb_xengallery_'. $content['content_type'] .'_comment_post',
                $viewParams
            );
		}
	}
	
	public function actionComments()
	{
        list($content, $album) = $this->_getControllerContentData();

		$beforeDate = $this->_input->filterSingle('before', XenForo_Input::UINT);
		
		$commentModel = $this->_getCommentModel();
		
		$conditions = array(
			'content_type' => $content['content_type'],
			'content_id' => $content['content_id']
		);
		
		if ($beforeDate)
		{
			$conditions['comment_date'] =  array('<', $beforeDate);
		}

		//Load next 20 older comments
		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Comment::FETCH_USER, 
			'limit' => 20,
			'order' => 'comment_date'
		);
		
		$comments = $commentModel->getComments($conditions, $fetchOptions);
		
		if (!$comments)
		{
			return $this->responseMessage(new XenForo_Phrase('no_comments_to_display'));
		}
		
		$comments = $commentModel->prepareComments($comments, $fetchOptions);
		$comments = array_reverse($comments, true);

		$firstCommentShown = reset($comments);
		$lastCommentShown = end($comments);
		$remainCommentCount = $commentModel->countComments($conditions) - count($comments);

		$viewParams = array(
			'comments' => $comments,
	
			'album' => $album,
			'content' => $content,
			
			'breadCrumbs' => $this->_getControllerContentModel()->getContentBreadCrumbs($content, $album),

			'remainCommentCount' => $remainCommentCount,
			'commentShownCount' => $content['comment_count'] - $remainCommentCount,
	
			'firstCommentShown' => $firstCommentShown,
			'lastCommentShown' => $lastCommentShown
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Content_Comments',
			'sonnb_xengallery_'. $content['content_type'] .'_comments',
			$viewParams
		);
	}

	public function actionCollectionEdit()
	{
		$this->_assertRegistrationRequired();

        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();

		if (XenForo_Application::getOptions()->sonnbXG_disableCollection)
		{
			throw $this->_throwFriendlyNoPermission();
		}

		if (!$this->_getCollectionModel()->canAddToCollection($content, $errorPhraseKey))
		{
			throw $this->_throwFriendlyNoPermission($errorPhraseKey);
		}

		if ($this->_request->isPost())
		{
			$collectionId = $this->_input->filterSingle('collection_id', XenForo_Input::UINT);

			if (!$collectionId)
			{
				throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_need_to_specify_a_collection');
			}

			$contentDw = XenForo_DataWriter::create($this->_getControllerContentDwClass());
			$contentDw->setExistingData($content);
			$contentDw->set('collection_id', $collectionId);
			$contentDw->save();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->_getControllerContentLink($content),
				new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			$viewParams = array(
				'content' => $content,
				'album' => $album,

				'collections' => $this->_getCollectionModel()->getAllCachedCollections(),

				'breadCrumbs' => $contentModel->getContentBreadCrumbs($content, $album)
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Content_CollectionEdit',
				'sonnb_xengallery_'. $content['content_type'] .'_collection_edit',
				$viewParams
			);
		}
	}

	public function actionCollectionRemove()
	{
		$this->_assertRegistrationRequired();

        list($content, $album) = $this->_getControllerContentData();

		if (empty($content['collection_id']) || !$this->_getCollectionModel()->canRemoveFromCollection($content, $errorPhraseKey))
		{
			throw $this->_throwFriendlyNoPermission($errorPhraseKey);
		}

		if ($this->_request->isPost())
		{
			$contentDw = XenForo_DataWriter::create($this->_getControllerContentDwClass());
			$contentDw->setExistingData($content);
			$contentDw->set('collection_id', 0);
			$contentDw->save();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->_getControllerContentLink($content),
				new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			$collections = $this->_getCollectionModel()->getAllCachedCollections();

			$viewParams = array(
				'content' => $content,
				'album' => $album,
				'collections' => $collections,
				'collection' => isset($collections[$content['collection_id']]) ? $collections[$content['collection_id']] : array(),
				'breadCrumbs' => $this->_getControllerContentModel()->getContentBreadCrumbs($content, $album)
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Content_CollectionRemove',
				'sonnb_xengallery_'. $content['content_type'] .'_collection_remove',
				$viewParams
			);
		}
	}
	
	public function actionLike()
	{
		$this->_assertRegistrationRequired();
		
		$visitor = XenForo_Visitor::getInstance();

        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();
        $xfContentType = $this->_getControllerContentXfType();

		if (!$contentModel->canLikeContent($content, $errorPhraseKey))
		{
			throw $this->_throwFriendlyNoPermission($errorPhraseKey);
		}

		$likeModel = $this->_getLikeModel();
	
		$existingLike = $likeModel->getContentLikeByLikeUser(
            $xfContentType, $content['content_id'], $visitor->getUserId());
	
		if ($this->_request->isPost())
		{
			if ($existingLike)
			{
				$latestUsers = $likeModel->unlikeContent($existingLike);
			}
			else
			{
				$latestUsers = $likeModel->likeContent($xfContentType, $content['content_id'], $content['user_id']);
				
				if ($visitor['user_id'] != $content['user_id'])
				{
					$this->_getWatchModel()->insertUpdateWatcherByContentId(
						$visitor,
                        $content['content_type'],
                        $content['content_id']
					);
				}
			}
	
			$liked = ($existingLike ? false : true);
	
			if ($this->_noRedirect() && $latestUsers !== false)
			{
                $content['likeUsers'] = $latestUsers;
                $content['likes'] += ($liked ? 1 : ($content['likes'] ? -1: 0));
                $content['like_date'] = ($liked ? XenForo_Application::$time : 0);
	
				$viewParams = array(
					'album' => $album,
					'content' => $content,
					'liked' => $liked,
				);
	
				return $this->responseView(
					'sonnb_XenGallery_ViewPublic_Content_LikeConfirmed', '', $viewParams
				);
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
					$this->_getControllerContentLink($content)
				);
			}
		}
		else
		{
			$viewParams = array(
				'album' => $album,
				'content' => $content,
				'like' => $existingLike,
				'breadCrumbs' => $contentModel->getContentBreadCrumbs($content, $album)
			);
	
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Content_Like',
				'sonnb_xengallery_'. $content['content_type'] .'_like',
				$viewParams
			);
		}
	}
	
	public function actionLikes()
	{
		$this->_assertRegistrationRequired();

        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();
        $xfContentType = $this->_getControllerContentXfType();
		
		$likes = $this->_getLikeModel()->getContentLikes($xfContentType, $content['content_id']);
		if (!$likes)
		{
			return $this->responseError(new XenForo_Phrase('sonnb_xengallery_no_one_likes_this_'. $content['content_type'] .'_yet'));
		}
		
		$viewParams = array(
			'album' => $album,
			'content' => $content,

			'breadCrumbs' => $contentModel->getContentBreadCrumbs($content, $album),

			'likes' => $likes
		);
		
		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Content_Likes',
			'sonnb_xengallery_'. $content['content_type'] .'_likes',
			$viewParams
		);
	}

	public function actionWatch()
	{
		$this->_assertRegistrationRequired();
		$visitor = XenForo_Visitor::getInstance();

        list($content, $album) = $this->_getControllerContentData();
        $contentModel = $this->_getControllerContentModel();

		if (!$contentModel->canWatchContent($content, $errorPhraseKey))
		{
			throw $this->_throwFriendlyNoPermission($errorPhraseKey);
		}

		$watchModel = $this->_getWatchModel();

		$existingWatch = $watchModel->getWatchByUserIdContentId(
			$visitor->getUserId(),
            $content['content_type'],
            $content['content_id']
		);

		if ($this->_request->isPost())
		{
			if ($existingWatch)
			{
				$latestUsers = $watchModel->unwatchContent($existingWatch);
			}
			else
			{
				$latestUsers = $watchModel->watchContent($content['content_type'], $content['content_id']);
			}

			$watched = ($existingWatch ? false : true);

			if ($this->_noRedirect() && $latestUsers !== false)
			{
                $content['watch_date'] = ($watched ? XenForo_Application::$time : 0);

				$viewParams = array(
					'album' => $album,
					'content' => $content,
					'watched' => $watched,
				);

				return $this->responseView(
					'sonnb_XenGallery_ViewPublic_'. ucfirst($content['content_type']) .'_WatchConfirmed', '', $viewParams
				);
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
					$this->_getControllerContentLink($content)
				);
			}
		}
		else
		{
			$viewParams = array(
				'album' => $album,
				'content' => $content,
				'watch' => $existingWatch,
				'breadCrumbs' => $contentModel->getContentBreadCrumbs($content, $album)
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Content_Watch',
				'sonnb_xengallery_'. $content['content_type'] .'_watch',
				$viewParams
			);
		}
	}

    public function actionOwner()
    {
        list($content, $album) = $this->_getControllerContentData();

        if (!$this->_getControllerContentModel()->canEditAnyContent())
        {
            throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_change_owner_this_'. $content['content_type']);
        }

        if ($this->_request->isPost())
        {
            $username = $this->_input->filterSingle('username', XenForo_Input::STRING);

            $user = $this->_getUserModel()->getUserByName($username);
            if (empty($user))
            {
                throw $this->_throwFriendlyNoPermission('requested_user_not_found');
            }

            $dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Content');
            $dw->setExistingData($content);
            $dw->set('user_id', $user['user_id']);
            $dw->set('username', $user['username']);
            $dw->save();

            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
                $this->_getControllerContentLink($content),
                new XenForo_Phrase('changes_saved')
            );
        }
        else
        {
            $viewParams = array(
                'content' => $content,
                'album' => $album,
                'breadCrumbs' => $this->_getControllerContentModel()->getContentBreadCrumbs($content, $album)
            );

            return $this->responseView(
                'sonnb_XenGallery_ViewPublic_Content_Owner',
                'sonnb_xengallery_'. $content['content_type'] .'_owner',
                $viewParams
            );
        }
    }

    /**
     * Builds content link
     *
     * @param array $content
     *
     * @return string
     */
    abstract protected function _getControllerContentLink(array $content);

    /**
     * Return $content and its container album
     *
     * @return array
     */
    abstract protected function _getControllerContentData();

    /**
     * Return the DataWriter classname for current content
     *
     * @return string
     */
    abstract protected function _getControllerContentDwClass();

    /**
     * @return sonnb_XenGallery_Model_Content
     */
    abstract protected function _getControllerContentModel();

    /**
     * @return sonnb_XenGallery_Model_ContentData
     */
    abstract protected function _getControllerContentDataModel();

    /**
     * @return string
     */
    abstract protected function _getControllerContentType();

    /**
     * @return string
     */
    abstract protected function _getControllerContentXfType();
}
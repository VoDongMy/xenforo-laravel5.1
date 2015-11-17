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
class sonnb_XenGallery_ControllerPublic_XenGallery_MyPlaylist extends sonnb_XenGallery_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		if ($this->_input->inRequest('playlist_id'))
		{
			return $this->responseReroute(__CLASS__, 'view');
		}

		$xenOptions = XenForo_Application::getOptions();;
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$itemsPerPage = $xenOptions->sonnbXG_photoPerPage;
		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => 'updated_date'));
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => 'desc'));

		$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('gallery/my-playlists', array('page' => $page)));

		$conditions = array();
		$fetchOptions = array(
			'perPage' => $itemsPerPage,
			'page' => $page,
			'order' => $order,
			'orderDirection' => $orderDirection
		);

		$playlistModel = $this->_getMyPlaylistModel();

		$playlists = $playlistModel->getPlaylistsByUserId(XenForo_Visitor::getUserId(), $conditions, $fetchOptions);
		$playlists = $playlistModel->preparePlaylists($playlists);

		$totalPlaylists = $playlistModel->countPlaylistsByUserId(XenForo_Visitor::getUserId());

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_MyPlaylist_Index',
			'sonnb_xengallery_myplaylist_index',
			array(
				'playlists' => $playlists,
				'breadCrumbs' => $playlistModel->getPlaylistBreadCrumb(),
				'totalPlaylists' => $totalPlaylists
			)
		);
	}

	public function actionView()
	{
		$playlist = $this->_getPlaylistOrError();

		$playlistModel = $this->_getMyPlaylistModel();

		$xenOptions = XenForo_Application::getOptions();;
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$itemsPerPage = $xenOptions->sonnbXG_photoPerPage;
		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => 'added_date'));
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => 'desc'));

		$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('gallery/my-playlists', $playlist+array('page' => $page)));

		$conditions = array(
			'playlist_id' => $playlist['playlist_id']
		);
		$fetchOptions = array(
			'perPage' => $itemsPerPage,
			'page' => $page,
			'order' => $order,
			'orderDirection' => $orderDirection
		);

		$items = $playlistModel->getPlaylistItems($conditions, $fetchOptions);
		$itemsGrouped = $this->_getGalleryModel()->groupContentsContentType($items);
		$totalItems = $playlistModel->countPlaylistItems($conditions);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_MyPlaylist_View',
			'sonnb_xengallery_myplaylist_view',
			array(
				'playlist' => $playlist,

				'items' => $items,
				'totalItems' => $totalItems,
				'itemsGrouped' => $itemsGrouped,

				'page' => $page,
				'itemsPerPage' => $itemsPerPage,

				'breadCrumbs' => $playlistModel->getPlaylistBreadCrumb($playlist)
			)
		);

	}

	public function actionEdit()
	{
		$playlist = $this->_getPlaylistOrError();

		return $this->EditCreate($playlist);
	}

	public function actionCreate()
	{
		$playlist = array(
			'playlist_id' => 0,
			'title' => '',
			'description' => '',
			'content_count' => 0
		);

		return $this->EditCreate($playlist);
	}

	public function EditCreate(array $playlist)
	{
		$viewParams = array(
			'playlist' => $playlist,

			'breadCrumbs' => $this->_getMyPlaylistModel()->getPlaylistBreadCrumb($playlist)
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_MyPlaylist_Edit',
			'sonnb_xengallery_myplaylist_edit',
			$viewParams
		);
	}

	public function actionSave()
	{
		$playlistId = $this->_input->filterSingle('playlist_id', XenForo_Input::UINT);

		$input = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'description' => XenForo_Input::STRING
		));

		/* @var sonnb_XenGallery_DataWriter_MyPlaylist $dw */
		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_MyPlaylist');
		if ($playlistId)
		{
			$dw->setExistingData($playlistId);
		}
		$dw->bulkSet($input);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
			$this->_buildLink('gallery/my-playlists'),
			new XenForo_Phrase('changes_saved')
		);
	}

	public function actionDelete()
	{
		$playlist = $this->_getPlaylistOrError();

		if ($this->isConfirmedPost())
		{
			/* @var sonnb_XenGallery_DataWriter_MyPlaylist $dw */
			$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_MyPlaylist');
			$dw->setExistingData($playlist['playlist_id']);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				$this->_buildLink('gallery/my-playlists'),
				new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			$viewParams = array(
				'playlist' => $playlist,

				'breadCrumbs' => $this->_getMyPlaylistModel()->getPlaylistBreadCrumb($playlist)
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_MyPlaylist_Delete',
				'sonnb_xengallery_myplaylist_delete',
				$viewParams
			);
		}
	}

	public function actionRemove()
	{
		$playlist = $this->_getPlaylistOrError();
		$input = $this->_input->filter(array(
			'content_id' => XenForo_Input::UINT,
			'content_type' => XenForo_Input::STRING
		));

		if (empty($input['content_id']) || empty($input['content_type']))
		{
			return $this->getNoPermissionResponseException();
		}

		$this->_getMyPlaylistModel()->deletePlaylistItem($playlist['playlist_id'], $input['content_type'], $input['content_id']);

		if ($this->_noRedirect())
		{
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_MyPlaylist_Remove',
				'',
				array(
					'playlist' => $playlist,
					'content_id' => $input['content_id'],
					'content_type' => $input['content_type']
				)
			);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				$this->_buildLink('gallery/my-playlists', $playlist),
				new XenForo_Phrase('changes_saved')
			);
		}
	}

	public function actionUpdate()
	{
		$playlistIds = $this->_input->filterSingle('playlist_ids', array('array' => true, XenForo_Input::UINT));
		$action = $this->_input->filterSingle('do', XenForo_Input::STRING);

		if (empty($playlistIds))
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_must_select_a_playlist');
		}

		switch($action)
		{
			case 'delete':
				foreach ($playlistIds as $playlistId)
				{
					/* @var sonnb_XenGallery_DataWriter_MyPlaylist $dw */
					$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_MyPlaylist', XenForo_DataWriter::ERROR_SILENT);
					$dw->setExistingData($playlistId);
					$dw->delete();
				}
				break;
			default:
				throw $this->_throwFriendlyNoPermission();
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
			$this->_buildLink('gallery/my-playlists'),
			new XenForo_Phrase('changes_saved')
		);
	}

	protected function _getPlaylistOrError($playlistId = null)
	{
		if ($playlistId === null)
		{
			$playlistId = $this->_input->filterSingle('playlist_id', XenForo_Input::UINT);
		}

		$playlistModel = $this->_getMyPlaylistModel();

		$playlist = $playlistModel->getPlaylistById($playlistId);

		if (empty($playlist) || intval($playlist['user_id']) !== XenForo_Visitor::getUserId())
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_requested_playlist_is_not_valid');
		}

		return $playlist;
	}
}
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
class sonnb_XenGallery_ControllerAdmin_Album extends sonnb_XenGallery_ControllerAdmin_Abstract
{
	/**
	 * @return XenForo_ControllerResponse_Reroute|XenForo_ControllerResponse_View
	 */
	public function actionIndex()
	{
		if ($this->_input->inRequest('delete'))
		{
			return $this->responseReroute(__CLASS__, 'delete');
		}
		if ($this->_input->inRequest('move'))
		{
			return $this->responseReroute(__CLASS__, 'move');
		}
		if ($this->_input->inRequest('collection'))
		{
			return $this->responseReroute(__CLASS__, 'collection');
		}
		if ($this->_input->inRequest('privacy'))
		{
			return $this->responseReroute(__CLASS__, 'privacy');
		}

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$albumPerPage = 50;

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING);
		$direction = $this->_input->filterSingle('direction', XenForo_Input::STRING);
		$mode = $this->_input->filterSingle('mode', XenForo_Input::STRING);

		$albumModel = $this->_getAlbumModel();
		$conditions = array();
		$fetchOptions = array(
			'perPage' => $albumPerPage,
			'page' => $page,
			'order' => $order,
			'orderDirection' => $direction,

			'join' => sonnb_XenGallery_Model_Album::FETCH_COVER_PHOTO
		);

		if (!empty($mode))
		{
			if ($mode === 'empty')
			{
				$conditions['content_count'] = array('=', '0');
			}
			elseif ($mode === 'uncategorized')
			{
				$conditions['category_id'] = 0;
			}
		}

		$totalAlbums = $albumModel->countAlbums($conditions, $fetchOptions);
		$albums = $albumModel->getAlbums($conditions, $fetchOptions);
		$albums = $albumModel->prepareAlbums($albums, $fetchOptions);
		$albums = $albumModel->attachCoversToAlbums($albums, $fetchOptions);

		$linkParams = array(
			'order' => $order,
			'direction' => $direction
		);

		if ($mode)
		{
			$linkParams['mode'] = $mode;
		}

		$viewParams = array(
			'albums' => $albums,
			'page' => $page,
			'albumsPerPage' => $albumPerPage,
			'totalAlbums' => $totalAlbums,

			'linkParams' => $linkParams,

			'mode' => $mode
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewAdmin_Album_List',
			'sonnb_xengallery_album_list',
			$viewParams
		);
	}

	/**
	 * @return XenForo_ControllerResponse_Error|XenForo_ControllerResponse_Redirect|XenForo_ControllerResponse_View
	 */
	public function actionMove()
	{
		$albumIds = $this->_input->filterSingle('album_id', XenForo_Input::UINT, array('array' => true));
		$albumIds = array_filter($albumIds);

		if (empty($albumIds))
		{
			return $this->responseError(new XenForo_Phrase('sonnb_xengallery_please_select_at_least_one_album'));
		}

		if ($this->isConfirmedPost())
		{
			$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);

			if (!$categoryId)
			{
				return $this->responseError(new XenForo_Phrase('sonnb_xengallery_please_select_destination_category'));
			}

			foreach ($albumIds as $albumId)
			{
				$albumDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
				$albumDw->setExistingData($albumId);
				$albumDw->set('category_id', $categoryId);
				$albumDw->save();
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('gallery/albums'),
				new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			$categories = $this->_getCategoryModel()->getAllCachedCategories();

			return $this->responseView(
				'sonnb_XenGallery_ViewAdmin_Album_Move',
				'sonnb_xengallery_album_move',
				array(
					'categories' => $categories,
					'albumIds' => $albumIds
				)
			);
		}
	}

	/**
	 * @return XenForo_ControllerResponse_Error|XenForo_ControllerResponse_Redirect|XenForo_ControllerResponse_View
	 */
	public function actionCollection()
	{
		$albumIds = $this->_input->filterSingle('album_id', XenForo_Input::UINT, array('array' => true));
		$albumIds = array_filter($albumIds);

		if (empty($albumIds))
		{
			return $this->responseError(new XenForo_Phrase('sonnb_xengallery_please_select_at_least_one_album'));
		}

		if ($this->isConfirmedPost())
		{
			$collectionId = $this->_input->filterSingle('collection_id', XenForo_Input::UINT);

			if (!$collectionId)
			{
				return $this->responseError(new XenForo_Phrase('sonnb_xengallery_please_select_a_collection'));
			}

			foreach ($albumIds as $albumId)
			{
				$albumDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
				$albumDw->setExistingData($albumId);
				$albumDw->set('collection_id', $collectionId);
				$albumDw->save();
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('gallery/albums'),
				new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			$collections = $this->_getCollectionModel()->getAllCachedCollections();

			return $this->responseView(
				'sonnb_XenGallery_ViewAdmin_Album_Collection',
				'sonnb_xengallery_album_collection',
				array(
					'collections' => $collections,
					'albumIds' => $albumIds
				)
			);
		}
	}

	/**
	 * @return XenForo_ControllerResponse_Error|XenForo_ControllerResponse_Redirect|XenForo_ControllerResponse_View
	 */
	public function actionDelete()
	{
		$albumIds = $this->_input->filterSingle('album_id', XenForo_Input::UINT, array('array' => true));
		$albumIds = array_filter($albumIds);

		if (empty($albumIds))
		{
			$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);
			if ($albumId)
			{
				$albumIds[] = $albumId;
			}
		}

		if (empty($albumIds))
		{
			return $this->responseError(new XenForo_Phrase('sonnb_xengallery_please_select_at_least_one_album'));
		}

		if ($this->isConfirmedPost())
		{
			foreach ($albumIds as $_albumId)
			{
				$albumDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
				$albumDw->setExistingData($_albumId);
				$albumDw->delete();
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('gallery/albums'),
				new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			return $this->responseView(
				'sonnb_XenGallery_ViewAdmin_Album_Delete',
				'sonnb_xengallery_album_delete',
				array(
					'albumIds' => $albumIds,
					'totalAlbums' => count($albumIds)
				)
			);
		}
	}

	/**
	 * @return XenForo_ControllerResponse_Error|XenForo_ControllerResponse_View
	 */
	public function actionEdit()
	{
		$albumId = $this->_input->filterSingle('album_id', XenForo_Input::UINT);

		if (empty($albumIds))
		{
			return $this->responseError(new XenForo_Phrase('sonnb_xengallery_please_select_at_least_one_album'));
		}

		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Album::FETCH_USER | sonnb_XenGallery_Model_Album::FETCH_COVER_PHOTO
		);
		$collections = $this->_getCollectionModel()->getAllCachedCollections();
		$categories = $this->_getCategoryModel()->getAllCachedCategories();
		$album = $this->_getAlbumModel()->getAlbumById($albumId, $fetchOptions);
		$album = $this->_getAlbumModel()->prepareAlbums($album, $fetchOptions);
		$album = $this->_getAlbumModel()->attachCoverToAlbum($album, $fetchOptions);

		if ($this->isConfirmedPost())
		{

		}
		else
		{
			return $this->responseView(
				'sonnb_XenGallery_ViewAdmin_Album_Edit',
				'sonnb_xengallery_album_edit',
				array(
					'collections' => $collections,
					'categories' => $categories,
					'album' => $album
				)
			);
		}
	}

	/**
	 * @return XenForo_ControllerResponse_Error|XenForo_ControllerResponse_Redirect|XenForo_ControllerResponse_View
	 */
	public function actionPrivacy()
	{
		$albumIds = $this->_input->filterSingle('album_id', XenForo_Input::UINT, array('array' => true));
		$albumIds = array_filter($albumIds);

		if (empty($albumIds))
		{
			return $this->responseError(new XenForo_Phrase('sonnb_xengallery_please_select_at_least_one_album'));
		}

		if ($this->isConfirmedPost())
		{
			$privacy = $this->_input->filter(array(
				'allow_view' => XenForo_Input::STRING,
				'allow_comment' => XenForo_Input::STRING,
				'allow_download' => XenForo_Input::STRING,
				'allow_add_photo' => XenForo_Input::STRING
			));

			foreach ($albumIds as $albumId)
			{
				/* @var $albumDw sonnb_XenGallery_DataWriter_Album */
				$albumDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
				$albumDw->setExistingData($albumId);
				$albumDw->set('album_privacy', $privacy);
				$albumDw->save();
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('gallery/albums'),
				new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			return $this->responseView(
				'sonnb_XenGallery_ViewAdmin_Album_Privacy',
				'sonnb_xengallery_album_privacy',
				array(
					'albumIds' => $albumIds,
					'totalAlbums' => count($albumIds)
				)
			);
		}
	}
}
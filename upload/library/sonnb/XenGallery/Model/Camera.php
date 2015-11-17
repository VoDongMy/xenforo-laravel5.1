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
class sonnb_XenGallery_Model_Camera extends sonnb_XenGallery_Model_Abstract
{	
	const FETCH_PHOTO = 0x01;
	const FETCH_CAMERA = 0x02;

	public function getCameraById($id, array $fetchOptions = array())
	{
		if (!$id)
		{
			return array();
		}
			
		$conditions['camera_id'] = $id;
		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getCameras($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}
	
	public function getCamerasByIds($ids, array $fetchOptions = array())
	{
		if (!$ids)
		{
			return array();
		}
		
		$conditions['camera_id'] = $ids;
		
		return $this->getCameras($conditions, $fetchOptions);
	}

	public function getCameraByPhotoId($photoId, array $fetchOptions = array())
	{
		if (!$photoId)
		{
			return array();
		}
		
		$conditions['photo_id'] = $photoId;
		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getCameras($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}

	public function getCamerasByPhotoIds($photoIds, array $fetchOptions = array())
	{
		if (!$photoIds)
		{
			return array();
		}
		
		$conditions['photo_id'] = $photoIds;
		
		return $this->getCameras($conditions, $fetchOptions);
	}

	public function getCamerasByCameraUrl($url, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$url)
		{
			return array();
		}
		
		$conditions['camera_url'] = $url;
		
		return $this->getCameras($conditions, $fetchOptions);
	}
	
	public function getPhotosByCameras(array $cameras, array $photoFetchOptions = array())
	{
		$photos = array();
		
		if (!empty($cameras))
		{
			$photoIds = array();
			foreach ($cameras as $camera)
			{
				$photoIds[] = $camera['photo_id'];
			}

			$photos = $this->_getPhotoModel()->getContentsByIds($photoIds, $photoFetchOptions);
			$photos = $this->_getPhotoModel()->prepareContents($photos, $photoFetchOptions);
		}
		
		return $photos;
	}
	
	public function getUniqueCameras(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareCameraConditions($conditions, $fetchOptions);
		
		$sqlClauses = $this->prepareCameraFetchOptions($fetchOptions);
		
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		
		return $this->fetchAllKeyed(
				$this->limitQueryResults(
					'SELECT camera.camera_name, camera.camera_url, COUNT(*) AS photo_count
	                        ' . $sqlClauses['selectFields'] . '
	                   FROM `sonnb_xengallery_photo_camera` AS camera
	                    	' . $sqlClauses['joinTables'] . '
	                   WHERE ' . $whereConditions . '
					   GROUP BY camera.camera_url
	                    	' . $sqlClauses['orderClause'] . '
                	', $limitOptions['limit'], $limitOptions['offset']
				), 'camera.camera_url'
		);
	}
	
	public function getCameras(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareCameraConditions($conditions, $fetchOptions);
		
		$sqlClauses = $this->prepareCameraFetchOptions($fetchOptions);
		
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed(
			$this->limitQueryResults(
					'
	                   SELECT camera.*
	                        ' . $sqlClauses['selectFields'] . '
	                   FROM `sonnb_xengallery_photo_camera` AS camera
	                    	' . $sqlClauses['joinTables'] . '
	                   WHERE ' . $whereConditions . '
	                    	' . $sqlClauses['orderClause'] . '
                	', $limitOptions['limit'], $limitOptions['offset']
			), 'camera.camera_id'
		);
	}

	public function countCamerasByCameraUrl($url, array $conditions = array(), array $fetchOptions = array())
	{
		if ($url)
		{
			return 0;
		}
		
		$conditions['camera_url'] = $url;
		
		return $this->countCameras($conditions, $fetchOptions);
	}
	
	public function countCameras(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareCameraConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareCameraFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_photo_camera` AS camera
                	' . $sqlClauses['joinTables'] . '
                WHERE ' . $whereConditions . '
            ');
	}

	public function countUniqueCameras(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareCameraConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareCameraFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM
                (SELECT camera.camera_url FROM `sonnb_xengallery_photo_camera` AS camera
                	' . $sqlClauses['joinTables'] . '
                WHERE ' . $whereConditions . '
                GROUP BY camera.camera_url
                ) AS camera
            ');
	}

	public function prepareCamera(array $camera)
	{
		if ($camera)
		{
			if (!empty($camera['camera_data']))
			{
				$camera['camera_data'] = @unserialize($camera['camera_data']);

				foreach($camera['camera_data'] as $key => &$data)
				{
					$data = array(
						'name' => new XenForo_Phrase('sonnb_xengallery_camera_data__'.$key),
						'value' => Zend_Uri::check($data) ? XenForo_Helper_String::autoLinkPlainText($data) : $data
					);
				}

				if (!isset($camera['photo_count']))
				{
					$camera['photo_count'] = $this->countCameras(array('camera_name' => $camera['camera_name']));
				}

				$camera['camera_data']['total_photos'] = array(
					'name' => new XenForo_Phrase('sonnb_xengallery_camera_data__total_photos'),
					'value' => $camera['photo_count']
				);
			}
		}

		return $camera;
	}

	public function prepareCameras(array $cameras)
	{
		if ($cameras)
		{
			foreach ($cameras as &$camera)
			{
				$camera = $this->prepareCamera($camera);
			}
		}

		return $cameras;
	}
	
	public function getCameraBreadCrumbs($camera = null)
	{
		$breadCrumbs['camera_index'] = array(
				'href' => XenForo_Link::buildPublicLink('full:gallery/cameras'),
				'value' => new XenForo_Phrase('sonnb_xengallery_cameras')
		);
		
		if (!empty($camera))
		{
			$breadCrumbs['camera_view'] = array(
					'href' => XenForo_Link::buildPublicLink('full:gallery/cameras', $camera),
					'value' => $camera['camera_name']
			);
		}
		
		return $breadCrumbs;
	}
	
	public function prepareCameraFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';
		
		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';
		
			switch ($fetchOptions['order'])
			{
				case 'camera_id':
				case 'photo_id':
				case 'camera_name':
					$orderBy = 'camera.' . $fetchOptions['order'];
					$orderBySecondary = ', camera.photo_id ASC';
					break;

				case 'photo_count':
					$orderBy = $fetchOptions['order'];
					break;

				case 'photo_id':
				default:
					$orderBy = 'camera.photo_id';
			}
			if (!isset($fetchOptions['orderDirection']) || $fetchOptions['orderDirection'] === 'asc')
			{
				$orderBy .= ' ASC';
			}
			else
			{
				$orderBy .= ' DESC';
			}
		
			$orderBy .= $orderBySecondary;
		}
        
        if (!empty($fetchOptions['join']))
        {
        	if ($fetchOptions['join'] & self::FETCH_PHOTO)
        	{
        		$selectFields .= ',
					photo.*, photo_data.*';
        		$joinTables .= '
					LEFT JOIN `sonnb_xengallery_content` AS photo ON
						(photo.content_id = camera.photo_id)
					LEFT JOIN `sonnb_xengallery_content_data` AS photo_data ON
						(photo.content_data_id = photo_data.content_data_id)';
        	}

	        if ($fetchOptions['join'] & self::FETCH_CAMERA)
	        {
		        $selectFields .= ',
					camera_data.camera_id, camera_data.camera_thumbnail, camera_data.camera_data';
		        $joinTables .= '
					LEFT JOIN `sonnb_xengallery_camera` AS camera_data ON
						(camera.camera_name = camera_data.camera_name)';
	        }
        }
		
		return array(
				'selectFields' => $selectFields,
				'joinTables' => $joinTables,
				'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}
	
	public function prepareCameraConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();
		
		if (!empty($conditions['camera_id']))
		{
			if (is_array($conditions['camera_id']))
			{
				$sqlConditions[] = 'camera.camera_id IN (' . $db->quote($conditions['camera_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'camera.camera_id = ' . $db->quote($conditions['camera_id']);
			}
		}
		
		if (isset($conditions['photo_id']))
		{
			if (is_array($conditions['photo_id']))
			{
				$sqlConditions[] = 'camera.photo_id IN (' . $db->quote($conditions['photo_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'camera.photo_id = ' . $db->quote($conditions['photo_id']);
			}
		}
		
		if (!empty($conditions['camera_name']))
		{
			if (is_array($conditions['camera_name']))
			{
				$sqlConditions[] = 'camera.camera_name IN (' . $db->quote($conditions['camera_name']) . ')';
			}
			else
			{
				$sqlConditions[] = 'camera.camera_name = ' . $db->quote($conditions['camera_name']);
			}
		}

		if (!empty($conditions['camera_name_search']))
		{
			if (is_array($conditions['camera_name_search']))
			{
				$sqlConditions[] = 'camera.camera_name LIKE ' . XenForo_Db::quoteLike($conditions['camera_name_search'][0], $conditions['camera_name_search'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'camera.camera_name LIKE ' . XenForo_Db::quoteLike($conditions['camera_name_search'], 'lr', $db);
			}
		}
		
		if (!empty($conditions['camera_url']))
		{
			if (is_array($conditions['camera_url']))
			{
				$sqlConditions[] = 'camera.camera_url IN (' . $db->quote($conditions['camera_url']) . ')';
			}
			else
			{
				$sqlConditions[] = 'camera.camera_url = ' . $db->quote($conditions['camera_url']);
			}
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}

	public function getDataCameraById($id, array $fetchOptions = array())
	{
		$conditions = array(
			'camera_id' => $id
		);

		$dataCameras = $this->getDataCameras($conditions, $fetchOptions);

		return $dataCameras ? reset($dataCameras) : $dataCameras;
	}

	public function getDataCamerasByIds($ids, array $fetchOptions = array())
	{
		$conditions = array(
			'camera_id' => $ids
		);

		return $this->getDataCameras($conditions, $fetchOptions);
	}

	public function getDataCameras(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareDataCameraConditions($conditions, $fetchOptions);

		$sqlClauses = $this->prepareDataCameraFetchOptions($fetchOptions);

		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed(
			$this->limitQueryResults(
				'
				   SELECT camera.*
						' . $sqlClauses['selectFields'] . '
	                   FROM `sonnb_xengallery_camera` AS camera
	                    	' . $sqlClauses['joinTables'] . '
	                   WHERE ' . $whereConditions . '
	                    	' . $sqlClauses['orderClause'] . '
                	', $limitOptions['limit'], $limitOptions['offset']
			), 'camera_id'
		);
	}

	public function countDataCameras(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareDataCameraConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareDataCameraFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_camera` AS camera
                	' . $sqlClauses['joinTables'] . '
                WHERE ' . $whereConditions . '
            ');
	}

	public function prepareDataCameraFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';

		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';

			switch ($fetchOptions['order'])
			{
				case 'unique_id':
				case 'camera_name':
				case 'camera_vendor':
					$orderBy = 'camera.' . $fetchOptions['order'];
					$orderBySecondary = ', camera.camera_id ASC';
					break;
				case 'camera_id':
				default:
					$orderBy = 'camera.camera_id';
			}
			if (!isset($fetchOptions['orderDirection']) || $fetchOptions['orderDirection'] === 'asc')
			{
				$orderBy .= ' ASC';
			}
			else
			{
				$orderBy .= ' DESC';
			}

			$orderBy .= $orderBySecondary;
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables' => $joinTables,
			'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}

	public function prepareDataCameraConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (!empty($conditions['camera_id']))
		{
			if (is_array($conditions['camera_id']))
			{
				$sqlConditions[] = 'camera.camera_id IN (' . $db->quote($conditions['camera_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'camera.camera_id = ' . $db->quote($conditions['camera_id']);
			}
		}

		if (isset($conditions['unique_id']))
		{
			if (is_array($conditions['unique_id']))
			{
				$sqlConditions[] = 'camera.unique_id IN (' . $db->quote($conditions['unique_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'camera.unique_id = ' . $db->quote($conditions['unique_id']);
			}
		}

		if (!empty($conditions['camera_vendor']))
		{
			if (is_array($conditions['camera_vendor']))
			{
				$sqlConditions[] = 'camera.camera_vendor IN (' . $db->quote($conditions['camera_vendor']) . ')';
			}
			else
			{
				$sqlConditions[] = 'camera.camera_vendor = ' . $db->quote($conditions['camera_vendor']);
			}
		}

		if (!empty($conditions['camera_thumbnail']))
		{
			if (is_array($conditions['camera_thumbnail']))
			{
				$sqlConditions[] = 'camera.camera_thumbnail IN (' . $db->quote($conditions['camera_thumbnail']) . ')';
			}
			else
			{
				$sqlConditions[] = 'camera.camera_thumbnail = ' . $db->quote($conditions['camera_thumbnail']);
			}
		}

		if (!empty($conditions['camera_name']))
		{
			if (is_array($conditions['camera_name']))
			{
				$sqlConditions[] = 'camera.camera_name IN (' . $db->quote($conditions['camera_name']) . ')';
			}
			else
			{
				$sqlConditions[] = 'camera.camera_name = ' . $db->quote($conditions['camera_name']);
			}
		}

		if (!empty($conditions['updated_date']) && is_array($conditions['updated_date']))
		{
			list($operator, $cutOff) = $conditions['updated_date'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "camera.updated_date $operator " . $db->quote($cutOff);
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * @return sonnb_XenGallery_Model_Photo
	 */
	protected function _getPhotoModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Photo');
	}
}

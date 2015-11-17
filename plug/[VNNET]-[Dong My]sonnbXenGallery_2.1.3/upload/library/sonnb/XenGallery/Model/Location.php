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
class sonnb_XenGallery_Model_Location extends sonnb_XenGallery_Model_Abstract
{	
	public function getLocationById($id, array $fetchOptions = array())
	{
		if (!$id)
		{
			return array();
		}
			
		$conditions['location_id'] = $id;
		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getLocations($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}
	
	public function getLocationsByIds($ids, array $fetchOptions = array())
	{
		if (!$ids)
		{
			return array();
		}
		
		$conditions['location_id'] = $ids;
		
		return $this->getLocations($conditions, $fetchOptions);
	}

	public function getLocationByContentId($contentType, $contentId, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$contentType || !$contentId)
		{
			return array();
		}
		
		$conditions['content_type'] = $contentType;
		$conditions['content_id'] = $contentId;
		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getLocations($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}

	public function getLocationsByContentIds($contentType, $contentId, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$contentType || !$contentId)
		{
			return array();
		}
		
		$conditions['content_type'] = $contentType;
		$conditions['content_id'] = $contentId;
		
		return $this->getLocations($conditions, $fetchOptions);
	}

	public function getLocationsByLocationUrl($url, array $conditions = array(), array $fetchOptions = array())
	{
		if (empty($url))
		{
			return array();
		}
		
		$conditions['location_url'] = $url;
		
		return $this->getLocations($conditions, $fetchOptions);
	}

	public function getUniqueLocations(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareLocationConditions($conditions, $fetchOptions);

		$sqlClauses = $this->prepareLocationFetchOptions($fetchOptions);

		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed(
			$this->limitQueryResults(
				'
				   SELECT location.*, COUNT(*) AS content_count
						' . $sqlClauses['selectFields'] . '
		                   FROM `sonnb_xengallery_location` AS location
		                    	' . $sqlClauses['joinTables'] . '
		                   WHERE ' . $whereConditions . '
		                   GROUP BY location.location_url
		                    	' . $sqlClauses['orderClause'] . '
	                	', $limitOptions['limit'], $limitOptions['offset']
			), 'location_id'
		);
	}
	
	public function getLocations(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareLocationConditions($conditions, $fetchOptions);
		
		$sqlClauses = $this->prepareLocationFetchOptions($fetchOptions);
		
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		
		return $this->fetchAllKeyed(
				$this->limitQueryResults(
						'
		                   SELECT location.*
		                        ' . $sqlClauses['selectFields'] . '
		                   FROM `sonnb_xengallery_location` AS location
		                    	' . $sqlClauses['joinTables'] . '
		                   WHERE ' . $whereConditions . '
		                    	' . $sqlClauses['orderClause'] . '
	                	', $limitOptions['limit'], $limitOptions['offset']
				), 'location_id'
		);
	}

    public function getLocationsWithoutCoordinate($start, $limit)
    {
        $db = $this->_getDb();

        return $db->fetchAssoc($db->limit("
				SELECT *
                       FROM `sonnb_xengallery_location`
                   WHERE (location_lat = '0' OR location_lng = '0') AND location_id > ?
                   ORDER BY location_id ASC
			", $limit), $start);
    }

	public function countLocationsByLocationUrl($url, array $conditions = array(), array $fetchOptions = array())
	{
		if ($url)
		{
			return array();
		}
		
		$conditions['location_url'] = $url;
		
		return $this->countLocations($conditions, $fetchOptions);
	}
	
	public function countLocations(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareLocationConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareLocationFetchOptions($fetchOptions);
		
		return $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_location` AS location
                	' . $sqlClauses['joinTables'] . '
                WHERE ' . $whereConditions . '
            ');
	}

	public function prepareLocation(array $location)
	{
		if ($location)
		{
			$location['location_name'] = trim($location['location_name']);
			$location['location_url'] = trim($location['location_url']);
		}

		return $location;
	}

	public function prepareLocations(array $locations)
	{
		if ($locations)
		{
			foreach ($locations as &$location)
			{
				$location = $this->prepareLocation($location);
			}
		}

		return $locations;
	}

	public function getLocationBreadCrumbs($location = null)
	{
		$breadCrumbs['location_index'] = array(
			'href' => XenForo_Link::buildPublicLink('full:gallery/locations'),
			'value' => new XenForo_Phrase('sonnb_xengallery_locations')
		);

		if (!empty($location))
		{
			$breadCrumbs['location_view'] = array(
				'href' => XenForo_Link::buildPublicLink('full:gallery/locations', $location),
				'value' => $location['location_name']
			);
		}

		return $breadCrumbs;
	}
	
	public function insertLocation($contentType, $contentId, $locationData)
	{
		$existingLocation = $this->getLocationByContentId($contentType, $contentId);
		$locationData['location_name'] = utf8_bad_replace($locationData['location_name'], '');

		if ($existingLocation)
		{
			if (sonnb_XenGallery_Model_Gallery::getTitleForUrl($locationData['location_name']) !== sonnb_XenGallery_Model_Gallery::getTitleForUrl($existingLocation['location_name']))
			{
				$this->_getDb()->update('sonnb_xengallery_location',
					array(
						'location_lat' => floatval($locationData['location_lat']),
						'location_lng' => floatval($locationData['location_lng']),
						'location_name' => trim($locationData['location_name']),
						'location_url' => sonnb_XenGallery_Model_Gallery::getTitleForUrl($locationData['location_name'])
					),
					'location_id = '.$existingLocation['location_id']
				);
			}
		}
		else
		{
			if (utf8_strlen($locationData['location_name']))
			{
				$this->_getDb()->insert('sonnb_xengallery_location', array(
					'content_type' => $contentType,
					'content_id' => $contentId,
					'location_lat' => floatval($locationData['location_lat']),
					'location_lng' => floatval($locationData['location_lng']),
					'location_name' => trim($locationData['location_name']),
					'location_url' => sonnb_XenGallery_Model_Gallery::getTitleForUrl($locationData['location_name'])
				));
			}
		}
	}
	
	public function prepareLocationFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';
		
		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';
		
			switch ($fetchOptions['order'])
			{
				case 'location_id':
				case 'content_type':
				case 'content_id':
				case 'user_id':
				case 'location_name':
					$orderBy = 'location.' . $fetchOptions['order'];
					$orderBySecondary = ', location.location_url ASC';
					break;

				case 'content_count':
					$orderBy = 'content_count';
					break;

				case 'location_url':
				default:
					$orderBy = 'location.location_url';
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
	
	public function prepareLocationConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();
		
		if (!empty($conditions['location_id']))
		{
			if (is_array($conditions['location_id']))
			{
				$sqlConditions[] = 'location.location_id IN (' . $db->quote($conditions['location_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'location.location_id = ' . $db->quote($conditions['location_id']);
			}
		}
		
		if (!empty($conditions['content_type']))
		{
			if (is_array($conditions['content_type']))
			{
				$sqlConditions[] = 'location.content_type IN (' . $db->quote($conditions['content_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'location.content_type = ' . $db->quote($conditions['content_type']);
			}
		}
		
		if (!empty($conditions['content_id']))
		{
			if (is_array($conditions['content_id']))
			{
				$sqlConditions[] = 'location.content_id IN (' . $db->quote($conditions['content_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'location.content_id = ' . $db->quote($conditions['content_id']);
			}
		}
		
		if (!empty($conditions['location_name']))
		{
			if (is_array($conditions['location_name']))
			{
				$sqlConditions[] = 'location.location_name IN (' . $db->quote($conditions['location_name']) . ')';
			}
			else
			{
				$sqlConditions[] = 'location.location_name = ' . $db->quote($conditions['location_name']);
			}
		}
		
		if (!empty($conditions['location_url']))
		{
			if (is_array($conditions['location_url']))
			{
				$sqlConditions[] = 'location.location_url IN (' . $db->quote($conditions['location_url']) . ')';
			}
			else
			{
				$sqlConditions[] = 'location.location_url = ' . $db->quote($conditions['location_url']);
			}
		}

        if (!empty($conditions['location_lat']))
        {
            if (is_array($conditions['location_lat']))
            {
                $sqlConditions[] = 'location.location_lat IN (' . $db->quote($conditions['location_lat']) . ')';
            }
            else
            {
                $sqlConditions[] = 'location.location_lat = ' . $db->quote($conditions['location_lat']);
            }
        }

        if (!empty($conditions['location_lng']))
        {
            if (is_array($conditions['location_lng']))
            {
                $sqlConditions[] = 'location.location_lng IN (' . $db->quote($conditions['location_lng']) . ')';
            }
            else
            {
                $sqlConditions[] = 'location.location_lng = ' . $db->quote($conditions['location_lng']);
            }
        }
		
		return $this->getConditionsForClause($sqlConditions);
	}

	public function getGeocodeUrlForCoordinate($lat, $lng)
	{
		$key = XenForo_Application::getOptions()->sonnbXG_mapApiKey ;

		$url = "http://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lng}{key}&sensor=true";
		$url = str_replace("{key}", $key ? "&key=$key" : '', $url);

		return $url;
	}

	public function getGeocodeUrlForAddress($address)
	{
		$key = XenForo_Application::getOptions()->sonnbXG_mapApiKey ;

		$url = "http://maps.googleapis.com/maps/api/geocode/json?address={address}{key}&sensor=true";
		$url = str_replace("{address}", urlencode($address), $url);
		$url = str_replace("{key}", $key ? "&key=$key" : '', $url);

		return $url;
	}
}

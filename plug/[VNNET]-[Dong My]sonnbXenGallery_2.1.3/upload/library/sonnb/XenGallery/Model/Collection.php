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
class sonnb_XenGallery_Model_Collection extends sonnb_XenGallery_Model_Abstract
{
	public static $allCacheKey = 'sonnb_xengallery_all_collection';

	public function getCollectionById($id, array $fetchOptions = array())
	{
		if (!$id)
		{
			return array();
		}
			
		$conditions['collection_id'] = $id;
		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getCollections($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}
	
	public function getCollectionsByIds($ids, array $fetchOptions = array())
	{
		if (!$ids)
		{
			return array();
		}
		
		$conditions['collection_id'] = $ids;
		
		return $this->getCollections($conditions, $fetchOptions);
	}

	public function getAllCachedCollections()
	{
		$collections = XenForo_Application::getSimpleCacheData(self::$allCacheKey);

		if (!$collections)
		{
			$collections = $this->getCollections();

			XenForo_Application::setSimpleCacheData(self::$allCacheKey, $collections);
		}

		return $collections;
	}

	public function getCollections(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareCollectionConditions($conditions, $fetchOptions);
		
		$sqlClauses = $this->prepareCollectionFetchOptions($fetchOptions);
		
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		
		return $this->fetchAllKeyed(
				$this->limitQueryResults(
						'
		                   SELECT collection.*
		                        ' . $sqlClauses['selectFields'] . '
		                   FROM `sonnb_xengallery_collection` AS collection
		                    	' . $sqlClauses['joinTables'] . '
		                   WHERE ' . $whereConditions . '
		                    	' . $sqlClauses['orderClause'] . '
	                	', $limitOptions['limit'], $limitOptions['offset']
				), 'collection_id'
		);
	}

	public function countCollections(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareCollectionConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareCollectionFetchOptions($fetchOptions);
		
		return $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_collection` AS collection
                	' . $sqlClauses['joinTables'] . '
                WHERE ' . $whereConditions . '
            ');
	}

	public function prepareCollection(array $collection, $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!empty($collection))
		{
			$collection['title'] = XenForo_Helper_String::censorString($collection['title']);
			$collection['description'] = XenForo_Helper_String::censorString($collection['description']);
		}

		return $collection;
	}

	public function prepareCollections(array $collections, $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (empty($collections))
		{
			return $collections;
		}

		foreach ($collections as &$collection)
		{
			$collection = $this->prepareCollection($collection, $viewingUser);
		}

		return $collections;
	}

	public function getCollectionBreadCrumb($collection = null)
	{
		if ($collection === null)
		{
			return $breadCrumbs['collection'] = array(
				'href' => XenForo_Link::buildPublicLink('full:gallery/collections'),
				'value' => new XenForo_Phrase('sonnb_xengallery_collections')
			);
		}

		if (!is_array($collection))
		{
			$collections = $this->getAllCachedCollections();

			if (!isset($collections[$collection]))
			{
				return array();
			}

			$collection = $collections[$collection];
		}

		$breadCrumbs['collection'] = array(
			'href' => XenForo_Link::buildPublicLink('full:gallery/collections'),
			'value' => new XenForo_Phrase('sonnb_xengallery_collections')
		);
		$breadCrumbs['collection_'.$collection['collection_id']] = array(
			'href' => XenForo_Link::buildPublicLink('full:gallery/collections', $collection),
			'value' => $collection['title'],
			'collection_id' => $collection['collection_id']
		);

		return $breadCrumbs;
	}

	public function deleteCollection($collectionId)
	{
		$db = $this->_getDb();

		$db->update('sonnb_xengallery_album', array('collection_id' => 0), 'collection_id = '. $db->quote($collectionId));
		$db->update('sonnb_xengallery_content', array('collection_id' => 0), 'collection_id = '. $db->quote($collectionId));
		
		$db->delete('sonnb_xengallery_collection_item', 'collection_id = '. $db->quote($collectionId));
	}

	public function countCollectedItems($collectionId = 0, $contentType = null, $contentId = null)
	{
		$db = $this->_getDb();
		$conditions = array(
			'content_type' => $contentType,
			'content_id' => $contentId,
			'collection_id' => $collectionId
		);

		$whereConditions = $this->prepareCollectedItemConditions($conditions);

		return $db->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_collection_item` AS collection_item
                WHERE ' . $whereConditions . '
            ');
	}

	public function getCollectedItems($conditions = array(), $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$whereConditions = $this->prepareCollectedItemConditions($conditions);

        return $this->fetchAllKeyed(
            $this->limitQueryResults(
                '
                   SELECT collection_item.*
                        FROM sonnb_xengallery_collection_item AS collection_item
                   WHERE ' . $whereConditions . '
		                ORDER BY collection_item.collection_date DESC
	            ', $limitOptions['limit'], $limitOptions['offset']
            ), 'collection_item_id'
        );
	}

	public function getCollectedItemByCollectionContentId($collectionId = 0, $contentType = null, $contentId = null)
	{
		$db = $this->_getDb();
		$conditions = array(
			'content_type' => $contentType,
			'content_id' => $contentId,
			'collection_id' => $collectionId
		);

		$whereConditions = $this->prepareCollectedItemConditions($conditions);

		return $db->fetchRow('
                SELECT *
                FROM `sonnb_xengallery_collection_item` AS collection_item
                WHERE ' . $whereConditions . '
            ');
	}

	public function getCollectedItemsByCollectionContentId($contentType = null, $contentId = null)
	{
		$conditions = array(
			'content_type' => $contentType,
			'content_id' => $contentId
		);

		$whereConditions = $this->prepareCollectedItemConditions($conditions);

		return $this->fetchAllKeyed('
                SELECT *
                FROM `sonnb_xengallery_collection_item` AS collection_item
                WHERE ' . $whereConditions . '
            ', 'collection_item_id');
	}

	public function prepareCollectedItemConditions($conditions = array(), $fetchOptions = array())
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (!empty($conditions['content_type']))
		{
			if (is_array($conditions['content_type']))
			{
				$sqlConditions[] = "collection_item.content_type IN (" . $db->quote($conditions['content_type']) . ")";
			}
			else
			{
				$sqlConditions[] = "collection_item.content_type = " . $db->quote($conditions['content_type']);
			}
		}

		if (!empty($conditions['content_id']))
		{
			if (is_array($conditions['content_id']))
			{
				$sqlConditions[] = "collection_item.content_id IN (" . $db->quote($conditions['content_id']) . ")";
			}
			else
			{
				$sqlConditions[] = "collection_item.content_id = " . $db->quote($conditions['content_id']);
			}
		}

		if (!empty($conditions['collection_id']))
		{
			if (is_array($conditions['collection_id']))
			{
				$sqlConditions[] = "collection_item.collection_id IN (" . $db->quote($conditions['collection_id']) . ")";
			}
			else
			{
				$sqlConditions[] = "collection_item.collection_id = " . $db->quote($conditions['collection_id']);
			}
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	public function insertCollectedItem($collectionId, $contentType, $contentId, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$collectionId || !$contentType || !$contentId)
		{
			return false;
		}

		return $this->_getDb()->insert('sonnb_xengallery_collection_item', array(
			'collection_id' => $collectionId,
			'content_id' => $contentId,
			'content_type' => $contentType,
			'user_id' => $viewingUser['user_id'],
			'username' => $viewingUser['username'],
			'collection_date' => XenForo_Application::$time
		));
	}

	public function modifyCollectionCount($collectionId, $modifyValue, $contentType, $contentId)
	{
		if (empty($collectionId))
		{
			return;
		}

		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Collection', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($collectionId);

		$itemCount = $dw->get('item_count') + $modifyValue;

		$dw->set('item_count', $itemCount > 0 ? $itemCount : 0);
		$dw->save();

		if ($modifyValue > 0)
		{
			$this->insertCollectedItem(
				$collectionId,
				$contentType,
				$contentId
			);
		}
		else
		{
			$this->removeCollectedItemByCollectContent(
				$collectionId,
				$contentType,
				$contentId
			);
		}

		return $dw->get('item_count');
	}

	public function removeCollectedItemByCollectContent($collectionId, $contentType, $contentId, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$collectionId || !$contentType || !$contentId)
		{
			return false;
		}

		$db = $this->_getDb();

		return $db->delete('sonnb_xengallery_collection_item', array(
			'collection_id = '.$db->quote($collectionId),
			'content_type = '.$db->quote($contentType),
			'content_id = '.$db->quote($contentId)
		));
	}

	public function canAddToCollection(array $item, &$errorPhraseKey = null, $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'promoteToCollection');
	}

	public function canRemoveFromCollection(array $item, &$errorPhraseKey = null, $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'removeFromCollection');
	}
	
	public function prepareCollectionFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';
		
		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';
		
			switch ($fetchOptions['order'])
			{
				case 'last_content_date':
				case 'title':
					$orderBy = 'collection.' . $fetchOptions['order'];
					$orderBySecondary = ', collection.collection_date DESC';
					break;
				case 'collection_id':
				default:
					$orderBy = 'collection.collection_id';
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
	
	public function prepareCollectionConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();
		
		if (!empty($conditions['collection_id']))
		{
			if (is_array($conditions['collection_id']))
			{
				$sqlConditions[] = 'collection.collection_id IN (' . $db->quote($conditions['collection_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'collection.collection_id = ' . $db->quote($conditions['collection_id']);
			}
		}
		
		if (isset($conditions['title']))
		{
			if (is_array($conditions['title']))
			{
				$sqlConditions[] = 'collection.title IN (' . $db->quote($conditions['title']) . ')';
			}
			else
			{
				$sqlConditions[] = 'collection.title = ' . $db->quote($conditions['title']);
			}
		}

		if (!empty($conditions['item_count']) && is_array($conditions['item_count']))
		{
			list($operator, $cutOff) = $conditions['item_count'];
		
			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "collection.item_count $operator " . $db->quote($cutOff);
		}

		if (!empty($conditions['last_content_date']) && is_array($conditions['last_content_date']))
		{
			list($operator, $cutOff) = $conditions['last_content_date'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "collection.last_content_date $operator " . $db->quote($cutOff);
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}
}

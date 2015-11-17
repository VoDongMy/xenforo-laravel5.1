<?php

class vtPhong_Model_SlideShow extends XenForo_Model
{ 
    public function getSlideByThreadId($threadId, array $fetchOptions = array())
    {
        $joinOptions = $this->prepareSlideFetchOptions($fetchOptions);

        return $this->_getDb()->fetchRow('
            SELECT Slide.*
            ' . $joinOptions['selectFields'] . '
            FROM xf_thread_slide AS Slide
            ' . $joinOptions['joinTables'] . '
            WHERE Slide.thread_id = ?
        ', $threadId);
    }

    public function getSlides(array $conditions, array $fetchOptions = array())
    {
        $whereConditions = $this->prepareSlideConditions($conditions, $fetchOptions);
        
        $sqlClauses = $this->prepareSlideFetchOptions($fetchOptions);
        
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        return $this->fetchAllKeyed($this->limitQueryResults('
				SELECT Slide.*
					' . $sqlClauses['selectFields'] . '
				FROM xf_thread_slide AS Slide
				' . $sqlClauses['joinTables'] . '
				WHERE ' . $whereConditions . '
				' . $sqlClauses['orderClause'] . '
			', $limitOptions['limit'], $limitOptions['offset']
        ), 'id');
    }
    
    
    public function prepareSlideConditions(array $conditions, array &$fetchOptions)
    {
        $sqlConditions = array();
        $db = $this->_getDb();
        if (isset($conditions['thread_id']))
        {
            $sqlConditions[] .= 'Slide.thread_id = ' . $db->quote($conditions['thread_id']);
        }
        return $this->getConditionsForClause($sqlConditions);
    }
    
    public function prepareSlideFetchOptions(array $fetchOptions)
    {
        $selectFields = '';
        $joinTables = '';
        $orderBy = 'id ASC';
        
        return array(
            'selectFields' => $selectFields,
            'joinTables' => $joinTables,
            'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
        );
    }
    
    public function deleteSlide($threadId)
    {
        $db = $this->_getDb()->query("
            DELETE FROM xf_thread_slide
            WHERE thread_id = ".$threadId."
        ");
    }
}
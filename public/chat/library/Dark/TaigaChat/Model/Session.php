<?php
class Dark_TaigaChat_Model_Session extends XenForo_Model_Session
{
	
	public function prepareSessionActivityConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (!empty($conditions['userLimit']))
		{
			switch ($conditions['userLimit'])
			{
				case 'registered': $sqlConditions[] = 'session_activity.user_id > 0'; break;
				case 'guest': $sqlConditions[] = 'session_activity.user_id = 0'; break;
			}
		}

		if (!empty($conditions['user_id']))
		{
			$sqlConditions[] = 'session_activity.user_id = ' . $db->quote($conditions['user_id']);
		}

		if (!empty($conditions['forceInclude']))
		{
			$forceIncludeClause = ' OR user.user_id = ' . $db->quote($conditions['forceInclude']);
		}
		else
		{
			$forceIncludeClause = '';
		}

		if (empty($conditions['getInvisible']))
		{
			$sqlConditions[] = 'user.visible = 1 OR session_activity.user_id = 0' . $forceIncludeClause;
			$this->addFetchOptionJoin($fetchOptions, self::FETCH_USER);
		}

		if (empty($conditions['getUnconfirmed']))
		{
			$sqlConditions[] = 'user.user_state = \'valid\' OR session_activity.user_id = 0' . $forceIncludeClause;
			$this->addFetchOptionJoin($fetchOptions, self::FETCH_USER);
		}

		if (!empty($conditions['cutOff']) && is_array($conditions['cutOff']))
		{
			list($operator, $cutOff) = $conditions['cutOff'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "session_activity.view_date $operator " . $db->quote($cutOff);
		}
		
		if (!empty($conditions['controller_name']) && $conditions['controller_name'])
		{
			$sqlConditions[] = "(session_activity.controller_name = 'Dark_TaigaChat_ControllerPublic_TaigaChat')";
		}

		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function getSessionActivityByUserID($user_id)
	{

		return $this->_getDb()->fetchAll(
			'
				SELECT session_activity.*
				FROM xf_session_activity AS session_activity
				WHERE user_id = ?
			', $user_id
		);
	}
	
	public function updateUserLastActivity($user_id)
	{		
		$db = $this->_getDb();
		$db->update('xf_session_activity',
			array('view_date' => XenForo_Application::$time),
			'user_id = ' . $db->quote($user_id)
		);
		$db->update('xf_user',
			array('last_activity' => XenForo_Application::$time),
			'user_id = ' . $db->quote($user_id)
		);
	}
		
}
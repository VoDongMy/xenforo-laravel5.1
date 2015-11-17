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
class sonnb_XenGallery_Model_UserTaggingOld extends XenForo_Model
{
    protected $_plainReplacements = null;

    public function getTaggedUsersInMessage($message, &$newMessage, $replaceStyle = 'bb')
    {
        // always set this for early returns
        $newMessage = $message;
        $filteredMessage = $message;
        $this->_plainReplacements = null;

        if ($replaceStyle == 'bb')
        {
            $this->_plainReplacements = array();
            $filteredMessage = preg_replace_callback(
                '#\[(code|php|html|plain|media|url|img|user)(=[^\]]*)?](.*)\[/\\1]#siU',
                array($this, '_plainReplaceHandler'),
                $filteredMessage
            );
        }
        else if ($replaceStyle == 'text')
        {
            $this->_plainReplacements = array();
            $filteredMessage = preg_replace_callback(
                '#(?<=^|\s|[\](,]|--|@)@\[(\d+):(\'|"|&quot;|)(.*)\\2\]#iU',
                array($this, '_plainReplaceHandler'),
                $filteredMessage
            );
        }

        $matches = $this->_getPossibleTagMatches($filteredMessage);
        if (!$matches)
        {
            return array();
        }

        $usersByMatch = $this->_getTagMatchUsers($matches);
        if (!$usersByMatch)
        {
            return array();
        }

        $newMessage = '';
        $lastOffset = 0;
        $testString = strtolower($filteredMessage);
        $alertUsers = array();
        foreach ($matches AS $key => $match)
        {
            if ($match[0][1] > $lastOffset)
            {
                $newMessage .= substr($filteredMessage, $lastOffset, $match[0][1] - $lastOffset);
            }
            else if ($lastOffset > $match[0][1])
            {
                continue;
            }

            $lastOffset = $match[0][1] + strlen($match[0][0]);

            $haveMatch = false;
            if (!empty($usersByMatch[$key]))
            {
                $testName = strtolower($match[1][0]);
                $testOffset = $match[1][1];
                $endMatch = $this->_getTagEndPartialRegex(false);

                foreach ($usersByMatch[$key] AS $userId => $user)
                {
                    $nameLen = strlen($user['lower']);
                    $nextOffsetStart = $testOffset + $nameLen;
                    if (
                        ($testName == $user['lower'] || substr($testString, $testOffset, $nameLen) == $user['lower'])
                        && (!isset($testString[$nextOffsetStart]) || preg_match('#' . $endMatch . '#i', $testString[$nextOffsetStart]))
                    )
                    {
                        $alertUsers[$userId] = $user;
                        $newMessage .= $this->_replaceTagUserMatch($user, $replaceStyle);
                        $haveMatch = true;
                        $lastOffset = $testOffset + $nameLen;
                        break;
                    }
                }
            }

            if (!$haveMatch)
            {
                $newMessage .= $match[0][0];
            }
        }

        $newMessage .= substr($filteredMessage, $lastOffset);

        if ($this->_plainReplacements)
        {
            $newMessage = strtr($newMessage, $this->_plainReplacements);
            $this->_plainReplacements = null;
        }

        return $alertUsers;
    }

    protected function _plainReplaceHandler(array $match)
    {
        if (!is_array($this->_plainReplacements))
        {
            $this->_plainBbCodeReplacements = array();
        }

        $placeholder = "\x1A" . count($this->_plainReplacements) . "\x1A";

        $this->_plainReplacements[$placeholder] = $match[0];

        return $placeholder;
    }

    protected function _getTagEndPartialRegex($negated)
    {
        return '[' . ($negated ? '^' : '') . ':;,.!?\s@\'")\[-]';
    }

    protected function _getPossibleTagMatches($message)
    {
        $min = 2;

        if (!preg_match_all(
            '#(?<=^|\s|[\](,]|--)@(?!\[|\s)(([^\s@]|(?<![\s\](,-])@| ){' . $min . '}((?>[:,.!?](?=[^\s:,.!?()])|' . $this->_getTagEndPartialRegex(true) . '+?))*)#iu',
            $message, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        ))
        {
            return array();
        }

        return $matches;
    }

    protected function _getTagMatchUsers(array $matches)
    {
        $db = $this->_getDb();
        $matchKeys = array_keys($matches);
        $whereParts = array();
        $matchParts = array();
        $usersByMatch = array();

        foreach ($matches AS $key => $match)
        {
            if (utf8_strlen($match[1][0]) > 50)
            {
                // longer than max username length
                continue;
            }

            $sql = 'user.username LIKE ' . XenForo_Db::quoteLike($match[1][0], 'r', $db);
            $whereParts[] = $sql;
            $matchParts[] = 'IF(' . $sql . ', 1, 0) AS match_' . $key;
        }

        if (!$whereParts)
        {
            return array();
        }

        $userResults = $db->query("
            SELECT user.user_id, user.username,
                " . implode(', ', $matchParts) . "
            FROM xf_user AS user
            WHERE (" . implode(' OR ', $whereParts) . ")
            ORDER BY LENGTH(user.username) DESC
        ");
        while ($user = $userResults->fetch())
        {
            $userInfo = array(
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'lower' => strtolower($user['username'])
            );

            foreach ($matchKeys AS $key)
            {
                if ($user["match_$key"])
                {
                    $usersByMatch[$key][$user['user_id']] = $userInfo;
                }
            }
        }

        return $usersByMatch;
    }

    protected function _replaceTagUserMatch(array $user, $replaceStyle)
    {
        $prefix = XenForo_Application::getOptions()->userTagKeepAt ? '@' : '';

        if ($replaceStyle == 'bb')
        {
            return '[USER=' . $user['user_id'] . ']' . $prefix . $user['username'] . '[/USER]';
        }
        else if ($replaceStyle == 'text')
        {
            if (strpos($user['username'], ']') !== false)
            {
                if (strpos($user['username'], "'") !== false)
                {
                    $username = '"' . $prefix . $user['username'] . '"';
                }
                else
                {
                    $username = "'" . $prefix . $user['username'] . "'";
                }
            }
            else
            {
                $username = $prefix . $user['username'];
            }
            return '@[' . $user['user_id'] . ':' . $username . ']';
        }
        else
        {
            return $prefix . $user['username'];
        }
    }

	public function alertTaggedMembers($content, $contentId, $contentType, $tagged, array $alreadyAlerted = array())
	{
		$userIds = XenForo_Application::arrayColumn($tagged, 'user_id');
		$userIds = array_diff($userIds, $alreadyAlerted);
		$alertedUserIds = array();

		if ($userIds)
		{
			$userModel = $this->_getUserModel();
			$users = $userModel->getUsersByIds($userIds, array(
				'join' => XenForo_Model_User::FETCH_USER_OPTION
					| XenForo_Model_User::FETCH_USER_PROFILE
					| XenForo_Model_User::FETCH_USER_PERMISSIONS
			));

			foreach ($users AS $user)
			{
				if (isset($alertedUserIds[$user['user_id']])
					|| $user['user_id'] == $content['user_id']
					|| $user['user_id'] == $content['user_id']
				)
				{
					continue;
				}

				$xfContentType = $this->_getXfContentType($contentType);

				if (!$userModel->isUserIgnored($user, $content['user_id'])
					&& !$userModel->isUserIgnored($user, $content['user_id'])
					&& XenForo_Model_Alert::userReceivesAlert($user, $xfContentType, 'tagging')
				)
				{
					$alertedUserIds[$user['user_id']] = true;

					XenForo_Model_Alert::alert($user['user_id'],
						$content['user_id'], $content['username'],
						$contentType, $contentId,
						'tagging'
					);
				}
			}
		}

		return array_keys($alertedUserIds);
	}

	protected function _getXfContentType($contentType)
	{
		$xfContentType = '';

		switch ($contentType)
		{
			case sonnb_XenGallery_Model_Album::$contentType:
				$xfContentType = sonnb_XenGallery_Model_Album::$xfContentType;
				break;
			case sonnb_XenGallery_Model_Photo::$contentType:
				$xfContentType = sonnb_XenGallery_Model_Photo::$xfContentType;
				break;
			case sonnb_XenGallery_Model_Video::$contentType:
				$xfContentType = sonnb_XenGallery_Model_Video::$xfContentType;
				break;
			case sonnb_XenGallery_Model_Comment::$contentType:
				$xfContentType = sonnb_XenGallery_Model_Comment::$xfContentType;
				break;
		}

		return $xfContentType;
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}
<?php

class XenGallery_Model_UserTag extends XenForo_Model
{
	public function getTagById($tagId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xengallery_user_tag
			WHERE tag_id = ?
		', $tagId);
	}
	
	public function getTagByMediaAndUserId($mediaId, $userId, $type = '')
	{
		$stateClause = '';
		if ($type)
		{
			$stateClause = "AND tag_state = '$type'";
		}

		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xengallery_user_tag
			WHERE media_id = ? ' .
			$stateClause . '
			AND user_id = ?
		', array($mediaId, $userId));
	}
	
	public function getAllTagsByMediaId($mediaId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xengallery_user_tag
			WHERE media_id = ?	
		', 'tag_id', $mediaId);
	}

	public function deleteTagsByMediaId($mediaId)
	{
		$tags = $this->getAllTagsByMediaId($mediaId);

		foreach ($tags AS $tag)
		{

		}
	}
	
	public function prepareTag(array $tag)
	{
		$tag['tag_data'] = @unserialize($tag['tag_data']);
		
		return $tag;
	}
	
	public function prepareTags(array $tags)
	{
		foreach ($tags AS $key => &$tag)
		{
			$tag = $this->prepareTag($tag);
			if ($tag['tag_state'] != 'approved')
			{
				unset ($tags[$key]);
			}
		}
		
		return $tags;
	}

	/**
	 * Gets the tag state for a newly inserted tags by the viewing user.
	 *
	 * @param array $taggedUser
	 * @param array|null $viewingUser
	 *
	 * @return string Tag state (approved, pending (no need to reject here))
	 */
	public function getTagInsertState(array $taggedUser = array(), array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$tagExpiry = XenForo_Application::getOptions()->xengalleryTagExpiry;
		$bypassTag = false;
		if (empty($tagExpiry['enabled']))
		{
			$bypassTag = true;
		}

		$taggingSelf = false;
		if ($taggedUser)
		{
			$taggingSelf = $taggedUser['user_id'] == $viewingUser['user_id'];
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'bypassApproval')
			|| $taggingSelf || $bypassTag
		)
		{
			return 'approved';
		}
		else
		{
			return 'pending';
		}
	}

    public function mergeTagsWithUsers(array $tags, array $users)
	{
        foreach ($tags AS $key => &$tag)
        {
			if (!isset($users[$tag['user_id']]))
			{
				unset ($tags[$key]);
				continue;
			}

			$tag['user'] = $users[$tag['user_id']];

			if (isset($tag['tag_by_user_id']) && isset($tag['tag_by_username']))
			{
				$tag['tag_user'] = array(
					'user_id' => $tag['tag_by_user_id'],
					'username' => $tag['tag_by_username']
				);
			}
        }

        return $tags;
    }
}
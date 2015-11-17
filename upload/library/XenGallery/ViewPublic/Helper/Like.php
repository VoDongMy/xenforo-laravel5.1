<?php

class XenGallery_ViewPublic_Helper_Like
{
	/**
	 * Fetches view parameters for a like link return.
	 * Gets the like/unlike phrase, and like/unlike CSS class instructions.
	 *
	 * @see XenForo_ViewPublic_Post_LikeConfirmed::renderJson() for an example.
	 *
	 * @param boolean $liked
	 *
	 * @return array
	 */
	public static function getLikeViewParams($liked, $likeCount)
	{
		$output = array();

		if ($liked)
		{
			$output['term'] = new XenForo_Phrase('xengallery_thumb_unlike', array('count' => $likeCount));

			$output['cssClasses'] = array(
				'like' => '-',
				'unlike' => '+'
			);
		}
		else
		{
			$output['term'] = new XenForo_Phrase('xengallery_thumb_like', array('count' => $likeCount));

			$output['cssClasses'] = array(
				'like' => '+',
				'unlike' => '-'
			);
		}

		return $output;
	}

}
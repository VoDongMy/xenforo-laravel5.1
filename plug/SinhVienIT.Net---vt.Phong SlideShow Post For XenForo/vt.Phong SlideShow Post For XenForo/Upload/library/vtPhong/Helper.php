<?php
class vtPhong_Helper
{
    public static function setSlideToRegistry(array $data)
    {
        $slide = array();
        for ($i=0;$i<=XenForo_Application::getOptions()->maxPicInSlide;$i++)
        {
            if (isset($data['url'][$i]))
            {
                // valid src img
                if (self::_checkImage($data['url'][$i]))
                {
                    $slide[] = array(
                        'url_slide' => $data['url'][$i],
                        'title_slide' => $data['title'][$i],
                        'des_slide' => $data['des'][$i]
                    );
                }
            }
            else
            {
                break;
            }
        }

        XenForo_Application::set('vtPhong.slide.' . XenForo_Visitor::getUserId(), $slide);
    }
    
    protected static function _checkImage($url) 
    {
        $is = @getimagesize ($url);
        if ($is) return true;

        return false;
    }
}
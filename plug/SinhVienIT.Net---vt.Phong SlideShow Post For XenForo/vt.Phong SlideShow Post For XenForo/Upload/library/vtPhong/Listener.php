<?php

class vtPhong_Listener
{
    public static function templateCreate($templateName, array &$params, XenForo_Template_Abstract $template)
    {
        if ($templateName == 'thread_create')
    	{
    		$template->preloadTemplate('vtPhong_add_slide_show_template');
    	}
        
        if ($templateName == 'post_edit')
    	{
    		$template->preloadTemplate('vtPhong_add_slide_show_template');
    	}
        
        if ($templateName == 'forum_edit')
    	{
    		$template->preloadTemplate('vtPhong_forum_options_add_slide');
    	}
    }

    public static function templateHook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
    {   
        // sonnn edit
        if ($hookName == 'thread_create_fields_extra')
        {
            $ourTemplate = $template->create('vtPhong_add_slide_show_template', $template->getParams());
            $rendered = $ourTemplate->render();
            $contents .= $rendered;
        }
        
        if ($hookName == 'post_edit_fields_extra')
        {
            $ourTemplate = $template->create('vtPhong_add_slide_show_template', $template->getParams('slides'));
            $rendered = $ourTemplate->render();
            $contents .= $rendered;
        }
        
        if ($hookName == 'admin_forum_edit_forum_options')
        {
            $ourTemplate = $template->create('vtPhong_forum_options_add_slide', $template->getParams());
            $rendered = $ourTemplate->render();
            $contents .= $rendered;
        }
    }
    
    public static function loadClassControllers($class, array &$extend)
    {
        if ($class == 'XenForo_ControllerPublic_Forum')
        {
            $extend[] = 'vtPhong_ControllerPublic_Forum';
        }
        
        if ($class == 'XenForo_ControllerPublic_Thread')
        {
            $extend[] = 'vtPhong_ControllerPublic_Thread';
        }
        
        if ($class == 'XenForo_ControllerPublic_Post')
        {
            $extend[] = 'vtPhong_ControllerPublic_Post';
        }
        
        if ($class == 'XenForo_ControllerAdmin_Forum')
        {
            $extend[] = 'vtPhong_ControllerAdmin_Forum';
        }
    }
    
    public static function loadClassDataWriter($class, array &$extend)
    {
        if ($class == 'XenForo_DataWriter_Discussion_Thread')
        {
            $extend[] = 'vtPhong_DataWriter_Discussion_Thread';
        }
        
        if ($class == 'XenForo_DataWriter_Forum')
        {
            $extend[] = 'vtPhong_DataWriter_Forum';
        }
        
        if ($class == 'XenForo_DataWriter_DiscussionMessage_Post')
        {
            $extend[] = 'vtPhong_DataWriter_DiscussionMessage_Post';
        }
    }
}
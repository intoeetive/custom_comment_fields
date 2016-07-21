<?php

/*
=====================================================
 Custom Comment Fields
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2013 Yuri Salimovskiy
=====================================================
 This software is intended for usage with
 ExpressionEngine CMS, version 2.0 or higher
=====================================================
 File: ext.custom_comment_fields.php
-----------------------------------------------------
 Purpose: Custom fields added to EE commenting system
=====================================================
*/

if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}

require_once PATH_THIRD.'custom_comment_fields/config.php';

class Custom_comment_fields_upd {

    var $version = CUSTOM_COMMENT_FIELDS_ADDON_VERSION;
    
    function __construct() { 
        // Make a local reference to the ExpressionEngine super object 
        $this->EE =& get_instance(); 
    } 
    
    function install() { 
		
		$this->EE->load->dbforge(); 

        $data = array( 'module_name' => 'Custom_comment_fields' , 'module_version' => $this->version, 'has_cp_backend' => 'y'); 
        $this->EE->db->insert('modules', $data); 

        
        return TRUE; 
        
    } 
    
    function uninstall() { 
		
		$this->EE->load->dbforge(); 
		
		$this->EE->db->select('module_id'); 
        $query = $this->EE->db->get_where('modules', array('module_name' => 'Custom_comment_fields')); 
        
        $this->EE->db->where('module_id', $query->row('module_id')); 
        $this->EE->db->delete('module_member_groups'); 
        
        $this->EE->db->where('module_name', 'Custom_comment_fields'); 
        $this->EE->db->delete('modules'); 
        
        $this->EE->db->where('class', 'Custom_comment_fields'); 
        $this->EE->db->delete('actions'); 

        return TRUE; 
    } 
    
    
    function update($current='') 
	{ 
        if ($current < 2.0) 
		{ 
            // Do your 2.0 version update queries 
        } 
        return TRUE; 
    } 
	

}
/* END */
?>
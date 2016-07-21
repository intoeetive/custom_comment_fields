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

class Custom_comment_fields_ext {

	var $name	     	= CUSTOM_COMMENT_FIELDS_ADDON_NAME;
	var $version 		= CUSTOM_COMMENT_FIELDS_ADDON_VERSION;
	var $description	= 'Custom fields added to EE commenting system';
	var $settings_exist	= 'n';
	var $docs_url		= 'http://www.intoeetive.com/docs/custom_comment_fields.html';
    
    var $settings 		= array();
    var $site_id		= 1;
    
	/**
	 * Constructor
	 *
	 * @param 	mixed	Settings array or empty string if none exist.
	 */
	function __construct($settings = '')
	{
		$this->EE =& get_instance();
		$this->settings = $settings;
		$this->site_id = $this->EE->config->item('site_id'); 
	}
    
    /**
     * Activate Extension
     */
    function activate_extension()
    {
        $this->EE->load->dbforge(); 
        
        $hooks = array(

    		array(
    			'hook'		=> 'insert_comment_end',
    			'method'	=> 'insert_comment_data',
    			'priority'	=> 10
    		),
    		array(
    			'hook'		=> 'comment_entries_tagdata',
    			'method'	=> 'fetch_comment_data',
    			'priority'	=> 10
    		),
    		array(
    			'hook'		=> 'comment_form_end',
    			'method'	=> 'set_enctype',
    			'priority'	=> 10
    		),
    		array(
    			'hook'		=> 'comment_form_tagdata',
    			'method'	=> 'display_form_fields',
    			'priority'	=> 10
    		)
    	);
    	
        foreach ($hooks AS $hook)
    	{
    		$data = array(
        		'class'		=> __CLASS__,
        		'method'	=> $hook['method'],
        		'hook'		=> $hook['hook'],
        		'settings'	=> '',
        		'priority'	=> $hook['priority'],
        		'version'	=> $this->version,
        		'enabled'	=> 'y'
        	);
            $this->EE->db->insert('extensions', $data);
    	}	
    	
    	//exp_comment_fields
        $fields = array(
			'field_id'			=> array('type' => 'INT',		'unsigned' => TRUE, 'auto_increment' => TRUE),
			'site_id'			=> array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0),
			'field_type'		=> array('type' => 'VARCHAR',	'constraint'=> 50,	'default' => ''),
			'field_name'		=> array('type' => 'VARCHAR',	'constraint'=> 250,	'default' => ''),
			'field_label'		=> array('type' => 'VARCHAR',	'constraint'=> 250,	'default' => ''),
			
			'field_related_to'	=> array('type' => 'VARCHAR',	'constraint'=> 12,	'default' => 'channel'),
			'field_related_id'	=> array('type' => 'INT',	'unsigned' => TRUE),
			'field_related_orderby'	=> array('type' => 'VARCHAR',	'constraint'=> 12,	'default' => 'date'),
			'field_related_sort'	=> array('type' => 'VARCHAR',	'constraint'=> 4,	'default' => 'desc'),
			'field_related_max'		=> array('type' => 'SMALLINT',	'constraint'=> 4),		
			
			'field_ta_rows'		=> array('type' => 'TINYINT',	'constraint'=> 2,	'default' => '8'),
			'field_maxl'		=> array('type' => 'SMALLINT',	'constraint'=> 3,	'null' => TRUE),
			'field_required'	=> array('type' => 'CHAR',		'constraint'=> 1,	'default' => 'n'),
			
			'field_text_direction'	=> array('type' => 'CHAR',		'constraint'=> 3,	'default' => 'ltr'),
			'field_fmt'			=> array('type' => 'VARCHAR',	'constraint'=> 40,	'default' => 'xhtml'),
			'field_show_fmt'	=> array('type' => 'CHAR',		'constraint'=> 1,	'default' => 'y'),
			
			'field_order'		=> array('type' => 'INT',		'unsigned' => TRUE,	'constraint'=> 3),
			'field_content_type'=> array('type' => 'VARCHAR',	'constraint'=> 20,	'default' => 'any'),
			
			'field_list_items'  => array('type' => 'TEXT'),
			'field_settings'    => array('type' => 'TEXT')
		);


		$this->EE->dbforge->add_field($fields);
		$this->EE->dbforge->add_key('field_id', TRUE);
		$this->EE->dbforge->create_table('comment_fields', TRUE);
		
		//exp_comment_data
        $fields = array(
			'comment_id'		=> array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0),
			'entry_id'			=> array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0),
			'site_id'			=> array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0)
		);

		$this->EE->dbforge->add_field($fields);
		$this->EE->dbforge->add_key('comment_id', TRUE);
		$this->EE->dbforge->create_table('comment_data', TRUE);
        
    }
    
    /**
     * Update Extension
     */
    function update_extension($current = '')
    {
    	if ($current == '' OR $current == $this->version)
    	{
    		return FALSE;
    	}
    	
    	if ($current < '2.0')
    	{
    		// Update to version 1.0
    	}
    	
    	$this->EE->db->where('class', __CLASS__);
    	$this->EE->db->update(
    				'extensions', 
    				array('version' => $this->version)
    	);
    }
    
    
    /**
     * Disable Extension
     */
    function disable_extension()
    {
    	$this->EE->load->dbforge(); 
		
		$this->EE->db->where('class', __CLASS__);
    	$this->EE->db->delete('extensions');
    	
    	$this->EE->dbforge->drop_table('comment_fields');
        $this->EE->dbforge->drop_table('comment_data');
    }
    
    
    
    function settings()
    {
		$settings = array();
        
        
        return $settings;
    }
    
    
    function fetch_comment_data($tagdata, $row)
    {
		
		$custom_fields_query = $this->EE->db->select('field_id, field_name')
								->from('comment_fields')
								->where('site_id', $this->EE->config->item('site_id'))
								->get();
								
		$fields = array();

        $this->EE->db->from('comment_data');

		$sql_what = "comment_id";
		foreach ($custom_fields_query->result_array() as $custom_field)
        {
			$fields['field_id_'.$custom_field['field_id']] = $custom_field['field_name'];
			$sql_what .= ', field_id_'.$custom_field['field_id'].' AS '.$custom_field['field_name'];
        }
		
		$this->EE->db->select($sql_what);
		
		$this->EE->db->where("comment_id", $row['comment_id']);
		
        $query = $this->EE->db->get();
        
        $vars = array();
        
        if ($query->num_rows()==0)
        {
        	foreach ($fields as $field_tech=>$field_human)
            {
            	$vars[$field_human] = '';
            }
        }
        else
        {

	        foreach ($query->result_array() as $row)
			{
	            
	            foreach ($fields as $field_tech=>$field_human)
	            {
	            	$vars[$field_human] = $row[$field_human];
	            	if (strpos($vars[$field_human], '{filedir_') !== FALSE)
					{
						$this->EE->load->library('file_field');
						$vars[$field_human] = $this->EE->file_field->parse_string($vars[$field_human]);
					}
	            }
	
			}
		}
		
		
		$tagdata = $this->EE->TMPL->parse_variables_row($tagdata, $vars);
		
		return $tagdata;
    }
    
    
    function insert_comment_data($data, $comment_moderate, $comment_id)
    {
    	//process custom fields, if any of them were submitted
		$custom_fields_query = $this->EE->db->select()
								->from('comment_fields')
								->where('site_id', $this->EE->config->item('site_id'))
								->get();	
		if ($custom_fields_query->num_rows()>0)
		{
			$this->EE->load->library('api');
			$this->EE->api->instantiate('channel_fields');
			$this->EE->api->instantiate('channel_entries');
			$cust_data = array();
			foreach ($custom_fields_query->result_array() as $custom_field)
			{
				//$this->EE->api_channel_fields->settings = $this->EE->api_channel_fields->get_settings($custom_field['field_id']);
				//$this->EE->api_channel_fields->settings[$custom_field['field_id']]['field_id'] = $custom_field['field_id'];
				//$this->EE->api_channel_fields->settings[$custom_field['field_id']]['field_type'] = $custom_field['field_type'];
				//var_dump($this->EE->api_channel_fields->settings);
				if (isset($_POST['field_id_'.$custom_field['field_id']]) || isset($_POST[$custom_field['field_name']]))
				{
					if (isset($_POST[$custom_field['field_name']]))
					{
						$_POST['field_id_'.$custom_field['field_id']] = $_POST[$custom_field['field_name']];
					}
					if (isset($_POST[$custom_field['field_name'].'_existing']))
					{
						$_POST['field_id_'.$custom_field['field_id'].'_existing'] = $_POST[$custom_field['field_name'].'_existing'];
					}
					
					if (isset($_FILES[$custom_field['field_name']]))
					{
						$_FILES['field_id_'.$custom_field['field_id']] = $_FILES[$custom_field['field_name']];
					}
					
					if ($custom_field['field_type'] == 'multi_select' OR $custom_field['field_type'] == 'checkboxes')
					{
						$this->EE->api_channel_entries->_prep_multi_field($_POST, $custom_field);
					}
					
					if ($_POST['field_id_'.$custom_field['field_id']]=='') continue;

					/*
					if ($custom_field['field_type'] == 'file')
					{
						unset($_POST['field_id_'.$custom_field['field_id'].'_directory']);
					}
					elseif ($custom_field['field_type'] == 'date')
					{
						$func = '_prep_'.$custom_field['field_type'].'_field';
						$this->EE->api_channel_entries->$func($_POST, $custom_field);
					}
					elseif ($custom_field['field_type'] == 'multi_select' OR $custom_field['field_type'] == 'checkboxes')
					{
						$this->EE->api_channel_entries->_prep_multi_field($_POST, $custom_field);
					}
					elseif ($custom_field['field_type'] == 'rel')
					{
						//relation fields are too complicated for initial release
					}*/
				
					//$val = (isset($_POST['field_id_'.$custom_field['field_id']]))?$_POST['field_id_'.$custom_field['field_id']]:$_POST[$custom_field['field_name']];

					$this->EE->api_channel_fields->include_handler($custom_field['field_type']);
					$this->EE->api_channel_fields->setup_handler($custom_field['field_type']);

					$this->EE->api_channel_fields->field_type = $custom_field['field_type'];
		
					$this->EE->api_channel_fields->field_types[$this->EE->api_channel_fields->field_type]->field_name = $custom_field['field_name'];
					
					$this->EE->api_channel_fields->field_types[$this->EE->api_channel_fields->field_type]->field_id = $custom_field['field_id'];
					
					$this->EE->api_channel_fields->field_types[$this->EE->api_channel_fields->field_type]->settings = array_merge(unserialize(base64_decode($custom_field['field_settings'])), $custom_field, $this->EE->api_channel_fields->get_global_settings($this->EE->api_channel_fields->field_type));
				
					$valid = $this->EE->api_channel_fields->apply('validate', array('field_id_'.$custom_field['field_id'] => $_POST['field_id_'.$custom_field['field_id']]));
					$val = $this->EE->api_channel_fields->apply('save', array('field_id_'.$custom_field['field_id'] => $_POST['field_id_'.$custom_field['field_id']]));
					//var_dump($this->EE->api_channel_fields->field_types[$this->EE->api_channel_fields->field_type]->settings);
					//var_dump($valid);
					//var_dump($val);
					//exit();
					$cust_data['field_id_'.$custom_field['field_id']] = $val;
    
				}
			}
			$cust_data['comment_id'] = $comment_id;
			$cust_data['entry_id'] = $data['entry_id'];
			$cust_data['site_id'] = $data['site_id'];

			$this->EE->db->insert('comment_data', $cust_data);
		}
    	
    }
    
    
    function set_enctype($html)
    {
    	$custom_fields_query = $this->EE->db->select('field_id')
								->from('comment_fields')
								->where_in('field_type', array('file', 'safecracker_file'))
								->where('site_id', $this->EE->config->item('site_id'))
								->get();
		if ($custom_fields_query->num_rows() > 0)
		{
			$html = str_replace('<form', '<form enctype="multipart/form-data"', $html);
		}
		return $html;
    }
    
    
    
    function display_form_fields($tagdata)
    {
    	$custom_fields_query = $this->EE->db->select()
								->from('comment_fields')
								->where('site_id', $this->EE->config->item('site_id'))
								->get();	
		if ($custom_fields_query->num_rows()>0)
		{
			$this->EE->load->library('api');
			$this->EE->load->helper('form');
			$this->EE->router->set_class('cp');
			$this->EE->load->library('cp');
			$this->EE->router->set_class('ee');
			$this->EE->load->library('javascript');
			$this->EE->api->instantiate('channel_fields');
			$vars = array();
			foreach ($custom_fields_query->result_array() as $custom_field)
			{
				$this->EE->api_channel_fields->include_handler($custom_field['field_type']);
				$this->EE->api_channel_fields->setup_handler($custom_field['field_type']);
				
				$this->EE->api_channel_fields->field_type = $custom_field['field_type'];
		
				$this->EE->api_channel_fields->field_types[$this->EE->api_channel_fields->field_type]->field_name = $custom_field['field_name'];
				
				$this->EE->api_channel_fields->field_types[$this->EE->api_channel_fields->field_type]->field_id = $custom_field['field_id'];
				
				$this->EE->api_channel_fields->field_types[$this->EE->api_channel_fields->field_type]->settings = array_merge(unserialize(base64_decode($custom_field['field_settings'])), $custom_field, $this->EE->api_channel_fields->get_global_settings($this->EE->api_channel_fields->field_type));
		
				//$this->EE->api_channel_fields->get_settings($custom_field['field_id']);
				$vars[$custom_field['field_name']] = $this->EE->api_channel_fields->apply('display_field', array('data' => ''));
                
                if ($custom_field['field_type']=='file')
                {
                    $tagdata .= "			<style type=\"text/css\">
			.file_set {
				color: #5F6C74;
				font-family: Helvetica, Arial, sans-serif;
				font-size: 12px;
				position: relative;
                display: none;
			}
			.filename {
				border: 1px solid #B6C0C2;
				position: relative;
				padding: 5px;
				text-align: center;
				float: left;
				margin: 0 0 5px;
			}
			.undo_remove {
				color: #5F6C74;
				font-family: Helvetica, Arial, sans-serif;
				font-size: 12px;
				text-decoration: underline;
				display: block;
				padding: 0;
				margin: 0 0 8px;
			}
			.filename img {
				display: block;
			}
			.filename p {
				padding: 0;
				margin: 4px 0 0;
			}
			.remove_file {
				position: absolute;
				top: -6px;
				left: -6px;
				z-index: 5;
			}
			.clear {
				clear: both;
			}
            .file_upload>.sub_filename
            {
                display: none;
            }
			</style>";
                    $tagdata .= "<script type=\"text/javascript\">
			$(document).ready(function() {
				function setupFileField(container) {
					var last_value = [],
						fileselector = container.find('.no_file'),
						hidden_name = container.find('input[name*=\"_hidden_file\"]').prop('name'),
						placeholder;

					if ( ! hidden_name) {
						return;
					}

					remove = $('<input/>', {
						'type': 'hidden',
						'value': '',
						'name': hidden_name.replace('_hidden_file', '')
					});

					container.find(\".remove_file\").click(function() {
						container.find(\"input[type=hidden][name*='hidden']\").val(function(i, current_value) {
							last_value[i] = current_value;
							return '';
						});
						container.find(\".file_set\").hide();
						container.find('.sub_filename a').show();
						fileselector.show();
						container.append(remove);

						return false;
					});

					container.find('.undo_remove').click(function() {
						container.find(\"input[type=hidden]\").val(function(i) {
							return last_value.length ? last_value[i] : '';
						});
						container.find(\".file_set\").show();
						container.find('.sub_filename a').hide();
						fileselector.hide();
						remove.remove();

						return false;
					});
				}
				// most of them
				$('.file_field').not('.grid_field .file_field').each(function() {
					setupFileField($(this));
				});
			});                    
                    </script>";
                }
                
			}
			$tagdata = $this->EE->TMPL->parse_variables_row($tagdata, $vars);
		}
		
		return $tagdata;
		
    }

  

}
// END CLASS

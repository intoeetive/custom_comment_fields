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


class Custom_comment_fields {

    var $return_data	= ''; 						// Bah!
    
    var $settings = array();
    
    var $perpage = 100;
    
    var $comment 		= array(); //comments to display; array of objects
    var $thread_start_ids		= array(); //
    var $thread_end_ids		= array(); //
    var $thread_open_ids		= array(); //
    var $thread_close_ids		= array(); //
    
    var $prev_level = 0; //
    var $displayed_prev = 0; //
    var $displayed_prev_root = 0; //
    
    var $types_table = array(
		'entry'		=> array(
						'data_table'		=>'exp_channel_titles',
						'data_title_field'	=>'title',
						'entry_id_field'	=>'entry_id',
						'join_rule'			=>'exp_channels.channel_id=exp_channel_titles.channel_id',
						'group_table'		=>'exp_channels',
						'group_title_field'	=>'channel_title',
						'group_id_field'	=>'channel_id',
						),
		'member'	=> array(
						'data_table'		=>'exp_members',
						'data_title_field'	=>'screen_name',
						'entry_id_field'	=>'member_id',
						'join_rule'			=>'exp_members.group_id=exp_member_groups.group_id',
						'group_table'		=>'exp_member_groups',
						'group_title_field'	=>'group_title',
						'group_id_field'	=>'group_id',
						),
		'category'	=> array(
						'data_table'		=>'exp_categories',
						'data_title_field'	=>'cat_name',
						'entry_id_field'	=>'cat_id',
						'join_rule'			=>'exp_categories.group_id=exp_category_groups.group_id',
						'group_table'		=>'exp_category_groups',
						'group_title_field'	=>'group_name',
						'group_id_field'	=>'group_id',
						)
	);

    /** ----------------------------------------
    /**  Constructor
    /** ----------------------------------------*/

    function __construct()
    {        
    	$this->EE =& get_instance(); 
    	
    	$this->EE->lang->loadfile('comment');     
		$this->EE->lang->loadfile('better_comments');
        
    }
    /* END */
    
	
	
	function _show_error($text, $ajax=false, $type='general')
	{
		if ($ajax==false)
		{
			$this->EE->output->show_user_error($type, $text);
		}
		else
		{
			if (is_array($text))
			{
				$text = explode(BR.BR, $text);
			}
			return "<div class=\"better_comments_error_message\">".$text."<\/div>";
		}
	}
	
	
	
	
	
	
 	function insert_new_comment()
    {
        
        $ajax = ($this->EE->input->get_post('ajax')=='yes')?true:false;
        
        $data_type = ($this->EE->input->get_post('data_type')!='')?$this->EE->input->get_post('data_type'):'entry';

		// Basic input check
        // entry_id provided?
        if ( ! is_numeric($_POST['entry_id']))
        {
        	return $this->_show_error(lang('invalid_entry_id'), $ajax, 'submission');
        }
        //comment provided?
        if ($_POST['comment'] == '')
        {
            return $this->_show_error(lang('cmt_missing_comment'), $ajax, 'submission');
        }
        
		//ban checks

		if ($this->EE->session->userdata('is_banned') == TRUE)
		{
			return $this->_show_error(lang('not_authorized'), $ajax);
		}

		if ($this->EE->config->item('require_ip_for_posting') == 'y')
		{
			if ($this->EE->input->ip_address() == '0.0.0.0' OR $this->EE->session->userdata['user_agent'] == "")
			{
				return $this->_show_error(lang('not_authorized'), $ajax);
			}
		}
		
		if ($this->EE->config->item('require_ip_for_posting') == 'y' && $this->EE->config->item('ip2nation') == 'y')
		{
			if ($this->EE->session->nation_ban_check(false)===false)
			{
				return $this->_show_error($this->EE->config->item('ban_message'), $ajax);
			}
		}
		

		if ($this->EE->session->userdata['can_post_comments'] == 'n')
		{
			return $this->_show_error(lang('cmt_no_authorized_for_comments'), $ajax);
		}

		if ($this->EE->blacklist->blacklisted == 'y' && $this->EE->blacklist->whitelisted == 'n')
		{
			return $this->_show_error(lang('not_authorized'), $ajax);
		}
        
        // -------------------------------------------
		// 'insert_comment_start' hook.
		//  - Allows complete rewrite of comment submission routine.
		//  - Or could be used to modify the POST data before processing
		//
			$edata = $this->EE->extensions->call('insert_comment_start');
			if ($this->EE->extensions->end_script === TRUE) return;
		//
		// -------------------------------------------
        
        
        //we'll start colleting data to insert here... quite early, eh?
        $data = array(
                        'channel_id'    => 0,
                        'entry_id'      => $this->EE->input->post('entry_id'),
                        'author_id'     => $this->EE->session->userdata('member_id'),
                        'name'          => ($this->EE->input->post('name')!='')?$this->EE->input->post('name'):$this->EE->session->userdata('screen_name'),
                        'email'         => ($this->EE->input->post('email')!='')?$this->EE->input->post('email'):$this->EE->session->userdata('email'),
                        'url'           => ($this->EE->input->post('url')!='')?$this->EE->input->post('url'):$this->EE->session->userdata('url'),
                        'location'      => ($this->EE->input->post('location')!='')?$this->EE->input->post('location'):$this->EE->session->userdata('location'),
                        'comment'       => $this->EE->input->post('comment'),
                        'comment_date'  => $this->EE->localize->now,
                        'ip_address'    => $this->EE->input->ip_address(),
                        'status'		=> 'o',
                        'site_id'		=> $this->EE->config->item('site_id'),
                        'parent_id'     => 0,
                        'root_id'       => 0,
                        'level'         => 0,
                        'data_type'		=> $data_type
                     );
        
        if (in_array($data_type, $this->types_table))
    	{
    		$record = $this->types_table[$data_type];
    		if ($data_type != 'entry')
    		{
				//basic query for non-entries only, entries have advanced one below
				$query = $this->EE->db->select($record['data_table'].'.'.$record['data_title_field'].', '.$record['data_table'].'.'.$record['group_id_field'].', '.$record['group_table'].'.'.$record['group_title_field'])->from($record['data_table'])->join($record['group_table'], $record['join_rule'], 'left')->where($record['entry_id_field'], $data['entry_id'])->get();
				if ($query->num_rows() == 0)
				{
					return $this->_show_error(lang('invalid_entry_id'), $ajax, 'submission');
				}
				$data['channel_id'] = $query->row($record['group_id_field']);
			}
    	}
        
        
        if ($data_type == 'entry')
        {
	        $sql = "SELECT exp_channel_titles.title,
							exp_channel_titles.url_title,
							exp_channel_titles.entry_id,
							exp_channel_titles.channel_id,
							exp_channel_titles.author_id,
							exp_channel_titles.comment_total,
							exp_channel_titles.allow_comments,
							exp_channel_titles.entry_date,
							exp_channel_titles.comment_expiration_date,
							exp_channels.channel_title,
							exp_channels.comment_system_enabled,
							exp_channels.comment_max_chars,
							exp_channels.comment_use_captcha,
							exp_channels.comment_timelock,
							exp_channels.comment_require_membership,
							exp_channels.comment_moderate,
							exp_channels.comment_require_email,
							exp_channels.comment_notify,
							exp_channels.comment_notify_authors,
							exp_channels.comment_notify_emails,
							exp_channels.comment_expiration, 
							exp_channels.channel_url, 
							exp_channels.comment_url,
							exp_channels.site_id  
					FROM	exp_channel_titles, exp_channels
					WHERE	exp_channel_titles.channel_id = exp_channels.channel_id
					AND	exp_channel_titles.entry_id = '".$this->EE->input->post('entry_id')."'";
					
					//  Added entry_status param, so it is possible to post to closed title
					//AND	exp_channel_titles.status != 'closed' ";
	
			// -------------------------------------------
			// 'insert_comment_preferences_sql' hook.
			//  - Rewrite or add to the comment preference sql query
			//  - Could be handy for comment/channel restrictions
			//
				if ($this->EE->extensions->active_hook('insert_comment_preferences_sql') === TRUE)
				{
					$sql = $this->EE->extensions->call('insert_comment_preferences_sql', $sql);
					if ($this->EE->extensions->end_script === TRUE) return;
				}
			//
			// -------------------------------------------
	
			$query = $this->EE->db->query($sql);
	
			unset($sql);
	
	
			if ($query->num_rows() == 0)
			{
				return $this->_show_error(lang('invalid_entry_id'), $ajax, 'submission');
			}
	
			if ($query->row('allow_comments')  == 'n' OR $query->row('comment_system_enabled')  == 'n')
			{
				return $this->_show_error(lang('cmt_comments_not_allowed'), $ajax, 'submission');
			}
	
			$force_moderation = $query->row('comment_moderate');
	
			if ($query->row('comment_expiration_date')  > 0)
			{
				if ($this->EE->localize->now > $query->row('comment_expiration_date') )
				{
					if ($this->EE->config->item('comment_moderation_override') == 'y')
					{
						$force_moderation = 'y';
					}
					else
					{
						return $this->_show_error(lang('cmt_commenting_has_expired'), $ajax, 'submission');
					}
				}
			}
	
			if ($query->row('comment_timelock') != '' AND $query->row('comment_timelock') > 0)
			{
				if ($this->EE->session->userdata['group_id'] != 1)
				{
					$time = $this->EE->localize->now - $query->row('comment_timelock') ;
	
					$this->EE->db->where('comment_date >', $time);
					$this->EE->db->where('ip_address', $this->EE->input->ip_address());
					
					$result = $this->EE->db->count_all_results('comments');
	
					if ($result  > 0)
					{
						return $this->_show_error(str_replace("%s", $query->row('comment_timelock') , lang('cmt_comments_timelock')), $ajax, 'submission');
					}
				}
			}
			
			
			
			if ($query->row('comment_require_membership')  == 'y')
			{
				// Not logged in
	
				if ($this->EE->session->userdata('member_id') == 0)
				{
					return $this->_show_error(lang('cmt_must_be_member'), $ajax, 'submission');
				}
	
				// Membership is pending
	
				if ($this->EE->session->userdata['group_id'] == 4)
				{
					return $this->_show_error(lang('cmt_account_not_active'), $ajax);
				}
	
			}
			
			
			
			$data['channel_id'] = $query->row('channel_id');
		
		}
		
		//avoiding duplicates?
		if ($this->EE->config->item('deny_duplicate_data') == 'y')
		{
			if ($this->EE->session->userdata['group_id'] != 1)
			{
				$this->EE->db->where('comment', $_POST['comment']);
				$result = $this->EE->db->count_all_results('comments');

				if ($result > 0)
				{
					return $this->_show_error(lang('cmt_duplicate_comment_warning'), $ajax, 'submission');
				}
			}
		}


		$error = array();
		
		if ($this->EE->session->userdata('member_id')==0)
		{

			if ($data['name'] == '')
			{
				$error[] = $this->EE->lang->line('cmt_missing_name');
			}
	
			if ($this->EE->session->ban_check('screen_name', $data['name']))
			{
				$error[] = $this->EE->lang->line('cmt_name_not_allowed');
			}
			
			// do some xtra cleanup for name
			$data['name'] = str_replace(array('<', '>'), array('&lt;', '&gt;'), $data['name']);			
	
			if ($data_type == 'entry' && $query->row('comment_require_email')  == 'y')
			{
				$this->EE->load->helper('email');
	
				if ($data['email'] == '')
				{
					$error[] = $this->EE->lang->line('cmt_missing_email');
				}
				elseif ( ! valid_email($data['email']))
				{
					$error[] = $this->EE->lang->line('cmt_invalid_email');
				}
			}
	
			if ($data['email'] != '')
			{
				if ($this->EE->session->ban_check('email', $data['email']))
				{
					$error[] = $this->EE->lang->line('cmt_banned_email');
				}
			}
		}

		// comment too big?

		if ($data_type == 'entry' && $query->row('comment_max_chars')  != '' && $query->row('comment_max_chars')  != 0)
		{
			if (strlen($_POST['comment']) > $query->row('comment_max_chars') )
			{
				$str = str_replace("%n", strlen($_POST['comment']), $this->EE->lang->line('cmt_too_large'));

				$str = str_replace("%x", $query->row('comment_max_chars') , $str);

				$error[] = $str;
			}
		}

		if (count($error) > 0)
		{
			return $this->_show_error($error, $ajax, 'submission');
		}

		// CAPTCHA check

		if ($data_type == 'entry' && $query->row('comment_use_captcha')  == 'y')
		{
			if ($this->EE->config->item('captcha_require_members') == 'y'  OR  ($this->EE->config->item('captcha_require_members') == 'n' AND $this->EE->session->userdata('member_id') == 0))
			{
				if ( ! isset($_POST['captcha']) OR $_POST['captcha'] == '')
				{
					return $this->_show_error(lang('captcha_required'), $ajax, 'submission');
				}
				else
				{
					$this->EE->db->where('word', $_POST['captcha']);
					$this->EE->db->where('ip_address', $this->EE->input->ip_address());
					$this->EE->db->where('date > UNIX_TIMESTAMP()-7200', NULL, FALSE);
					
					$result = $this->EE->db->count_all_results('captcha');

					if ($result == 0)
					{
						return $this->_show_error(lang('captcha_incorrect'), $ajax, 'submission');
					}

					$this->EE->db->query("DELETE FROM exp_captcha WHERE (word='".$this->EE->db->escape_str($_POST['captcha'])."' AND ip_address = '".$this->EE->input->ip_address()."') OR date < UNIX_TIMESTAMP()-7200");
				}
			}
		}
        
        if ($this->EE->input->post('parent_id')!='' && $this->EE->input->post('parent_id')!=0)
        {
            $data['parent_id'] = (int) $this->EE->input->post('parent_id');
            $data['root_id'] = $data['parent_id'];
            $data['level'] = 0;
            do {
                $data['level']++;
                $q = $this->EE->db->select('parent_id')->from('exp_comments')->where('comment_id', $data['root_id'])->get();
                if ($q->row('parent_id')==0) break;
                $data['root_id'] = $q->row('parent_id');
            } while ($data['root_id']!=0);
            
        }
        
        $comment_moderate		= ($this->EE->session->userdata['group_id'] == 1 OR $this->EE->session->userdata['exclude_from_moderation'] == 'y') ? 'n' : $force_moderation;
        if ($comment_moderate=='y') $data['status'] = 'p';
        

          
        // -------------------------------------------
		// 'insert_comment_insert_array' hook.
		//  - Modify any of the soon to be inserted values
		//
			if ($this->EE->extensions->active_hook('insert_comment_insert_array') === TRUE)
			{
				$data = $this->EE->extensions->call('insert_comment_insert_array', $data);
				if ($this->EE->extensions->end_script === TRUE) return $edata;
			}
		//
        // -------------------------------------------
        
        
        if ($this->EE->security->secure_forms_check($this->EE->input->post('XID')) == FALSE)
		{
			return $this->_show_error(lang('not_authorized'), $ajax, 'submission');
		}				        

      
        $this->EE->db->query($this->EE->db->insert_string('exp_comments', $data));
        $comment_id = $this->EE->db->insert_id();

		
		//process custom fields, if any of them were submitted
		$custom_fields_query = $this->EE->db->select('field_id, field_name, field_type, field_related_to, field_related_id')->from('comment_fields')->where('site_id', $this->EE->config->item('site_id'))->get();	
		if ($custom_fields_query->num_rows()>0)
		{
			$this->EE->load->library('api');
			$this->EE->api->instantiate('channel_fields');
			$this->EE->api->instantiate('channel_entries');
			$cust_data = array();
			var_dump($_POST);
			foreach ($custom_fields_query->result_array() as $custom_field)
			{
				//$this->EE->api_channel_fields->settings = $this->EE->api_channel_fields->get_settings($custom_field['field_id']);
				//$this->EE->api_channel_fields->settings[$custom_field['field_id']]['field_id'] = $custom_field['field_id'];
				//$this->EE->api_channel_fields->settings[$custom_field['field_id']]['field_type'] = $custom_field['field_type'];
				//var_dump($this->EE->api_channel_fields->settings);
				var_dump($_POST[$custom_field['field_name']]);
				if (isset($_POST['field_id_'.$custom_field['field_id']]) || isset($_POST[$custom_field['field_name']]))
				{
					if (isset($_POST[$custom_field['field_name']]))
					{
						$_POST['field_id_'.$custom_field['field_id']] = $_POST[$custom_field['field_name']];
					}
					
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
					$this->EE->api_channel_fields->get_settings($custom_field['field_id']);
					$val = $this->EE->api_channel_fields->apply('save', array('field_id_'.$custom_field['field_id'] => $_POST['field_id_'.$custom_field['field_id']]));
					$cust_data['field_id_'.$custom_field['field_id']] = $val;
    
				}
			}
			$cust_data['comment_id'] = $comment_id;
			$cust_data['entry_id'] = $data['entry_id'];
			$cust_data['data_type'] = $data['data_type'];
			$cust_data['site_id'] = $data['site_id'];
			var_dump($cust_data);
			$this->EE->db->insert('comment_data', $cust_data);
		}


		$this->EE->load->helper('string');
        $this->EE->load->helper('text'); 
        
        $this->EE->load->library('email');
        
        $this->EE->load->library('subscription');
		
        $this->EE->load->library('typography');
        $this->EE->typography->initialize();
 		$this->EE->typography->smileys = FALSE;
		$data['comment'] = $this->EE->typography->parse_type($data['comment'], 
				   array(
							'text_format'   => 'none',
							'html_format'   => 'none',
							'auto_links'    => 'n',
							'allow_img_url' => 'n'
						)
				);
    	
    	$sent = array($data['email']);//make sure current address is always 'kinda' sent
    	
    	$data['cp_url'] = $this->EE->config->item('cp_url').'?D=cp&C=addons_modules&M=show_module_cp&module=better_comments&method=edit_comment&comment_id='.$comment_id;
    	$data['cp_delete_url'] = $this->EE->config->item('cp_url').'?D=cp&C=addons_modules&M=show_module_cp&module=comment&method=delete_comment_confirm&comment_id='.$comment_id;
    	$data['cp_approve_url'] = $this->EE->config->item('cp_url').'?D=cp&C=addons_modules&M=show_module_cp&module=comment&method=change_comment_status&status=o&comment_id='.$comment_id;
    	$data['cp_close_url'] = $this->EE->config->item('cp_url').'?D=cp&C=addons_modules&M=show_module_cp&module=comment&method=change_comment_status&status=c&comment_id='.$comment_id;
    	$data['unsubscribe_url'] = '';
    	
    	
    	$data['comment_url'] = $this->EE->input->remove_session_id($this->EE->input->get_post('URI'));
    	//we need to find out what page should we redirect to
    	$perpage = (is_numeric($this->EE->input->get_post('limit'))) ? $this->EE->input->get_post('limit') : $this->perpage;
    	$this->EE->db->where('data_type', $data_type);
    	$this->EE->db->where('entry_id', $data['entry_id']);
    	$this->EE->db->where('level', '0');
    	$this->EE->db->where('status', 'o');
    	$count = $this->EE->db->count_all_results('comments');
    	if ($count > $perpage)
    	{
    		if ($data['root_id']==0)
    		{
    			//if it's root level then we go to last page
				$current_page = floor($count/$perpage)*$perpage;
    			$data['comment_url'] = $data['comment_url'].'/P'.$current_page;
    		}
    		else
    		{
    			//if it's not root then we need to find out root's number in natural order
    			$this->EE->db->where('data_type', $data_type);
		    	$this->EE->db->where('entry_id', $data['entry_id']);
		    	$this->EE->db->where('level', '0');
		    	$this->EE->db->where('comment_id <=', $data['root_id']);
		    	$this->EE->db->where('status', 'o');
		    	$count = $this->EE->db->count_all_results('comments');
    			$current_page = floor($count/$perpage)*$perpage;
    			$data['comment_url'] = $data['comment_url'].'/P'.$current_page;
    		}
    	}
    	
    	$data['title'] = $data_type;
    	$data['group'] = $this->EE->config->item('site_name');
    	$data['comment_id'] = $comment_id;
    	$data['recipient_name'] = '';

    	if (in_array($data_type, $this->types_table))
    	{
    		$data['title'] = $query->row($record['data_title_field']);
    		$data['group'] = $query->row($record['group_title_field']);
    	}
    	
    	$replyto = ($data['email'] == '') ? $this->EE->config->item('webmaster_email') : $data['email'];
    	
        //admin and author notifications are for entries only
        $this->EE->load->library('template');
        $this->EE->TMPL = $this->EE->template;
        if ($data_type == 'entry')
        {

			if ($query->row('comment_notify') == 'y')
        	{
        		//notify admin(s)
        		$recipients = array();
        		
        		if ($query->row('comment_notify_emails') != '')
        		{
	        		$notify_address = reduce_multiples($query->row('comment_notify_emails'), ",", TRUE); 
	        		$recipients = explode(",",$notify_address);  
				}   
        		//if comments are moderated, make sure we include webmaster's email
        		if ($comment_moderate=='y') $recipients[] = $this->EE->config->item('webmaster_email');
        		
        		$recipients = array_unique($recipients);  
		        $key = array_search($email, $recipients);
		        if ($key!==FALSE)
		        {
		            unset($recipients[$key]);
		        }
		        
		        if (count($recipients)>0)
		        {
		        	$template = $this->EE->functions->fetch_email_template('better_comments_admin_notification');

					$email_subject = $this->EE->TMPL->parse_variables_row($template['title'], $data);
					$email_message = $this->EE->TMPL->parse_variables_row($template['data'], $data);
					
					foreach ($recipients as $email)
					{
						$this->EE->email->initialize();
						$this->EE->email->from($this->EE->config->item('webmaster_email'), $this->EE->config->item('webmaster_name'));
						$this->EE->email->to($email);
						$this->EE->email->reply_to($replyto);
						$this->EE->email->subject($email_subject);
						$this->EE->email->message(entities_to_ascii($email_message));
						$this->EE->email->send();
	
						$sent[] = $email;
					}

		        }
        	}
        	
        	if ($query->row('comment_notify_authors')=='y')
        	{
        		//notify author, if not yet
        		$q = $this->EE->db->select('email')->from('members')->where('member_id', $query->row('author_id'))->get();
				$email = $q->row('email');
				
				if (! in_array($email, $sent))
				{
					$template = $this->EE->functions->fetch_email_template('better_comments_author_notification');

					$email_subject = $this->EE->TMPL->parse_variables_row($template['title'], $data);
					$email_message = $this->EE->TMPL->parse_variables_row($template['data'], $data);
					
					$this->EE->email->initialize();
					$this->EE->email->from($this->EE->config->item('webmaster_email'), $this->EE->config->item('webmaster_name'));
					$this->EE->email->to($email);
					$this->EE->email->reply_to($replyto);
					$this->EE->email->subject($email_subject);
					$this->EE->email->message(entities_to_ascii($email_message));
					$this->EE->email->send();

					$sent[] = $email;
				}
        	}
        	
        }
        
        //user notifications
        $data['cp_url'] = '';
    	$data['cp_delete_url'] = '';
    	$data['cp_approve_url'] = '';
    	$data['cp_close_url'] = '';
    	
    	if ($comment_moderate!='y')
    	{
    		
			if ($data_type=='entry')
			{
				$this->EE->db->set('comment_total', $query->row('comment_total')  + 1);
				$this->EE->db->set('recent_comment_date', $this->EE->localize->now);
				$this->EE->db->where('entry_id', $data['entry_id']);
				$this->EE->db->update('channel_titles');
				
				$this->EE->stats->update_comment_stats($data['channel_id'], $this->EE->localize->now);
			}

			if ($this->EE->session->userdata('member_id') != 0)
			{
				$this->EE->db->select('total_comments');
				$this->EE->db->where('member_id', $this->EE->session->userdata('member_id'));
				
				$q = $this->EE->db->get('members');
				
				$this->EE->db->set('total_comments', $q->row('total_comments') + 1);
				$this->EE->db->set('last_comment_date', $this->EE->localize->now);
				$this->EE->db->where('member_id', $this->EE->session->userdata('member_id'));
				
				$this->EE->db->update('members');
			}


	        $recipients = array();
	        
	        /** ----------------------------------------
			/**  Fetch email notification addresses
			/** ----------------------------------------*/
			
			$this->EE->subscription->init('comment', array('entry_id' => $data['entry_id'], 'data_type' => $data_type, 'thread_id' => 0), TRUE);
			$ignore = ($this->EE->session->userdata('member_id') != 0) ? $this->EE->session->userdata('member_id') : $this->EE->input->post('email');
			$subscriptions = $this->EE->subscription->get_subscriptions($ignore);
			
			if ($data['parent_id']!=0)
			{
				$all_parents = array();
				$current_parent = $comment_id;
		        while ($current_parent!=0)
		        {
		            $this->EE->db->select('parent_id')
		                    ->from('comments')
		                    ->where('comment_id', $current_parent);
		            $all_parents_q = $this->EE->db->get();
		            $current_parent = $all_parents_q->row('parent_id');
		            if ($current_parent!=0) 
		            {
		            	$all_parents[] = $current_parent;
						
						$this->EE->subscription->init('comment', array('entry_id' => $data['entry_id'], 'data_type' => $data_type, 'thread_id' => $current_parent), TRUE);
						$subscriptions_thread = $this->EE->subscription->get_subscriptions($ignore);
						$subscriptions = array_merge($subscriptions, $subscriptions_thread);
		            }
		        } 
      		}
			
			
			$recipients = $this->_fetch_email_recipients($data['entry_id'], $subscriptions, $data_type);
						
			$act = $this->EE->db->select('action_id')->from('actions')->where('class', 'Better_comments')->where('method', 'remove_subscription')->get();
			
			if (count($recipients)>0)
	        {
	        	$template = $this->EE->functions->fetch_email_template('better_comments_user_notification');

				$email_subject = $this->EE->TMPL->parse_variables_row($template['title'], $data);
				$email_message = $this->EE->TMPL->parse_variables_row($template['data'], $data);
				
				foreach ($recipients as $recipient_data)
				{
					$email = $recipient_data[0];
					$data['recipient_name'] = $recipient_data[2];
					$sub	= $subscriptions[$recipient_data[1]];
					$data['unsubscribe_url'] = $this->EE->functions->fetch_site_index(0, 0).QUERY_MARKER.'ACT='.$act->row('action_id').'&id='.$sub['subscription_id'].'&hash='.$sub['hash'];
					
					if (! in_array($email, $sent))
					{
						$this->EE->email->initialize();
						$this->EE->email->from($this->EE->config->item('webmaster_email'), $this->EE->config->item('webmaster_name'));
						$this->EE->email->to($email);
						$this->EE->email->reply_to($replyto);
						$this->EE->email->subject($email_subject);
						$this->EE->email->message(entities_to_ascii($email_message));
						$this->EE->email->send();
	
						$sent[] = $email;
					}
					
				}

	        }
	        
	        
	        // clear the cache
			$this->EE->functions->clear_caching('all', $this->EE->input->post('URI'), true);
			
			if ($data_type=='entry')
			{
				// clear out the entry_id version if the url_title is in the URI, and vice versa
				if (preg_match("#\/".preg_quote($query->row('url_title'))."\/#", $this->EE->input->post('URI'), $matches))
				{
					$this->EE->functions->clear_caching('all', preg_replace("#".preg_quote($matches['0'])."#", "/{$query->row('entry_id')}/", true));
				}
				else
				{
					$this->EE->functions->clear_caching('all', preg_replace("#{$query->row('entry_id')}#", $query->row('url_title'), true));
				}
			}
	        
    	}
    	
        //add subscriptions
        
        $notify_thread = $this->EE->input->post('notify_thread') ? 'y' : 'n';

        if ($this->EE->input->post('notify_me')!==false)
        {
            //entry level first
			$this->EE->subscription->init('comment', array('entry_id' => $entry_id, $data_type => 'entry', 'thread_id' => 0), TRUE);

			if ($this->EE->session->userdata('member_id')!=0)
			{
				$this->EE->subscription->subscribe($this->EE->session->userdata('member_id'));
			}
			else if ($data['email']!='')
			{
				$this->EE->subscription->subscribe($data['email']);
			}
			
		}
		else if ($this->EE->input->post('notify_thread')!==false)
		{
			//if there has been entry level subscription then no need to subscribe for thread
			
			//check whether any subscription with higher precedence exists
			if (!isset($all_parents))
			{
				$all_parents = array();
				$current_parent = $comment_id;
		        while ($current_parent!=0)
		        {
		            $this->EE->db->select('parent_id')
		                    ->from('comments')
		                    ->where('comment_id', $current_parent);
		            $all_parents_q = $this->EE->db->get();
		            $current_parent = $all_parents_q->row('parent_id');
		            if ($current_parent!=0) 
		            {
		            	$all_parents[] = $current_parent;
		            }
		        } 
			}
			$all_parents[] = 0;
			$all_parents_list = implode(",",$all_parents);
			$thread_id = ($parent_id!=0) ? $root_id : $comment_id;
            
            $qstr = "SELECT subscription_id FROM exp_comment_subscriptions WHERE entry_id='".$data['entry_id']."' AND data_type='".$data_type."' AND thread_id IN (".$all_parents_list.") AND (email='".$data['email']."' ";
            if ($this->EE->session->userdata('member_id')!=0)
            {
                $qstr .= " OR (member_id='".$this->EE->session->userdata('member_id')."')";
            }
            $qstr .= " ) ";
            
            $subscr_q = $this->EE->db->query($qstr);
            if ($subscr_q->num_rows()==0)
            {
            	$this->EE->subscription->init('comment', array('entry_id' => $entry_id, $data_type => 'entry', 'thread_id' => $thread_id), TRUE);

				if ($this->EE->session->userdata('member_id')!=0)
				{
					$this->EE->subscription->subscribe($this->EE->session->userdata('member_id'));
				}
				else if ($data['email']!='')
				{
					$this->EE->subscription->subscribe($data['email']);
				}
            }
            
			
		}


		//fortune cookies
		if ($this->EE->input->post('notify_me')!==false)
		{        
			$this->EE->functions->set_cookie('notify_me', 'yes', 60*60*24*365);
		}
		else
		{
			$this->EE->functions->set_cookie('notify_me', 'no', 60*60*24*365);
		}
        /*
        if ($this->EE->input->post('notify_thread')!==false)
		{        
            $this->EE->functions->set_cookie('notify_thread_'.$root_id, 'yes', 60*60*24*365);
		}
		else
		{
            $this->EE->functions->set_cookie('notify_thread_'.$root_id, 'no', 60*60*24*365);
		}
		*/
        if ($this->EE->input->post('save_info'))
        {        
            $this->EE->functions->set_cookie('save_info',   'yes',              60*60*24*365);
            $this->EE->functions->set_cookie('my_name',     $data['name'],     60*60*24*365);
            $this->EE->functions->set_cookie('my_email',    $data['email'],    60*60*24*365);
            $this->EE->functions->set_cookie('my_url',      $data['url'],      60*60*24*365);
            $this->EE->functions->set_cookie('my_location', $data['location'], 60*60*24*365);
        }
        else
        {
			$this->EE->functions->set_cookie('save_info',   'no', 60*60*24*365);
			$this->EE->functions->set_cookie('my_name',     '');
			$this->EE->functions->set_cookie('my_email',    '');
			$this->EE->functions->set_cookie('my_url',      '');
			$this->EE->functions->set_cookie('my_location', '');
        }
        
        // -------------------------------------------
        // 'insert_comment_end' hook.
        //  - More emails, more processing, different redirect
        //  - $comment_id added 1.6.1
		//
        	$edata = $this->EE->extensions->call('insert_comment_end', $data, $comment_moderate, $comment_id);
        	if ($this->EE->extensions->end_script === TRUE) return;
        //
        // -------------------------------------------

        //success! do the redirect
        
        if ($ajax)
        {
        	if ($comment_moderate == 'y')
	        {						
				return lang('cmt_will_be_reviewed');
			}
			else
			{
	        	return $data['comment'].BR.'&mdash; '.$data['name'];
	    	}
        }
        else
        {
	        if ($comment_moderate == 'y')
	        {
				$msg = array(	'title' 	=> $this->EE->lang->line('cmt_comment_accepted'),
								'heading'	=> $this->EE->lang->line('thank_you'),
								'content'	=> $this->EE->lang->line('cmt_will_be_reviewed'),
								'redirect'	=> $_POST['RET'],							
								'link'		=> array($_POST['RET'], $this->EE->lang->line('cmt_return_to_comments')),
								'rate'		=> 3
							 );
						
				return $this->EE->output->show_message($msg);
			}
			else
			{
	        	$this->EE->functions->redirect($this->EE->input->post('RET'));
	    	}
  		}
    }    
    /* END */
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    function _fetch_email_recipients($entry_id, $subscriptions = array(), $data_type='entry')
	{
		$recipients = array();
		
		$subscribed_members = array();
		$subscribed_emails = array();
	
		// No subscribers - skip!
		if (count($subscriptions))
		{
			// Do some work to figure out the user's name,
			// either based on their user id or on the comment
			// data (stored with their email)
			
			$subscription_map = array();
		
			foreach($subscriptions as $id => $row)
			{
				if ($row['member_id'])
				{
					$subscribed_members[] = $row['member_id'];
					$subscription_map[$row['member_id']] = $id;
				}
				else
				{
					$subscribed_emails[] = $row['email'];
					$subscription_map[$row['email']] = $id;
				}
			}
			
			if (count($subscribed_members))
			{
				$this->EE->db->select('member_id, email, screen_name, smart_notifications');
				$this->EE->db->where_in('member_id', $subscribed_members);
				$member_q = $this->EE->db->get('members');

				if ($member_q->num_rows() > 0)
				{
					foreach ($member_q->result() as $row)
					{
						$sub_id = $subscription_map[$row->member_id];
						
						if ($row->smart_notifications == 'n' OR $subscriptions[$sub_id]['notification_sent'] == 'n')
						{
							$recipients[] = array($row->email, $sub_id, $row->screen_name);
						}
					}
				}
			}


			// Get all comments by these subscribers so we can grab their names

			if (count($subscribed_emails))
			{
				$this->EE->db->select('DISTINCT(email), name, entry_id');
				$this->EE->db->where('status', 'o');
				$this->EE->db->where('entry_id', $entry_id);
				$this->EE->db->where('data_type', $data_type);
				$this->EE->db->where_in('email', $subscribed_emails);

				$comment_q = $this->EE->db->get('comments');
				
				if ($comment_q->num_rows() > 0)
				{
					foreach ($comment_q->result() as $row)
					{
						$sub_id = $subscription_map[$row->email];
						$recipients[] = array($row->email, $sub_id, $row->name);
					}
				}
			}
			
			unset($subscription_map);
		}
		
		
		// Mark it as unread
		// if smart notifications are turned on, will
		// will prevent further emails from being sent
		$this->EE->load->library('subscription');
		
		$this->EE->subscription->mark_as_unread(array($subscribed_members, $subscribed_emails), TRUE);
		
		return $recipients;
	}
    
    
    
    
    
    
    
    function add_subscription()
    {
    	
    }
    
    
    
    
    function remove_subscription()
    {
    	$ajax = ($this->EE->input->get_post('ajax')=='yes')?true:false;
		
		if ( ! $id = $this->EE->input->get_post('id') OR 
			 ! $hash = $this->EE->input->get_post('hash'))
		{
			return $this->_show_error(lang('not_authorized'), $ajax);
		}

		if ( ! is_numeric($id))
		{
			return $this->_show_error(lang('not_authorized'), $ajax);
		}

		$this->EE->load->library('subscription');
		$this->EE->subscription->init('comment', array('subscription_id' => $id), TRUE);
		$this->EE->subscription->unsubscribe('', $hash);
		
		if ($ajax)
		{
			return lang('cmt_you_have_been_removed');
		}
		
		$data = array(
				'title' 	=> lang('cmt_notification_removal'),
				'heading'	=> lang('thank_you'),
				'content'	=> lang('cmt_you_have_been_removed'),
				'redirect'	=> '',
				'link'		=> array($this->EE->config->item('site_url'), stripslashes($this->EE->config->item('site_name')))
		);

		$this->EE->output->show_message($data);
		
    }
    
    
    
    
    
    /** ----------------------------------------
    /**  Build comment form
    /** ----------------------------------------*/
	function form()
	{
		$this->EE->load->helper('form');
		$this->EE->load->library('javascript');
		
		
		$data_type = ($this->EE->TMPL->fetch_param('data_type')!='')?$this->EE->TMPL->fetch_param('data_type'):'entry';
		
		$qstring		 = $this->EE->uri->query_string;
		if (preg_match("#(^|/)P(\d+)(/|$)#", $qstring, $match))
		{
			$qstring = trim($this->EE->functions->remove_double_slashes(str_replace($match['0'], '/', $qstring)), '/');
		}		

		// Figure out the right entry ID
		// Order of precedence: POST, entry_id=, url_title=, $qstring
		if ($this->EE->input->get_post('entry_id')!==false)
		{
			$entry_id = $this->EE->input->get_post('entry_id');
		}
		elseif ($this->EE->TMPL->fetch_param('entry_id')!='')
		{
			$entry_id = $this->EE->TMPL->fetch_param('entry_id');
		}
		elseif ($this->EE->TMPL->fetch_param('data_id')!='')
		{
			$entry_id = $this->EE->TMPL->fetch_param('data_id');
		}
		elseif ($this->EE->TMPL->fetch_param('url_title')!='')
		{
			$url_title = $this->EE->TMPL->fetch_param('url_title');
		}
		else
		{
			// If there is a slash in the entry ID we'll kill everything after it.
			$entry_id = trim($qstring); 
			$entry_id = preg_replace("#/.+#", "", $entry_id);

			if ( ! is_numeric($entry_id))
			{
				$url_title = $entry_id;
			}
		}
        
        if ($data_type=='entry')
        {
	        if (isset($url_title))
	        {
	        	$this->EE->db->where('url_title', $url_title);
	        	if ($this->EE->TMPL->fetch_param('channel')!='')
	        	{
	        		if (! is_numeric($this->EE->TMPL->fetch_param('channel')))
	        		{
	        			$this->EE->db->where('exp_channels.channel_name', $this->EE->TMPL->fetch_param('channel'));
	        		}
	        		else
	        		{
	        			$this->EE->db->where('exp_channels.channel_id', $this->EE->TMPL->fetch_param('channel'));
	        		}
	        	}
	       	}
	       	else
	       	{
	       		$this->EE->db->where('entry_id', $entry_id);
	       	}
	       	$status_arr = ($this->EE->TMPL->fetch_param('status')) ? explode("|", $this->EE->TMPL->fetch_param('status')) : array();
	       	if (!empty($status_arr))
	       	{
	       		$this->EE->db->where_in('status', $status_arr);
	       	}
        	$q = $this->EE->db->select('entry_id, entry_date, expiration_date, comment_expiration_date, allow_comments, comment_system_enabled, comment_use_captcha, comment_expiration')
					->from('exp_channel_titles')
					->join('exp_channels', 'exp_channel_titles.channel_id=exp_channels.channel_id', 'left')
					->where('exp_channel_titles.site_id', $this->EE->config->item('site_id'))
					//->where('status !=', 'closed')
					->get();
			if ($q->num_rows()==0)
			{
				$this->EE->TMPL->no_results = $this->EE->TMPL->swap_var_single('error_message', lang('invalid_entry_id'), $this->EE->TMPL->no_results);	
				return $this->EE->TMPL->no_results();
			}
			else
			{
				$entry_id = $q->row('entry_id');
			}
        }
        else if (in_array($data_type, $this->types_table))
    	{
    		$record = $this->types_table[$data_type];
			//basic query for non-entries only, entries have advanced one above
			$query = $this->EE->db->select($record['entry_id_field'])->from($record['data_table'])->where($record['entry_id_field'], $entry_id)->get();
			if ($query->num_rows() == 0)
			{
				$this->EE->TMPL->no_results = $this->EE->TMPL->swap_var_single('error_message', lang('invalid_entry_id'), $this->EE->TMPL->no_results);	
				return $this->EE->TMPL->no_results();
			}
    	}
        else if ( ! is_numeric($entry_id))
        {
        	$this->EE->TMPL->no_results = $this->EE->TMPL->swap_var_single('error_message', lang('invalid_entry_id'), $this->EE->TMPL->no_results);	
			return $this->EE->TMPL->no_results();
        }
        
        
        //mark comments as read
        /*
		if ($this->EE->session->userdata('smart_notifications') == 'y')
		{
			$this->EE->load->library('subscription');
			$this->EE->subscription->init('comment', array('entry_id' => $entry_id, 'data_type' => $data_type), TRUE);
			$this->EE->subscription->mark_as_read();
		}        
		*/

        if ($data_type=='entry')
        {
	        //commenting expired?
	        if ($q->row('allow_comments') == 'n' || $q->row('comment_system_enabled') == 'n')
	        {
				$this->EE->TMPL->no_results = $this->EE->TMPL->swap_var_single('error_message', lang('cmt_commenting_has_expired'), $this->EE->TMPL->no_results);	
				return $this->EE->TMPL->no_results();
	        }
	
			if ($this->EE->config->item('comment_moderation_override') !== 'y')
			{
				if (($q->row('comment_expiration_date')  > 0) && ($this->EE->localize->now > $q->row('comment_expiration_date') ))
				{
					$this->EE->TMPL->no_results = $this->EE->TMPL->swap_var_single('error_message', lang('cmt_commenting_has_expired'), $this->EE->TMPL->no_results);
					return $this->EE->TMPL->no_results();
				}
			}
		}
        
        
        $tagdata = $this->EE->TMPL->tagdata;
        

		// -------------------------------------------
		// 'comment_form_tagdata' hook.
		//  - Modify, add, etc. something to the comment form
		//
			if ($this->EE->extensions->active_hook('comment_form_tagdata') === TRUE)
			{
				$tagdata = $this->EE->extensions->call('comment_form_tagdata', $tagdata);
				if ($this->EE->extensions->end_script === TRUE) return;
			}
		//
		// -------------------------------------------
		
		$vars = array();
		$vars[0] = array();

		if ($q->row('comment_use_captcha')  == 'n')
		{
			$vars[0]['captcha'] = FALSE;
		}
		else if ($q->row('comment_use_captcha')  == 'y')
		{
			$vars[0]['captcha'] =  ($this->EE->config->item('captcha_require_members') == 'y' || $this->EE->session->userdata('member_id') == 0) ? TRUE : FALSE;
		}

		foreach ($this->EE->TMPL->var_single as $key => $val)
		{

			if ($key == 'name')
			{
                $vars[0]['name'] = ($this->EE->input->cookie('my_name')!='')?$this->EE->input->cookie('my_name'):(($this->EE->session->userdata('screen_name') != '') ? $this->EE->session->userdata('screen_name') : $this->EE->session->userdata('username'));
			}

			if (in_array($key, array('email', 'url', 'location')))
			{
				$vars[0][$key] = ($this->EE->input->cookie('my_'.$key)!='') ? $this->EE->input->cookie('my_'.$key) : $this->EE->session->userdata($key);
			}

			if ($key == 'save_info')
			{
				$vars[0]['save_info'] = ($this->EE->input->cookie('save_info') == 'yes') ? " checked=\"checked\"" : '';
			}

			if ($key == 'notify_me')
			{
				$checked = ($this->EE->input->cookie('notify_me')!='') ? $this->EE->input->cookie('notify_me') : ((isset($this->EE->session->userdata['notify_by_default']) && $this->EE->session->userdata['notify_by_default']=='y')?'yes':'no');
				
				$vars[0]['notify_me'] = ($checked == 'yes') ? " checked=\"checked\"" : '';
			}
            
            if ($key == 'notify_thread')
			{
                //use checked value for notify_me
				$vars[0]['notify_thread'] = ($checked == 'yes') ? " checked=\"checked\"" : '';
			}
			
			if ($this->EE->input->get_post($key)!==false)
			{
				$vars[0][$key] = $this->EE->input->get_post($key);
			}
		}
		
		$tagdata = $this->EE->TMPL->parse_variables($tagdata, $vars);

		//where shall we go after?
		if ($this->EE->TMPL->fetch_param('return')=='')
        {
            $return = $this->EE->functions->fetch_site_index();
        }
        else if ($this->EE->TMPL->fetch_param('return')=='SAME_PAGE')
        {
            $return = $this->EE->functions->fetch_current_uri();
        }
        else if (strpos($this->EE->TMPL->fetch_param('return'), "http://")!==FALSE || strpos($this->EE->TMPL->fetch_param('return'), "https://")!==FALSE)
        {
            $return = $this->EE->TMPL->fetch_param('return');
        }
        else
        {
            $return = $this->EE->functions->create_url($this->EE->TMPL->fetch_param('return'));
        }
        
        //what's the actual URI displayed for comments?
		
		if ($this->EE->TMPL->fetch_param('comments_page')=='' || $this->EE->TMPL->fetch_param('comments_page')=='SAME_PAGE')
		{
			//use this page URI
			$uri = $this->EE->functions->create_url($this->EE->uri->uri_string);
	        $query_string = ($this->EE->uri->page_query_string != '') ? $this->EE->uri->page_query_string : $this->EE->uri->query_string;
	
			if (preg_match("#^P(\d+)|/P(\d+)#", $query_string, $match))
			{
				$uri = $this->EE->functions->remove_double_slashes(str_replace($match[0], '', $uri));
			}
		}
		else
		{
			//we have it as parameter
			if (strpos($this->EE->TMPL->fetch_param('comments_page'), "http://")!==FALSE || strpos($this->EE->TMPL->fetch_param('comments_page'), "https://")!==FALSE)
	        {
	            $uri = $this->EE->TMPL->fetch_param('comments_page');
	        }
	        else
	        {
	            $uri = $this->EE->functions->create_url($this->EE->TMPL->fetch_param('comments_page'));
	        }
		}
        
        $data['enctype'] = 'multipart/form-data';
        
        $data['hidden_fields']['ACT'] 		= $this->EE->functions->fetch_action_id('Better_comments', 'insert_new_comment');
		$data['hidden_fields']['RET'] 		= $return;
		$data['hidden_fields']['URI'] 		= $uri;
		$data['hidden_fields']['entry_id'] 	= $entry_id;
		$data['hidden_fields']['limit'] 	= ($this->EE->TMPL->fetch_param('limit') != "")?$this->EE->TMPL->fetch_param('limit'):$this->perpage;;
		$data['hidden_fields']['data_type'] = $data_type;
		$data['hidden_fields']['parent_id'] = ($this->EE->TMPL->fetch_param('parent_id') != "") ? $this->EE->TMPL->fetch_param('parent_id') : (($this->EE->input->get_post('parent_id')!='')?$this->EE->input->get_post('parent_id'):0);	
			        
        if ($this->EE->TMPL->fetch_param('ajax')=='yes') $data['hidden_fields']['ajax'] = 'yes';

		if ($vars[0]['captcha'] == TRUE)
		{
			if (preg_match("/({captcha})/", $tagdata))
			{
				$tagdata = preg_replace("/{captcha}/", $this->EE->functions->create_captcha(), $tagdata);
			}
		}

		// -------------------------------------------
		// 'comment_form_hidden_fields' hook.
		//  - Add/Remove Hidden Fields for Comment Form
		//
			if ($this->EE->extensions->active_hook('comment_form_hidden_fields') === TRUE)
			{
				$data['hidden_fields'] = $this->EE->extensions->call('comment_form_hidden_fields', $data['hidden_fields']);
				if ($this->EE->extensions->end_script === TRUE) return;
			}
		//
		// -------------------------------------------

		
									      
        $data['id']		= ($this->EE->TMPL->fetch_param('id')!='') ? $this->EE->TMPL->fetch_param('id') : 'better_comments_form';
        $data['name']	= ($this->EE->TMPL->fetch_param('name')!='') ? $this->EE->TMPL->fetch_param('name') : 'better_comments_form';
        $data['class']	= ($this->EE->TMPL->fetch_param('class')!='') ? $this->EE->TMPL->fetch_param('class') : 'better_comments_form';
		
		if ( preg_match_all("/".LD."loader".RD."(.*?)".LD."\/loader".RD."/s", $tagdata, $loader)!=0)
        {
            if ($this->EE->TMPL->fetch_param('ajax')=='yes')
			{
				$tagdata = str_replace($loader[0][0], "<div class=\"".$data['class']."_ajax_loader\" style=\"display: none\">".$loader[1][0]."</div>", $tagdata);
			}
			else
			{
				$tagdata = str_replace($loader[0][0], "", $tagdata);
			}
        }
        else if ($this->EE->TMPL->fetch_param('ajax')=='yes')
        {
        	$tagdata = '<div class="'.$data['class'].'_ajax_loader">'.lang('please_wait').'</div>'.$tagdata;
        }
        
        if ($this->EE->TMPL->fetch_param('ajax')=='yes')
		{
			$tagdata = '<div class="'.$data['class'].'_error"></div>'.$tagdata;
		}
		$tagdata = $this->EE->functions->form_declaration($data).$tagdata."\n"."</form>";
        
        if ($this->EE->TMPL->fetch_param('add_jquery')=='yes')
        {
        	$tagdata .= "
<script type=\"text/javascript\">
$(document).ready(function(){
	$('.better_comments_reply').live('click', function() {
		$('#".$data['id']." input[name=parent_id]').val($(this).attr('rel'));
		$('#".$data['id']."').insertAfter( // Insert the comment form after...
		$(this)
		.parent() // The containing p tag
		);
		$('#".$data['id']."').show();
	});
	$('.better_comments_quote').live('click', function() {
		$('#".$data['id']." textarea[name=comment]').val('[quote]'+ 
		$(this).parent().parent().find('.better_comments_comment_text').text()+
		'[/quote]'
		);
	});
});
</script>
			";
		
			if ($this->EE->TMPL->fetch_param('ajax')=='yes')
			{
				$submit_act = $this->EE->db->select('action_id')->from('exp_actions')->where('class', 'Better_comments')->where('method', 'new_comment_submit')->get();
				$xid_act = $this->EE->db->select('action_id')->from('exp_actions')->where('class', 'Better_comments')->where('method', 'get_xid')->get();
				$post_process_a = ($this->EE->TMPL->fetch_param('post_process')!='') ? explode("|", $this->EE->TMPL->fetch_param('post_process')) : array();

		  		$ajax_js = "
$(document).ready(function(){
	$('#".$data['id']."').live('submit', function(event){
		event.preventDefault();
		$('#".$data['id']." .".$data['class']."_error').hide();
		$('#".$data['id']." .".$data['class']."_ajax_loader').show();
		$.post(
			'".$this->EE->config->item('site_url')."?ACT=".$submit_act->row('action_id')."',
			$(this).serialize(),
			function(msg) {
				$('#".$data['id']." .".$data['class']."_ajax_loader').hide();
				if (msg.indexOf('better_comments_error_message') >= 0)
				{
					var out = /<div class=\"better_comments_error_message\">[\s\S]*<\/div>/.exec(msg);
					$('#".$data['id']." .".$data['class']."_error').text(''+out);
					$('#".$data['id']." .".$data['class']."_error').show();
				} else {
        		";
        		
				if ($this->EE->TMPL->fetch_param('return')!='')
				{
				    $ajax_js .= "
		            $.get('".$return."', 
		            function(ret){
		                $(ret).insertBefore('#".$data['id']."');
		            });";
				}
				else
				{
				    /*$ajax_js .= "				    
				    var author = ($('#".$data['id']." input[name=name]').val()!='')?$('#".$data['id']." input[name=name]').val():'".$this->EE->db->escape_str($this->EE->session->userdata('screen_name'))."';
					var ret = $('#".$data['id']." textarea[name=comment]').val().replace(/\n/g,\"<br />\")+'<br />&mdash; '+author;
				    $(ret).insertBefore('#".$data['id']."');
					";*/
					$ajax_js .= "				    
				    $(msg).insertBefore('#".$data['id']."');
					";
				}
				foreach ($post_process_a as $post_process)
				{
				    $ajax_js .= "
				            ".$post_process."();
				            ";
				}
				if ($this->EE->config->item('secure_forms') == 'y')
				{
					$ajax_js .= "
					$.get('".$this->EE->config->item('site_url')."?ACT=".$xid_act->row('action_id')."', 
		            function(xid){
		                $('#".$data['id']." input[name=XID]').val(xid);
		            });
     				";
     			}	
     			$ajax_js .= "
		        }
		        return false;
		    }
		);
		return false;
	});
});
";
				$tagdata .= $ajax_js;
			}
		
		}
		
        
        
        
		
		// -------------------------------------------
		// 'comment_form_end' hook.
		//  - Modify, add, etc. something to the comment form at end of processing
		//
			if ($this->EE->extensions->active_hook('comment_form_end') === TRUE)
			{
				$tagdata = $this->EE->extensions->call('comment_form_end', $tagdata);
				if ($this->EE->extensions->end_script === TRUE) return $tagdata;
			}
		//
		// -------------------------------------------


		return $tagdata;
	}
    /* END */
    
    
    
    function get_xid()
    {
    	return $this->EE->functions->add_form_security_hash('{XID_HASH}');
    }
    
    
    
    /** ----------------------------------------
    /**  Display comments
    /** ----------------------------------------*/
    function display()
    {
  		
  		$this->EE->load->helper('string');
  		$this->EE->load->helper('url');
  		
  		$this->EE->load->library('pagination');
		$pagination = new Pagination_object(__CLASS__);
  		
  		$ajax = ($this->EE->input->get_post('ajax')=='yes')?true:false;
  		
        $data_type = ($this->EE->TMPL->fetch_param('data_type')!='')?$this->EE->TMPL->fetch_param('data_type'):'entry';
            	
		//get and strip page number		
		$pagination->offset = 0;
		$pagination->current_page = 1;
		$basepath = $this->EE->functions->create_url($this->EE->uri->uri_string);
        $qstring = ($this->EE->uri->page_query_string != '') ? $this->EE->uri->page_query_string : $this->EE->uri->query_string;
		if (preg_match("#(^|/)P(\d+)(/|$)#", $qstring, $match))
		{
			$pagination->offset = $match[1];	
			$pagination->current_page = $match[2];	
			$qstring = trim($this->EE->functions->remove_double_slashes(str_replace($match[0], '/', $qstring)), '/');
			$basepath = $this->EE->functions->remove_double_slashes(str_replace($match[0], '', $basepath));
		}		

		// Figure out the right entry ID
		// Order of precedence: POST, entry_id=, url_title=, $qstring
		if ($this->EE->input->get_post('entry_id')!==false)
		{
			$entry_id = $this->EE->input->get_post('entry_id');
		}
		elseif ($this->EE->TMPL->fetch_param('entry_id')!='')
		{
			$entry_id = $this->EE->TMPL->fetch_param('entry_id');
		}
		elseif ($this->EE->TMPL->fetch_param('data_id')!='')
		{
			$entry_id = $this->EE->TMPL->fetch_param('data_id');
		}
		elseif ($this->EE->TMPL->fetch_param('url_title')!='')
		{
			$url_title = $this->EE->TMPL->fetch_param('url_title');
		}
		else
		{
			// If there is a slash in the entry ID we'll kill everything after it.
			$entry_id = trim($qstring); 
			$entry_id = preg_replace("#/.+#", "", $entry_id);

			if ( ! is_numeric($entry_id))
			{
				$url_title = $entry_id;
			}
		}
        
        if ($data_type=='entry')
        {
	        if (isset($url_title))
	        {
	        	$this->EE->db->where('url_title', $url_title);
	       	}
	       	else
	       	{
	       		$this->EE->db->where('entry_id', $entry_id);
	       	}
        	$q = $this->EE->db->select('entry_id')
					->from('exp_channel_titles')
					->where('exp_channel_titles.site_id', $this->EE->config->item('site_id'))
					//->where('status !=', 'closed')
					->get();
			if ($q->num_rows()==0)
			{
				$this->EE->TMPL->no_results = $this->EE->TMPL->swap_var_single('error_message', lang('invalid_entry_id'), $this->EE->TMPL->no_results);	
				return $this->EE->TMPL->no_results();
			}
			else
			{
				$entry_id = $q->row('entry_id');
			}
        }
        else if (in_array($data_type, $this->types_table))
    	{
    		$record = $this->types_table[$data_type];
			//basic query for non-entries only, entries have advanced one above
			$query = $this->EE->db->select($record['entry_id_field'])->from($record['data_table'])->where($record['entry_id_field'], $entry_id)->get();
			if ($query->num_rows() == 0)
			{
				$this->EE->TMPL->no_results = $this->EE->TMPL->swap_var_single('error_message', lang('invalid_entry_id'), $this->EE->TMPL->no_results);	
				return $this->EE->TMPL->no_results();
			}
    	}
        else if ( ! is_numeric($entry_id))
        {
        	$this->EE->TMPL->no_results = $this->EE->TMPL->swap_var_single('error_message', lang('invalid_entry_id'), $this->EE->TMPL->no_results);	
			return $this->EE->TMPL->no_results();
        }
        
        $this->EE->TMPL->no_results = $this->EE->TMPL->swap_var_single('error_message', '', $this->EE->TMPL->no_results);	

		//still no luck? show nothing
        if (!isset($entry_id))
        {
            return $this->EE->TMPL->no_results();
        }

        
        $pagination->per_page = (is_numeric($this->EE->input->get_post('limit'))) ? $this->EE->input->get_post('limit') : $this->perpage;
        $paginate = ($this->EE->TMPL->fetch_param('paginate')=='top')?'top':($this->EE->TMPL->fetch_param('paginate')=='both')?'both':'bottom';
        $allowed_sort = array('asc', 'desc');
		$sort  = ( ! $this->EE->TMPL->fetch_param('sort') OR ! in_array(strtolower($this->EE->TMPL->fetch_param('sort')), $allowed_sort))  ? 'asc' : $this->EE->TMPL->fetch_param('sort');
        /*$allowed_order = array('date', 'email', 'location', 'name', 'url');
        $orderby  = ($this->EE->TMPL->fetch_param('orderby') == 'date' OR ! in_array($this->EE->TMPL->fetch_param('orderby'), $allowed_order))  ? 'comment_date' : $this->EE->TMPL->fetch_param('orderby');*/

		//pagination, the earlier the better
		
		
		
    	$this->EE->db->where('data_type', $data_type);
    	$this->EE->db->where('entry_id', $entry_id);
    	$this->EE->db->where('level', '0');
    	$this->EE->db->where('status', 'o');
    	$total_threads = $this->EE->db->count_all_results('comments');
    	if ($total_threads==0)
        {
            return $this->EE->TMPL->no_results();
        }
        
        $pagination->total_rows = $total_threads;
		$pagination->get_template();
		$pagination->build($pagination->per_page);

        if ($pagination->offset > $pagination->total_rows)
		{
			$pagination->current_page = 1;
			$pagination->offset = 0;
		}
		
		$this->EE->db->where('data_type', $data_type);
    	$this->EE->db->where('entry_id', $entry_id);
    	$this->EE->db->where('status', 'o');
    	$total_comments = $this->EE->db->count_all_results('comments');
        
        
        //get current page zero level comment ids
        $commentids_q = $this->EE->db->select('comment_id')->from('exp_comments')->where('entry_id',$entry_id)->where('data_type', $data_type)->where('status', 'o')->where('level', 0)->order_by('comment_id', $sort)->limit($pagination->per_page, $pagination->offset)->get();
        if ($commentids_q->num_rows()==0)
        {
            return $this->EE->TMPL->no_results();
        }
        $commentids_a = array();
        foreach ($commentids_q->result_array() as $row)
        {
            $commentids_a[] = $row['comment_id'];
        }
        $commentids = implode(",", $commentids_a);
        
        //absolute counter
        if ($pagination->offset!=0)
        {
            $prev_q = $this->EE->db->select('comment_id')->from('exp_comments')->where('entry_id', $entry_id)->where('data_type', $data_type)->where('status', 'o')->where('parent_id', 0)->order_by('comment_id', $sort)->limit($pagination->offset, 0)->get();
            $prev_commentids_a = array();
            foreach ($prev_q->result_array() as $row)
            {
                $prev_commentids_a[] = $row['comment_id'];
            }
            $this->displayed_prev_root = count($prev_commentids_a); 
            if (count($prev_commentids_a)>0)
            {
                $prev_commentids = implode(",", $prev_commentids_a);
                $this->EE->db->where("comment_id IN ($prev_commentids) OR root_id IN ($prev_commentids)");
                $this->displayed_prev = $this->EE->db->count_all_results('comments');
            }
        }
        
        //Smart Notifications? Mark comments as read.
        if ($this->EE->session->userdata('smart_notifications') == 'y')
		{
			$this->EE->load->library('subscription');
			$this->EE->subscription->init('comment', array('entry_id' => $query->row('entry_id'), 'data_type' => $data_type), TRUE);
			$this->EE->subscription->mark_as_read();
		}
        
        
        //get actual comments - zero level and all related
        
        $mfields_q = $this->EE->db->query("SELECT m_field_id, m_field_name FROM exp_member_fields");
		$mfields = array();				
		if ($mfields_q->num_rows() > 0)
		{
			foreach ($mfields_q->result_array() as $row)
			{        		
				$mfields[$row['m_field_name']] = $row['m_field_id'];
			}
		}
		
		$custom_fields_query = $this->EE->db->select('field_id, field_name')->from('comment_fields')->where('site_id', $this->EE->config->item('site_id'))->get();

        $this->EE->db->from('comments');
        
        $sql_what = 'comments.comment_id, comments.entry_id, comments.channel_id, comments.author_id, comments.name, comments.email AS c_email, comments.url AS c_url, comments.location AS c_location, comments.ip_address, comments.comment_date, comments.edit_date, comments.comment, comments.site_id AS comment_site_id, comments.level, comments.parent_id, comments.root_id,
			members.username, members.group_id, members.email, members.url, members.location, members.occupation, members.interests, members.aol_im, members.yahoo_im, members.msn_im, members.icq, members.group_id, members.member_id, members.signature, members.sig_img_filename, members.sig_img_width, members.sig_img_height, members.avatar_filename, members.avatar_width, members.avatar_height, members.photo_filename, members.photo_width, members.photo_height,
			member_data.*';
		
		foreach ($custom_fields_query->result_array() as $custom_field)
        {
			$sql_what .= ', field_id_'.$custom_field['field_id'].' AS '.$custom_field['field_name'];
        }
        $this->EE->db->join('comment_data',		'comments.comment_id = comment_data.comment_id','left');
		$this->EE->db->join('members',			'members.member_id = comments.author_id',		'left');
		$this->EE->db->join('member_data',		'member_data.member_id = members.member_id',	'left');
		
		if ($data_type=='entry')
		{
			$sql_what .= ',
			channel_titles.title, channel_titles.url_title, channel_titles.author_id AS entry_author_id,
			channels.comment_text_formatting, channels.comment_html_formatting, channels.comment_allow_img_urls, channels.comment_auto_link_urls, channels.channel_url, channels.comment_url, channels.channel_title';
			$this->EE->db->join('channels',			'comments.channel_id = channels.channel_id',	'left');
			$this->EE->db->join('channel_titles',	'comments.entry_id = channel_titles.entry_id',	'left');
		}
		else if (in_array($data_type, $this->types_table))
		{
			$record = $this->types_table[$data_type];
    		if ($data_type != 'entry')
    		{
				$sql_what .= ','.$record['group_table'].'.'.$record['group_title_field'];
				$this->EE->db->join($record['group_table'],	$record['join_rule'],	'left');
			}
		}
		
		$this->EE->db->select($sql_what);
		
		$this->EE->db->where("exp_comments.comment_id  IN ($commentids) OR exp_comments.root_id IN ($commentids)");
		$this->EE->db->where('exp_comments.status', 'o');  
		$this->EE->db->order_by('comment_id', $sort);
		
        $query = $this->EE->db->get();
        
        
        $first_comment_id = 0;
        $query_fields = $query->list_fields();

        foreach ($query->result_array() as $row)
		{
            if ($first_comment_id==0 && $row['level']==0) $first_comment_id = $row['comment_id'];
            if (!isset($this->comment[$row['comment_id']]->has_children)) $this->comment[$row['comment_id']]->has_children = false;
            $this->comment[$row['parent_id']]->has_children = true;
            if ($this->comment[$row['parent_id']]->has_children == true)
            {
                $this->comment[$row['parent_id']]->children[] = $row['comment_id'];
            }
            
            foreach ($query_fields as $field)
            {
            	$this->comment[$row['comment_id']]->$field = $row[$field];
            }
            
            foreach ($mfields as $mfield_name=>$mfield_id)
            {
                $this->comment[$row['comment_id']]->$mfield_name = $row['m_field_id_'.$mfield_id];
            }
       
            $this->comment[$row['comment_id']]->name = ($row['name']!='')?$row['name']:($row['screen_name']!=''?$row['screen_name']:$row['username']);
            $this->comment[$row['comment_id']]->email = ($row['c_email']!='')?$row['c_email']:$row['email'];
            $this->comment[$row['comment_id']]->url = ($row['c_url']!='')?$row['c_url']:$row['url'];
            if ($this->comment[$row['comment_id']]->url!='')
            {
                $this->comment[$row['comment_id']]->url = prep_url($this->comment[$row['comment_id']]->url);
            }
            $this->comment[$row['comment_id']]->location = ($row['c_location']!='')?$row['c_location']:$row['location'];
            $this->comment[$row['comment_id']]->comment_url = ($row['comment_url']!='')?$row['comment_url']:$row['channel_url'];         

		}
		
		//build the properly ordered tree         
  		$comments_sorted = $this->_build_thread($commentids_a);   
		
		$count = 0;
		
        $output = '';
        $total = count($comments_sorted)-1;
        foreach ($comments_sorted as $count=>$cmt_id)
        {
            $rootcount = array_search($cmt_id, $commentids_a);
            if ($rootcount!==FALSE)
            {
                $rootcount = $count;
            }
            
            $lastcall = ($count==$total) ? true : false;
            $nextid = (isset($comments_sorted[$count+1]))?$comments_sorted[$count+1]:0;  
			
			$tagdata = $this->EE->TMPL->tagdata;

			$row = array();
			$row['count'] = ++$count;
			$row['count_root'] = ++$rootcount;
	        $row['absolute_count'] = $pagination->offset + $row['count'];
	        $row['absolute_count_root'] = $this->displayed_prev_root + $row['count_root'];
	        $row['total_threads'] = $total_threads;
	        $row['total_results'] = $total_comments;
	        $row['total_comments'] = $total_comments;
			
			$output .= $this->_commentloop($cmt_id, $tagdata, $row, $nextid, $lastcall);

        }      
	        
		return $pagination->render($output);

    }
    /* END */
    
    /** ----------------------------------------
	/**  Build the properly sorted tree of comment IDs
	/** ----------------------------------------*/
    function _build_thread($arr)
    {
        foreach ($arr as $cmt_id)
        {
            $comments_sorted[] = $this->comment[$cmt_id]->comment_id;
            if ($this->comment[$cmt_id]->has_children == true)
            {
                //thread start
                $this->thread_open_ids[] = $this->comment[$cmt_id]->comment_id;
                $this->thread_start_ids[] = $this->comment[$cmt_id]->children[0];
                $arr_last_el = count($this->comment[$cmt_id]->children)-1;
                
                $child_comments = $this->_build_thread($this->comment[$cmt_id]->children);
                
                //thread end
                $idx = $this->comment[$cmt_id]->children[$arr_last_el];
                if ($this->comment[$idx]->has_children == false)
                {
                    $close_idx = $idx - 1;
                    $this->thread_end_ids[] = $idx;
                    $this->thread_close_ids[] = $close_idx;
                }
                
                foreach ($child_comments as $child_comment) array_push($comments_sorted, $child_comment);
            }
        }
        return $comments_sorted;    
    } 
    /* END */
    
    
    
    
    /* ----------------------------------------
	/*  Deal with {comments} loop 
	/* ----------------------------------------*/
    function _commentloop($currentid, $tagdata, $return, $nextid, $lastcall=false)
    {      
		
		// -------------------------------------------
		// 'comment_entries_tagdata' hook.
		//  - Modify and play with the tagdata before everyone else
		//
			if ($this->EE->extensions->active_hook('comment_entries_tagdata') === TRUE)
			{
				$tagdata = $this->EE->extensions->call('comment_entries_tagdata', $tagdata, $row);
				if ($this->EE->extensions->end_script === TRUE) return $tagdata;
			}
		//
		// -------------------------------------------
		
        $row = get_object_vars($this->comment[$currentid]);
        $return = array_merge($return, $row);
        
        $this->EE->load->library('typography');
        $this->EE->typography->initialize();
 
		$return['is_ignored']			= ( ! isset($row['member_id']) || ! in_array($row['member_id'], $this->EE->session->userdata['ignore_list'])) ? FALSE : TRUE;
        $return['has_replies']			= ($row['has_children'] != true) ? FALSE : TRUE;

		
		if (!isset($row['comment_text_formatting'])) $row['comment_text_formatting'] = 'none';
		if (!isset($row['comment_html_formatting'])) $row['comment_html_formatting'] = 'none';
		if (!isset($row['auto_links'])) $row['auto_links'] = 'n';
		if (!isset($row['allow_img_url'])) $row['allow_img_url'] = 'n';
		
 
        // -------------------------------------------
		// 'comment_entries_comment_format' hook.
		//  - Play with the tagdata contents of the comment entries
		//
		if ($this->EE->extensions->active_hook('comment_entries_comment_format') === TRUE)
		{
			$return['comment'] = $this->EE->extensions->call('comment_entries_comment_format', $row);
			if ($this->EE->extensions->end_script === TRUE) return;
		}
		else
		{
			$return['comment'] = $this->EE->typography->parse_type( $row['comment'], 
										   array(
													'text_format'   => $row['comment_text_formatting'],
													'html_format'   => $row['comment_html_formatting'],
													'auto_links'    => $row['comment_auto_link_urls'],
													'allow_img_url' => $row['comment_allow_img_urls']
												)
										);
		}
		//
		// -------------------------------------------
		
		$return['signature_image_url'] = '';
		$return['signature_image_width'] = '';
		$return['signature_image_height'] = '';      
		$return['signature_image'] = false;           
		if ($this->EE->session->userdata('display_signatures') == 'y' && $row['sig_img_filename'] != '')
		{			
			$return['signature_image_url'] = $this->EE->config->item('sig_img_url').$row['sig_img_filename'];
			$return['signature_image_width'] = $row['sig_img_width'];
			$return['signature_image_height'] = $row['sig_img_height'];	
			$return['signature_image'] = true;    					
		}
		
		$return['avatar_url'] = '';
		$return['avatar_image_width'] = '';
		$return['avatar_image_height'] = ''; 
		$return['avatar'] = false;                
		if ($this->EE->session->userdata('display_avatars') == 'y' && $row['avatar_filename'] != '')
		{			
			$return['avatar_url'] = $this->EE->config->item('avatar_url').$row['avatar_filename'];
			$return['avatar_image_width'] = $row['avatar_width'];
			$return['avatar_image_height'] = $row['avatar_height'];	
			$return['avatar'] = true;    	
		}

        $return['photo_url'] = '';
		$return['photo_image_width'] = '';
		$return['photo_image_height'] = ''; 
		$return['photo'] = false;                
		if ($this->EE->session->userdata('display_photos') == 'y' && $row['photo_filename'] != '')
		{			
			$return['photo_url'] = $this->EE->config->item('photo_url').$row['photo_filename'];
			$return['photo_image_width'] = $row['photo_width'];
			$return['photo_image_height'] = $row['photo_height'];	
			$return['photo'] = true;    	
		}
		
		if ($this->EE->session->userdata('display_signatures') == 'n' || $row['signature'] == '')
		{			
			$return['signature'] = '';
		}
		else
		{
			$return['signature'] = $this->EE->typography->parse_type($row['signature'], array(
																		'text_format'   => 'xhtml',
																		'html_format'   => 'safe',
																		'auto_links'    => 'y',
																		'allow_img_url' => $this->EE->config->item('sig_allow_img_hotlink')
																	)
																);
		}
		
		$return['author'] = $row['name'];
		$return['url_or_email'] = ($row['url'] != '') ? $row['url'] : $row['email'];
		$return['url_as_author'] = ($row['url'] != '') ? "<a href=\"".$row['url']."\">".$row['name']."</a>" : $row['name'];
		$return['url_or_email_as_author'] = ($row['url'] != '') ? "<a href=\"".$row['url']."\">".$row['name']."</a>" : (($row['email'] != '')?$this->EE->typography->encode_email($row['email'], $row['name']):$row['name']);
		$return['url_or_email_as_link'] = ($row['url'] != '') ? "<a href=\"".$row['url']."\">".$row['url']."</a>" : (($row['email'] != '')?$this->EE->typography->encode_email($row['email']):$row['name']);

	

	
		

        foreach ($this->EE->TMPL->var_single as $key => $val)
        { 

            // {permalink}
            
            if (strncmp('permalink', $key, 9) == 0)
            {                     
				$tagdata = $this->EE->TMPL->swap_var_single(
													$key, 
													$this->EE->functions->create_url($uristr.'#'.$row['comment_id'], 0, 0), 
													$tagdata
												 );
            }                

            // {comment_path}
            
            if (preg_match("#^(comment_path|entry_id_path)#", $key))
            {                       
				$tagdata = $this->EE->TMPL->swap_var_single(
													$key, 
													$this->EE->functions->create_url($this->EE->functions->extract_path($key).'/'.$row['entry_id']), 
													$tagdata
												 );
            }


            // {title_permalink}            
            if (preg_match("#^(title_permalink|url_title_path)#", $key))
            { 
				$path = ($this->EE->functions->extract_path($key) != '' && $this->EE->functions->extract_path($key) != 'SITE_INDEX') ? $this->EE->functions->extract_path($key).'/'.$row['url_title'] : $row['url_title'];

				$tagdata = $this->EE->TMPL->swap_var_single(
													$key, 
													$this->EE->functions->create_url($path, 1, 0), 
													$tagdata
												 );
            }
        

            // {member_search_path}
            
			if (strncmp('member_search_path', $key, 18) == 0)
            {                   
				$tagdata = $this->EE->TMPL->swap_var_single($key, $search_link.$row['author_id'], $tagdata);
            }


           
            // {comment_auto_path}            
            if ($key == "comment_auto_path")
            {           
                $tagdata = $this->EE->TMPL->swap_var_single($key, $row['comment_url'], $tagdata);
            }
            
            // {comment_url_title_auto_path}            
            if ($key == "comment_url_title_auto_path")
            { 
                $tagdata = $this->EE->TMPL->swap_var_single(
                								$key, 
                								$row['comment_url'].$row['url_title'].'/', 
                								$tagdata
                							 );
            }
            
            // {comment_entry_id_auto_path}            
            if ($key == "comment_entry_id_auto_path" AND $comments_exist == TRUE)
            {           
                $tagdata = $this->EE->TMPL->swap_var_single(
                								$key, 
                								$row['comment_url'].$row['entry_id'].'/', 
                								$tagdata
                							 );
            }

            
            
		}
        
        //complete missing closing tags
        if ($row['level']<($this->prev_level-1))
        {
            
            if (preg_match_all("/".LD."thread_end".RD."(.*?)".LD."\/thread_end".RD."/s", $tagdata, $tmp)!=0)// && in_array($currentid, $this->thread_end_ids)
            {
                if ( preg_match_all("/".LD."thread_start".RD."(.*?)".LD."\/thread_start".RD."/s", $tagdata, $tmp2)!=0)
                {
                
                    $replace = '';
                    //echo $row['comment_id'];
                    //echo $row['level']."-".$this->prev_level;
                    //var_dump($tmp);
                    
                    for($i=$row['level']; $i<$this->prev_level-1; $i++)
                    {
                        //echo $i;
                        $replace .= $tmp[1][0];
                    }
                    $replace .= $tmp2[0][0];
                    //echo $replace;
                    $tagdata = str_replace($tmp2[0][0], $replace, $tagdata);
                }
            }
        }
        
        //first in thread?
        //echo preg_match_all("/".LD."thread_start".RD."(.*?)".LD."\/thread_start".RD."/s", $tagdata, $tmp);
        //echo in_array($currentid, $this->thread_start_ids);
        //var_dump($tmp);
        if ( preg_match_all("/".LD."thread_start".RD."(.*?)".LD."\/thread_start".RD."/s", $tagdata, $tmp)!=0)
        {
            if (in_array($currentid, $this->thread_start_ids))
            {
                $tagdata = str_replace($tmp[0][0], $tmp[1][0], $tagdata);
            }
            else
            {
                $tagdata = str_replace($tmp[0][0], '', $tagdata);
            }
        } 
        
        if ( preg_match_all("/".LD."thread_open".RD."(.*?)".LD."\/thread_open".RD."/s", $tagdata, $tmp)!=0)
        {
            if (in_array($currentid, $this->thread_open_ids))
            {
                $tagdata = str_replace($tmp[0][0], $tmp[1][0], $tagdata);
            }
            else
            {
                $tagdata = str_replace($tmp[0][0], '', $tagdata);
            }
        } 
        
        
        //last in thread?
        // also make sure no childs present 
        if (preg_match_all("/".LD."thread_end".RD."(.*?)".LD."\/thread_end".RD."/s", $tagdata, $tmp)!=0)
        {
            if (in_array($currentid, $this->thread_end_ids) && $row['has_children']==false)
            {
                //is last call? close all tags
                $replace = $tmp[1][0];
                //$replace = '';
                if ($lastcall==true)
                {
                    //echo 'lastcall';
                    for($i=0; $i<$row['level']-1; $i++)
                    {
                        $replace .= $tmp[1][0];
                    }
                }
                $tagdata = str_replace($tmp[0][0], $replace, $tagdata);
            }
            else
            {
                $tagdata = str_replace($tmp[0][0], '', $tagdata);
            }
        } 

        if (preg_match_all("/".LD."thread_close".RD."(.*?)".LD."\/thread_close".RD."/s", $tagdata, $tmp)!=0)
        {
            if (in_array($currentid, $this->thread_end_ids) && $row['level']!=0)
            {
                //is last call? close all tags
                $replace = $tmp[1][0];
                //$replace = '';
  
                if ($lastcall==true)
                {
                    $repeats_nr = $row['level']-1;
                }
                else
                {
                    $nextrow = get_object_vars($this->comment[$nextid]);
                    $repeats_nr = $row['level']-$nextrow['level']-1;
                }
                

                for($i=0; $i<$repeats_nr; $i++)
                {
                    $replace .= $tmp[1][0];
                }

                $tagdata = str_replace($tmp[0][0], $replace, $tagdata);
            }
            else
            {
                $tagdata = str_replace($tmp[0][0], '', $tagdata);
            }
        } 
        
        if (preg_match_all("/".LD."thread_container_close".RD."(.*?)".LD."\/thread_container_close".RD."/s", $tagdata, $tmp)!=0)
        {
            //if (($row['level']==0 && $row['has_children']==false) || (in_array($currentid, $this->thread_end_ids)))
            if (($row['has_children']==false) || (in_array($currentid, $this->thread_end_ids)))
            {
                $tagdata = str_replace($tmp[0][0], $tmp[1][0], $tagdata);
            }
            else
            {
                $tagdata = str_replace($tmp[0][0], '', $tagdata);
            }
        } 
        
        $this->prev_level = $row['level'];

		$out = $this->EE->TMPL->parse_variables_row($tagdata, $return);
        return $out;                    
      }
      /* END */
	
	
	
	
	
    

}
/* END */
?>
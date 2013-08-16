<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
// TODO !!! Correct the help links !!! TODO
	require_once('include/users.inc.php');

	function insert_slideshow_form(){
		$form = new CFormTable(S_SLIDESHOW, null, 'post');
		$form->setHelp('config_advanced.php');

		if(isset($_REQUEST['slideshowid'])){
			$form->addVar('slideshowid', $_REQUEST['slideshowid']);
		}

		$name		= get_request('name', '');
		$delay		= get_request('delay', 5);
		$steps		= get_request('steps', array());

		$new_step	= get_request('new_step', null);

		if((isset($_REQUEST['slideshowid']) && !isset($_REQUEST['form_refresh']))){
			$slideshow_data = DBfetch(DBselect('SELECT * FROM slideshows WHERE slideshowid='.$_REQUEST['slideshowid']));

			$name		= $slideshow_data['name'];
			$delay		= $slideshow_data['delay'];
			$steps		= array();
			$db_steps = DBselect('SELECT * FROM slides WHERE slideshowid='.$_REQUEST['slideshowid'].' order by step');

			while($step_data = DBfetch($db_steps)){
				$steps[$step_data['step']] = array(
						'screenid' => $step_data['screenid'],
						'delay' => $step_data['delay']
					);
			}
		}

		$form->addRow(S_NAME, new CTextBox('name', $name, 40));

		$delayBox = new CComboBox('delay', $delay);
		$delayBox->addItem(10,'10');
		$delayBox->addItem(30,'30');
		$delayBox->addItem(60,'60');
		$delayBox->addItem(120,'120');
		$delayBox->addItem(600,'600');
		$delayBox->addItem(900,'900');

		$form->addRow(S_UPDATE_INTERVAL_IN_SEC, $delayBox);

		$tblSteps = new CTableInfo(S_NO_SLIDES_DEFINED);
		$tblSteps->setHeader(array(S_SCREEN, S_DELAY, S_SORT));
		if(count($steps) > 0){
			ksort($steps);
			$first = min(array_keys($steps));
			$last = max(array_keys($steps));
		}

		foreach($steps as $sid => $s){
			if( !isset($s['screenid']) ) $s['screenid'] = 0;

			if(isset($s['delay']) && $s['delay'] > 0 )
				$s['delay'] = bold($s['delay']);
			else
				$s['delay'] = $delay;

			$up = null;
			if($sid != $first){
				$up = new CSpan(S_UP,'link');
				$up->onClick("return create_var('".$form->getName()."','move_up',".$sid.", true);");
			}

			$down = null;
			if($sid != $last){
				$down = new CSpan(S_DOWN,'link');
				$down->onClick("return create_var('".$form->getName()."','move_down',".$sid.", true);");
			}

			$screen_data = get_screen_by_screenid($s['screenid']);
			$name = new CSpan($screen_data['name'],'link');
			$name->onClick("return create_var('".$form->getName()."','edit_step',".$sid.", true);");

			$tblSteps->addRow(array(
				array(new CCheckBox('sel_step[]',null,null,$sid), $name),
				$s['delay'],
				array($up, isset($up) && isset($down) ? SPACE : null, $down)
				));
		}
		$form->addVar('steps', $steps);

		$form->addRow(S_SLIDES, array(
			$tblSteps,
			!isset($new_step) ? new CButton('add_step_bttn',S_ADD,
				"return create_var('".$form->getName()."','add_step',1, true);") : null,
			(count($steps) > 0) ? new CButton('del_sel_step',S_DELETE_SELECTED) : null
			));

		if(isset($new_step)){
			if( !isset($new_step['screenid']) )	$new_step['screenid'] = 0;
			if( !isset($new_step['delay']) )	$new_step['delay'] = 0;

			if( isset($new_step['sid']) )
				$form->addVar('new_step[sid]',$new_step['sid']);

			$form->addVar('new_step[screenid]',$new_step['screenid']);

			$screen_data = get_screen_by_screenid($new_step['screenid']);

			$form->addRow(S_NEW_SLIDE, array(
					S_DELAY,
					new CNumericBox('new_step[delay]', $new_step['delay'], 5), BR(),
					new CTextBox('screen_name', $screen_data['name'], 40, 'yes'),
					new CButton('select_screen',S_SELECT,
						'return PopUp("popup.php?dstfrm='.$form->getName().'&srctbl=screens'.
						'&dstfld1=screen_name&srcfld1=name'.
						'&dstfld2=new_step%5Bscreenid%5D&srcfld2=screenid");'),
					BR(),
					new CButton('add_step', isset($new_step['sid']) ? S_SAVE : S_ADD),
					new CButton('cancel_step', S_CANCEL)

				),
				isset($new_step['sid']) ? 'edit' : 'new');
		}

		$form->addItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST['slideshowid'])){
			$form->addItemToBottomRow(SPACE);
			$form->addItemToBottomRow(new CButton('clone',S_CLONE));
			$form->addItemToBottomRow(SPACE);
			$form->addItemToBottomRow(new CButtonDelete(S_DELETE_SLIDESHOW_Q,
				url_param('form').url_param('slideshowid').url_param('config')));
		}
		$form->addItemToBottomRow(SPACE);
		$form->addItemToBottomRow(new CButtonCancel());

		return $form;
	}

	function insert_httpstep_form(){
		$form = new CFormTable(S_STEP_OF_SCENARIO, null, 'post');
		$form->setHelp("web.webmon.httpconf.php");

		$form->addVar('dstfrm', get_request('dstfrm', null));
		$form->addVar('stepid', get_request('stepid', null));
		$form->addVar('list_name', get_request('list_name', null));

		$stepid = get_request('stepid', null);
		$name = get_request('name', '');
		$url = get_request('url', '');
		$posts = get_request('posts', '');
		$timeout = get_request('timeout', 15);
		$required = get_request('required', '');
		$status_codes = get_request('status_codes', '');

		$form->addRow(S_NAME, new CTextBox('name', $name, 50));
		$form->addRow(S_URL, new CTextBox('url', $url, 80));
		$form->addRow(S_POST, new CTextArea('posts', $posts, 50, 10));
		$form->addRow(S_TIMEOUT, new CNumericBox('timeout', $timeout, 5));
		$form->addRow(S_REQUIRED, new CTextBox('required', $required, 80));
		$form->addRow(S_STATUS_CODES, new CTextBox('status_codes', $status_codes, 80));

		$form->addItemToBottomRow(new CButton("save", isset($stepid) ? S_SAVE : S_ADD));

		$form->addItemToBottomRow(new CButtonCancel(null,'close_window();'));

		$form->show();
	}

// Insert form for User
	function getUserForm($userid, $profile=0){
		global $ZBX_LOCALES;
		global $USER_DETAILS;

		$config = select_config();

		$frm_title = S_USER;
		if(isset($userid)){
/*			if(bccomp($userid,$USER_DETAILS['userid'])==0) $profile = 1;*/
			$options = array(
					'userids' => $userid,
					'output' => API_OUTPUT_EXTEND
				);
			if($profile) $options['nodeids'] = id2nodeid($userid);

			$users = CUser::get($options);
			$user = reset($users);

			$frm_title = S_USER.' "'.$user['alias'].'"';
		}

		if(isset($userid) && (!isset($_REQUEST['form_refresh']) || isset($_REQUEST['register']))){
			$alias		= $user['alias'];
			$name		= $user['name'];
			$surname	= $user['surname'];
			$password	= null;
			$password1	= null;
			$password2	= null;
			$url		= $user['url'];
			$autologin	= $user['autologin'];
			$autologout	= $user['autologout'];
			$lang		= $user['lang'];
			$theme		= $user['theme'];
			$refresh	= $user['refresh'];
			$rows_per_page	= $user['rows_per_page'];
			$user_type	= $user['type'];

			if($autologout > 0) $_REQUEST['autologout'] = $autologout;

			$user_groups	= array();
			$user_medias	= array();

			$options = array(
				'userids' => $userid,
				'output' => API_OUTPUT_SHORTEN
			);
			$user_groups = CUserGroup::get($options);
			$user_groups = zbx_objectValues($user_groups, 'usrgrpid');
			$user_groups = zbx_toHash($user_groups);

			$db_medias = DBselect('SELECT m.* FROM media m WHERE m.userid='.$userid);
			while($db_media = DBfetch($db_medias)){
				$user_medias[] = array(
					'mediaid' => $db_media['mediaid'],
					'mediatypeid' => $db_media['mediatypeid'],
					'period' => $db_media['period'],
					'sendto' => $db_media['sendto'],
					'severity' => $db_media['severity'],
					'active' => $db_media['active']
				);
			}

			$messages = getMessageSettings();
		}
		else{
			$alias		= get_request('alias','');
			$name		= get_request('name','');
			$surname	= get_request('surname','');
			$password	= null;
			$password1	= get_request('password1', '');
			$password2	= get_request('password2', '');
			$url		= get_request('url','');

			$autologin	= get_request('autologin',0);
			$autologout	= get_request('autologout',90);

			$lang		= get_request('lang','en_gb');
			$theme		= get_request('theme','default.css');
			$refresh	= get_request('refresh',30);
			$rows_per_page	= get_request('rows_per_page',50);

			$user_type		= get_request('user_type',USER_TYPE_ZABBIX_USER);;
			$user_groups		= get_request('user_groups',array());
			$change_password	= get_request('change_password', null);
			$user_medias		= get_request('user_medias', array());


			$messages = get_request('messages', array());

			if(!isset($messages['enabled'])) $messages['enabled'] = 0;
			if(!isset($messages['sounds.recovery'])) $messages['sounds.recovery'] = 0;
			if(!isset($messages['triggers.recovery'])) $messages['triggers.recovery'] = 0;
			if(!isset($messages['triggers.severities'])) $messages['triggers.severities'] = array();

			$pMsgs = getMessageSettings();
			$messages = array_merge($pMsgs, $messages);

		}

		if($autologin || !isset($_REQUEST['autologout'])) $autologout = 0;
		else if(isset($_REQUEST['autologout']) && ($autologout < 90)) $autologout = 90;

		$perm_details	= get_request('perm_details',0);

		$media_types = array();
		$media_type_ids = array();
		foreach($user_medias as $one_media) $media_type_ids[$one_media['mediatypeid']] = 1;

		if(count($media_type_ids) > 0){
			$sql = 'SELECT mt.mediatypeid, mt.description '.
				' FROM media_type mt '.
				' WHERE mt.mediatypeid IN ('.implode(',',array_keys($media_type_ids)).')';
			$db_media_types = DBselect($sql);
			while($db_media_type = DBfetch($db_media_types)){
				$media_types[$db_media_type['mediatypeid']] = $db_media_type['description'];
			}
		}

		$frmUser = new CFormTable($frm_title);
		$frmUser->setName('user_form');
		$frmUser->setHelp('web.users.php');
		$frmUser->addVar('config',get_request('config',0));

		if(isset($userid))	$frmUser->addVar('userid',$userid);

		if($profile==0){
			$frmUser->addRow(S_ALIAS,	new CTextBox('alias',$alias,40));
			$frmUser->addRow(S_NAME,	new CTextBox('name',$name,40));
			$frmUser->addRow(S_SURNAME,	new CTextBox('surname',$surname,40));
		}

		$auth_type = isset($userid) ? get_user_system_auth($userid) : $config['authentication_type'];

		if(ZBX_AUTH_INTERNAL == $auth_type){
			if(!isset($userid) || isset($change_password)){
				$frmUser->addRow(S_PASSWORD,	new CPassBox('password1',$password1,20));
				$frmUser->addRow(S_PASSWORD_ONCE_AGAIN,	new CPassBox('password2',$password2,20));
				if(isset($change_password))
					$frmUser->addVar('change_password', $change_password);
			}
			else{
				$passwd_but = new CButton('change_password', S_CHANGE_PASSWORD);
				if($alias == ZBX_GUEST_USER){
					$passwd_but->setAttribute('disabled','disabled');
				}
				$frmUser->addRow(S_PASSWORD, $passwd_but);
			}
		}

		if($profile==0){
			$frmUser->addVar('user_groups',$user_groups);

			if(isset($userid) && (bccomp($USER_DETAILS['userid'], $userid)==0)){
				$frmUser->addVar('user_type',$user_type);
			}
			else{
				$cmbUserType = new CComboBox('user_type', $user_type, $perm_details ? 'submit();' : null);
				$cmbUserType->addItem(USER_TYPE_ZABBIX_USER,	user_type2str(USER_TYPE_ZABBIX_USER));
				$cmbUserType->addItem(USER_TYPE_ZABBIX_ADMIN,	user_type2str(USER_TYPE_ZABBIX_ADMIN));
				$cmbUserType->addItem(USER_TYPE_SUPER_ADMIN,	user_type2str(USER_TYPE_SUPER_ADMIN));
				$frmUser->addRow(S_USER_TYPE, $cmbUserType);
			}

			$lstGroups = new CListBox('user_groups_to_del[]', null, 10);
			$lstGroups->attributes['style'] = 'width: 320px';

			$options = array(
				'usrgrpids' => $user_groups,
				'output' => API_OUTPUT_EXTEND
			);
			$groups = CUserGroup::get($options);
			order_result($groups, 'name');
			foreach($groups as $num => $group){
				$lstGroups->addItem($group['usrgrpid'], $group['name']);
			}

			$frmUser->addRow(S_GROUPS,
				array(
					$lstGroups,
					BR(),
					new CButton('add_group',S_ADD,
						'return PopUp("popup_usrgrp.php?dstfrm='.$frmUser->getName().
						'&list_name=user_groups_to_del[]&var_name=user_groups",450, 450);'),
					SPACE,
					(count($user_groups) > 0)?new CButton('del_user_group',S_DELETE_SELECTED):null
				));
		}



		$cmbLang = new CComboBox('lang',$lang);
		foreach($ZBX_LOCALES as $loc_id => $loc_name){
			$cmbLang->addItem($loc_id,$loc_name);
		}

		$frmUser->addRow(S_LANGUAGE, $cmbLang);

		$cmbTheme = new CComboBox('theme',$theme);
			$cmbTheme->addItem(ZBX_DEFAULT_CSS,S_SYSTEM_DEFAULT);
			$cmbTheme->addItem('css_ob.css',S_ORIGINAL_BLUE);
			$cmbTheme->addItem('css_bb.css',S_BLACK_AND_BLUE);
			$cmbTheme->addItem('css_od.css',S_DARK_ORANGE);

		$frmUser->addRow(S_THEME, $cmbTheme);

		$script = "javascript:
			var autologout_visible = document.getElementById('autologout_visible');
			var autologout = document.getElementById('autologout');
			if(this.checked){
				if(autologout_visible.checked){
					autologout_visible.checked = false;
					autologout_visible.onclick();
				}
				autologout_visible.disabled = true;
			}
			else{
				autologout_visible.disabled = false;
			}";
		$chkbx_autologin = new CCheckBox("autologin", $autologin, $script, 1);

		$chkbx_autologin->setAttribute('autocomplete','off');
		$frmUser->addRow(S_AUTO_LOGIN,	$chkbx_autologin);

		$script = "javascript: var autologout = document.getElementById('autologout');
					if(this.checked) autologout.disabled = false;
					else autologout.disabled = true;";
		$autologoutCheckBox = new CCheckBox('autologout_visible', ($autologout == 0) ? 'no' : 'yes', $script);

		$autologoutTextBox = new CNumericBox("autologout", ($autologout == 0) ? '90' : $autologout, 4);
// if autologout is disabled
		if($autologout == 0) {
			$autologoutTextBox->setAttribute('disabled','disabled');
		}

		if($autologin != 0) {
			$autologoutCheckBox->setAttribute('disabled','disabled');
		}

		$frmUser->addRow(S_AUTO_LOGOUT, array($autologoutCheckBox, $autologoutTextBox));
		$frmUser->addRow(S_SCREEN_REFRESH,	new CNumericBox('refresh',$refresh,4));

		$frmUser->addRow(S_ROWS_PER_PAGE,	new CNumericBox('rows_per_page',$rows_per_page,6));
		$frmUser->addRow(S_URL_AFTER_LOGIN,	new CTextBox("url",$url,50));

//view Media Settings for users above "User" +++
		if(uint_in_array($USER_DETAILS['type'], array(USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN))) {
			$frmUser->addVar('user_medias', $user_medias);

			$media_table = new CTableInfo(S_NO_MEDIA_DEFINED);
			foreach($user_medias as $id => $one_media){
				if(!isset($one_media['active']) || $one_media['active']==0){
					$status = new CLink(S_ENABLED,'#','enabled');
					$status->onClick('return create_var("'.$frmUser->getName().'","disable_media",'.$id.', true);');
				}
				else{
					$status = new CLink(S_DISABLED,'#','disabled');
					$status->onClick('return create_var("'.$frmUser->getName().'","enable_media",'.$id.', true);');
				}

				$media_url = '?dstfrm='.$frmUser->getName().
								'&media='.$id.
								'&mediatypeid='.$one_media['mediatypeid'].
								'&sendto='.urlencode($one_media['sendto']).
								'&period='.$one_media['period'].
								'&severity='.$one_media['severity'].
								'&active='.$one_media['active'];

				$media_table->addRow(array(
					new CCheckBox('user_medias_to_del['.$id.']',null,null,$id),
					new CSpan($media_types[$one_media['mediatypeid']], 'nowrap'),
					new CSpan($one_media['sendto'], 'nowrap'),
					new CSpan($one_media['period'], 'nowrap'),
					media_severity2str($one_media['severity']),
					$status,
					new CButton('edit_media',S_EDIT,'javascript: return PopUp("popup_media.php'.$media_url.'",550,400);'))
				);
			}

			$frmUser->addRow(
				S_MEDIA,
				array($media_table,
					new CButton('add_media',S_ADD,'javascript: return PopUp("popup_media.php?dstfrm='.$frmUser->getName().'",550,400);'),
					SPACE,
					(count($user_medias) > 0) ? new CButton('del_user_media',S_DELETE_SELECTED) : null
				));
		}


		if(0 == $profile){
			$frmUser->addVar('perm_details', $perm_details);

			$link = new CSpan($perm_details?S_HIDE:S_SHOW ,'link');
			$link->onClick("return create_var('".$frmUser->getName()."','perm_details',".($perm_details ? 0 : 1).", true);");
			$resources_list = array(
				S_RIGHTS_OF_RESOURCES,
				SPACE.'(',$link,')'
				);
			$frmUser->addSpanRow($resources_list,'right_header');

			if($perm_details){
				$group_ids = array_values($user_groups);
				if(count($group_ids) == 0) $group_ids = array(-1);
				$db_rights = DBselect('SELECT * FROM rights r WHERE '.DBcondition('r.groupid',$group_ids));

				$tmp_perm = array();
				while($db_right = DBfetch($db_rights)){
					if(isset($tmp_perm[$db_right['id']])){
						$tmp_perm[$db_right['id']] = min($tmp_perm[$db_right['id']],$db_right['permission']);
					}
					else{
						$tmp_perm[$db_right['id']] = $db_right['permission'];
					}
				}

				$user_rights = array();
				foreach($tmp_perm as $id => $perm){
					array_push($user_rights, array(
						'id'		=> $id,
						'permission'	=> $perm
						));
				}
//SDI($user_rights);
//SDI($user_type);
				$frmUser->addSpanRow(get_rights_of_elements_table($user_rights, $user_type));
			}
		}

		if($profile){
			$msgVisibility = array('1' => array(
					'messages[timeout]',
					'messages[sounds.repeat]',
					'messages[sounds.recovery]',
					'messages[triggers.recovery]',
					'timeout_row',
					'repeat_row',
					'triggers_row',
				)
			);

			$frmUser->addRow(S_GUI_MESSAGING, new CCheckBox('messages[enabled]', $messages['enabled'], null, 1));

			$newRow = $frmUser->addRow(S_MESSAGE_TIMEOUT.SPACE.'('.S_SECONDS_SMALL.')', new CNumericBox("messages[timeout]", $messages['timeout'], 5));
			$newRow->setAttribute('id', 'timeout_row');

			$repeatSound = new CComboBox('messages[sounds.repeat]', $messages['sounds.repeat'], 'javascript: if(IE) submit();');
			$repeatSound->setAttribute('id', 'messages[sounds.repeat]');
			$repeatSound->addItem(1, S_ONCE);
			$repeatSound->addItem(10, '10 '.S_SECONDS);
			$repeatSound->addItem(-1, S_MESSAGE_TIMEOUT);

			$newRow = $frmUser->addRow(S_PLAY_SOUND, $repeatSound);
			$newRow->setAttribute('id', 'repeat_row');

// trigger sounds
			$severities = array(
				TRIGGER_SEVERITY_NOT_CLASSIFIED,
				TRIGGER_SEVERITY_INFORMATION,
				TRIGGER_SEVERITY_WARNING,
				TRIGGER_SEVERITY_AVERAGE,
				TRIGGER_SEVERITY_HIGH,
				TRIGGER_SEVERITY_DISASTER
			);

			$zbxSounds = getSounds();
			$triggers = new CTable('', 'invisible');

			$soundList = new CComboBox('messages[sounds.recovery]', $messages['sounds.recovery']);
			foreach($zbxSounds as $filename => $file) $soundList->addItem($file, $filename);

			$resolved = array(
				new CCheckBox('messages[triggers.recovery]', $messages['triggers.recovery'], null, 1),
				S_RECOVERY,
				$soundList,
				new CButton('start', S_PLAY, "javascript: testUserSound('messages[sounds.recovery]');", false),
				new CButton('stop', S_STOP, 'javascript: AudioList.stopAll();', false)
			);

			$triggers->addRow($resolved);

			foreach($severities as $snum => $severity){
				$soundList = new CComboBox('messages[sounds.'.$severity.']', $messages['sounds.'.$severity]);
				foreach($zbxSounds as $filename => $file) $soundList->addItem($file, $filename);

				$triggers->addRow(array(
					new CCheckBox('messages[triggers.severities]['.$severity.']', isset($messages['triggers.severities'][$severity]), null, 1),
					getSeverityCaption($severity),
					$soundList,
					new CButton('start', S_PLAY, "javascript: testUserSound('messages[sounds.".$severity."]');", false),
					new CButton('stop', S_STOP, 'javascript: AudioList.stopAll();', false)
				));


				zbx_subarray_push($msgVisibility, 1, 'messages[triggers.severities]['.$severity.']');
				zbx_subarray_push($msgVisibility, 1, 'messages[sounds.'.$severity.']');
			}

			$newRow = $frmUser->addRow(S_TRIGGER_SEVERITY, $triggers);
			$newRow->setAttribute('id', 'triggers_row');

			zbx_add_post_js("var userMessageSwitcher = new CViewSwitcher('messages[enabled]', 'click', ".zbx_jsvalue($msgVisibility, true).");");
			zbx_add_post_js("$('messages[enabled]').observe('click',function() {
								if (this.checked
										&& !$('messages[triggers.recovery]').checked
										&& !$('messages[triggers.severities][0]').checked
										&& !$('messages[triggers.severities][1]').checked
										&& !$('messages[triggers.severities][2]').checked
										&& !$('messages[triggers.severities][3]').checked
										&& !$('messages[triggers.severities][4]').checked
										&& !$('messages[triggers.severities][5]').checked) {
									$('messages[triggers.recovery]').checked = true;
									$('messages[triggers.severities][0]').checked = true;
									$('messages[triggers.severities][1]').checked = true;
									$('messages[triggers.severities][2]').checked = true;
									$('messages[triggers.severities][3]').checked = true;
									$('messages[triggers.severities][4]').checked = true;
									$('messages[triggers.severities][5]').checked = true;
								}
							});");
 		}

		$frmUser->addItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($userid) && ($profile == 0)){
			$frmUser->addItemToBottomRow(SPACE);
			$delete_b = new CButtonDelete(S_DELETE_SELECTED_USER_Q,url_param("form").url_param("config").url_param("userid"));
			if(bccomp($USER_DETAILS['userid'],$userid) == 0){
				$delete_b->setAttribute('disabled','disabled');
			}

			$frmUser->addItemToBottomRow($delete_b);
		}
		$frmUser->addItemToBottomRow(SPACE);
		$frmUser->addItemToBottomRow(new CButtonCancel(url_param("config")));

	return $frmUser;
	}

// Insert form for User Groups
	function insert_usergroups_form(){
		$frm_title = S_USER_GROUP;

		if(isset($_REQUEST['usrgrpid'])){
			$usrgrp		= CUserGroup::get(array('usrgrpids' => $_REQUEST['usrgrpid'],  'extendoutput' => 1));
			$usrgrp = reset($usrgrp);

			$frm_title	= S_USER_GROUP.' "'.$usrgrp['name'].'"';
		}

		if(isset($_REQUEST['usrgrpid']) && !isset($_REQUEST['form_refresh'])){
			$name	= $usrgrp['name'];

			$users_status = $usrgrp['users_status'];
			$gui_access = $usrgrp['gui_access'];
			$api_access = $usrgrp['api_access'];
			$debug_mode = $usrgrp['debug_mode'];

			$group_users = array();
			$sql = 'SELECT DISTINCT u.userid '.
						' FROM users u,users_groups ug '.
						' WHERE u.userid=ug.userid '.
							' AND ug.usrgrpid='.$_REQUEST['usrgrpid'];

			$db_users=DBselect($sql);

			while($db_user=DBfetch($db_users))
				$group_users[$db_user['userid']] = $db_user['userid'];

			$group_rights = array();
			$sql = 'SELECT r.*, n.name as node_name, g.name as name '.
					' FROM groups g '.
						' LEFT JOIN rights r on r.id=g.groupid '.
						' LEFT JOIN nodes n on n.nodeid='.DBid2nodeid('g.groupid').
					' WHERE r.groupid='.$_REQUEST['usrgrpid'];

			$db_rights = DBselect($sql);
			while($db_right = DBfetch($db_rights)){
				if(isset($db_right['node_name']))
					$db_right['name'] = $db_right['node_name'].':'.$db_right['name'];

				$group_rights[$db_right['id']] = array(
					'permission'	=> $db_right['permission'],
					'name'		=> $db_right['name'],
					'id'		=> $db_right['id']
				);
			}
		}
		else{
			$name			= get_request('gname','');
			$users_status	= get_request('users_status',GROUP_STATUS_ENABLED);
			$gui_access	= get_request('gui_access',GROUP_GUI_ACCESS_SYSTEM);
			$api_access	= get_request('api_access',GROUP_API_ACCESS_DISABLED);
			$debug_mode	= get_request('debug_mode',GROUP_DEBUG_MODE_DISABLED);
			$group_users	= get_request('group_users',array());
			$group_rights	= get_request('group_rights',array());
		}
		$perm_details = get_request('perm_details', 0);

		order_result($group_rights, 'name');

		$frmUserG = new CFormTable($frm_title,'usergrps.php');
		$frmUserG->setHelp('web.users.groups.php');

		if(isset($_REQUEST['usrgrpid'])){
			$frmUserG->addVar('usrgrpid',$_REQUEST['usrgrpid']);
		}

		$grName = new CTextBox('gname',$name,49);
		$grName->attributes['style'] = 'width: 280px';
		$frmUserG->addRow(S_GROUP_NAME,$grName);

		$frmUserG->addVar('group_rights', $group_rights);

/////////////////

// create table header +

	$selusrgrp = get_request('selusrgrp', 0);
	$cmbGroups = new CComboBox('selusrgrp', $selusrgrp, 'submit()');
	$cmbGroups->addItem(0,S_ALL_S);

	$sql = 'SELECT usrgrpid, name FROM usrgrp WHERE '.DBin_node('usrgrpid').' ORDER BY name';
	$result=DBselect($sql);
	while($row=DBfetch($result)){
		$cmbGroups->addItem($row['usrgrpid'], $row['name']);
	}
// -

// create user twinbox +
	$user_tb = new CTweenBox($frmUserG, 'group_users', $group_users, 10);

	$sql_from = '';
	$sql_where = '';
	if($selusrgrp > 0) {
		$sql_from = ', users_groups g ';
		$sql_where = ' AND u.userid=g.userid AND g.usrgrpid='.$selusrgrp;
	}
	$sql = 'SELECT DISTINCT u.userid, u.alias '.
			' FROM users u '.$sql_from.
			' WHERE '.DBcondition('u.userid', $group_users).
			' OR ('.DBin_node('u.userid').
				$sql_where.
			' ) ORDER BY u.alias';
	$result=DBselect($sql);
	while($row=DBfetch($result)){
		$user_tb->addItem($row['userid'], $row['alias']);
	}

	$frmUserG->addRow(S_USERS, $user_tb->get(S_IN.SPACE.S_GROUP,array(S_OTHER.SPACE.S_GROUPS.SPACE.'|'.SPACE, $cmbGroups)));
// -

/////////////////
/*
		$lstUsers = new CListBox('group_users_to_del[]');
		$lstUsers->attributes['style'] = 'width: 280px';

		foreach($group_users as $userid => $alias){
			$lstUsers->addItem($userid,	$alias);
		}

		$frmUserG->addRow(S_USERS,
			array(
				$lstUsers,
				BR(),
				new CButton('add_user',S_ADD,
					"return PopUp('popup_users.php?dstfrm=".$frmUserG->getName().
					"&list_name=group_users_to_del[]&var_name=group_users',600,300);"),
				(count($group_users) > 0) ? new CButton('del_group_user',S_DELETE_SELECTED) : null
			));
*/
/////////////////

		$granted = true;
		if(isset($_REQUEST['usrgrpid'])){
			$granted = granted2update_group($_REQUEST['usrgrpid']);
		}

		if($granted){
			$cmbGUI = new CComboBox('gui_access',$gui_access);
			$cmbGUI->addItem(GROUP_GUI_ACCESS_SYSTEM,user_auth_type2str(GROUP_GUI_ACCESS_SYSTEM));
			$cmbGUI->addItem(GROUP_GUI_ACCESS_INTERNAL,user_auth_type2str(GROUP_GUI_ACCESS_INTERNAL));
			$cmbGUI->addItem(GROUP_GUI_ACCESS_DISABLED,user_auth_type2str(GROUP_GUI_ACCESS_DISABLED));
			$frmUserG->addRow(S_GUI_ACCESS, $cmbGUI);

			$cmbStat = new CComboBox('users_status',$users_status);
			$cmbStat->addItem(GROUP_STATUS_ENABLED,S_ENABLED);
			$cmbStat->addItem(GROUP_STATUS_DISABLED,S_DISABLED);

			$frmUserG->addRow(S_USERS_STATUS, $cmbStat);

		}
		else{
			$frmUserG->addVar('gui_access',$gui_access);
			$frmUserG->addRow(S_GUI_ACCESS, new CSpan(user_auth_type2str($gui_access),'green'));

			$frmUserG->addVar('users_status',GROUP_STATUS_ENABLED);
			$frmUserG->addRow(S_USERS_STATUS, new CSpan(S_ENABLED,'green'));
		}

		$cmbAPI = new CComboBox('api_access', $api_access);
		$cmbAPI->addItem(GROUP_API_ACCESS_ENABLED, S_ENABLED);
		$cmbAPI->addItem(GROUP_API_ACCESS_DISABLED, S_DISABLED);
		$frmUserG->addRow(S_API_ACCESS, $cmbAPI);

		$cmbDebug = new CComboBox('debug_mode', $debug_mode);
		$cmbDebug->addItem(GROUP_DEBUG_MODE_ENABLED, S_ENABLED);
		$cmbDebug->addItem(GROUP_DEBUG_MODE_DISABLED, S_DISABLED);
		$frmUserG->addRow(S_DEBUG_MODE, $cmbDebug);


		$table_Rights = new CTable(S_NO_RIGHTS_DEFINED,'right_table');

		$lstWrite = new CListBox('right_to_del[read_write][]'	,null	,20);
		$lstRead  = new CListBox('right_to_del[read_only][]'	,null	,20);
		$lstDeny  = new CListBox('right_to_del[deny][]'			,null	,20);

		foreach($group_rights as $id => $element_data){
			if($element_data['permission'] == PERM_DENY)			$lstDeny->addItem($id, $element_data['name']);
			else if($element_data['permission'] == PERM_READ_ONLY)	$lstRead->addItem($id, $element_data['name']);
			else if($element_data['permission'] == PERM_READ_WRITE)	$lstWrite->addItem($id, $element_data['name']);
		}

		$table_Rights->setHeader(array(S_READ_WRITE, S_READ_ONLY, S_DENY),'header');
		$table_Rights->addRow(array(new CCol($lstWrite,'read_write'), new CCol($lstRead,'read_only'), new CCol($lstDeny,'deny')));
		$table_Rights->addRow(array(
			array(new CButton('add_read_write',S_ADD,
					"return PopUp('popup_right.php?dstfrm=".$frmUserG->getName().
					"&permission=".PERM_READ_WRITE."',450,450);"),
				new CButton('del_read_write',S_DELETE_SELECTED)),
			array(	new CButton('add_read_only',S_ADD,
					"return PopUp('popup_right.php?dstfrm=".$frmUserG->getName().
					"&permission=".PERM_READ_ONLY."',450,450);"),
				new CButton('del_read_only',S_DELETE_SELECTED)),
			array(new CButton('add_deny',S_ADD,
					"return PopUp('popup_right.php?dstfrm=".$frmUserG->getName().
					"&permission=".PERM_DENY."',450,450);"),
				new CButton('del_deny',S_DELETE_SELECTED))
			));

		$frmUserG->addRow(S_RIGHTS,$table_Rights);

		$frmUserG->addVar('perm_details', $perm_details);

		$link = new CSpan($perm_details?S_HIDE:S_SHOW,'link');
		$link->onClick("return create_var('".$frmUserG->getName()."','perm_details',".($perm_details ? 0 : 1).", true);");
		$resources_list = array(
			S_RIGHTS_OF_RESOURCES,
			SPACE.'(',$link,')'
			);
		$frmUserG->addSpanRow($resources_list,'right_header');

		if($perm_details){
			$frmUserG->addSpanRow(get_rights_of_elements_table($group_rights));
		}

		$frmUserG->addItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($_REQUEST['usrgrpid'])){
			$frmUserG->addItemToBottomRow(SPACE);
			$frmUserG->addItemToBottomRow(new CButtonDelete('Delete selected group?',
				url_param('form').url_param('usrgrpid')));
		}
		$frmUserG->addItemToBottomRow(SPACE);
		$frmUserG->addItemToBottomRow(new CButtonCancel());

		return($frmUserG);
	}

	function get_rights_of_elements_table($rights=array(),$user_type=USER_TYPE_ZABBIX_USER){
		global $ZBX_LOCALNODEID;

		$table = new CTable('S_NO_ACCESSIBLE_RESOURCES', 'right_table');
		$table->setHeader(array(SPACE, S_READ_WRITE, S_READ_ONLY, S_DENY),'header');

		if(ZBX_DISTRIBUTED){
			$lst['node']['label']		= S_NODES;
			$lst['node']['read_write']	= new CListBox('nodes_write',null	,10);
			$lst['node']['read_only']	= new CListBox('nodes_read'	,null	,10);
			$lst['node']['deny']		= new CListBox('nodes_deny'	,null	,10);

			$nodes = get_accessible_nodes_by_rights($rights, $user_type, PERM_DENY, PERM_RES_DATA_ARRAY);
			foreach($nodes as $node){
				switch($node['permission']){
					case PERM_READ_ONLY:	$list_name='read_only';		break;
					case PERM_READ_WRITE:	$list_name='read_write';	break;
					default:		$list_name='deny';		break;
				}
				$lst['node'][$list_name]->addItem($node['nodeid'],$node['name']);
			}
			unset($nodes);
		}

		$lst['group']['label']		= S_HOST_GROUPS;
		$lst['group']['read_write']	= new CListBox('groups_write'	,null	,15);
		$lst['group']['read_only']	= new CListBox('groups_read'	,null	,15);
		$lst['group']['deny']		= new CListBox('groups_deny'	,null	,15);

		$groups = get_accessible_groups_by_rights($rights, $user_type, PERM_DENY, PERM_RES_DATA_ARRAY, get_current_nodeid(true));

		foreach($groups as $group){
			switch($group['permission']){
				case PERM_READ_ONLY:
					$list_name='read_only';
					break;
				case PERM_READ_WRITE:
					$list_name='read_write';
					break;
				default:
					$list_name='deny';
			}
			$lst['group'][$list_name]->addItem($group['groupid'],(!empty($group['node_name'])?$group['node_name'].':':$group['node_name']).$group['name']);
		}
		unset($groups);

		$lst['host']['label']		= S_HOSTS;
		$lst['host']['read_write']	= new CListBox('hosts_write'	,null	,15);
		$lst['host']['read_only']	= new CListBox('hosts_read'	,null	,15);
		$lst['host']['deny']		= new CListBox('hosts_deny'	,null	,15);

		$hosts = get_accessible_hosts_by_rights($rights, $user_type, PERM_DENY, PERM_RES_DATA_ARRAY, get_current_nodeid(true));

		foreach($hosts as $host){
			switch($host['permission']){
				case PERM_READ_ONLY:	$list_name='read_only';		break;
				case PERM_READ_WRITE:	$list_name='read_write';	break;
				default:		$list_name='deny';		break;
			}
			$lst['host'][$list_name]->addItem($host['hostid'], (!empty($host['node_name'])?$host['node_name'].':':$host['node_name']).$host['host']);
		}
		unset($hosts);

		foreach($lst as $name => $lists){
			$row = new CRow();
			foreach($lists as $class => $list_obj){
				$row->addItem(new CCol($list_obj, $class));
			}
			$table->addRow($row);
		}
		unset($lst);

		return $table;
	}

/* ITEMS FILTER functions { --->>> */
	function prepare_subfilter_output($data, $subfilter, $subfilter_name){

		$output = array();
		order_result($data, 'name');
		foreach($data as $id => $elem){

// subfilter is activated
			if(str_in_array($id, $subfilter)){
				$span = new CSpan($elem['name'].' ('.$elem['count'].')', 'subfilter_enabled');
				$script = "javascript: create_var('zbx_filter', '".$subfilter_name.'['.$id."]', null, true);";
				$span->onClick($script);
				$output[] = $span;
			}
// subfilter isn't activated
			else{
				$script = "javascript: create_var('zbx_filter', '".$subfilter_name.'['.$id."]', '$id', true);";

// subfilter has 0 items
				if($elem['count'] == 0){
					$span = new CSpan($elem['name'].' ('.$elem['count'].')', 'subfilter_inactive');
					$span->onClick($script);
					$output[] = $span;
				}
				else{
					// this level has no active subfilters
					if(empty($subfilter)){
						$nspan = new CSpan(' ('.$elem['count'].')', 'subfilter_active');
					}
					else{
						$nspan = new CSpan(' (+'.$elem['count'].')', 'subfilter_active');
					}
					$span = new CSpan($elem['name'], 'subfilter_disabled');
					$span->onClick($script);

					$output[] = $span;
					$output[] = $nspan;
				}
			}
			$output[] = ' , ';
		}
		array_pop($output);

		return $output;
	}

	function get_item_filter_form(&$items){

		$filter_group			= $_REQUEST['filter_group'];
		$filter_host			= $_REQUEST['filter_host'];
		$filter_application		= $_REQUEST['filter_application'];
		$filter_description		= $_REQUEST['filter_description'];
		$filter_type			= $_REQUEST['filter_type'];
		$filter_key			= $_REQUEST['filter_key'];
		$filter_snmp_community		= $_REQUEST['filter_snmp_community'];
		$filter_snmpv3_securityname	= $_REQUEST['filter_snmpv3_securityname'];
		$filter_snmp_oid		= $_REQUEST['filter_snmp_oid'];
		$filter_snmp_port		= $_REQUEST['filter_snmp_port'];
		$filter_value_type		= $_REQUEST['filter_value_type'];
		$filter_data_type		= $_REQUEST['filter_data_type'];
		$filter_delay			= $_REQUEST['filter_delay'];
		$filter_history			= $_REQUEST['filter_history'];
		$filter_trends			= $_REQUEST['filter_trends'];
		$filter_status			= $_REQUEST['filter_status'];
		$filter_templated_items		= $_REQUEST['filter_templated_items'];
		$filter_with_triggers		= $_REQUEST['filter_with_triggers'];
// subfilter
		$subfilter_hosts		= $_REQUEST['subfilter_hosts'];
		$subfilter_apps			= $_REQUEST['subfilter_apps'];
		$subfilter_types		= $_REQUEST['subfilter_types'];
		$subfilter_value_types		= $_REQUEST['subfilter_value_types'];
		$subfilter_status		= $_REQUEST['subfilter_status'];
		$subfilter_templated_items	= $_REQUEST['subfilter_templated_items'];
		$subfilter_with_triggers	= $_REQUEST['subfilter_with_triggers'];
		$subfilter_history		= $_REQUEST['subfilter_history'];
		$subfilter_trends		= $_REQUEST['subfilter_trends'];
		$subfilter_interval		= $_REQUEST['subfilter_interval'];

		$form = new CForm();
		$form->setAttribute('name','zbx_filter');
		$form->setAttribute('id','zbx_filter');
		$form->setMethod('get');
		$form->addVar('filter_hostid',get_request('filter_hostid',get_request('hostid')));

		$form->addVar('subfilter_hosts',		$subfilter_hosts);
		$form->addVar('subfilter_apps',			$subfilter_apps);
		$form->addVar('subfilter_types',		$subfilter_types);
		$form->addVar('subfilter_value_types',		$subfilter_value_types);
		$form->addVar('subfilter_status',		$subfilter_status);
		$form->addVar('subfilter_templated_items',	$subfilter_templated_items);
		$form->addVar('subfilter_with_triggers',	$subfilter_with_triggers);
		$form->addVar('subfilter_history',		$subfilter_history);
		$form->addVar('subfilter_trends',		$subfilter_trends);
		$form->addVar('subfilter_interval',		$subfilter_interval);

// FORM FOR FILTER DISPLAY {
		$table = new CTable('', 'itemfilter');
		$table->setCellPadding(0);
		$table->setCellSpacing(0);

// 1st col
		$col_table1 = new CTable();
		$col_table1->setClass('filter');
		$col_table1->addRow(array(bold(S_HOST_GROUP.': '),
				array(new CTextBox('filter_group', $filter_group, 20),
					new CButton('btn_group', S_SELECT, 'return PopUp("popup.php?dstfrm='.$form->getName().
						'&dstfld1=filter_group&srctbl=host_group&srcfld1=name",450,450);', 'G'))
		));
		$col_table1->addRow(array(bold(S_HOST.': '),
				array(new CTextBox('filter_host', $filter_host, 20),
					new CButton('btn_host', S_SELECT, 'return PopUp("popup.php?dstfrm='.$form->getName().
						'&dstfld1=filter_host&srctbl=hosts_and_templates&srcfld1=host",450,450);', 'H'))
		));
		$col_table1->addRow(array(bold(S_APPLICATION.': '),
				array(new CTextBox('filter_application', $filter_application, 20),
					new CButton('btn_app', S_SELECT, 'return PopUp("popup.php?dstfrm='.$form->getName().
						'&dstfld1=filter_application&srctbl=applications&srcfld1=name",400,300,"application");', 'A'))
		));
		$col_table1->addRow(array(array(bold(S_DESCRIPTION),SPACE.S_LIKE_SMALL.': '),
			new CTextBox("filter_description", $filter_description, 30)));
		$col_table1->addRow(array(array(bold(S_KEY),SPACE.S_LIKE_SMALL.': '),
			new CTextBox("filter_key", $filter_key, 30)));

// 2nd col
		$col_table2 = new CTable();
		$col_table2->setClass('filter');
		$fTypeVisibility = array();

//first row
		$cmbType = new CComboBox("filter_type", $filter_type); //"javascript: create_var('zbx_filter', 'filter_set', '1', true); ");
		$cmbType->setAttribute('id', 'filter_type');
		$cmbType->addItem(-1, S_ALL_SMALL);
		foreach(array('filter_delay_label','filter_delay') as $vItem){
			zbx_subarray_push($fTypeVisibility, -1, $vItem);
		}

		$itemTypes = item_type2str();
// httptest items are only for internal zabbix logic
		unset($itemTypes[ITEM_TYPE_HTTPTEST]);

		$cmbType->addItems($itemTypes);

		foreach($itemTypes as $typeNum => $typeLabel){
			if($typeNum != ITEM_TYPE_TRAPPER){
				zbx_subarray_push($fTypeVisibility, $typeNum, 'filter_delay_label');
				zbx_subarray_push($fTypeVisibility, $typeNum, 'filter_delay');
			}

			switch($typeNum){
				case ITEM_TYPE_SNMPV1:
				case ITEM_TYPE_SNMPV2C:
					$snmp_types = array(
						'filter_snmp_community_label', 'filter_snmp_community',
						'filter_snmp_oid_label', 'filter_snmp_oid',
						'filter_snmp_port_label', 'filter_snmp_port'
					);
					foreach($snmp_types as $vItem){
						zbx_subarray_push($fTypeVisibility, $typeNum, $vItem);
					}
					break;
				case ITEM_TYPE_SNMPV3:
					foreach(array(
						'filter_snmpv3_securityname_label', 'filter_snmpv3_securityname',
						'filter_snmp_oid_label', 'filter_snmp_oid',
						'filter_snmp_port_label', 'filter_snmp_port'
					) as $vItem)
						zbx_subarray_push($fTypeVisibility, $typeNum, $vItem);
					break;
			}
		}

		zbx_add_post_js("var filterTypeSwitcher = new CViewSwitcher('filter_type', 'change', ".zbx_jsvalue($fTypeVisibility, true).");");
		$col21 = new CCol(bold(S_TYPE.': '));
		$col21->setAttribute('style', 'width: 170px');

		$col_table2->addRow(array($col21, $cmbType));
	//second row
		$label221 = new CSpan(bold(S_UPDATE_INTERVAL_IN_SEC.': '));
		$label221->setAttribute('id', 'filter_delay_label');

		$field221 = new CNumericBox('filter_delay', $filter_delay, 5, null, true);
		$field221->setEnabled('no');

		$col_table2->addRow(array(array($label221, SPACE), array($field221, SPACE)));
	//third row
		$label231 = new CSpan(array(bold(S_SNMP_COMMUNITY), SPACE.S_LIKE_SMALL.': '));
		$label231->setAttribute('id', 'filter_snmp_community_label');

		$field231 = new CTextBox('filter_snmp_community', $filter_snmp_community, 40);
		$field231->setEnabled('no');

		$label232 = new CSpan(array(bold(S_SNMPV3_SECURITY_NAME), SPACE.S_LIKE_SMALL.': '));
		$label232->setAttribute('id', 'filter_snmpv3_securityname_label');

		$field232 = new CTextBox('filter_snmpv3_securityname', $filter_snmpv3_securityname, 40);
		$field232->setEnabled('no');

		$col_table2->addRow(array(array($label231, $label232, SPACE), array($field231, $field232, SPACE)));
	//fourth row
		$label241 = new CSpan(array(bold(S_SNMP_OID), SPACE.S_LIKE_SMALL.': '));
		$label241->setAttribute('id', 'filter_snmp_oid_label');

		$field241 = new CTextBox('filter_snmp_oid', $filter_snmp_oid, 40);
		$field241->setEnabled('no');

		$col_table2->addRow(array(array($label241, SPACE), array($field241, SPACE)));
	//fifth row
		$label251 = new CSpan(array(bold(S_SNMP_PORT), SPACE.S_LIKE_SMALL.': '));
		$label251->setAttribute('id', 'filter_snmp_port_label');

		$field251 = new CNumericBox('filter_snmp_port', $filter_snmp_port, 5 ,null, true);
		$field251->setEnabled('no');

		$col_table2->addRow(array(array($label251, SPACE), array($field251, SPACE)));
// 3rd col
		$col_table3 = new CTable();
		$col_table3->setClass('filter');
		$fVTypeVisibility = array();

		$cmbValType = new CComboBox('filter_value_type', $filter_value_type); //, "javascript: create_var('zbx_filter', 'filter_set', '1', true);");
		$cmbValType->addItem(-1, S_ALL_SMALL);
		$cmbValType->addItem(ITEM_VALUE_TYPE_UINT64, S_NUMERIC_UNSIGNED);
		$cmbValType->addItem(ITEM_VALUE_TYPE_FLOAT, S_NUMERIC_FLOAT);
		$cmbValType->addItem(ITEM_VALUE_TYPE_STR, S_CHARACTER);
		$cmbValType->addItem(ITEM_VALUE_TYPE_LOG, S_LOG);
		$cmbValType->addItem(ITEM_VALUE_TYPE_TEXT, S_TEXT);

		foreach(array('filter_data_type_label','filter_data_type') as $vItem)
			zbx_subarray_push($fVTypeVisibility, ITEM_VALUE_TYPE_UINT64, $vItem);

		$col_table3->addRow(array(bold(S_TYPE_OF_INFORMATION.': '), $cmbValType));

		zbx_add_post_js("var filterValueTypeSwitcher = new CViewSwitcher('filter_value_type', 'change', ".zbx_jsvalue($fVTypeVisibility, true).");");
//second row
		$label321 = new CSpan(bold(S_DATA_TYPE.': '));
		$label321->setAttribute('id', 'filter_data_type_label');

		$field321 = new CComboBox('filter_data_type', $filter_data_type);//, 'submit()');
		$field321->addItem(-1, S_ALL_SMALL);
		$field321->addItem(ITEM_DATA_TYPE_DECIMAL, item_data_type2str(ITEM_DATA_TYPE_DECIMAL));
		$field321->addItem(ITEM_DATA_TYPE_OCTAL, item_data_type2str(ITEM_DATA_TYPE_OCTAL));
		$field321->addItem(ITEM_DATA_TYPE_HEXADECIMAL, item_data_type2str(ITEM_DATA_TYPE_HEXADECIMAL));
		$field321->setEnabled('no');

		$col_table3->addRow(array(array($label321, SPACE), array($field321, SPACE)));

		$col_table3->addRow(array(bold(S_KEEP_HISTORY_IN_DAYS.': '), new CNumericBox('filter_history',$filter_history,8,null,true)));

		$col_table3->addRow(array(bold(S_KEEP_TRENDS_IN_DAYS.': '), new CNumericBox('filter_trends',$filter_trends,8,null,true)));
// 4th col
		$col_table4 = new CTable();
		$col_table4->setClass('filter');

		$cmbStatus = new CComboBox('filter_status',$filter_status);
		$cmbStatus->addItem(-1,S_ALL_SMALL);
		foreach(array(ITEM_STATUS_ACTIVE,ITEM_STATUS_DISABLED,ITEM_STATUS_NOTSUPPORTED) as $st)
			$cmbStatus->addItem($st,item_status2str($st));

		$cmbBelongs = new CComboBox('filter_templated_items', $filter_templated_items);
		$cmbBelongs->addItem(-1, S_ALL_SMALL);
		$cmbBelongs->addItem(1, S_TEMPLATED_ITEMS);
		$cmbBelongs->addItem(0, S_NOT_TEMPLATED_ITEMS);

		$cmbWithTriggers = new CComboBox('filter_with_triggers', $filter_with_triggers);
		$cmbWithTriggers->addItem(-1, S_ALL_SMALL);
		$cmbWithTriggers->addItem(1, S_WITH_TRIGGERS);
		$cmbWithTriggers->addItem(0, S_WITHOUT_TRIGGERS);

		$col_table4->addRow(array(bold(S_STATUS.': '), $cmbStatus));
		$col_table4->addRow(array(bold(S_TRIGGERS.': '), $cmbWithTriggers));
		$col_table4->addRow(array(bold(S_TEMPLATE.': '), $cmbBelongs));

//adding all cols tables to main table
		$col1 = new CCol($col_table1, 'top');
		$col1->setAttribute('style', 'width: 280px');
		$col2 = new CCol($col_table2, 'top');
		$col2->setAttribute('style', 'width: 410px');
		$col3 = new CCol($col_table3, 'top');
		$col3->setAttribute('style', 'width: 160px');
		$col4 = new CCol($col_table4, 'top');

		$table->addRow(array($col1, $col2, $col3, $col4));

		$reset = new CSpan( S_RESET,'biglink');
		$reset->onClick("javascript: clearAllForm('zbx_filter');");
		$filter = new CSpan(S_FILTER,'biglink');
		$filter->onClick("javascript: create_var('zbx_filter', 'filter_set', '1', true);");

		$div_buttons = new CDiv(array($filter, SPACE, SPACE, SPACE, $reset));
		$div_buttons->setAttribute('style', 'padding: 4px 0;');
		$footer = new CCol($div_buttons, 'center');
		$footer->setColSpan(4);

		$table->addRow($footer);
		$form->addItem($table);

// } FORM FOR FILTER DISPLAY

// SUBFILTERS {
		$h = new CDiv(S_SUBFILTER.SPACE.'['.S_AFFECTS_ONLY_FILTERED_DATA_SMALL.']');
		$h->setClass('thin_header');
		$form->addItem($h);
		$table_subfilter = new Ctable();
		$table_subfilter->setClass('filter');

// array contains subfilters and number of items in each
		$item_params = array(
			'hosts' => array(),
			'applications' => array(),
			'types' => array(),
			'value_types' => array(),
			'status' => array(),
			'templated_items' => array(),
			'with_triggers' => array(),
			'history' => array(),
			'trends' => array(),
			'interval' => array()
		);

// generate array with values for subfilters of selected items
		foreach($items as $num => $item){
			if(zbx_empty($filter_host)){
// hosts
				$host = reset($item['hosts']);

				if(!isset($item_params['hosts'][$host['hostid']]))
					$item_params['hosts'][$host['hostid']] = array('name' => $host['host'], 'count' => 0);

				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_hosts') continue;
					$show_item &= $value;
				}
				if($show_item){
					$host = reset($item['hosts']);
					$item_params['hosts'][$host['hostid']]['count']++;
				}
			}

// applications
			foreach($item['applications'] as $appid => $app){
				if(!isset($item_params['applications'][$app['name']])){
					$item_params['applications'][$app['name']] = array('name' => $app['name'], 'count' => 0);
				}
			}
			$show_item = true;
			foreach($item['subfilters'] as $name => $value){
				if($name == 'subfilter_apps') continue;
				$show_item &= $value;
			}
			$sel_app = false;
			if($show_item){
// if any of item applications are selected
				foreach($item['applications'] as $app){
					if(str_in_array($app['name'], $subfilter_apps)){
						$sel_app = true;
						break;
					}
				}

				foreach($item['applications'] as $app){
					if(str_in_array($app['name'], $subfilter_apps) || !$sel_app){
						$item_params['applications'][$app['name']]['count']++;
					}
				}
			}

// types
			if($filter_type == -1){
				if(!isset($item_params['types'][$item['type']])){
					$item_params['types'][$item['type']] = array('name' => item_type2str($item['type']), 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_types') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['types'][$item['type']]['count']++;
				}
			}

// value types
			if($filter_value_type == -1){
				if(!isset($item_params['value_types'][$item['value_type']])){
					$item_params['value_types'][$item['value_type']] = array('name' => item_value_type2str($item['value_type']), 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_value_types') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['value_types'][$item['value_type']]['count']++;
				}
			}

// status
			if($filter_status == -1){
				if(!isset($item_params['status'][$item['status']])){
					$item_params['status'][$item['status']] = array('name' => item_status2str($item['status']), 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_status') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['status'][$item['status']]['count']++;
				}
			}

// template
			if($filter_templated_items == -1){
				if(($item['templateid'] == 0) && !isset($item_params['templated_items'][0])){
					$item_params['templated_items'][0] = array('name' => S_NOT_TEMPLATED_ITEMS, 'count' => 0);
				}
				else if(($item['templateid'] > 0) && !isset($item_params['templated_items'][1])){
					$item_params['templated_items'][1] = array('name' => S_TEMPLATED_ITEMS, 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_templated_items') continue;
					$show_item &= $value;
				}
				if($show_item){
					if($item['templateid'] == 0){
						$item_params['templated_items'][0]['count']++;
					}
					else{
						$item_params['templated_items'][1]['count']++;
					}
				}
			}

// with triggers
			if($filter_with_triggers == -1){
				if((count($item['triggers']) == 0) && !isset($item_params['with_triggers'][0])){
					$item_params['with_triggers'][0] = array('name' => S_WITHOUT_TRIGGERS, 'count' => 0);
				}
				else if((count($item['triggers']) > 0) && !isset($item_params['with_triggers'][1])){
					$item_params['with_triggers'][1] = array('name' => S_WITH_TRIGGERS, 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_with_triggers') continue;
					$show_item &= $value;
				}
				if($show_item){
					if(count($item['triggers']) == 0){
						$item_params['with_triggers'][0]['count']++;
					}
					else{
						$item_params['with_triggers'][1]['count']++;
					}
				}
			}

// trends
			if(zbx_empty($filter_trends)){
				if(!isset($item_params['trends'][$item['trends']])){
					$item_params['trends'][$item['trends']] = array('name' => $item['trends'], 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_trends') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['trends'][$item['trends']]['count']++;
				}
			}

// history
			if(zbx_empty($filter_history)){
				if(!isset($item_params['history'][$item['history']])){
					$item_params['history'][$item['history']] = array('name' => $item['history'], 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_history') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['history'][$item['history']]['count']++;
				}
			}

// interval
			if(zbx_empty($filter_delay) && ($filter_type != ITEM_TYPE_TRAPPER)){
				if(!isset($item_params['interval'][$item['delay']])){
					$item_params['interval'][$item['delay']] = array('name' => $item['delay'], 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_interval') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['interval'][$item['delay']]['count']++;
				}
			}
		}

// output
		if(zbx_empty($filter_host) && (count($item_params['hosts']) > 1)){
			$hosts_output = prepare_subfilter_output($item_params['hosts'], $subfilter_hosts, 'subfilter_hosts');
			$table_subfilter->addRow(array(S_HOSTS, $hosts_output));
		}

		if(!empty($item_params['applications']) && (count($item_params['applications']) > 1)){
			$application_output = prepare_subfilter_output($item_params['applications'], $subfilter_apps, 'subfilter_apps');
			$table_subfilter->addRow(array(S_APPLICATIONS, $application_output));
		}

		if(($filter_type == -1) && (count($item_params['types']) > 1)){
			$type_output = prepare_subfilter_output($item_params['types'], $subfilter_types, 'subfilter_types');
			$table_subfilter->addRow(array(S_TYPES, $type_output));
		}

		if(($filter_value_type == -1) && (count($item_params['value_types']) > 1)){
			$value_types_output = prepare_subfilter_output($item_params['value_types'], $subfilter_value_types, 'subfilter_value_types');
			$table_subfilter->addRow(array(S_TYPE_OF_INFORMATION, $value_types_output));
		}

		if(($filter_status == -1) && (count($item_params['status']) > 1)){
			$status_output = prepare_subfilter_output($item_params['status'], $subfilter_status, 'subfilter_status');
			$table_subfilter->addRow(array(S_STATUS, $status_output));
		}

		if(($filter_templated_items == -1) && (count($item_params['templated_items']) > 1)){
			$templated_items_output = prepare_subfilter_output($item_params['templated_items'], $subfilter_templated_items, 'subfilter_templated_items');
			$table_subfilter->addRow(array(S_TEMPLATE, $templated_items_output));
		}

		if(($filter_with_triggers == -1) && (count($item_params['with_triggers']) > 1)){
			$with_triggers_output = prepare_subfilter_output($item_params['with_triggers'], $subfilter_with_triggers, 'subfilter_with_triggers');
			$table_subfilter->addRow(array(S_WITH_TRIGGERS, $with_triggers_output));
		}

		if(zbx_empty($filter_history) && (count($item_params['history']) > 1)){
			$history_output = prepare_subfilter_output($item_params['history'], $subfilter_history, 'subfilter_history');
			$table_subfilter->addRow(array(S_HISTORY, $history_output));
		}

		if(zbx_empty($filter_trends) && (count($item_params['trends']) > 1)){
			$trends_output = prepare_subfilter_output($item_params['trends'], $subfilter_trends, 'subfilter_trends');
			$table_subfilter->addRow(array(S_TRENDS, $trends_output));
		}

		if(zbx_empty($filter_delay) && ($filter_type != ITEM_TYPE_TRAPPER) && (count($item_params['interval']) > 1)){
			$interval_output = prepare_subfilter_output($item_params['interval'], $subfilter_interval, 'subfilter_interval');
			$table_subfilter->addRow(array(S_INTERVAL, $interval_output));
		}
//} SUBFILTERS

		$form->addItem($table_subfilter);

	return $form;
	}

// Insert form for Item information
	function insert_item_form(){
		global $USER_DETAILS;

		$frmItem = new CFormTable(S_ITEM, 'items.php', 'post');
		$frmItem->setAttribute('style','visibility: hidden;');
		$frmItem->setHelp('web.items.item.php');

		$hostid			= get_request('form_hostid',		0);

		$description		= get_request('description',		'');
		$key			= get_request('key',			'');
		$host			= get_request('host',			null);
		$delay			= get_request('delay',			30);
		$history		= get_request('history',		90);
		$status			= get_request('status',			0);
		$type			= get_request('type',			0);
		$snmp_community		= get_request('snmp_community',		'public');
		$snmp_oid		= get_request('snmp_oid',		'interfaces.ifTable.ifEntry.ifInOctets.1');
		$snmp_port		= get_request('snmp_port',		161);
		$value_type		= get_request('value_type',		ITEM_VALUE_TYPE_UINT64);
		$data_type		= get_request('data_type',		ITEM_DATA_TYPE_DECIMAL);
		$trapper_hosts		= get_request('trapper_hosts',		'');
		$units			= get_request('units',			'');
		$valuemapid		= get_request('valuemapid',		0);
		$params			= get_request('params',			'');
		$multiplier		= get_request('multiplier',		0);
		$delta			= get_request('delta',			0);
		$trends			= get_request('trends',			365);
		$new_application	= get_request('new_application',	'');
		$applications		= get_request('applications',		array());
		$delay_flex		= get_request('delay_flex',		array());

		$snmpv3_securityname	= get_request('snmpv3_securityname',	'');
		$snmpv3_securitylevel	= get_request('snmpv3_securitylevel',	0);
		$snmpv3_authpassphrase	= get_request('snmpv3_authpassphrase',	'');
		$snmpv3_privpassphrase	= get_request('snmpv3_privpassphrase',	'');
		$ipmi_sensor		= get_request('ipmi_sensor',		'');
		$authtype		= get_request('authtype',		0);
		$username		= get_request('username',		'');
		$password		= get_request('password',		'');
		$publickey		= get_request('publickey',		'');
		$privatekey		= get_request('privatekey',		'');

		$formula		= get_request('formula',		'1');
		$logtimefmt		= get_request('logtimefmt',		'');

		$add_groupid		= get_request('add_groupid', 		get_request('groupid', 0));

		$limited		= null;

		$types = item_type2str();
		// http items only for internal processes
		unset($types[ITEM_TYPE_HTTPTEST]);

		if(isset($_REQUEST['itemid'])){
			$frmItem->addVar('itemid', $_REQUEST['itemid']);

			$options = array(
				'itemids' => $_REQUEST['itemid'],
				'output' => API_OUTPUT_EXTEND
			);
			$item_data = CItem::get($options);
			$item_data = reset($item_data);

			$hostid	= ($hostid > 0) ? $hostid : $item_data['hostid'];
			$limited = (($item_data['templateid'] == 0)  && ($item_data['type'] != ITEM_TYPE_HTTPTEST)) ? null : 'yes';
			$item_data['snmp_port'] = $item_data['snmp_port'] == '0' ? 161 : $item_data['snmp_port'];
		}

		if(is_null($host)){
			if($hostid > 0){
				$options = array(
					'hostids' => $hostid,
					'output' => API_OUTPUT_EXTEND,
					'templated_hosts' => 1
				);
				$host_info = CHost::get($options);
				$host_info = reset($host_info);
				$host = $host_info['host'];
			} else
				$host = S_NOT_SELECTED_SMALL;
		}

		if((isset($_REQUEST['itemid']) && !isset($_REQUEST['form_refresh'])) || isset($limited)){
			$description		= $item_data['description'];
			$key			= $item_data['key_'];
//			$host			= $item_data['host'];
			$type			= $item_data['type'];
			$snmp_community		= $item_data['snmp_community'];
			$snmp_oid		= $item_data['snmp_oid'];
			$snmp_port		= $item_data['snmp_port'];
			$value_type		= $item_data['value_type'];
			$data_type		= $item_data['data_type'];
			$trapper_hosts		= $item_data['trapper_hosts'];
			$units			= $item_data['units'];
			$valuemapid		= $item_data['valuemapid'];
			$multiplier		= $item_data['multiplier'];
			$hostid			= $item_data['hostid'];
			$params			= $item_data['params'];

			$snmpv3_securityname	= $item_data['snmpv3_securityname'];
			$snmpv3_securitylevel	= $item_data['snmpv3_securitylevel'];
			$snmpv3_authpassphrase	= $item_data['snmpv3_authpassphrase'];
			$snmpv3_privpassphrase	= $item_data['snmpv3_privpassphrase'];

			$ipmi_sensor		= $item_data['ipmi_sensor'];

			$authtype		= $item_data['authtype'];
			$username		= $item_data['username'];
			$password		= $item_data['password'];
			$publickey		= $item_data['publickey'];
			$privatekey		= $item_data['privatekey'];

			$formula		= $item_data['formula'];
			$logtimefmt		= $item_data['logtimefmt'];

			$new_application	= get_request('new_application',	'');

			if(!isset($limited) || !isset($_REQUEST['form_refresh'])){
				$delay		= $item_data['delay'];
				$history	= $item_data['history'];
				$status		= $item_data['status'];
				$delta		= $item_data['delta'];
				$trends		= $item_data['trends'];
				$db_delay_flex	= $item_data['delay_flex'];

				if(isset($db_delay_flex)){
					$arr_of_dellays = explode(';',$db_delay_flex);
					foreach($arr_of_dellays as $one_db_delay){
						$arr_of_delay = explode('/',$one_db_delay);
						if(!isset($arr_of_delay[0]) || !isset($arr_of_delay[1])) continue;

						array_push($delay_flex, array('delay'=> $arr_of_delay[0], 'period'=> $arr_of_delay[1]));
					}
				}

				$applications = array_unique(zbx_array_merge($applications, get_applications_by_itemid($_REQUEST['itemid'])));
			}
		}

		$securityLevelVisibility = array();
		$valueTypeVisibility = array();
		$authTypeVisibility = array();
		$typeVisibility = array();
		$delay_flex_el = array();


		//if($type != ITEM_TYPE_TRAPPER){
			$i = 0;
			foreach($delay_flex as $val){
				if(!isset($val['delay']) && !isset($val['period'])) continue;

				array_push($delay_flex_el,
					array(
						new CCheckBox('rem_delay_flex['.$i.']', 'no', null,$i),
						$val['delay'],
						SPACE.S_SEC_AT.SPACE,
						$val['period']),
					BR());
				$frmItem->addVar('delay_flex['.$i.'][delay]', $val['delay']);
				$frmItem->addVar('delay_flex['.$i.'][period]', $val['period']);
				foreach($types as $it => $caption) {
					if($it == ITEM_TYPE_TRAPPER || $it == ITEM_TYPE_ZABBIX_ACTIVE) continue;
					zbx_subarray_push($typeVisibility, $it, 'delay_flex['.$i.'][delay]');
					zbx_subarray_push($typeVisibility, $it, 'delay_flex['.$i.'][period]');
					zbx_subarray_push($typeVisibility, $it, 'rem_delay_flex['.$i.']');
				}
				$i++;
				if($i >= 7) break;	/* limit count of intervals
							 * 7 intervals by 30 symbols = 210 characters
							 * db storage field is 256
							 */
			}
		//}

		array_push($delay_flex_el, count($delay_flex_el)==0 ? S_NO_FLEXIBLE_INTERVALS : new CButton('del_delay_flex',S_DELETE_SELECTED));

		if(count($applications)==0) array_push($applications, 0);

		if(isset($_REQUEST['itemid'])){
			$caption = array();
			$itemid = $_REQUEST['itemid'];
			do{
				$sql = 'SELECT i.itemid, i.templateid, h.host'.
						' FROM items i, hosts h'.
						' WHERE i.itemid='.$itemid.
							' AND h.hostid=i.hostid';
				$itemFromDb = DBfetch(DBselect($sql));
				if($itemFromDb){
					if($_REQUEST['itemid'] == $itemid){
						$caption[] = SPACE;
						$caption[] = $itemFromDb['host'];
					}
					else{
						$caption[] = ' : ';
						$caption[] = new CLink($itemFromDb['host'], 'items.php?form=update&itemid='.$itemFromDb['itemid'], 'highlight underline');
					}

					$itemid = $itemFromDb['templateid'];
				}
				else break;
			}while($itemid != 0);

			$caption[] = S_ITEM.' "';
			$caption = array_reverse($caption);
			$caption[] = ': ';
			$caption[] = $item_data['description'];
			$caption[] = '"';
			$frmItem->setTitle($caption);
		}
		else
			$frmItem->setTitle(S_ITEM." $host : $description");

		$frmItem->addVar('form_hostid', $hostid);
		$frmItem->addRow(S_HOST,array(
			new CTextBox('host',$host,32,true),
			new CButton('btn_host', S_SELECT,
				"return PopUp('popup.php?dstfrm=".$frmItem->getName().
				"&dstfld1=host&dstfld2=form_hostid&srctbl=hosts_and_templates&srcfld1=host&srcfld2=hostid&noempty=1',450,450);",
				'H')
			));

		$frmItem->addRow(S_DESCRIPTION, new CTextBox('description',$description,40, $limited));

		if(isset($limited)){
			$frmItem->addRow(S_TYPE,  new CTextBox('typename', item_type2str($type), 40, 'yes'));
			$frmItem->addVar('type', $type);
		}
		else{
			$cmbType = new CComboBox('type',$type);
			$cmbType->addItems($types);
			$frmItem->addRow(S_TYPE, $cmbType);
		}

		$row = new CRow(array(new CCol(S_SNMP_OID,'form_row_l'), new CCol(new CTextBox('snmp_oid',$snmp_oid,40,$limited), 'form_row_r')));
		$row->setAttribute('id', 'row_snmp_oid');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'snmp_oid');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'snmp_oid');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'snmp_oid');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'row_snmp_oid');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'row_snmp_oid');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_snmp_oid');

		$row = new CRow(array(new CCol(S_SNMP_COMMUNITY,'form_row_l'), new CCol(new CTextBox('snmp_community',$snmp_community,16), 'form_row_r')));
		$row->setAttribute('id', 'row_snmp_community');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'snmp_community');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'snmp_community');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'row_snmp_community');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'row_snmp_community');

		$row = new CRow(array(new CCol(S_SNMPV3_SECURITY_NAME,'form_row_l'), new CCol(new CTextBox('snmpv3_securityname',$snmpv3_securityname,64), 'form_row_r')));
		$row->setAttribute('id', 'row_snmpv3_securityname');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'snmpv3_securityname');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_snmpv3_securityname');

		$cmbSecLevel = new CComboBox('snmpv3_securitylevel', $snmpv3_securitylevel);
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,'noAuthNoPriv');
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,'authNoPriv');
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,'authPriv');

		$row = new CRow(array(new CCol(S_SNMPV3_SECURITY_LEVEL,'form_row_l'), new CCol($cmbSecLevel, 'form_row_r')));
		$row->setAttribute('id', 'row_snmpv3_securitylevel');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'snmpv3_securitylevel');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_snmpv3_securitylevel');

		$row = new CRow(array(new CCol(S_SNMPV3_AUTH_PASSPHRASE,'form_row_l'), new CCol(new CTextBox('snmpv3_authpassphrase',$snmpv3_authpassphrase,64), 'form_row_r')));
		$row->setAttribute('id', 'row_snmpv3_authpassphrase');
		$frmItem->addRow($row);
		zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'snmpv3_authpassphrase');
		zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'row_snmpv3_authpassphrase');

		$row = new CRow(array(new CCol(S_SNMPV3_PRIV_PASSPHRASE,'form_row_l'), new CCol(new CTextBox('snmpv3_privpassphrase',$snmpv3_privpassphrase,64), 'form_row_r')));
		$row->setAttribute('id', 'row_snmpv3_privpassphrase');
		$frmItem->addRow($row);
		zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_privpassphrase');
		zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_privpassphrase');
		zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_authpassphrase');
		zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_authpassphrase');

		$row = new CRow(array(new CCol(S_SNMP_PORT,'form_row_l'), new CCol(new CNumericBox('snmp_port',$snmp_port,5), 'form_row_r')));
		$row->setAttribute('id', 'row_snmp_port');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'snmp_port');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'snmp_port');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'snmp_port');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'row_snmp_port');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'row_snmp_port');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_snmp_port');


		$row = new CRow(array(new CCol(S_IPMI_SENSOR,'form_row_l'), new CCol(new CTextBox('ipmi_sensor', $ipmi_sensor, 64, $limited),'form_row_r')));
		$row->setAttribute('id', 'row_ipmi_sensor');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_IPMI, 'ipmi_sensor');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_IPMI, 'row_ipmi_sensor');

		if(isset($limited))
			$btnSelect = null;
		else
			$btnSelect = new CButton('btn1', S_SELECT,
				"return PopUp('popup.php?dstfrm=".$frmItem->getName()."&dstfld1=key&srctbl=help_items&srcfld1=key_&itemtype='+$('type')[$('type').selectedIndex].value);",
				'T');

		$frmItem->addRow(S_KEY, array(new CTextBox('key',$key,40,$limited), $btnSelect));
		foreach($types as $it => $ilabel) {
			switch($it){
				case ITEM_TYPE_DB_MONITOR:
					zbx_subarray_push($typeVisibility, $it, array('id'=>'key','defaultValue'=> 'db.odbc.select[<unique short description>]'));
					zbx_subarray_push($typeVisibility, $it, array('id'=>'params_dbmonitor','defaultValue'=> "DSN=<database source name>\nuser=<user name>\npassword=<password>\nsql=<query>"));
				break;
				case ITEM_TYPE_SSH:
					zbx_subarray_push($typeVisibility, $it, array('id'=>'key','defaultValue'=> 'ssh.run[<unique short description>,<ip>,<port>,<encoding>]'));
				break;
				case ITEM_TYPE_TELNET:
					zbx_subarray_push($typeVisibility, $it, array('id'=>'key', 'defaultValue'=> 'telnet.run[<unique short description>,<ip>,<port>,<encoding>]'));
				break;
				default:
					zbx_subarray_push($typeVisibility, $it, array('id'=>'key', 'defaultValue'=> ''));
			}
		}

/*
ITEM_TYPE_DB_MONITOR $key = 'db.odbc.select[<unique short description>]'; $params = "DSN=<database source name>\nuser=<user name>\npassword=<password>\nsql=<query>";
ITEM_TYPE_SSH $key = 'ssh.run[<unique short description>,<ip>,<port>,<encoding>]'; $params = '';
ITEM_TYPE_TELNET $key = 'telnet.run[<unique short description>,<ip>,<port>,<encoding>]'; $params = '';
ITEM_TYPE_CALCULATED $key = ''; $params = '';
//*/

		$cmbAuthType = new CComboBox('authtype', $authtype);
		$cmbAuthType->addItem(ITEM_AUTHTYPE_PASSWORD,S_PASSWORD);
		$cmbAuthType->addItem(ITEM_AUTHTYPE_PUBLICKEY,S_PUBLIC_KEY);

		$row = new CRow(array(new CCol(S_AUTHENTICATION_METHOD,'form_row_l'), new CCol($cmbAuthType,'form_row_r')));
		$row->setAttribute('id', 'row_authtype');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'authtype');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'row_authtype');

		$row = new CRow(array(new CCol(S_USER_NAME,'form_row_l'), new CCol(new CTextBox('username',$username,16),'form_row_r')));
		$row->setAttribute('id', 'row_username');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'username');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'row_username');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'username');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'row_username');

		$row = new CRow(array(new CCol(S_PUBLIC_KEY_FILE,'form_row_l'), new CCol(new CTextBox('publickey',$publickey,16),'form_row_r')));
		$row->setAttribute('id', 'row_publickey');
		$frmItem->addRow($row);
		zbx_subarray_push($authTypeVisibility, ITEM_AUTHTYPE_PUBLICKEY, 'publickey');
		zbx_subarray_push($authTypeVisibility, ITEM_AUTHTYPE_PUBLICKEY, 'row_publickey');

		$row = new CRow(array(new CCol(S_PRIVATE_KEY_FILE,'form_row_l'), new CCol(new CTextBox('privatekey',$privatekey,16),'form_row_r')));
		$row->setAttribute('id', 'row_privatekey');
		$frmItem->addRow($row);
		zbx_subarray_push($authTypeVisibility, ITEM_AUTHTYPE_PUBLICKEY, 'privatekey');
		zbx_subarray_push($authTypeVisibility, ITEM_AUTHTYPE_PUBLICKEY, 'row_privatekey');

		$row = new CRow(array(new CCol(S_PASSWORD,'form_row_l'), new CCol(new CTextBox('password',$password,16),'form_row_r')));
		$row->setAttribute('id', 'row_password');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'password');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'row_password');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'password');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'row_password');

		$spanEC = new CSpan(S_EXECUTED_SCRIPT);
		$spanEC->setAttribute('id', 'label_executed_script');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'label_executed_script');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'label_executed_script');

		$spanP = new CSpan(S_PARAMS);
		$spanP->setAttribute('id', 'label_params');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_DB_MONITOR, 'label_params');

		$spanF = new CSpan(S_FORMULA);
		$spanF->setAttribute('id', 'label_formula');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_CALCULATED, 'label_formula');


		$params_script = new CTextArea('params', $params, 60, 4);
		$params_script->setAttribute('id', 'params_script');
		$params_dbmonitor = new CTextArea('params', $params, 60, 4);
		$params_dbmonitor->setAttribute('id', 'params_dbmonitor');
		$params_calculted = new CTextArea('params', $params, 60, 4);
		$params_calculted->setAttribute('id', 'params_calculted');

		$row = new CRow(array(
			new CCol(array($spanEC, $spanP, $spanF),'form_row_l'),
			new CCol(array($params_script, $params_dbmonitor, $params_calculted),'form_row_r')
		));
		$row->setAttribute('id', 'row_params');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'params_script');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'row_params');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'params_script');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'row_params');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_DB_MONITOR, 'params_dbmonitor');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_DB_MONITOR, 'row_params');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_CALCULATED, 'params_calculted');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_CALCULATED, 'row_params');

/*
ITEM_TYPE_DB_MONITOR $key = 'db.odbc.select[<unique short description>]'; $params = "DSN=<database source name>\nuser=<user name>\npassword=<password>\nsql=<query>";
ITEM_TYPE_SSH $key = 'ssh.run[<unique short description>,<ip>,<port>,<encoding>]'; $params = '';
ITEM_TYPE_TELNET $key = 'telnet.run[<unique short description>,<ip>,<port>,<encoding>]'; $params = '';
ITEM_TYPE_CALCULATED $key = ''; $params = '';
//*/

		if(isset($limited)){
			$frmItem->addVar('value_type', $value_type);
			$cmbValType = new CTextBox('value_type_name', item_value_type2str($value_type), 40, 'yes');
		}
		else {
			$cmbValType = new CComboBox('value_type',$value_type);
			$cmbValType->addItem(ITEM_VALUE_TYPE_UINT64,	S_NUMERIC_UNSIGNED);
			$cmbValType->addItem(ITEM_VALUE_TYPE_FLOAT,	S_NUMERIC_FLOAT);
			$cmbValType->addItem(ITEM_VALUE_TYPE_STR, 	S_CHARACTER);
			$cmbValType->addItem(ITEM_VALUE_TYPE_LOG, 	S_LOG);
			$cmbValType->addItem(ITEM_VALUE_TYPE_TEXT,	S_TEXT);
		}

		$frmItem->addRow(S_TYPE_OF_INFORMATION,$cmbValType);

		if(isset($limited)) {
			$frmItem->addVar('data_type', $data_type);
			$cmbDataType = new CTextBox('data_type_name', item_data_type2str($data_type), 20, 'yes');
		}
		else {
			$cmbDataType = new CComboBox('data_type', $data_type);
			$cmbDataType->addItem(ITEM_DATA_TYPE_DECIMAL,		item_data_type2str(ITEM_DATA_TYPE_DECIMAL));
			$cmbDataType->addItem(ITEM_DATA_TYPE_OCTAL,		item_data_type2str(ITEM_DATA_TYPE_OCTAL));
			$cmbDataType->addItem(ITEM_DATA_TYPE_HEXADECIMAL, 	item_data_type2str(ITEM_DATA_TYPE_HEXADECIMAL));
		}

		$row = new CRow(array(new CCol(S_DATA_TYPE,'form_row_l'), new CCol($cmbDataType,'form_row_r')));
		$row->setAttribute('id', 'row_data_type');
		$frmItem->addRow($row);
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'data_type');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_data_type');

		$row = new CRow(array(new CCol(S_UNITS,'form_row_l'), new CCol(new CTextBox('units',$units,40, $limited),'form_row_r')));
		$row->setAttribute('id', 'row_units');
		$frmItem->addRow($row);
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'units');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'row_units');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'units');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_units');

		$mltpbox = Array();
		if (isset($limited)) {
			$frmItem->addVar('multiplier', $multiplier);

			$mcb = new CCheckBox('multiplier', $multiplier == 1 ? 'yes':'no');
			$mcb->setAttribute('disabled', 'disabled');
			$mltpbox[] = $mcb;

			$mltpbox[] = SPACE;
			$ctb = new CTextBox('formula', $formula, 10, 1);
			$ctb->setAttribute('style', 'text-align: right;');
			$mltpbox[] = $ctb;
		}
		else {
			$mltpbox[] = new CCheckBox('multiplier',$multiplier == 1 ? 'yes':'no', 'var editbx = document.getElementById(\'formula\'); if(editbx) editbx.readOnly = !this.checked;', 1);
			$mltpbox[] = SPACE;
			$ctb = new CTextBox('formula', $formula, 10);
			$ctb->setAttribute('style', 'text-align: right;');
			$mltpbox[] = $ctb;
		}


		$row = new CRow(array(new CCol(S_USE_CUSTOM_MULTIPLIER,'form_row_l'), new CCol($mltpbox,'form_row_r')));
		$row->setAttribute('id', 'row_multiplier');
		$frmItem->addRow($row);
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'multiplier');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'row_multiplier');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'multiplier');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_multiplier');


		$row = new CRow(array(new CCol(S_UPDATE_INTERVAL_IN_SEC,'form_row_l'), new CCol(new CNumericBox('delay',$delay,5),'form_row_r')));
		$row->setAttribute('id', 'row_delay');
		$frmItem->addRow($row);
		foreach($types as $it => $ilabel) {
			if($it == ITEM_TYPE_TRAPPER) continue;
			zbx_subarray_push($typeVisibility, $it, 'delay');
			zbx_subarray_push($typeVisibility, $it, 'row_delay');
		}

		$row = new CRow(array(new CCol(S_FLEXIBLE_INTERVALS,'form_row_l'), new CCol($delay_flex_el,'form_row_r')));
		$row->setAttribute('id', 'row_flex_intervals');
		$frmItem->addRow($row);

		$row = new CRow(array(new CCol(S_NEW_FLEXIBLE_INTERVAL,'form_row_l'), new CCol(
					array(
						S_DELAY, SPACE,
						new CNumericBox('new_delay_flex[delay]','50',5),
						S_PERIOD, SPACE,
						new CTextBox('new_delay_flex[period]','1-7,00:00-23:59',27), BR(),
						new CButton('add_delay_flex',S_ADD)
					),'form_row_r')), 'new');
		$row->setAttribute('id', 'row_new_delay_flex');
		$frmItem->addRow($row);

		foreach($types as $it => $ilabel){
			if($it == ITEM_TYPE_TRAPPER || $it == ITEM_TYPE_ZABBIX_ACTIVE) continue;
			zbx_subarray_push($typeVisibility, $it, 'row_flex_intervals');
			zbx_subarray_push($typeVisibility, $it, 'row_new_delay_flex');
			zbx_subarray_push($typeVisibility, $it, 'new_delay_flex[delay]');
			zbx_subarray_push($typeVisibility, $it, 'new_delay_flex[period]');
			zbx_subarray_push($typeVisibility, $it, 'add_delay_flex');
		}

		$frmItem->addRow(S_KEEP_HISTORY_IN_DAYS, array(
			new CNumericBox('history',$history,8),
			(!isset($_REQUEST['itemid'])) ? null :
				new CButtonQMessage('del_history',S_CLEAR_HISTORY,S_HISTORY_CLEARING_CAN_TAKE_A_LONG_TIME_CONTINUE_Q)
			));

		$row = new CRow(array(new CCol(S_KEEP_TRENDS_IN_DAYS,'form_row_l'), new CCol(new CNumericBox('trends',$trends,8),'form_row_r')));
		$row->setAttribute('id', 'row_trends');
		$frmItem->addRow($row);
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'trends');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'row_trends');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'trends');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_trends');

		$cmbStatus = new CComboBox('status',$status);
		foreach(array(ITEM_STATUS_ACTIVE,ITEM_STATUS_DISABLED,ITEM_STATUS_NOTSUPPORTED) as $st)
			$cmbStatus->addItem($st, item_status2str($st));
		$frmItem->addRow(S_STATUS, $cmbStatus);

		$row = new CRow(array(new CCol(S_LOG_TIME_FORMAT,'form_row_l'), new CCol(new CTextBox('logtimefmt',$logtimefmt,16,$limited),'form_row_r')));
		$row->setAttribute('id', 'row_logtimefmt');
		$frmItem->addRow($row);
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_LOG, 'logtimefmt');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_LOG, 'row_logtimefmt');

		$deltaOptions = array(
			0 => S_AS_IS,
			1 => S_DELTA_SPEED_PER_SECOND,
			2 => S_DELTA_SIMPLE_CHANGE
		);
		// delta row
		if ($limited) {
			$frmItem->addVar('delta', $delta);
			$cmbDelta = new CTextBox('delta_name', $deltaOptions[$delta], null, 'yes');
		}
		else {
			$cmbDelta= new CComboBox('delta', $delta);
			$cmbDelta->addItems($deltaOptions);
		}
		$row = new CRow(array(new CCol(S_STORE_VALUE,'form_row_l'), new CCol($cmbDelta,'form_row_r')));
		$row->setAttribute('id', 'row_delta');
		$frmItem->addRow($row);
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'delta');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'row_delta');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'delta');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_delta');

		if(isset($limited)){
			$frmItem->addVar('valuemapid', $valuemapid);
			$map_name = S_AS_IS;
			if($map_data = DBfetch(DBselect('SELECT name FROM valuemaps WHERE valuemapid='.$valuemapid))){
				$map_name = $map_data['name'];
			}
			$cmbMap = new CTextBox('valuemap_name', $map_name, 20, 'yes');
		}
		else {
			$cmbMap = new CComboBox('valuemapid',$valuemapid);
			$cmbMap->addItem(0,S_AS_IS);
			$db_valuemaps = DBselect('SELECT * FROM valuemaps WHERE '.DBin_node('valuemapid'));
			while($db_valuemap = DBfetch($db_valuemaps))
				$cmbMap->addItem(
					$db_valuemap['valuemapid'],
					get_node_name_by_elid($db_valuemap['valuemapid'], null, ': ').$db_valuemap['name']
					);
		}

		$link = new CLink(S_SHOW_VALUE_MAPPINGS,'config.php?config=6');
		$link->setAttribute('target','_blank');

		$row = new CRow(array(new CCol(S_SHOW_VALUE), new CCol(array($cmbMap, SPACE, $link))));
		$row->setAttribute('id', 'row_valuemap');
		$frmItem->addRow($row);
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'valuemapid');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'row_valuemap');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'valuemap_name');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'valuemapid');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_valuemap');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'valuemap_name');

		$row = new CRow(array(new CCol(S_ALLOWED_HOSTS,'form_row_l'), new CCol(new CTextBox('trapper_hosts',$trapper_hosts,40),'form_row_r')));
		$row->setAttribute('id', 'row_trapper_hosts');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TRAPPER, 'trapper_hosts');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TRAPPER, 'row_trapper_hosts');


		$new_app = new CTextBox('new_application',$new_application,40);
		$frmItem->addRow(S_NEW_APPLICATION,$new_app,'new');

		$cmbApps = new CListBox('applications[]',$applications,6);
		$cmbApps->addItem(0,'-'.S_NONE.'-');

		$sql = 'SELECT DISTINCT applicationid,name '.
				' FROM applications '.
				' WHERE hostid='.$hostid.
				' ORDER BY name';
		$db_applications = DBselect($sql);
		while($db_app = DBfetch($db_applications)){
			$cmbApps->addItem($db_app['applicationid'],$db_app['name']);
		}
		$frmItem->addRow(S_APPLICATIONS,$cmbApps);

		$frmRow = array(new CButton('save',S_SAVE));
		if(isset($_REQUEST['itemid'])){
			array_push($frmRow,
				SPACE,
				new CButton('clone',S_CLONE));

			if(!isset($limited)){
				array_push($frmRow,
					SPACE,
					new CButtonDelete(S_DELETE_SELECTED_ITEM_Q,
						url_param('form').url_param('groupid').url_param('itemid'))
				);
			}
		}
		array_push($frmRow,
			SPACE,
			new CButtonCancel(url_param('groupid')));

		$frmItem->addSpanRow($frmRow,'form_row_last');

// GROUP OPERATIONS
		$cmbGroups = new CComboBox('add_groupid',$add_groupid);
		$groups = CHostGroup::get(array(
			'editable' => 1,
			'output' => API_OUTPUT_EXTEND,
		));
		order_result($groups, 'name');
		foreach($groups as $group){
			$cmbGroups->addItem($group['groupid'], get_node_name_by_elid($group['groupid'], null, ': ').$group['name']);
		}
		$frmItem->addRow(S_GROUP,$cmbGroups);

		$cmbAction = new CComboBox('action');
		$cmbAction->addItem('add to group',S_ADD_TO_GROUP);
		if(isset($_REQUEST['itemid'])){
			$cmbAction->addItem('update in group',S_UPDATE_IN_GROUP);
			$cmbAction->addItem('delete from group',S_DELETE_FROM_GROUP);
		}
		$frmItem->addItemToBottomRow(array($cmbAction, SPACE, new CButton('register',S_DO)));

		$json = new CJSON();

		zbx_add_post_js("var valueTypeSwitcher = new CViewSwitcher('value_type', 'change', ".zbx_jsvalue($valueTypeVisibility, true).");");
		zbx_add_post_js("var authTypeSwitcher = new CViewSwitcher('authtype', 'change', ".zbx_jsvalue($authTypeVisibility, true).");");
		zbx_add_post_js("var typeSwitcher = new CViewSwitcher('type', 'change', ".zbx_jsvalue($typeVisibility, true).(isset($_REQUEST['itemid'])? ', true': '').');');
		zbx_add_post_js("var securityLevelSwitcher = new CViewSwitcher('snmpv3_securitylevel', 'change', ".zbx_jsvalue($securityLevelVisibility, true).");");
		zbx_add_post_js("var multpStat = document.getElementById('multiplier'); if(multpStat && multpStat.onclick) multpStat.onclick();");
		zbx_add_post_js("var mnFrmTbl = document.getElementById('web.items.item.php'); if(mnFrmTbl) mnFrmTbl.style.visibility = 'visible';");

		return $frmItem;
	}

	function insert_mass_update_item_form(){
		global $USER_DETAILS;

		$frmItem = new CFormTable(S_ITEM,null,'post');
		$frmItem->setHelp('web.items.item.php');
		$frmItem->setTitle(S_MASS_UPDATE);

		$frmItem->addVar('massupdate',1);

		$frmItem->addVar('group_itemid',get_request('group_itemid',array()));
		$frmItem->addVar('config',get_request('config',0));

		$delay		= get_request('delay'		,30);
		$history	= get_request('history'		,90);
		$status		= get_request('status'		,0);
		$type		= get_request('type'		,0);
		$snmp_community	= get_request('snmp_community'	,'public');
		$snmp_port	= get_request('snmp_port'	,161);
		$value_type	= get_request('value_type'	,ITEM_VALUE_TYPE_UINT64);
		$data_type	= get_request('data_type'	,ITEM_DATA_TYPE_DECIMAL);
		$trapper_hosts	= get_request('trapper_hosts'	,'');
		$units		= get_request('units'		,'');
		$authtype = get_request('authtype', '');
		$username = get_request('username', '');
		$password = get_request('password', '');
		$publickey = get_request('publickey', '');
		$privatekey = get_request('privatekey', '');
		$valuemapid	= get_request('valuemapid'	,0);
		$delta		= get_request('delta'		,0);
		$trends		= get_request('trends'		,365);
		$applications	= get_request('applications'	,array());
		$delay_flex	= get_request('delay_flex'	,array());

		$snmpv3_securityname	= get_request('snmpv3_securityname'	,'');
		$snmpv3_securitylevel	= get_request('snmpv3_securitylevel'	,0);
		$snmpv3_authpassphrase	= get_request('snmpv3_authpassphrase'	,'');
		$snmpv3_privpassphrase	= get_request('snmpv3_privpassphrase'	,'');

		$formula	= get_request('formula'		,'1');
		$logtimefmt	= get_request('logtimefmt'	,'');

		$delay_flex_el = array();

		$i = 0;
		foreach($delay_flex as $val){
			if(!isset($val['delay']) && !isset($val['period'])) continue;

			array_push($delay_flex_el,
				array(
					new CCheckBox('rem_delay_flex[]', 'no', null,$i),
						$val['delay'],
						SPACE.S_SEC_AT.SPACE,
						$val['period']
				),
				BR());
			$frmItem->addVar("delay_flex[".$i."][delay]", $val['delay']);
			$frmItem->addVar("delay_flex[".$i."][period]", $val['period']);
			$i++;
			if($i >= 7) break; /* limit count of  intervals
					    * 7 intervals by 30 symbols = 210 characters
					    * db storage field is 256
					    */
		}

		if(count($delay_flex_el)==0)
			array_push($delay_flex_el, S_NO_FLEXIBLE_INTERVALS);
		else
			array_push($delay_flex_el, new CButton('del_delay_flex',S_DELETE_SELECTED));

		if(count($applications)==0)  array_push($applications,0);

		$itemTypes = item_type2str();
// http items only for internal processes
		unset($itemTypes[ITEM_TYPE_HTTPTEST]);
		$cmbType = new CComboBox('type',$type);
		$cmbType->addItems($itemTypes);

		$frmItem->addRow(array( new CVisibilityBox('type_visible', get_request('type_visible'), 'type', S_ORIGINAL),
			S_TYPE), $cmbType);

		$frmItem->addRow(array( new CVisibilityBox('community_visible', get_request('community_visible'), 'snmp_community', S_ORIGINAL),
			S_SNMP_COMMUNITY), new CTextBox('snmp_community',$snmp_community,16));

		$frmItem->addRow(array( new CVisibilityBox('securityname_visible', get_request('securityname_visible'), 'snmpv3_securityname',
			S_ORIGINAL), S_SNMPV3_SECURITY_NAME), new CTextBox('snmpv3_securityname',$snmpv3_securityname,64));

		$cmbSecLevel = new CComboBox('snmpv3_securitylevel',$snmpv3_securitylevel);
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,"noAuthNoPriv");
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,"authNoPriv");
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,"authPriv");
		$frmItem->addRow(array( new CVisibilityBox('securitylevel_visible',  get_request('securitylevel_visible'), 'snmpv3_securitylevel',
			S_ORIGINAL), S_SNMPV3_SECURITY_LEVEL), $cmbSecLevel);
		$frmItem->addRow(array( new CVisibilityBox('authpassphrase_visible', get_request('authpassphrase_visible'),
			'snmpv3_authpassphrase', S_ORIGINAL), S_SNMPV3_AUTH_PASSPHRASE),
			new CTextBox('snmpv3_authpassphrase',$snmpv3_authpassphrase,64));

		$frmItem->addRow(array( new CVisibilityBox('privpassphras_visible', get_request('privpassphras_visible'), 'snmpv3_privpassphrase',
			S_ORIGINAL), S_SNMPV3_PRIV_PASSPHRASE), new CTextBox('snmpv3_privpassphrase',$snmpv3_privpassphrase,64));

		$frmItem->addRow(array( new CVisibilityBox('port_visible', get_request('port_visible'), 'snmp_port', S_ORIGINAL), S_SNMP_PORT),
			new CNumericBox('snmp_port',$snmp_port,5));

		$cmbValType = new CComboBox('value_type',$value_type);
		$cmbValType->addItem(ITEM_VALUE_TYPE_UINT64,	S_NUMERIC_UNSIGNED);		$cmbValType->addItem(ITEM_VALUE_TYPE_FLOAT,	S_NUMERIC_FLOAT);		$cmbValType->addItem(ITEM_VALUE_TYPE_STR, 	S_CHARACTER);		$cmbValType->addItem(ITEM_VALUE_TYPE_LOG, 	S_LOG);		$cmbValType->addItem(ITEM_VALUE_TYPE_TEXT,	S_TEXT);		$frmItem->addRow(array( new CVisibilityBox('value_type_visible', get_request('value_type_visible'), 'value_type', S_ORIGINAL),			S_TYPE_OF_INFORMATION), $cmbValType);

		$cmbDataType = new CComboBox('data_type',$data_type);
		$cmbDataType->addItem(ITEM_DATA_TYPE_DECIMAL,		item_data_type2str(ITEM_DATA_TYPE_DECIMAL));
		$cmbDataType->addItem(ITEM_DATA_TYPE_OCTAL,		item_data_type2str(ITEM_DATA_TYPE_OCTAL));
		$cmbDataType->addItem(ITEM_DATA_TYPE_HEXADECIMAL, 	item_data_type2str(ITEM_DATA_TYPE_HEXADECIMAL));
		$frmItem->addRow(array( new CVisibilityBox('data_type_visible', get_request('data_type_visible'), 'data_type', S_ORIGINAL),
			S_DATA_TYPE), $cmbDataType);

		$frmItem->addRow(array( new CVisibilityBox('units_visible', get_request('units_visible'), 'units', S_ORIGINAL), S_UNITS),
			new CTextBox('units',$units,40));


		$cmbAuthType = new CComboBox('authtype', $authtype);
		$cmbAuthType->addItem(ITEM_AUTHTYPE_PASSWORD, S_PASSWORD);
		$cmbAuthType->addItem(ITEM_AUTHTYPE_PUBLICKEY, S_PUBLIC_KEY);
		$frmItem->addRow(
			array(new CVisibilityBox('authtype_visible', get_request('authtype_visible'), 'authtype', S_ORIGINAL), S_AUTHENTICATION_METHOD),
			$cmbAuthType
		);
		$frmItem->addRow(
			array(new CVisibilityBox('username_visible', get_request('username_visible'), 'username', S_ORIGINAL), S_USER_NAME),
			new CTextBox('username', $username, 40)
		);
		$frmItem->addRow(
			array(new CVisibilityBox('publickey_visible', get_request('publickey_visible'), 'publickey', S_ORIGINAL), S_PUBLIC_KEY_FILE),
			new CTextBox('publickey', $publickey, 40)
		);
		$frmItem->addRow(
			array(new CVisibilityBox('privatekey_visible', get_request('privatekey_visible'), 'privatekey', S_ORIGINAL), S_PRIVATE_KEY_FILE),
			new CTextBox('privatekey', $privatekey, 40)
		);
		$frmItem->addRow(
			array(new CVisibilityBox('password_visible', get_request('password_visible'), 'password', S_ORIGINAL), S_PASSWORD),
			new CTextBox('password', $password, 40)
		);

		$frmItem->addRow(array( new CVisibilityBox('formula_visible', get_request('formula_visible'), 'formula', S_ORIGINAL),
			S_CUSTOM_MULTIPLIER.' (0 - '.S_DISABLED.')'), new CTextBox('formula',$formula,40));

		$frmItem->addRow(array( new CVisibilityBox('delay_visible', get_request('delay_visible'), 'delay', S_ORIGINAL),
			S_UPDATE_INTERVAL_IN_SEC), new CNumericBox('delay',$delay,5));

		$delay_flex_el = new CSpan($delay_flex_el);
		$delay_flex_el->setAttribute('id', 'delay_flex_list');

		$frmItem->addRow(array(
						new CVisibilityBox('delay_flex_visible',
								get_request('delay_flex_visible'),
								array('delay_flex_list', 'new_delay_flex_el'),
								S_ORIGINAL),
						S_FLEXIBLE_INTERVALS), $delay_flex_el);

		$new_delay_flex_el = new CSpan(array(
										S_DELAY, SPACE,
										new CNumericBox("new_delay_flex[delay]","50",5),
										S_PERIOD, SPACE,
										new CTextBox("new_delay_flex[period]","1-7,00:00-23:59",27), BR(),
										new CButton("add_delay_flex",S_ADD)
									));
		$new_delay_flex_el->setAttribute('id', 'new_delay_flex_el');

		$frmItem->addRow(S_NEW_FLEXIBLE_INTERVAL, $new_delay_flex_el, 'new');

		$frmItem->addRow(array( new CVisibilityBox('history_visible', get_request('history_visible'), 'history', S_ORIGINAL),
			S_KEEP_HISTORY_IN_DAYS), new CNumericBox('history',$history,8));
		$frmItem->addRow(array( new CVisibilityBox('trends_visible', get_request('trends_visible'), 'trends', S_ORIGINAL),
			S_KEEP_TRENDS_IN_DAYS), new CNumericBox('trends',$trends,8));

		$cmbStatus = new CComboBox('status',$status);
		foreach(array(ITEM_STATUS_ACTIVE,ITEM_STATUS_DISABLED,ITEM_STATUS_NOTSUPPORTED) as $st)
			$cmbStatus->addItem($st,item_status2str($st));
		$frmItem->addRow(array( new CVisibilityBox('status_visible', get_request('status_visible'), 'status', S_ORIGINAL), S_STATUS),
			$cmbStatus);

		$frmItem->addRow(array( new CVisibilityBox('logtimefmt_visible', get_request('logtimefmt_visible'), 'logtimefmt', S_ORIGINAL),
			S_LOG_TIME_FORMAT), new CTextBox("logtimefmt",$logtimefmt,16));

		$cmbDelta= new CComboBox('delta',$delta);
		$cmbDelta->addItem(0,S_AS_IS);
		$cmbDelta->addItem(1,S_DELTA_SPEED_PER_SECOND);
		$cmbDelta->addItem(2,S_DELTA_SIMPLE_CHANGE);
		$frmItem->addRow(array( new CVisibilityBox('delta_visible', get_request('delta_visible'), 'delta', S_ORIGINAL),
			S_STORE_VALUE),$cmbDelta);

		$cmbMap = new CComboBox('valuemapid',$valuemapid);
		$cmbMap->addItem(0,S_AS_IS);
		$db_valuemaps = DBselect('SELECT * FROM valuemaps WHERE '.DBin_node('valuemapid'));
		while($db_valuemap = DBfetch($db_valuemaps))
			$cmbMap->addItem(
					$db_valuemap["valuemapid"],
					get_node_name_by_elid($db_valuemap["valuemapid"], null, ': ').$db_valuemap["name"]
					);

		$link = new CLink(S_SHOW_VALUE_MAPPINGS,'config.php?config=6');
		$link->setAttribute('target','_blank');

		$frmItem->addRow(array( new CVisibilityBox('valuemapid_visible', get_request('valuemapid_visible'), 'valuemapid', S_ORIGINAL),
			S_SHOW_VALUE), array($cmbMap, SPACE, $link));

		$frmItem->addRow(array( new CVisibilityBox('trapper_hosts_visible', get_request('trapper_hosts_visible'), 'trapper_hosts',
			S_ORIGINAL), S_ALLOWED_HOSTS), new CTextBox('trapper_hosts',$trapper_hosts,40));

		$cmbApps = new CListBox('applications[]',$applications,6);
		$cmbApps->addItem(0,'-'.S_NONE.'-');

		if(isset($_REQUEST['hostid'])){
			$sql = 'SELECT applicationid,name '.
				' FROM applications '.
				' WHERE hostid='.$_REQUEST['hostid'].
				' ORDER BY name';
			$db_applications = DBselect($sql);
			while($db_app = DBfetch($db_applications)){
				$cmbApps->addItem($db_app["applicationid"],$db_app["name"]);
			}
		}
		$frmItem->addRow(array( new CVisibilityBox('applications_visible', get_request('applications_visible'), 'applications[]',
			S_ORIGINAL), S_APPLICATIONS),$cmbApps);

		$frmItem->addItemToBottomRow(array(new CButton("update",S_UPDATE),
			SPACE, new CButtonCancel(url_param('groupid').url_param("hostid").url_param("config"))));

	return $frmItem;
	}

	function insert_copy_elements_to_forms($elements_array_name){

		$copy_type = get_request('copy_type', 0);
		$copy_mode = get_request('copy_mode', 0);
		$filter_groupid = get_request('filter_groupid', 0);
		$group_itemid = get_request($elements_array_name, array());
		$copy_targetid = get_request('copy_targetid', array());

		if(!is_array($group_itemid) || (is_array($group_itemid) && count($group_itemid) < 1)){
			error(S_INCORRECT_LIST_OF_ITEMS);
			return;
		}

		$frmCopy = new CFormTable(count($group_itemid).' '.S_X_ELEMENTS_COPY_TO_DOT_DOT_DOT,null,'post',null,'go');
		$frmCopy->setHelp('web.items.copyto.php');
		$frmCopy->addVar($elements_array_name, $group_itemid);

		$cmbCopyType = new CComboBox('copy_type',$copy_type,'submit()');
		$cmbCopyType->addItem(0,S_HOSTS);
		$cmbCopyType->addItem(1,S_HOST_GROUPS);
		$frmCopy->addRow(S_TARGET_TYPE, $cmbCopyType);

		$target_list = array();

		$groups = CHostGroup::get(array('extendoutput'=>1, 'order'=>'name'));
		order_result($groups, 'name');

		if(0 == $copy_type){
			$cmbGroup = new CComboBox('filter_groupid',$filter_groupid,'submit()');

			foreach($groups as $gnum => $group){
				if(empty($filter_groupid)) $filter_groupid = $group['groupid'];
				$cmbGroup->addItem($group['groupid'],$group['name']);
			}

			$frmCopy->addRow('Group', $cmbGroup);

			$options = array(
				'extendoutput'=>1,
				'groupids' => $filter_groupid,
				'templated_hosts' => 1
			);
			$hosts = CHost::get($options);
			order_result($hosts, 'host');

			foreach($hosts as $num => $host){
				$hostid = $host['hostid'];

				array_push($target_list,array(
					new CCheckBox('copy_targetid['.$hostid.']',
						uint_in_array($hostid, $copy_targetid),
						null,
						$hostid),
					SPACE,
					$host['host'],
					BR()
				));
			}
		}
		else{
			foreach($groups as $groupid => $group){
				array_push($target_list,array(
					new CCheckBox('copy_targetid['.$group['groupid'].']',
						uint_in_array($group['groupid'], $copy_targetid),
						null,
						$group['groupid']),
					SPACE,
					$group['name'],
					BR()
					));
			}
		}

		$frmCopy->addRow(S_TARGET, $target_list);

		$cmbCopyMode = new CComboBox('copy_mode',$copy_mode);
		$cmbCopyMode->addItem(0, S_UPDATE_EXISTING_NON_LINKED_ITEMS);
		$cmbCopyMode->addItem(1, S_SKIP_EXISTING_ITEMS);
		$cmbCopyMode->setEnabled(false);
		$frmCopy->addRow(S_MODE, $cmbCopyMode);

		$frmCopy->addItemToBottomRow(new CButton("copy",S_COPY));
		$frmCopy->addItemToBottomRow(array(SPACE,
			new CButtonCancel(url_param('groupid').url_param("hostid").url_param("config"))));

	return $frmCopy;
	}

// TRIGGERS
	function insert_mass_update_trigger_form(){//$elements_array_name){
		$visible = get_request('visible',array());
		$priority = get_request('priority',	'');
		$dependencies = get_request('dependencies',array());

		$original_templates = array();

		asort($dependencies);

		$frmMTrig = new CFormTable(S_TRIGGERS_MASSUPDATE, 'triggers.php');
		$frmMTrig->addVar('massupdate',get_request('massupdate',1));
		$frmMTrig->addVar('go',get_request('go','massupdate'));
		$frmMTrig->setAttribute('id', 'massupdate');
		$frmMTrig->setName('trig_form');

		$triggers = $_REQUEST['g_triggerid'];
		foreach($triggers as $id => $triggerid){
			$frmMTrig->addVar('g_triggerid['.$triggerid.']',$triggerid);
		}

		$cmbPrior = new CComboBox("priority",$priority);
		$cmbPrior->addItems(get_severity_description());

		$frmMTrig->addRow(array(
			new CVisibilityBox('visible[priority]', isset($visible['priority']), 'priority', S_ORIGINAL), S_SEVERITY),
			$cmbPrior
		);

/* dependencies */
		$dep_el = array();
		foreach($dependencies as $val){
			array_push($dep_el,
				array(
					new CCheckBox("rem_dependence[]", 'no', null, strval($val)),
					expand_trigger_description($val)
				),
				BR());
			$frmMTrig->addVar("dependencies[]",strval($val));
		}

		if(count($dep_el)==0)
			$dep_el[] = S_NO_DEPENDENCES_DEFINED;
		else
			$dep_el[] = new CButton('del_dependence',S_DELETE_SELECTED);

		$btnSelect = new CButton('btn1', S_ADD,
				"return PopUp('popup.php?dstfrm=massupdate&dstact=add_dependence&reference=deptrigger".
				"&dstfld1=new_dependence[]&srctbl=triggers&objname=triggers&srcfld1=triggerid&multiselect=1".
				"',1000,700);",
				'T');

		array_push($dep_el, array(br(),$btnSelect));

		$dep_div = new CDiv($dep_el);
		$dep_div->setAttribute('id','dependency_box');

		$dependencyBox = new CVisibilityBox('visible[dependencies]', isset($visible['dependencies']), 'dependency_box',
											S_ORIGINAL);
		$frmMTrig->addRow(array($dependencyBox, S_REPLACE_TRIGGER_DEPENDENCIES), $dep_div);
/* end new dependency */

		$frmMTrig->addItemToBottomRow(new CButton('mass_save',S_SAVE));
		$frmMTrig->addItemToBottomRow(SPACE);
		$frmMTrig->addItemToBottomRow(new CButtonCancel(url_param('groupid')));

		$script = "function addPopupValues(list){
						if(!isset('object', list)) return false;

						if(list.object == 'deptrigger'){
							for(var i=0; i < list.values.length; i++){
								create_var('".$frmMTrig->getName()."', 'new_dependence['+i+']', list.values[i], false);
							}

							create_var('".$frmMTrig->getName()."','add_dependence', 1, true);
						}
					}";
		insert_js($script);

	return $frmMTrig;
	}

// Insert form for Trigger
	function insert_trigger_form(){
		$frmTrig = new CFormTable(S_TRIGGER,'triggers.php');
		$frmTrig->setHelp('config_triggers.php');

//		if(isset($_REQUEST['hostid'])){
//			$frmTrig->addVar('hostid',$_REQUEST['hostid']);
//		}

		$dep_el=array();
		$dependencies = get_request('dependencies',array());

		$limited = null;

		if(isset($_REQUEST['triggerid'])){
			$frmTrig->addVar('triggerid', $_REQUEST['triggerid']);

			$trigger = get_trigger_by_triggerid($_REQUEST['triggerid']);

			$caption = array();
			$trigid = $_REQUEST['triggerid'];
			do{
				$sql = 'SELECT t.triggerid, t.templateid, h.host'.
						' FROM triggers t, functions f, items i, hosts h'.
						' WHERE t.triggerid='.$trigid.
							' AND h.hostid=i.hostid'.
							' AND i.itemid=f.itemid'.
							' AND f.triggerid=t.triggerid';
				$trig = DBfetch(DBselect($sql));

				if($_REQUEST['triggerid'] != $trigid){
					$caption[] = ' : ';
					$caption[] = new CLink($trig['host'], 'triggers.php?form=update&triggerid='.$trig['triggerid'], 'highlight underline');
				}

				$trigid = $trig['templateid'];
			}while($trigid != 0);

			$caption[] = S_TRIGGER.' "';
			$caption = array_reverse($caption);
			$caption[] = htmlspecialchars($trigger['description']);
			$caption[] = '"';
			$frmTrig->setTitle($caption);

			$limited = $trigger['templateid'] ? 'yes' : null;
		}

		$expression		= get_request('expression',	'');
		$description	= get_request('description',	'');
		$type 			= get_request('type',		0);
		$priority		= get_request('priority',	0);
		$status			= get_request('status',		0);
		$comments		= get_request('comments',	'');
		$url			= get_request('url',		'');

		$expr_temp		= get_request('expr_temp',	'');
		$input_method	= get_request('input_method',	IM_ESTABLISHED);

		if((isset($_REQUEST['triggerid']) && !isset($_REQUEST['form_refresh']))  || isset($limited)){
			$description	= $trigger['description'];

			$expression	= explode_exp($trigger['expression']);

			if(!isset($limited) || !isset($_REQUEST['form_refresh'])){
				$type = $trigger['type'];
				$priority	= $trigger['priority'];
				$status		= $trigger['status'];
				$comments	= $trigger['comments'];
				$url		= $trigger['url'];

				$trigs=DBselect('SELECT t.triggerid,t.description,t.expression '.
							' FROM triggers t,trigger_depends d '.
							' WHERE t.triggerid=d.triggerid_up '.
								' AND d.triggerid_down='.$_REQUEST['triggerid']);

				while($trig=DBfetch($trigs)){
					if(uint_in_array($trig['triggerid'],$dependencies))	continue;
					array_push($dependencies,$trig['triggerid']);
				}
			}
		}

		$frmTrig->addRow(S_NAME, new CTextBox('description',$description,90, $limited));

		if($input_method == IM_TREE){
			$alz = analyzeExpression($expression);

			if($alz !== false){
				list($outline, $eHTMLTree) = $alz;
				if(isset($_REQUEST['expr_action']) && $eHTMLTree != null){

					$new_expr = remakeExpression($expression, $_REQUEST['expr_target_single'],
							$_REQUEST['expr_action'], $expr_temp);
					if($new_expr !== false){
						$expression = $new_expr;
						$alz = analyzeExpression($expression);

						if($alz !== false) list($outline, $eHTMLTree) = $alz;
						else show_messages(false, '', S_EXPRESSION_SYNTAX_ERROR);

						$expr_temp = '';
					}
					else{
						show_messages(false, '', S_EXPRESSION_SYNTAX_ERROR);
					}
				}

				$frmTrig->addVar('expression', $expression);
				$exprfname = 'expr_temp';
				$exprtxt = new CTextBox($exprfname, $expr_temp, 65, 'yes');
				$macrobtn = new CButton('insert_macro', S_INSERT_MACRO, 'return call_ins_macro_menu(event);');
				//disabling button, if this trigger is templated
				if($limited=='yes'){
					$macrobtn->setAttribute('disabled', 'disabled');
				}

				$exprparam = "this.form.elements['$exprfname'].value";
			}
			else{
				show_messages(false, '', S_EXPRESSION_SYNTAX_ERROR);
				$input_method = IM_ESTABLISHED;
			}
		}

		if($input_method != IM_TREE){
			$exprfname = 'expression';
			$exprtxt = new CTextBox($exprfname,$expression,75,$limited);
			$exprparam = "getSelectedText(this.form.elements['$exprfname'])";
		}

		$add_expr_button = new CButton('insert',$input_method == IM_TREE ? S_EDIT : S_ADD,
										 "return PopUp('popup_trexpr.php?dstfrm=".$frmTrig->getName().
										 "&dstfld1=${exprfname}&srctbl=expression".
										 "&srcfld1=expression&expression=' + escape($exprparam),1000,700);");
		//disabling button, if this trigger is templated
		if($limited=='yes'){
			$add_expr_button->setAttribute('disabled', 'disabled');
		}

		$row = array($exprtxt, $add_expr_button);

		if(isset($macrobtn)) array_push($row, $macrobtn);
		if($input_method == IM_TREE){
			array_push($row, BR());
			if(empty($outline)){
				$tmpbtn = new CButton('add_expression', S_ADD, "");
				if($limited=='yes'){
					$tmpbtn->setAttribute('disabled', 'disabled');
				}
				array_push($row, $tmpbtn);
			}
			else{
				$tmpbtn = new CButton('and_expression', S_AND_BIG, "");
				if($limited=='yes'){
					$tmpbtn->setAttribute('disabled', 'disabled');
				}
				array_push($row, $tmpbtn);

				$tmpbtn = new CButton('or_expression', S_OR_BIG, "");
				if($limited=='yes'){
					$tmpbtn->setAttribute('disabled', 'disabled');
				}
				array_push($row, $tmpbtn);

				$tmpbtn = new CButton('replace_expression', S_REPLACE, "");
				if($limited=='yes'){
					$tmpbtn->setAttribute('disabled', 'disabled');
				}
				array_push($row, $tmpbtn);
			}
		}
		$frmTrig->addVar('input_method', $input_method);
		$frmTrig->addVar('toggle_input_method', '');
		$exprtitle = array(S_EXPRESSION);

		if($input_method != IM_FORCED){
			$btn_im = new CSpan(S_TOGGLE_INPUT_METHOD,'link');
			$btn_im->setAttribute('onclick','javascript: '.
								"document.getElementById('toggle_input_method').value=1;".
								"document.getElementById('input_method').value=".(($input_method==IM_TREE)?IM_ESTABLISHED:IM_TREE).';'.
								"document.forms['".$frmTrig->getName()."'].submit();");

			$exprtitle[] = array(SPACE, '(', $btn_im, ')');
		}

		$frmTrig->addRow($exprtitle, $row);

		if($input_method == IM_TREE){
			$exp_table = new CTable();
			$exp_table->setClass('tableinfo');
			$exp_table->setAttribute('id','exp_list');
			$exp_table->setOddRowClass('even_row');
			$exp_table->setEvenRowClass('even_row');

			$exp_table->setHeader(array(($limited == 'yes' ? null : S_TARGET), S_EXPRESSION, S_EXPRESSION_PART_ERROR, ($limited == 'yes' ? null : S_DELETE)));

			$allowedTesting = true;
			if($eHTMLTree != null){
				foreach($eHTMLTree as $i => $e){

					if($limited != 'yes'){
						$del_url = new CSpan(S_DELETE,'link');

						$del_url->setAttribute('onclick', 'javascript: if(confirm("'.S_DELETE_EXPRESSION_Q.'")) {'.
										' delete_expression(\''.$e['id'] .'\');'.
										' document.forms["config_triggers.php"].submit(); '.
									'}');
						$tgt_chk = new CCheckbox('expr_target_single', ($i==0) ? 'yes':'no', 'check_target(this);', $e['id']);
					}
					else{
						$tgt_chk = null;
					}

					if(!isset($e['expression']['levelErrors'])) {
						$errorImg = new CImg('images/general/ok_icon.png', 'expression_no_errors');
						$errorImg->setHint(S_EXPRESSION_PART_NO_ERROR, '', '', false);
					}else{
						$allowedTesting = false;
						$errorImg = new CImg('images/general/error_icon.png', 'expression_errors');

						$errorTexts = Array();
						if(is_array($e['expression']['levelErrors'])) {
							foreach($e['expression']['levelErrors'] as $expVal => $errTxt) {
								if(count($errorTexts) > 0) array_push($errorTexts, BR());
								array_push($errorTexts, $expVal, ':', $errTxt);
							}
						}

						$errorImg->setHint($errorTexts, '', 'left', false);
					}

					//if it is a templated trigger
					if($limited == 'yes'){
						//make all links inside inactive
						for($i = 0; $i < count($e['list']); $i++){
							if(gettype($e['list'][$i]) == 'object' && get_class($e['list'][$i]) == 'CSpan' && $e['list'][$i]->getAttribute('class') == 'link'){
								$e['list'][$i]->setClass('');
								$e['list'][$i]->setAttribute('onclick', '');
							}
						}
					}

					$errorCell = new CCol($errorImg, 'center');
					$row = new CRow(array($tgt_chk, $e['list'], $errorCell, (isset($del_url) ? $del_url : null)));
					$exp_table->addRow($row);
				}
			}
			else{
				$allowedTesting = false;
				$outline = '';
			}

			$frmTrig->addVar('remove_expression', '');

			$btn_test = new CButton('test_expression', S_TEST,
									"openWinCentered(".
									"'tr_testexpr.php?expression=' + encodeURIComponent(this.form.elements['expression'].value)".
									",'ExpressionTest'".
									",850,400".
									",'titlebar=no, resizable=yes, scrollbars=yes');".
									"return false;");
			if(!isset($allowedTesting) || !$allowedTesting) $btn_test->setAttribute('disabled', 'disabled');
			if (empty($outline)) $btn_test->setAttribute('disabled', 'yes');
			//SDI($outline);
			$wrapOutline = new CSpan(array($outline));
			$wrapOutline->addStyle('white-space: pre;');
			$frmTrig->addRow(SPACE, array($wrapOutline,
										  BR(),BR(),
										  $exp_table,
										  $btn_test));
		}

// dependencies
		foreach($dependencies as $val){
			array_push($dep_el,
				array(
					new CCheckBox('rem_dependence['.$val.']', 'no', null, strval($val)),
					expand_trigger_description($val)
				),
				BR());
			$frmTrig->addVar('dependencies[]',strval($val));
		}

		if(count($dep_el)==0)
			array_push($dep_el,  S_NO_DEPENDENCES_DEFINED);
		else
			array_push($dep_el, new CButton('del_dependence',S_DELETE_SELECTED));
		$frmTrig->addRow(S_THE_TRIGGER_DEPENDS_ON,$dep_el);
	/* end dependencies */

	/* new dependency */
//		$frmTrig->addVar('new_dependence','0');

//		$txtCondVal = new CTextBox('trigger','',75,'yes');

		$btnSelect = new CButton('btn1',S_ADD,
				"return PopUp('popup.php?srctbl=triggers".
							'&srcfld1=triggerid'.
							'&reference=deptrigger'.
							'&multiselect=1'.
						"',1000,700);",'T');

		$frmTrig->addRow(S_NEW_DEPENDENCY, $btnSelect, 'new');
// end new dependency

		$type_select = new CComboBox('type');
		$type_select->additem(TRIGGER_MULT_EVENT_DISABLED, S_NORMAL, ($type == TRIGGER_MULT_EVENT_ENABLED) ? 'no' : 'yes');
		$type_select->additem(TRIGGER_MULT_EVENT_ENABLED, S_NORMAL.' + '.S_MULTIPLE_PROBLEM_EVENTS, ($type == TRIGGER_MULT_EVENT_ENABLED) ? 'yes' : 'no');

		$frmTrig->addRow(S_EVENT_GENERATION,$type_select);

		$cmbPrior = new CComboBox('priority',$priority);
		for($i = 0; $i <= 5; $i++){
			$cmbPrior->addItem($i,get_severity_description($i));
		}
		$frmTrig->addRow(S_SEVERITY,$cmbPrior);

		$frmTrig->addRow(S_COMMENTS,new CTextArea("comments",$comments,90,7));
		$frmTrig->addRow(S_URL,new CTextBox("url",$url,90));
		$frmTrig->addRow(S_DISABLED,new CCheckBox("status",$status));

		$frmTrig->addItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["triggerid"])){
			$frmTrig->addItemToBottomRow(SPACE);
			$frmTrig->addItemToBottomRow(new CButton("clone",S_CLONE));
			$frmTrig->addItemToBottomRow(SPACE);
			if( !$limited ){
				$frmTrig->addItemToBottomRow(new CButtonDelete(S_DELETE_TRIGGER_Q,
					url_param("form").url_param('groupid').url_param("hostid").
					url_param("triggerid")));
			}
		}
		$frmTrig->addItemToBottomRow(SPACE);
		$frmTrig->addItemToBottomRow(new CButtonCancel(url_param('groupid').url_param("hostid")));

		$script = "function addPopupValues(list){
						if(!isset('object', list)) return false;

						if(list.object == 'deptrigger'){
							for(var i=0; i < list.values.length; i++){
								create_var('".$frmTrig->getName()."', 'new_dependence['+i+']', list.values[i], false);
							}

							create_var('".$frmTrig->getName()."','add_dependence', 1, true);
						}
					}";
		insert_js($script);

	return $frmTrig;
	}

	function insert_graph_form(){

		$frmGraph = new CFormTable(S_GRAPH, null, 'post');
		$frmGraph->setName('frm_graph');
		//$frmGraph->setHelp("web.graphs.graph.php");

		$items = get_request('items', array());

		if(isset($_REQUEST['graphid'])){
			$frmGraph->addVar('graphid', $_REQUEST['graphid']);

			$options = array(
						'graphids' => $_REQUEST['graphid'],
						'extendoutput' => 1
					);
			$graphs = CGraph::get($options);
			$row = reset($graphs);

			$frmGraph->setTitle(S_GRAPH.' "'.$row['name'].'"');
		}

		if(isset($_REQUEST['graphid']) && !isset($_REQUEST['form_refresh'])){
			$name = $row['name'];
			$width = $row['width'];
			$height = $row['height'];
			$ymin_type = $row['ymin_type'];
			$ymax_type = $row['ymax_type'];
			$yaxismin = $row['yaxismin'];
			$yaxismax = $row['yaxismax'];
			$ymin_itemid = $row['ymin_itemid'];
			$ymax_itemid = $row['ymax_itemid'];
			$showworkperiod = $row['show_work_period'];
			$showtriggers = $row['show_triggers'];
			$graphtype = $row['graphtype'];
			$legend = $row['show_legend'];
			$graph3d = $row['show_3d'];
			$percent_left = $row['percent_left'];
			$percent_right = $row['percent_right'];

			$options = array(
						'graphids' => $_REQUEST['graphid'],
						'sortfield' => 'sortorder',
						'extendoutput' => 1
					);

			$items = CGraphItem::get($options);
		}
		else{
			$name = get_request('name', '');
			$graphtype = get_request('graphtype', GRAPH_TYPE_NORMAL);

			if(($graphtype == GRAPH_TYPE_PIE) || ($graphtype == GRAPH_TYPE_EXPLODED)){
				$width = get_request('width', 400);
				$height = get_request('height', 300);
			}
			else{
				$width = get_request('width', 900);
				$height = get_request('height', 200);

			}
			$ymin_type = get_request('ymin_type', GRAPH_YAXIS_TYPE_CALCULATED);
			$ymax_type = get_request('ymax_type', GRAPH_YAXIS_TYPE_CALCULATED);
			$yaxismin = get_request('yaxismin', 0.00);
			$yaxismax = get_request('yaxismax', 100.00);
			$ymin_itemid = get_request('ymin_itemid', 0);
			$ymax_itemid	= get_request('ymax_itemid', 0);
			$showworkperiod = get_request('showworkperiod', 0);
			$showtriggers	= get_request('showtriggers', 0);
			$legend = get_request('legend' ,0);
			$graph3d	= get_request('graph3d', 0);
			$visible = get_request('visible');
			$percent_left  = 0;
			$percent_right = 0;

			if(isset($visible['percent_left'])) $percent_left = get_request('percent_left', 0);
			if(isset($visible['percent_right'])) $percent_right = get_request('percent_right', 0);
		}

/* reinit $_REQUEST */
		$_REQUEST['items']		= $items;
		$_REQUEST['name']		= $name;
		$_REQUEST['width']		= $width;
		$_REQUEST['height']		= $height;

		$_REQUEST['ymin_type']		= $ymin_type;
		$_REQUEST['ymax_type']		= $ymax_type;

		$_REQUEST['yaxismin']		= $yaxismin;
		$_REQUEST['yaxismax']		= $yaxismax;

		$_REQUEST['ymin_itemid']	= $ymin_itemid;
		$_REQUEST['ymax_itemid']	= $ymax_itemid;

		$_REQUEST['showworkperiod']	= $showworkperiod;
		$_REQUEST['showtriggers']	= $showtriggers;
		$_REQUEST['graphtype']		= $graphtype;
		$_REQUEST['legend']		= $legend;
		$_REQUEST['graph3d']		= $graph3d;
		$_REQUEST['percent_left']	= $percent_left;
		$_REQUEST['percent_right']	= $percent_right;
/********************/

		if($graphtype != GRAPH_TYPE_NORMAL){
			foreach($items as $gid => $gitem){
				if($gitem['type'] == GRAPH_ITEM_AGGREGATED)
					unset($items[$gid]);
			}
		}

		$items = array_values($items);
		$icount = count($items);
		for($i=0; $i < $icount-1;){
// check if we deletd an item
			$next = $i+1;
			while(!isset($items[$next]) && ($next < ($icount-1))) $next++;

			if(isset($items[$next]) && ($items[$i]['sortorder'] == $items[$next]['sortorder']))
				for($j=$next; $j < $icount; $j++)
					if($items[$j-1]['sortorder'] >= $items[$j]['sortorder']) $items[$j]['sortorder']++;

			$i = $next;
		}

		asort_by_key($items, 'sortorder');

		$items = array_values($items);

		$group_gid = get_request('group_gid', array());

		$frmGraph->addVar('ymin_itemid', $ymin_itemid);
		$frmGraph->addVar('ymax_itemid', $ymax_itemid);

		$frmGraph->addRow(S_NAME, new CTextBox('name', $name, 32));

		$frmGraph->addRow(S_WIDTH, new CNumericBox('width', $width, 5));
		$frmGraph->addRow(S_HEIGHT, new CNumericBox('height', $height, 5));

		$cmbGType = new CComboBox('graphtype', $graphtype, 'graphs.submit(this)');
		$cmbGType->addItem(GRAPH_TYPE_NORMAL, S_NORMAL);
		$cmbGType->addItem(GRAPH_TYPE_STACKED, S_STACKED);
		$cmbGType->addItem(GRAPH_TYPE_PIE, S_PIE);
		$cmbGType->addItem(GRAPH_TYPE_EXPLODED, S_EXPLODED);

		zbx_add_post_js('graphs.graphtype = '.$graphtype.";\n");

		$frmGraph->addRow(S_GRAPH_TYPE, $cmbGType);


// items beforehead, to get only_hostid for miny maxy items
		$only_hostid = null;
		$monitored_hosts = null;

		if(count($items)){
			$frmGraph->addVar('items', $items);

			$keys = array_keys($items);
			$first = reset($keys);
			$last = end($keys);

			$items_table = new CTableInfo();
			foreach($items as $gid => $gitem){
				//if($graphtype == GRAPH_TYPE_STACKED && $gitem['type'] == GRAPH_ITEM_AGGREGATED) continue;
				$host = get_host_by_itemid($gitem['itemid']);
				$item = get_item_by_itemid($gitem['itemid']);

				if($host['status'] == HOST_STATUS_TEMPLATE)
					$only_hostid = $host['hostid'];
				else
					$monitored_hosts = 1;

				if($gitem['type'] == GRAPH_ITEM_AGGREGATED)
					$color = '-';
				else
					$color = new CColorCell(null,$gitem['color']);


				if($gid == $first){
					$do_up = null;
				}
				else{
					$do_up = new CSpan(S_UP,'link');
					$do_up->onClick("return create_var('".$frmGraph->getName()."','move_up',".$gid.", true);");
				}

				if($gid == $last){
					$do_down = null;
				}
				else{
					$do_down = new CSpan(S_DOWN,'link');
					$do_down->onClick("return create_var('".$frmGraph->getName()."','move_down',".$gid.", true);");
				}

				$description = new CSpan($host['host'].': '.item_description($item),'link');
				$description->onClick(
					'return PopUp("popup_gitem.php?list_name=items&dstfrm='.$frmGraph->getName().
					url_param($only_hostid, false, 'only_hostid').
					url_param($monitored_hosts, false, 'monitored_hosts').
					url_param($graphtype, false, 'graphtype').
					url_param($gitem, false).
					url_param($gid,false,'gid').
					url_param(get_request('graphid',0),false,'graphid').
					'",550,400,"graph_item_form");'
				);

				if(($graphtype == GRAPH_TYPE_PIE) || ($graphtype == GRAPH_TYPE_EXPLODED)){
					$items_table->addRow(array(
							new CCheckBox('group_gid['.$gid.']',isset($group_gid[$gid])),
							$description,
							graph_item_calc_fnc2str($gitem["calc_fnc"],$gitem["type"]),
							graph_item_type2str($gitem['type'],$gitem["periods_cnt"]),
							$color,
							array( $do_up, SPACE."|".SPACE, $do_down )
						));
				}
				else{
					$items_table->addRow(array(
							new CCheckBox('group_gid['.$gid.']',isset($group_gid[$gid])),
//							$gitem['sortorder'],
							$description,
							graph_item_calc_fnc2str($gitem["calc_fnc"],$gitem["type"]),
							graph_item_type2str($gitem['type'],$gitem["periods_cnt"]),
							($gitem['yaxisside']==GRAPH_YAXIS_SIDE_LEFT)?S_LEFT:S_RIGHT,
							graph_item_drawtype2str($gitem["drawtype"],$gitem["type"]),
							$color,
							array( $do_up, ((!is_null($do_up) && !is_null($do_down)) ? SPACE."|".SPACE : ''), $do_down )
						));
				}
			}
			$dedlete_button = new CButton('delete_item', S_DELETE_SELECTED);
		}
		else{
			$items_table = $dedlete_button = null;
		}

//		$frmGraph->addRow(S_SHOW_LEGEND, new CCheckBox('legend',$legend, null, 1));

		if(($graphtype == GRAPH_TYPE_NORMAL) || ($graphtype == GRAPH_TYPE_STACKED)){
			$frmGraph->addRow(S_SHOW_WORKING_TIME,new CCheckBox('showworkperiod',$showworkperiod,null,1));
			$frmGraph->addRow(S_SHOW_TRIGGERS,new CCheckBox('showtriggers',$showtriggers,null,1));


			if($graphtype == GRAPH_TYPE_NORMAL){
				$percent_left = sprintf('%2.2f',$percent_left);
				$percent_right = sprintf('%2.2f',$percent_right);

				$pr_left_input = new CTextBox('percent_left',$percent_left,'5');
				$pr_left_chkbx = new CCheckBox('visible[percent_left]',1,"javascript: ShowHide('percent_left');",1);
				if($percent_left == 0){
					$pr_left_input->setAttribute('style','display: none;');
					$pr_left_chkbx->setChecked(0);
				}

				$pr_right_input = new CTextBox('percent_right',$percent_right,'5');
				$pr_right_chkbx = new CCheckBox('visible[percent_right]',1,"javascript: ShowHide('percent_right');",1);
				if($percent_right == 0){
					$pr_right_input->setAttribute('style','display: none;');
					$pr_right_chkbx->setChecked(0);
				}

				$frmGraph->addRow(S_PERCENTILE_LINE.' ('.S_LEFT.')',array($pr_left_chkbx,$pr_left_input));

				$frmGraph->addRow(S_PERCENTILE_LINE.' ('.S_RIGHT.')',array($pr_right_chkbx,$pr_right_input));
			}

			$yaxis_min = array();

			$cmbYType = new CComboBox('ymin_type',$ymin_type,'javascript: submit();');
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_CALCULATED,S_CALCULATED);
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_FIXED,S_FIXED);
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_ITEM_VALUE,S_ITEM);

			$yaxis_min[] = $cmbYType;

			if($ymin_type == GRAPH_YAXIS_TYPE_FIXED){
				$yaxis_min[] = new CTextBox("yaxismin",$yaxismin,9);
			}
			else if($ymin_type == GRAPH_YAXIS_TYPE_ITEM_VALUE){
				$frmGraph->addVar('yaxismin',$yaxismin);

				$ymin_name = '';
				if($ymin_itemid > 0){
					$min_host = get_host_by_itemid($ymin_itemid);
					$min_item = get_item_by_itemid($ymin_itemid);
					$ymin_name = $min_host['host'].':'.item_description($min_item);
				}

				if(count($items)){
					$yaxis_min[] = new CTextBox("ymin_name",$ymin_name,80,'yes');
					$yaxis_min[] = new CButton('yaxis_min',S_SELECT,'javascript: '.
													"return PopUp('popup.php?dstfrm=".$frmGraph->getName().
													url_param($only_hostid, false, 'only_hostid').
													url_param($monitored_hosts, false, 'monitored_hosts').
														"&dstfld1=ymin_itemid".
														"&dstfld2=ymin_name".
														"&srctbl=items".
														"&srcfld1=itemid".
														"&srcfld2=description',0,0,'zbx_popup_item');");
				}
				else{
					$yaxis_min[] = SPACE.S_ADD_GRAPH_ITEMS;
				}
			}
			else{
				$frmGraph->addVar('yaxismin', $yaxismin);
			}

			$frmGraph->addRow(S_YAXIS_MIN_VALUE, $yaxis_min);

			$yaxis_max = array();

			$cmbYType = new CComboBox("ymax_type",$ymax_type,"submit()");
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_CALCULATED,S_CALCULATED);
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_FIXED,S_FIXED);
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_ITEM_VALUE,S_ITEM);

			$yaxis_max[] = $cmbYType;

			if($ymax_type == GRAPH_YAXIS_TYPE_FIXED){
				$yaxis_max[] = new CTextBox('yaxismax',$yaxismax,9);
			}
			else if($ymax_type == GRAPH_YAXIS_TYPE_ITEM_VALUE){
				$frmGraph->addVar('yaxismax',$yaxismax);

				$ymax_name = '';
				if($ymax_itemid > 0){
					$max_host = get_host_by_itemid($ymax_itemid);
					$max_item = get_item_by_itemid($ymax_itemid);
					$ymax_name = $max_host['host'].':'.item_description($max_item);
				}

				if(count($items)){
					$yaxis_max[] = new CTextBox("ymax_name",$ymax_name,80,'yes');
					$yaxis_max[] = new CButton('yaxis_max',S_SELECT,'javascript: '.
													"return PopUp('popup.php?dstfrm=".$frmGraph->getName().
													url_param($only_hostid, false, 'only_hostid').
													url_param($monitored_hosts, false, 'monitored_hosts').
														"&dstfld1=ymax_itemid".
														"&dstfld2=ymax_name".
														"&srctbl=items".
														"&srcfld1=itemid".
														"&srcfld2=description',0,0,'zbx_popup_item');");
				}
				else{
					$yaxis_max[] = SPACE.S_ADD_GRAPH_ITEMS;
				}
			}
			else{
				$frmGraph->addVar('yaxismax',$yaxismax);
			}

			$frmGraph->addRow(S_YAXIS_MAX_VALUE, $yaxis_max);
		}
		else{
			$frmGraph->addRow(S_SHOW_LEGEND, new CCheckBox('legend',$legend, null, 1));
			$frmGraph->addRow(S_3D_VIEW,new CCheckBox('graph3d',$graph3d,null,1));
		}

		$frmGraph->addRow(S_ITEMS,
				array(
					$items_table,
					new CButton('add_item',S_ADD,
						"return PopUp('popup_gitem.php?dstfrm=".$frmGraph->getName().
						url_param($only_hostid, false, 'only_hostid').
						url_param($monitored_hosts, false, 'monitored_hosts').
						url_param($graphtype, false, 'graphtype').
						"',550,400,'graph_item_form');"),
					$dedlete_button
				));
		unset($items_table, $dedlete_button);

		$preView = new CButton('preview',S_PREVIEW);
		//$preView->setAttribute('style', 'float: left;');

		$frmGraph->addItemToBottomRow($preView);
		$frmGraph->addItemToBottomRow(SPACE);
		$frmGraph->addItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($_REQUEST['graphid'])){
			$frmGraph->addItemToBottomRow(SPACE);
			$frmGraph->addItemToBottomRow(new CButton('clone',S_CLONE));
			$frmGraph->addItemToBottomRow(SPACE);
			$frmGraph->addItemToBottomRow(new CButtonDelete(S_DELETE_GRAPH_Q,url_param('graphid').
				url_param('groupid').url_param('hostid')));
		}
		$frmGraph->addItemToBottomRow(SPACE);
		$frmGraph->addItemToBottomRow(new CButtonCancel(url_param('groupid').url_param('hostid')));

		$frmGraph->show();
	}


	function get_timeperiod_form(){
		$tblPeriod = new CTableInfo();

		/* init new_timeperiod variable */
		$new_timeperiod = get_request('new_timeperiod', array());

		$new = is_array($new_timeperiod);

		if(is_array($new_timeperiod) && isset($new_timeperiod['id'])){
			$tblPeriod->addItem(new Cvar('new_timeperiod[id]',$new_timeperiod['id']));
		}

		if(!is_array($new_timeperiod)){
			$new_timeperiod = array();
			$new_timeperiod['timeperiod_type'] = TIMEPERIOD_TYPE_ONETIME;
		}

		if(!isset($new_timeperiod['every']))		$new_timeperiod['every']		= 1;
		if(!isset($new_timeperiod['day']))			$new_timeperiod['day']			= 1;
		if(!isset($new_timeperiod['hour']))			$new_timeperiod['hour']			= 12;
		if(!isset($new_timeperiod['minute']))		$new_timeperiod['minute']		= 0;
		if(!isset($new_timeperiod['start_date']))	$new_timeperiod['start_date']		= 0;

		if(!isset($new_timeperiod['period_days']))		$new_timeperiod['period_days']		= 0;
		if(!isset($new_timeperiod['period_hours']))		$new_timeperiod['period_hours']		= 1;
		if(!isset($new_timeperiod['period_minutes']))	$new_timeperiod['period_minutes']	= 0;

		if(!isset($new_timeperiod['month_date_type']))	$new_timeperiod['month_date_type'] = !(bool)$new_timeperiod['day'];

// START TIME
		if(isset($new_timeperiod['start_time'])){
			$new_timeperiod['hour'] = floor($new_timeperiod['start_time'] / 3600);
			$new_timeperiod['minute'] = floor(($new_timeperiod['start_time'] - ($new_timeperiod['hour'] * 3600)) / 60);
		}
//--

// PERIOD
		if(isset($new_timeperiod['period'])){
			$new_timeperiod['period_days'] = floor($new_timeperiod['period'] / 86400);
			$new_timeperiod['period_hours'] = floor(($new_timeperiod['period'] - ($new_timeperiod['period_days'] * 86400)) / 3600);
			$new_timeperiod['period_minutes'] = floor(($new_timeperiod['period'] - $new_timeperiod['period_days'] * 86400 -
					$new_timeperiod['period_hours'] * 3600) / 60);
		}
//--

// DAYSOFWEEK
		$dayofweek = '';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_mo']))? '0':'1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_tu']))? '0':'1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_we']))? '0':'1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_th']))? '0':'1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_fr']))? '0':'1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_sa']))? '0':'1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_su']))? '0':'1';

		if(isset($new_timeperiod['dayofweek'])){
			$dayofweek = zbx_num2bitstr($new_timeperiod['dayofweek'],true);
		}

		$new_timeperiod['dayofweek_mo'] = $dayofweek[0];
		$new_timeperiod['dayofweek_tu'] = $dayofweek[1];
		$new_timeperiod['dayofweek_we'] = $dayofweek[2];
		$new_timeperiod['dayofweek_th'] = $dayofweek[3];
		$new_timeperiod['dayofweek_fr'] = $dayofweek[4];
		$new_timeperiod['dayofweek_sa'] = $dayofweek[5];
		$new_timeperiod['dayofweek_su'] = $dayofweek[6];
//--

// MONTHS
		$month = '';
		$month .= (!isset($new_timeperiod['month_jan']))? '0':'1';
		$month .= (!isset($new_timeperiod['month_feb']))? '0':'1';
		$month .= (!isset($new_timeperiod['month_mar']))? '0':'1';
		$month .= (!isset($new_timeperiod['month_apr']))? '0':'1';
		$month .= (!isset($new_timeperiod['month_may']))? '0':'1';
		$month .= (!isset($new_timeperiod['month_jun']))? '0':'1';
		$month .= (!isset($new_timeperiod['month_jul']))? '0':'1';
		$month .= (!isset($new_timeperiod['month_aug']))? '0':'1';
		$month .= (!isset($new_timeperiod['month_sep']))? '0':'1';
		$month .= (!isset($new_timeperiod['month_oct']))? '0':'1';
		$month .= (!isset($new_timeperiod['month_nov']))? '0':'1';
		$month .= (!isset($new_timeperiod['month_dec']))? '0':'1';

		if(isset($new_timeperiod['month'])){
			$month = zbx_num2bitstr($new_timeperiod['month'],true);
		}

		$new_timeperiod['month_jan'] = $month[0];
		$new_timeperiod['month_feb'] = $month[1];
		$new_timeperiod['month_mar'] = $month[2];
		$new_timeperiod['month_apr'] = $month[3];
		$new_timeperiod['month_may'] = $month[4];
		$new_timeperiod['month_jun'] = $month[5];
		$new_timeperiod['month_jul'] = $month[6];
		$new_timeperiod['month_aug'] = $month[7];
		$new_timeperiod['month_sep'] = $month[8];
		$new_timeperiod['month_oct'] = $month[9];
		$new_timeperiod['month_nov'] = $month[10];
		$new_timeperiod['month_dec'] = $month[11];

//--

		$bit_dayofweek = zbx_str_revert($dayofweek);
		$bit_month = zbx_str_revert($month);

		$cmbType = new CComboBox('new_timeperiod[timeperiod_type]', $new_timeperiod['timeperiod_type'],'submit()');
			$cmbType->addItem(TIMEPERIOD_TYPE_ONETIME,	S_ONE_TIME_ONLY);
			$cmbType->addItem(TIMEPERIOD_TYPE_DAILY,	S_DAILY);
			$cmbType->addItem(TIMEPERIOD_TYPE_WEEKLY,	S_WEEKLY);
			$cmbType->addItem(TIMEPERIOD_TYPE_MONTHLY,	S_MONTHLY);

		$tblPeriod->addRow(array(S_PERIOD_TYPE, $cmbType));

		if($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_DAILY){
			$tblPeriod->addItem(new Cvar('new_timeperiod[dayofweek]',bindec($bit_dayofweek)));
			$tblPeriod->addItem(new Cvar('new_timeperiod[month]',bindec($bit_month)));

			$tblPeriod->addItem(new Cvar('new_timeperiod[day]',$new_timeperiod['day']));
			$tblPeriod->addItem(new Cvar('new_timeperiod[start_date]',$new_timeperiod['start_date']));
			$tblPeriod->addItem(new Cvar('new_timeperiod[month_date_type]',$new_timeperiod['month_date_type']));

			$tblPeriod->addRow(array(S_EVERY_DAY_S,		new CNumericBox('new_timeperiod[every]', $new_timeperiod['every'], 3)));
		}
		else if($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY){
			$tblPeriod->addItem(new Cvar('new_timeperiod[month]',bindec($bit_month)));
			$tblPeriod->addItem(new Cvar('new_timeperiod[day]',$new_timeperiod['day']));
			$tblPeriod->addItem(new Cvar('new_timeperiod[start_date]',$new_timeperiod['start_date']));
			$tblPeriod->addItem(new Cvar('new_timeperiod[month_date_type]',$new_timeperiod['month_date_type']));

			$tblPeriod->addRow(array(S_EVERY_WEEK_S,	new CNumericBox('new_timeperiod[every]', $new_timeperiod['every'], 2)));

			$tabDays = new CTable();
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_mo]',$dayofweek[0],null,1), S_MONDAY));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_tu]',$dayofweek[1],null,1), S_TUESDAY));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_we]',$dayofweek[2],null,1), S_WEDNESDAY));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_th]',$dayofweek[3],null,1), S_THURSDAY));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_fr]',$dayofweek[4],null,1), S_FRIDAY));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_sa]',$dayofweek[5],null,1), S_SATURDAY));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_su]',$dayofweek[6],null,1), S_SUNDAY));

			$tblPeriod->addRow(array(S_DAY_OF_WEEK,$tabDays));

		}
		else if($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY){
			$tblPeriod->addItem(new Cvar('new_timeperiod[start_date]',$new_timeperiod['start_date']));

			$tabMonths = new CTable();
			$tabMonths->addRow(array(
								new CCheckBox('new_timeperiod[month_jan]',$month[0],null,1), S_JANUARY,
								SPACE,SPACE,
								new CCheckBox('new_timeperiod[month_jul]',$month[6],null,1), S_JULY
								 ));

			$tabMonths->addRow(array(
								new CCheckBox('new_timeperiod[month_feb]',$month[1],null,1), S_FEBRUARY,
								SPACE,SPACE,
								new CCheckBox('new_timeperiod[month_aug]',$month[7],null,1), S_AUGUST
								 ));

			$tabMonths->addRow(array(
								new CCheckBox('new_timeperiod[month_mar]',$month[2],null,1), S_MARCH,
								SPACE,SPACE,
								new CCheckBox('new_timeperiod[month_sep]',$month[8],null,1), S_SEPTEMBER
								 ));

			$tabMonths->addRow(array(
								new CCheckBox('new_timeperiod[month_apr]',$month[3],null,1), S_APRIL,
								SPACE,SPACE,
								new CCheckBox('new_timeperiod[month_oct]',$month[9],null,1), S_OCTOBER
								 ));

			$tabMonths->addRow(array(
								new CCheckBox('new_timeperiod[month_may]',$month[4],null,1), S_MAY,
								SPACE,SPACE,
								new CCheckBox('new_timeperiod[month_nov]',$month[10],null,1), S_NOVEMBER
								 ));

			$tabMonths->addRow(array(
								new CCheckBox('new_timeperiod[month_jun]',$month[5],null,1), S_JUNE,
								SPACE,SPACE,
								new CCheckBox('new_timeperiod[month_dec]',$month[11],null,1), S_DECEMBER
								 ));

			$tblPeriod->addRow(array(S_MONTH,	$tabMonths));

			$radioDaily = new CTag('input');
			$radioDaily->setAttribute('type','radio');
			$radioDaily->setAttribute('name','new_timeperiod[month_date_type]');
			$radioDaily->setAttribute('value','0');
			$radioDaily->setAttribute('onclick','submit()');

			$radioDaily2 = new CTag('input');
			$radioDaily2->setAttribute('type','radio');
			$radioDaily2->setAttribute('name','new_timeperiod[month_date_type]');
			$radioDaily2->setAttribute('value','1');
			$radioDaily2->setAttribute('onclick','submit()');

			if($new_timeperiod['month_date_type']){
				$radioDaily2->setAttribute('checked','checked');
			}
			else{
				$radioDaily->setAttribute('checked','checked');
			}

			$tblPeriod->addRow(array(S_DATE, array($radioDaily, S_DAY, SPACE, SPACE, $radioDaily2, S_DAY_OF_WEEK)));

			if($new_timeperiod['month_date_type'] > 0){
				$tblPeriod->addItem(new Cvar('new_timeperiod[day]',$new_timeperiod['day']));

				$cmbCount = new CComboBox('new_timeperiod[every]', $new_timeperiod['every']);
					$cmbCount->addItem(1, S_FIRST);
					$cmbCount->addItem(2, S_SECOND);
					$cmbCount->addItem(3, S_THIRD);
					$cmbCount->addItem(4, S_FOURTH);
					$cmbCount->addItem(5, S_LAST);

				$td = new CCol($cmbCount);
				$td->setColSpan(2);

				$tabDays = new CTable();
				$tabDays->addRow($td);
				$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_mo]',$dayofweek[0],null,1), S_MONDAY));
				$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_tu]',$dayofweek[1],null,1), S_TUESDAY));
				$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_we]',$dayofweek[2],null,1), S_WEDNESDAY));
				$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_th]',$dayofweek[3],null,1), S_THURSDAY));
				$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_fr]',$dayofweek[4],null,1), S_FRIDAY));
				$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_sa]',$dayofweek[5],null,1), S_SATURDAY));
				$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_su]',$dayofweek[6],null,1), S_SUNDAY));


				$tblPeriod->addRow(array(S_DAY_OF_WEEK,$tabDays));
			}
			else{
				$tblPeriod->addItem(new Cvar('new_timeperiod[dayofweek]',bindec($bit_dayofweek)));

				$tblPeriod->addRow(array(S_DAY_OF_MONTH, new CNumericBox('new_timeperiod[day]', $new_timeperiod['day'], 2)));
			}
		}
		else{
			$tblPeriod->addItem(new Cvar('new_timeperiod[every]',$new_timeperiod['every']));
			$tblPeriod->addItem(new Cvar('new_timeperiod[dayofweek]',bindec($bit_dayofweek)));
			$tblPeriod->addItem(new Cvar('new_timeperiod[month]',bindec($bit_month)));
			$tblPeriod->addItem(new Cvar('new_timeperiod[day]',$new_timeperiod['day']));
			$tblPeriod->addItem(new Cvar('new_timeperiod[hour]',$new_timeperiod['hour']));
			$tblPeriod->addItem(new Cvar('new_timeperiod[minute]',$new_timeperiod['minute']));
			$tblPeriod->addItem(new Cvar('new_timeperiod[month_date_type]',$new_timeperiod['month_date_type']));

/***********************************************************/
			$tblPeriod->addItem(new Cvar('new_timeperiod[start_date]',$new_timeperiod['start_date']));

			$clndr_icon = new CImg('images/general/bar/cal.gif','calendar', 16, 12, 'pointer');

			$clndr_icon->addAction('onclick','javascript: '.
												'var pos = getPosition(this); '.
												'pos.top+=10; '.
												'pos.left+=16; '.
												"CLNDR['new_timeperiod_date'].clndr.clndrshow(pos.top,pos.left);");

			$filtertimetab = new CTable(null,'calendar');
			$filtertimetab->setAttribute('width','10%');

			$filtertimetab->setCellPadding(0);
			$filtertimetab->setCellSpacing(0);

			$start_date = zbxDateToTime($new_timeperiod['start_date']);
			$filtertimetab->addRow(array(
					new CNumericBox('new_timeperiod_day',(($start_date>0)?date('d',$start_date):''),2),
					'/',
					new CNumericBox('new_timeperiod_month',(($start_date>0)?date('m',$start_date):''),2),
					'/',
					new CNumericBox('new_timeperiod_year',(($start_date>0)?date('Y',$start_date):''),4),
					SPACE,
					new CNumericBox('new_timeperiod_hour',(($start_date>0)?date('H',$start_date):''),2),
					':',
					new CNumericBox('new_timeperiod_minute',(($start_date>0)?date('i',$start_date):''),2),
					$clndr_icon
					));

			zbx_add_post_js('create_calendar(null,'.
							'["new_timeperiod_day","new_timeperiod_month","new_timeperiod_year","new_timeperiod_hour","new_timeperiod_minute"],'.
							'"new_timeperiod_date",'.
							'"new_timeperiod[start_date]");');

			$clndr_icon->addAction('onclick','javascript: '.
												'var pos = getPosition(this); '.
												'pos.top+=10; '.
												'pos.left+=16; '.
												"CLNDR['mntc_active_till'].clndr.clndrshow(pos.top,pos.left);");

			$tblPeriod->addRow(array(S_DATE,$filtertimetab));

//-------
		}

		if($new_timeperiod['timeperiod_type'] != TIMEPERIOD_TYPE_ONETIME){
			$tabTime = new CTable(null,'calendar');
			$tabTime->addRow(array(new CNumericBox('new_timeperiod[hour]', $new_timeperiod['hour'], 2),':',new CNumericBox('new_timeperiod[minute]', $new_timeperiod['minute'], 2)));

			$tblPeriod->addRow(array(S_AT.SPACE.'('.S_HOUR.':'.S_MINUTE.')', $tabTime));
		}


		$perHours = new CComboBox('new_timeperiod[period_hours]',$new_timeperiod['period_hours']);
		for($i=0; $i < 24; $i++){
			$perHours->addItem($i,$i);
		}
		$perMinutes = new CComboBox('new_timeperiod[period_minutes]',$new_timeperiod['period_minutes']);
		for($i=0; $i < 60; $i++){
			$perMinutes->addItem($i,$i);
		}
		$tblPeriod->addRow(array(
							S_MAINTENANCE_PERIOD_LENGTH,
							array(
								new CNumericBox('new_timeperiod[period_days]',$new_timeperiod['period_days'],3),
								S_DAYS.SPACE.SPACE,
								$perHours,
								SPACE.S_HOURS,
								$perMinutes,
								SPACE.S_MINUTES
							)));
//			$tabPeriod = new CTable();
//			$tabPeriod->addRow(S_DAYS)
//			$tblPeriod->addRow(array(S_AT.SPACE.'('.S_HOUR.':'.S_MINUTE.')', $tabTime));

		$td = new CCol(array(
			new CButton('add_timeperiod', $new ? S_SAVE : S_ADD),
			SPACE,
			new CButton('cancel_new_timeperiod',S_CANCEL)
			));

		$td->setAttribute('colspan','3');
		$td->setAttribute('style','text-align: right;');

		$tblPeriod->setFooter($td);

	return $tblPeriod;
	}

	function import_screen_form($rules){

		$form = new CFormTable(S_IMPORT, null, 'post', 'multipart/form-data');
		$form->addRow(S_IMPORT_FILE, new CFile('import_file'));

		$table = new CTable();
		$table->setHeader(array(S_ELEMENT, S_UPDATE.SPACE.S_EXISTING, S_ADD.SPACE.S_MISSING), 'bold');

		$titles = array('screen' => S_SCREEN);

		foreach($titles as $key => $title){
			$cbExist = new CCheckBox('rules['.$key.'][exist]', isset($rules[$key]['exist']));

			if($key == 'template')
				$cbMissed = null;
			else
				$cbMissed = new CCheckBox('rules['.$key.'][missed]', isset($rules[$key]['missed']));

			$table->addRow(array($title, $cbExist, $cbMissed));
		}

		$form->addRow(S_RULES, $table);

		$form->addItemToBottomRow(new CButton('import', S_IMPORT));
		return $form;
	}

	function insert_screen_form(){

		$frm_title = S_SCREEN;
		if(isset($_REQUEST['screenid'])){
			$result=DBselect('SELECT screenid,name,hsize,vsize '.
						' FROM screens g '.
						' WHERE screenid='.$_REQUEST['screenid']);
			$row=DBfetch($result);
			$frm_title = S_SCREEN.' "'.$row['name'].'"';
		}
		if(isset($_REQUEST['screenid']) && !isset($_REQUEST['form_refresh'])){
			$name=$row['name'];
			$hsize=$row['hsize'];
			$vsize=$row['vsize'];
		}
		else{
			$name=get_request('name','');
			$hsize=get_request('hsize',1);
			$vsize=get_request('bsize',1);
		}

		$frmScr = new CFormTable($frm_title,'screenconf.php');
		$frmScr->setHelp('web.screenconf.screen.php');

		$frmScr->addVar('config', 0);

		if(isset($_REQUEST['screenid'])){
			$frmScr->addVar('screenid',$_REQUEST['screenid']);
		}
		$frmScr->addRow(S_NAME, new CTextBox('name',$name,32));
		$frmScr->addRow(S_COLUMNS, new CNumericBox('hsize',$hsize,3));
		$frmScr->addRow(S_ROWS, new CNumericBox('vsize',$vsize,3));

		$frmScr->addItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($_REQUEST['screenid'])){
			/* $frmScr->addItemToBottomRow(SPACE);
			$frmScr->addItemToBottomRow(new CButton('clone',S_CLONE)); !!! TODO */
			$frmScr->addItemToBottomRow(SPACE);
			$frmScr->addItemToBottomRow(new CButtonDelete(S_DELETE_SCREEN_Q,
				url_param('form').url_param('screenid')));
		}
		$frmScr->addItemToBottomRow(SPACE);
		$frmScr->addItemToBottomRow(new CButtonCancel());

		return $frmScr;
	}

// HOSTS
	function insert_mass_update_host_form(){//$elements_array_name){
		global $USER_DETAILS;

		$visible = get_request('visible', array());

		$groups = get_request('groups', array());

		$newgroup = get_request('newgroup', '');

		$host 		= get_request('host',	'');
		$port 		= get_request('port',	CProfile::get('HOST_PORT',10050));
		$status		= get_request('status',	HOST_STATUS_MONITORED);
		$useip		= get_request('useip',	1);
		$dns		= get_request('dns',	'');
		$ip			= get_request('ip',	'0.0.0.0');
		$proxy_hostid	= get_request('proxy_hostid','');

		$useipmi	= get_request('useipmi', 'no');
		$ipmi_ip	= get_request('ipmi_ip', '');
		$ipmi_port	= get_request('ipmi_port', 623);
		$ipmi_authtype	= get_request('ipmi_authtype', -1);
		$ipmi_privilege	= get_request('ipmi_privilege', 2);
		$ipmi_username	= get_request('ipmi_username', '');
		$ipmi_password	= get_request('ipmi_password', '');


		$useprofile	= get_request('useprofile', 'no');
		$host_profile = get_request('host_profile', array());
		$profile_fields = array(
			'devicetype' => S_DEVICE_TYPE,
			'name' => S_NAME,
			'os' => S_OS,
			'serialno' => S_SERIALNO,
			'tag' => S_TAG,
			'macaddress' => S_MACADDRESS,
			'hardware' => S_HARDWARE,
			'software' => S_SOFTWARE,
			'contact' => S_CONTACT,
			'location' => S_LOCATION,
			'notes' => S_NOTES
		);
		foreach($profile_fields as $field => $caption){
			if(!isset($host_profile[$field])) $host_profile[$field] = '';
		}

// BEGIN: HOSTS PROFILE EXTENDED Section
		$useprofile_ext = get_request('useprofile_ext','no');
		$ext_host_profiles = get_request('ext_host_profiles', array());

		$ext_profiles_fields = array(
			'device_alias'=>S_DEVICE_ALIAS,
			'device_type'=>S_DEVICE_TYPE,
			'device_chassis'=>S_DEVICE_CHASSIS,
			'device_os'=>S_DEVICE_OS,
			'device_os_short'=>S_DEVICE_OS_SHORT,
			'device_hw_arch'=>S_DEVICE_HW_ARCH,
			'device_serial'=>S_DEVICE_SERIAL,
			'device_model'=>S_DEVICE_MODEL,
			'device_tag'=>S_DEVICE_TAG,
			'device_vendor'=>S_DEVICE_VENDOR,
			'device_contract'=>S_DEVICE_CONTRACT,
			'device_who'=>S_DEVICE_WHO,
			'device_status'=>S_DEVICE_STATUS,
			'device_app_01'=>S_DEVICE_APP_01,
			'device_app_02'=>S_DEVICE_APP_02,
			'device_app_03'=>S_DEVICE_APP_03,
			'device_app_04'=>S_DEVICE_APP_04,
			'device_app_05'=>S_DEVICE_APP_05,
			'device_url_1'=>S_DEVICE_URL_1,
			'device_url_2'=>S_DEVICE_URL_2,
			'device_url_3'=>S_DEVICE_URL_3,
			'device_networks'=>S_DEVICE_NETWORKS,
			'device_notes'=>S_DEVICE_NOTES,
			'device_hardware'=>S_DEVICE_HARDWARE,
			'device_software'=>S_DEVICE_SOFTWARE,
			'ip_subnet_mask'=>S_IP_SUBNET_MASK,
			'ip_router'=>S_IP_ROUTER,
			'ip_macaddress'=>S_IP_MACADDRESS,
			'oob_ip'=>S_OOB_IP,
			'oob_subnet_mask'=>S_OOB_SUBNET_MASK,
			'oob_router'=>S_OOB_ROUTER,
			'date_hw_buy'=>S_DATE_HW_BUY,
			'date_hw_install'=>S_DATE_HW_INSTALL,
			'date_hw_expiry'=>S_DATE_HW_EXPIRY,
			'date_hw_decomm'=>S_DATE_HW_DECOMM,
			'site_street_1'=>S_SITE_STREET_1,
			'site_street_2'=>S_SITE_STREET_2,
			'site_street_3'=>S_SITE_STREET_3,
			'site_city'=>S_SITE_CITY,
			'site_state'=>S_SITE_STATE,
			'site_country'=>S_SITE_COUNTRY,
			'site_zip'=>S_SITE_ZIP,
			'site_rack'=>S_SITE_RACK,
			'site_notes'=>S_SITE_NOTES,
			'poc_1_name'=>S_POC_1_NAME,
			'poc_1_email'=>S_POC_1_EMAIL,
			'poc_1_phone_1'=>S_POC_1_PHONE_1,
			'poc_1_phone_2'=>S_POC_1_PHONE_2,
			'poc_1_cell'=>S_POC_1_CELL,
			'poc_1_screen'=>S_POC_1_SCREEN,
			'poc_1_notes'=>S_POC_1_NOTES,
			'poc_2_name'=>S_POC_2_NAME,
			'poc_2_email'=>S_POC_2_EMAIL,
			'poc_2_phone_1'=>S_POC_2_PHONE_1,
			'poc_2_phone_2'=>S_POC_2_PHONE_2,
			'poc_2_cell'=>S_POC_2_CELL,
			'poc_2_screen'=>S_POC_2_SCREEN,
			'poc_2_notes'=>S_POC_2_NOTES
		);

		foreach($ext_profiles_fields as $field => $caption){
			if(!isset($ext_host_profiles[$field])) $ext_host_profiles[$field] = '';
		}

// END:   HOSTS PROFILE EXTENDED Section

		$templates = get_request('templates', array());
		natsort($templates);

		$frm_title = S_HOST.SPACE.S_MASS_UPDATE;

		$frmHost = new CFormTable($frm_title, 'hosts.php');
		$frmHost->setHelp('web.hosts.host.php');
		$frmHost->addVar('go', 'massupdate');

		$hosts = $_REQUEST['hosts'];
		foreach ($hosts as $id => $hostid) {
			$frmHost->addVar('hosts['.$hostid.']', $hostid);
		}

		$frmHost->addRow(S_NAME, S_ORIGINAL);

		$grp_tb = new CTweenBox($frmHost, 'groups', $groups, 6);
		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'editable' => 1,
		);
		$all_groups = CHostGroup::get($options);
		order_result($all_groups, 'name');
		foreach ($all_groups as $grp) {
			$grp_tb->addItem($grp['groupid'], $grp['name']);
		}

		$frmHost->addRow(array(new CVisibilityBox('visible[groups]', isset($visible['groups']), $grp_tb->getName(), S_ORIGINAL), S_REPLACE_HOST_GROUPS),
						$grp_tb->get(S_IN.SPACE.S_GROUPS, S_OTHER.SPACE.S_GROUPS)
					);

		$frmHost->addRow(array(new CVisibilityBox('visible[newgroup]', isset($visible['newgroup']), 'newgroup', S_ORIGINAL), S_NEW_HOST_GROUP),
						new CTextBox('newgroup', $newgroup),
						'new'
					);

// onchange does not work on some browsers: MacOS, KDE browser
		$frmHost->addRow(array(new CVisibilityBox('visible[dns]', isset($visible['dns']), 'dns', S_ORIGINAL),S_DNS_NAME),
						new CTextBox('dns',$dns,'40')
					);

		$frmHost->addRow(array(new CVisibilityBox('visible[ip]', isset($visible['ip']), 'ip', S_ORIGINAL),S_IP_ADDRESS),
						new CTextBox('ip',$ip,defined('ZBX_HAVE_IPV6')?39:15)
					);

		$cmbConnectBy = new CComboBox('useip', $useip);
		$cmbConnectBy->addItem(0, S_DNS_NAME);
		$cmbConnectBy->addItem(1, S_IP_ADDRESS);

		$frmHost->addRow(array(new CVisibilityBox('visible[useip]', isset($visible['useip']), 'useip', S_ORIGINAL),S_CONNECT_TO),
						$cmbConnectBy
					);

		$frmHost->addRow(array(new CVisibilityBox('visible[port]', isset($visible['port']), 'port', S_ORIGINAL),S_AGENT_PORT),						new CNumericBox('port',$port,5)
					);

//Proxy
		$cmbProxy = new CComboBox('proxy_hostid', $proxy_hostid);

		$cmbProxy->addItem(0, S_NO_PROXY);

		$sql = 'SELECT hostid,host '.
				' FROM hosts '.
				' WHERE status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.') '.
					' AND '.DBin_node('hostid').
				' ORDER BY host';
		$db_proxies = DBselect($sql);
		while ($db_proxy = DBfetch($db_proxies))
			$cmbProxy->addItem($db_proxy['hostid'], $db_proxy['host']);

		$frmHost->addRow(array(
						new CVisibilityBox('visible[proxy_hostid]', isset($visible['proxy_hostid']), 'proxy_hostid', S_ORIGINAL),
						S_MONITORED_BY_PROXY),
						$cmbProxy
					);
//----------

		$cmbStatus = new CComboBox('status',$status);
		$cmbStatus->addItem(HOST_STATUS_MONITORED,	S_MONITORED);
		$cmbStatus->addItem(HOST_STATUS_NOT_MONITORED,	S_NOT_MONITORED);

		$frmHost->addRow(array(new CVisibilityBox('visible[status]', isset($visible['status']), 'status', S_ORIGINAL),S_STATUS), $cmbStatus);

// LINK TEMPLATES {{{
		$template_table = new CTable();
		$template_table->setAttribute('name','template_table');
		$template_table->setAttribute('id','template_table');

		$template_table->setCellPadding(0);
		$template_table->setCellSpacing(0);

		foreach($templates as $id => $temp_name){
			$frmHost->addVar('templates['.$id.']',$temp_name);
			$template_table->addRow(array(
				new CCheckBox('templates_rem['.$id.']', 'no', null, $id),
				$temp_name,
			));
		}

		$template_table->addRow(array(
			new CButton('add_template', S_ADD, "return PopUp('popup.php?dstfrm=".$frmHost->getName().
				"&dstfld1=new_template&srctbl=templates&srcfld1=hostid&srcfld2=host".
				url_param($templates,false,'existed_templates')."',450,450)"),
			new CButton('unlink', S_REMOVE)
		));

		$vbox = new CVisibilityBox('visible[template_table]', isset($visible['template_table']), 'template_table', S_ORIGINAL);
		$vbox->setAttribute('id', 'cb_tpladd');
		if(isset($visible['template_table_r'])) $vbox->setAttribute('disabled', 'disabled');
		$action = $vbox->getAttribute('onclick');
		$action .= 'if($("cb_tplrplc").disabled) $("cb_tplrplc").enable(); else $("cb_tplrplc").disable();';
		$vbox->setAttribute('onclick', $action);

		$frmHost->addRow(array($vbox, S_LINK_ADDITIONAL_TEMPLATES), $template_table, 'T');
// }}} LINK TEMPLATES


// RELINK TEMPLATES {{{
		$template_table_r = new CTable();
		$template_table_r->setAttribute('name','template_table_r');
		$template_table_r->setAttribute('id','template_table_r');

		$template_table_r->setCellPadding(0);
		$template_table_r->setCellSpacing(0);

		foreach($templates as $id => $temp_name){
			$frmHost->addVar('templates['.$id.']',$temp_name);
			$template_table_r->addRow(array(
				new CCheckBox('templates_rem['.$id.']', 'no', null, $id),
				$temp_name,
			));
		}

		$template_table_r->addRow(array(
			new CButton('add_template', S_ADD, "return PopUp('popup.php?dstfrm=".$frmHost->getName().
				"&dstfld1=new_template&srctbl=templates&srcfld1=hostid&srcfld2=host".
				url_param($templates,false,'existed_templates')."',450,450)"),
			new CButton('unlink', S_REMOVE)
		));

		$vbox = new CVisibilityBox('visible[template_table_r]', isset($visible['template_table_r']), 'template_table_r', S_ORIGINAL);
		$vbox->setAttribute('id', 'cb_tplrplc');
		if(isset($visible['template_table'])) $vbox->setAttribute('disabled', 'disabled');
		$action = $vbox->getAttribute('onclick');
		$action .= <<<JAVASCRIPT
if($("cb_tpladd").disabled){
	$("cb_tpladd").enable();
}
else{
	$("cb_tpladd").disable();
}
$("clrcbdiv").toggle();
JAVASCRIPT;
		$vbox->setAttribute('onclick', $action);

		$clear_cb = new CCheckBox('mass_clear_tpls', get_request('mass_clear_tpls', false));
		$div = new CDiv(array($clear_cb, S_CLEAR_WHEN_UNLINKING));
		$div->setAttribute('id', 'clrcbdiv');
		$div->addStyle('margin-left: 20px;');
		if(!isset($visible['template_table_r'])) $div->addStyle('display: none;');

		$frmHost->addRow(array($vbox, S_RELINK_TEMPLATES, $div), $template_table_r, 'T');
// }}} RELINK TEMPLATES


		$frmHost->addRow(array(
			new CVisibilityBox('visible[useipmi]', isset($visible['useipmi']), 'useipmi', S_ORIGINAL), S_USEIPMI),
			new CCheckBox('useipmi', $useipmi, 'submit()')
		);

		if($useipmi == 'yes'){
			$frmHost->addRow(array(
				new CVisibilityBox('visible[ipmi_ip]', isset($visible['ipmi_ip']), 'ipmi_ip', S_ORIGINAL), S_IPMI_IP_ADDRESS),
				new CTextBox('ipmi_ip', $ipmi_ip, defined('ZBX_HAVE_IPV6') ? 39 : 15)
			);

			$frmHost->addRow(array(
				new CVisibilityBox('visible[ipmi_port]', isset($visible['ipmi_port']), 'ipmi_port', S_ORIGINAL), S_IPMI_PORT),
				new CNumericBox('ipmi_port', $ipmi_port, 15)
			);

			$cmbIPMIAuthtype = new CComboBox('ipmi_authtype', $ipmi_authtype);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_DEFAULT,	S_AUTHTYPE_DEFAULT);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_NONE,		S_AUTHTYPE_NONE);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_MD2,		S_AUTHTYPE_MD2);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_MD5,		S_AUTHTYPE_MD5);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_STRAIGHT,	S_AUTHTYPE_STRAIGHT);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_OEM,		S_AUTHTYPE_OEM);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_RMCP_PLUS,	S_AUTHTYPE_RMCP_PLUS);
			$frmHost->addRow(array(
				new CVisibilityBox('visible[ipmi_authtype]', isset($visible['ipmi_authtype']), 'ipmi_authtype', S_ORIGINAL), S_IPMI_AUTHTYPE),
				$cmbIPMIAuthtype
			);

			$cmbIPMIPrivilege = new CComboBox('ipmi_privilege', $ipmi_privilege);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_CALLBACK,	S_PRIVILEGE_CALLBACK);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_USER,		S_PRIVILEGE_USER);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_OPERATOR,	S_PRIVILEGE_OPERATOR);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_ADMIN,	S_PRIVILEGE_ADMIN);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_OEM,		S_PRIVILEGE_OEM);
			$frmHost->addRow(array(
				new CVisibilityBox('visible[ipmi_privilege]', isset($visible['ipmi_privilege']), 'ipmi_privilege', S_ORIGINAL), S_IPMI_PRIVILEGE),
				$cmbIPMIPrivilege
			);

			$frmHost->addRow(array(
				new CVisibilityBox('visible[ipmi_username]', isset($visible['ipmi_username']), 'ipmi_username', S_ORIGINAL), S_IPMI_USERNAME),
				new CTextBox('ipmi_username', $ipmi_username, 16)
			);

			$frmHost->addRow(array(
				new CVisibilityBox('visible[ipmi_password]', isset($visible['ipmi_password']), 'ipmi_password', S_ORIGINAL), S_IPMI_PASSWORD),
				new CTextBox('ipmi_password', $ipmi_password, 20)
			);
		}

		$frmHost->addRow(array(
					new CVisibilityBox('visible[useprofile]', isset($visible['useprofile']), 'useprofile', S_ORIGINAL),S_USE_PROFILE),
					new CCheckBox('useprofile',$useprofile,'submit()')
				);

// BEGIN: HOSTS PROFILE EXTENDED Section
		$frmHost->addRow(array(
			new CVisibilityBox('visible[useprofile_ext]', isset($visible['useprofile_ext']), 'useprofile_ext', S_ORIGINAL),S_USE_EXTENDED_PROFILE),
			new CCheckBox('useprofile_ext',$useprofile_ext,'submit()')
		);
// END:   HOSTS PROFILE EXTENDED Section

		if($useprofile==='yes'){
			if($useprofile === 'yes'){
				foreach($profile_fields as $field => $caption){
					$frmHost->addRow(array(
						new CVisibilityBox('visible['.$field.']', isset($visible[$field]), 'host_profile['.$field.']', S_ORIGINAL), $caption),
						new CTextBox('host_profile['.$field.']',$host_profile[$field],80)
					);
				}
			}
			else{
				foreach($profile_fields as $field => $caption){
					$frmHost->addVar('host_profile['.$field.']', $host_profile[$field]);
				}
			}
		}

// BEGIN: HOSTS PROFILE EXTENDED Section
		if($useprofile_ext=='yes'){
			foreach($ext_profiles_fields as $prof_field => $caption){
				$frmHost->addRow(array(
					new CVisibilityBox('visible['.$prof_field.']', isset($visible[$prof_field]), 'ext_host_profiles['.$prof_field.']', S_ORIGINAL),$caption),
					new CTextBox('ext_host_profiles['.$prof_field.']',$ext_host_profiles[$prof_field],80)
				);
			}
		}
		else{
			foreach($ext_profiles_fields as $prof_field => $caption){
				$frmHost->addVar('ext_host_profiles['.$prof_field.']',	$ext_host_profiles[$prof_field]);
			}
		}
// END:   HOSTS PROFILE EXTENDED Section

		$frmHost->addItemToBottomRow(new CButton('masssave',S_SAVE));
		$frmHost->addItemToBottomRow(SPACE);
		$frmHost->addItemToBottomRow(new CButtonCancel(url_param('config').url_param('groupid')));

		return $frmHost;
	}

// Host import form
	function import_host_form($template=false){
		$form = new CFormTable(S_IMPORT, null, 'post', 'multipart/form-data');
		$form->addRow(S_IMPORT_FILE, new CFile('import_file'));

		$table = new CTable();
		$table->setHeader(array(S_ELEMENT, S_UPDATE.SPACE.S_EXISTING, S_ADD.SPACE.S_MISSING), 'bold');

		$titles = array('host' => $template?S_TEMPLATE:S_HOST, 'template' => S_TEMPLATE_LINKAGE, 'item' => S_ITEM, 'trigger' => S_TRIGGER, 'graph' => S_GRAPH);
		foreach($titles as $key => $title){
			$cbExist = new CCheckBox('rules['.$key.'][exist]', true);

			if($key == 'template')
				$cbMissed = null;
			else
				$cbMissed = new CCheckBox('rules['.$key.'][missed]', true);

			$table->addRow(array($title, $cbExist, $cbMissed));
		}

		$form->addRow(S_RULES, $table);

		$form->addItemToBottomRow(new CButton('import', S_IMPORT));

	return $form;
	}

// Host form
	function insert_host_form(){
		global $USER_DETAILS;

		$host_groups = get_request('groups', array());
		if(isset($_REQUEST['groupid']) && ($_REQUEST['groupid']>0) && empty($host_groups)){
			array_push($host_groups, $_REQUEST['groupid']);
		}

		$newgroup	= get_request('newgroup','');

		$host 		= get_request('host',	'');
		$port 		= get_request('port',	CProfile::get('HOST_PORT',10050));
		$status		= get_request('status',	HOST_STATUS_MONITORED);
		$useip		= get_request('useip',	1);
		$dns		= get_request('dns',	'');
		$ip		= get_request('ip',	'0.0.0.0');
		$proxy_hostid	= get_request('proxy_hostid','');

		$useipmi	= get_request('useipmi','no');
		$ipmi_ip	= get_request('ipmi_ip','');
		$ipmi_port	= get_request('ipmi_port',623);
		$ipmi_authtype	= get_request('ipmi_authtype',-1);
		$ipmi_privilege	= get_request('ipmi_privilege',2);
		$ipmi_username	= get_request('ipmi_username','');
		$ipmi_password	= get_request('ipmi_password','');

		$useprofile = get_request('useprofile','no');

		$devicetype	= get_request('devicetype','');
		$name		= get_request('name','');
		$os			= get_request('os','');
		$serialno	= get_request('serialno','');
		$tag		= get_request('tag','');
		$macaddress	= get_request('macaddress','');
		$hardware	= get_request('hardware','');
		$software	= get_request('software','');
		$contact	= get_request('contact','');
		$location	= get_request('location','');
		$notes		= get_request('notes','');

		$_REQUEST['hostid'] = get_request('hostid', 0);
// BEGIN: HOSTS PROFILE EXTENDED Section
		$useprofile_ext		= get_request('useprofile_ext','no');
		$ext_host_profiles	= get_request('ext_host_profiles',array());
// END:   HOSTS PROFILE EXTENDED Section

		$templates		= get_request('templates',array());
		$clear_templates	= get_request('clear_templates',array());

		$frm_title = S_HOST;
		if($_REQUEST['hostid']>0){
			$db_host = get_host_by_hostid($_REQUEST['hostid']);
			$frm_title	.= SPACE.' ['.$db_host['host'].']';

			$original_templates = get_templates_by_hostid($_REQUEST['hostid']);
		}
		else{
			$original_templates = array();
		}

		if(($_REQUEST['hostid']>0) && !isset($_REQUEST['form_refresh'])){
			$proxy_hostid		= $db_host['proxy_hostid'];
			$host			= $db_host['host'];
			$port			= $db_host['port'];
			$status			= $db_host['status'];
			$useip			= $db_host['useip'];
			$useipmi		= $db_host['useipmi'] ? 'yes' : 'no';
			$ip			= $db_host['ip'];
			$dns			= $db_host['dns'];
			$ipmi_ip		= $db_host['ipmi_ip'];

			$ipmi_port		= $db_host['ipmi_port'];
			$ipmi_authtype		= $db_host['ipmi_authtype'];
			$ipmi_privilege		= $db_host['ipmi_privilege'];
			$ipmi_username		= $db_host['ipmi_username'];
			$ipmi_password		= $db_host['ipmi_password'];

// add groups
			$options = array('hostids' => $_REQUEST['hostid']);
			$host_groups = CHostGroup::get($options);
			$host_groups = zbx_objectValues($host_groups, 'groupid');

// read profile
			$db_profiles = DBselect('SELECT * FROM hosts_profiles WHERE hostid='.$_REQUEST['hostid']);

			$useprofile = 'no';
			$db_profile = DBfetch($db_profiles);
			if($db_profile){
				$useprofile = 'yes';

				$devicetype	= $db_profile['devicetype'];
				$name		= $db_profile['name'];
				$os		= $db_profile['os'];
				$serialno	= $db_profile['serialno'];
				$tag		= $db_profile['tag'];
				$macaddress	= $db_profile['macaddress'];
				$hardware	= $db_profile['hardware'];
				$software	= $db_profile['software'];
				$contact	= $db_profile['contact'];
				$location	= $db_profile['location'];
				$notes		= $db_profile['notes'];
			}

// BEGIN: HOSTS PROFILE EXTENDED Section
			$useprofile_ext = 'no';

			$db_profiles_alt = DBselect('SELECT * FROM hosts_profiles_ext WHERE hostid='.$_REQUEST['hostid']);
			if($ext_host_profiles = DBfetch($db_profiles_alt)){
				$useprofile_ext = 'yes';
			}
			else{
				$ext_host_profiles = array();
			}
// END:   HOSTS PROFILE EXTENDED Section

			$templates = $original_templates;
		}

		$ext_profiles_fields = array(
				'device_alias'=>S_DEVICE_ALIAS,
				'device_type'=>S_DEVICE_TYPE,
				'device_chassis'=>S_DEVICE_CHASSIS,
				'device_os'=>S_DEVICE_OS,
				'device_os_short'=>S_DEVICE_OS_SHORT,
				'device_hw_arch'=>S_DEVICE_HW_ARCH,
				'device_serial'=>S_DEVICE_SERIAL,
				'device_model'=>S_DEVICE_MODEL,
				'device_tag'=>S_DEVICE_TAG,
				'device_vendor'=>S_DEVICE_VENDOR,
				'device_contract'=>S_DEVICE_CONTRACT,
				'device_who'=>S_DEVICE_WHO,
				'device_status'=>S_DEVICE_STATUS,
				'device_app_01'=>S_DEVICE_APP_01,
				'device_app_02'=>S_DEVICE_APP_02,
				'device_app_03'=>S_DEVICE_APP_03,
				'device_app_04'=>S_DEVICE_APP_04,
				'device_app_05'=>S_DEVICE_APP_05,
				'device_url_1'=>S_DEVICE_URL_1,
				'device_url_2'=>S_DEVICE_URL_2,
				'device_url_3'=>S_DEVICE_URL_3,
				'device_networks'=>S_DEVICE_NETWORKS,
				'device_notes'=>S_DEVICE_NOTES,
				'device_hardware'=>S_DEVICE_HARDWARE,
				'device_software'=>S_DEVICE_SOFTWARE,
				'ip_subnet_mask'=>S_IP_SUBNET_MASK,
				'ip_router'=>S_IP_ROUTER,
				'ip_macaddress'=>S_IP_MACADDRESS,
				'oob_ip'=>S_OOB_IP,
				'oob_subnet_mask'=>S_OOB_SUBNET_MASK,
				'oob_router'=>S_OOB_ROUTER,
				'date_hw_buy'=>S_DATE_HW_BUY,
				'date_hw_install'=>S_DATE_HW_INSTALL,
				'date_hw_expiry'=>S_DATE_HW_EXPIRY,
				'date_hw_decomm'=>S_DATE_HW_DECOMM,
				'site_street_1'=>S_SITE_STREET_1,
				'site_street_2'=>S_SITE_STREET_2,
				'site_street_3'=>S_SITE_STREET_3,
				'site_city'=>S_SITE_CITY,
				'site_state'=>S_SITE_STATE,
				'site_country'=>S_SITE_COUNTRY,
				'site_zip'=>S_SITE_ZIP,
				'site_rack'=>S_SITE_RACK,
				'site_notes'=>S_SITE_NOTES,
				'poc_1_name'=>S_POC_1_NAME,
				'poc_1_email'=>S_POC_1_EMAIL,
				'poc_1_phone_1'=>S_POC_1_PHONE_1,
				'poc_1_phone_2'=>S_POC_1_PHONE_2,
				'poc_1_cell'=>S_POC_1_CELL,
				'poc_1_screen'=>S_POC_1_SCREEN,
				'poc_1_notes'=>S_POC_1_NOTES,
				'poc_2_name'=>S_POC_2_NAME,
				'poc_2_email'=>S_POC_2_EMAIL,
				'poc_2_phone_1'=>S_POC_2_PHONE_1,
				'poc_2_phone_2'=>S_POC_2_PHONE_2,
				'poc_2_cell'=>S_POC_2_CELL,
				'poc_2_screen'=>S_POC_2_SCREEN,
				'poc_2_notes'=>S_POC_2_NOTES
			);


		foreach($ext_profiles_fields as $field => $caption){
			if(!isset($ext_host_profiles[$field])) $ext_host_profiles[$field] = '';
		}

		$clear_templates = array_intersect($clear_templates, array_keys($original_templates));
		$clear_templates = array_diff($clear_templates,array_keys($templates));
		natcasesort($templates);

		$frmHost = new CForm('hosts.php', 'post');
		$frmHost->setName('web.hosts.host.php.');
//		$frmHost->setHelp('web.hosts.host.php');
//		$frmHost->addVar('config',get_request('config',0));
		$frmHost->addVar('form', get_request('form', 1));
		$from_rfr = get_request('form_refresh',0);
		$frmHost->addVar('form_refresh', $from_rfr+1);
		$frmHost->addVar('clear_templates', $clear_templates);

// HOST WIDGET {
		$host_tbl = new CTable('', 'tablestripped');
		$host_tbl->setOddRowClass('form_odd_row');
		$host_tbl->setEvenRowClass('form_even_row');

		if ($_REQUEST['hostid']>0) $frmHost->addVar('hostid', $_REQUEST['hostid']);
		if ($_REQUEST['groupid']>0) $frmHost->addVar('groupid', $_REQUEST['groupid']);

		$host_tbl->addRow(array(S_NAME, new CTextBox('host', $host, 54)));

		$grp_tb = new CTweenBox($frmHost, 'groups', $host_groups, 10);

		$all_groups = CHostGroup::get(array('editable' => 1, 'extendoutput' => 1));
		order_result($all_groups, 'name');
		foreach ($all_groups as $group) {
			$grp_tb->addItem($group['groupid'], $group['name']);
		}

		$host_tbl->addRow(array(S_GROUPS, $grp_tb->get(S_IN_GROUPS, S_OTHER_GROUPS)));

		$host_tbl->addRow(array(S_NEW_HOST_GROUP, new CTextBox('newgroup', $newgroup)));

// onchange does not work on some browsers: MacOS, KDE browser
		$host_tbl->addRow(array(S_DNS_NAME,new CTextBox('dns', $dns, '40')));
		if (defined('ZBX_HAVE_IPV6')) {
			$host_tbl->addRow(array(S_IP_ADDRESS,new CTextBox('ip', $ip, '39')));
		}
		else {
			$host_tbl->addRow(array(S_IP_ADDRESS,new CTextBox('ip', $ip, '15')));
		}

		$cmbConnectBy = new CComboBox('useip', $useip);
		$cmbConnectBy->addItem(0, S_DNS_NAME);
		$cmbConnectBy->addItem(1, S_IP_ADDRESS);
		$host_tbl->addRow(array(S_CONNECT_TO, $cmbConnectBy));

		$host_tbl->addRow(array(S_AGENT_PORT, new CNumericBox('port', $port, 5)));

// Proxy
		$cmbProxy = new CComboBox('proxy_hostid', $proxy_hostid);

		$cmbProxy->addItem(0, S_NO_PROXY);
		$options = array('extendoutput' => 1);
		$db_proxies = CProxy::get($options);
		order_result($db_proxies, 'host');

		foreach($db_proxies as $proxy){
			$cmbProxy->addItem($proxy['proxyid'], $proxy['host']);
		}

		$host_tbl->addRow(array(S_MONITORED_BY_PROXY, $cmbProxy));
//----------

		$cmbStatus = new CComboBox('status',$status);
		$cmbStatus->addItem(HOST_STATUS_MONITORED,	S_MONITORED);
		$cmbStatus->addItem(HOST_STATUS_NOT_MONITORED,	S_NOT_MONITORED);
		$host_tbl->addRow(array(S_STATUS,$cmbStatus));

		$host_tbl->addRow(array(S_USEIPMI, new CCheckBox('useipmi', $useipmi, 'submit()')));

		if($useipmi == 'yes'){
			$host_tbl->addRow(array(S_IPMI_IP_ADDRESS, new CTextBox('ipmi_ip', $ipmi_ip, defined('ZBX_HAVE_IPV6') ? 39 : 15)));
			$host_tbl->addRow(array(S_IPMI_PORT, new CNumericBox('ipmi_port', $ipmi_port, 5)));

			$cmbIPMIAuthtype = new CComboBox('ipmi_authtype', $ipmi_authtype);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_DEFAULT,	S_AUTHTYPE_DEFAULT);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_NONE,		S_AUTHTYPE_NONE);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_MD2,		S_AUTHTYPE_MD2);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_MD5,		S_AUTHTYPE_MD5);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_STRAIGHT,	S_AUTHTYPE_STRAIGHT);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_OEM,		S_AUTHTYPE_OEM);
			$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_RMCP_PLUS,	S_AUTHTYPE_RMCP_PLUS);
			$host_tbl->addRow(array(S_IPMI_AUTHTYPE, $cmbIPMIAuthtype));

			$cmbIPMIPrivilege = new CComboBox('ipmi_privilege', $ipmi_privilege);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_CALLBACK,	S_PRIVILEGE_CALLBACK);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_USER,		S_PRIVILEGE_USER);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_OPERATOR,	S_PRIVILEGE_OPERATOR);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_ADMIN,	S_PRIVILEGE_ADMIN);
			$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_OEM,		S_PRIVILEGE_OEM);
			$host_tbl->addRow(array(S_IPMI_PRIVILEGE, $cmbIPMIPrivilege));

			$host_tbl->addRow(array(S_IPMI_USERNAME, new CTextBox('ipmi_username', $ipmi_username, 16)));
			$host_tbl->addRow(array(S_IPMI_PASSWORD, new CTextBox('ipmi_password', $ipmi_password, 20)));
		}
		else{
			$frmHost->addVar('ipmi_ip', $ipmi_ip);
			$frmHost->addVar('ipmi_port', $ipmi_port);
			$frmHost->addVar('ipmi_authtype', $ipmi_authtype);
			$frmHost->addVar('ipmi_privilege', $ipmi_privilege);
			$frmHost->addVar('ipmi_username', $ipmi_username);
			$frmHost->addVar('ipmi_password', $ipmi_password);
		}

		if($_REQUEST['form'] == 'full_clone'){
			// host items
			$options = array(
				'inherited' => 0,
				'hostids' => $_REQUEST['hostid'],
				'output' => API_OUTPUT_EXTEND,
			);
			$host_items = CItem::get($options);

			if(!empty($host_items)){
				$items_lbx = new CListBox('items', null, 8);
				$items_lbx->setAttribute('disabled', 'disabled');

				order_result($host_items, 'description');
				foreach($host_items as $hitem){
					$items_lbx->addItem($hitem['itemid'], item_description($hitem));
				}
				$host_tbl->addRow(array(S_ITEMS, $items_lbx));
				unset($items_lbx);
			}

			// host triggers
			$options = array(
				'inherited' => 0,
				'hostids' => $_REQUEST['hostid'],
				'output' => array('triggerid', 'description'),
				'select_items' => API_OUTPUT_EXTEND,
				'expandDescription' => true,
			);
			$host_triggers = CTrigger::get($options);

			if(!empty($host_triggers)){
				$trig_lbx = new CListBox('triggers' ,null, 8);
				$trig_lbx->setAttribute('disabled', 'disabled');

				order_result($host_triggers, 'description');
				foreach($host_triggers as $htrigger){
					if (httpitemExists($htrigger['items']))
						continue;

					$trig_lbx->addItem($htrigger['triggerid'], $htrigger['description']);
				}

				if($trig_lbx->itemsCount())
					$host_tbl->addRow(array(S_TRIGGERS, $trig_lbx));
				unset($trig_lbx);
			}

			// host graphs
			$options = array(
				'inherited' => 0,
				'hostids' => $_REQUEST['hostid'],
				'select_hosts' => API_OUTPUT_REFER,
				'select_items' => API_OUTPUT_EXTEND,
				'output' => API_OUTPUT_EXTEND,
			);
			$host_graphs = CGraph::get($options);

			if(!empty($host_graphs)){
				$graphs_lbx = new CListBox('graphs', null, 8);
				$graphs_lbx->setAttribute('disabled', 'disabled');

				order_result($host_graphs, 'name');
				foreach($host_graphs as $hgraph){
					if(count($hgraph['hosts']) > 1)
						continue;

					if (httpitemExists($hgraph['items']))
						continue;

					$graphs_lbx->addItem($hgraph['graphid'], $hgraph['name']);
				}

				if($graphs_lbx->itemsCount())
					$host_tbl->addRow(array(S_GRAPHS, $graphs_lbx));
				unset($graphs_lbx);
			}
		}

		$host_footer = array();
		$host_footer[] = new CButton('save', S_SAVE);
		if(($_REQUEST['hostid']>0) && ($_REQUEST['form'] != 'full_clone')){
			array_push($host_footer, SPACE, new CButton('clone', S_CLONE), SPACE, new CButton('full_clone', S_FULL_CLONE), SPACE,
				new CButtonDelete(S_DELETE_SELECTED_HOST_Q, url_param('form').url_param('hostid').url_param('groupid')));
		}
		array_push($host_footer, SPACE, new CButtonCancel(url_param('groupid')));

		$host_footer = new CCol($host_footer);
		$host_footer->setColSpan(2);
		$host_tbl->setFooter($host_footer);

		$host_wdgt = new CWidget();
		$host_wdgt->setClass('header');
		$host_wdgt->addHeader($frm_title);
		$host_wdgt->addItem($host_tbl);
// } HOST WIDGET

// TEMPLATES{
		$template_tbl = new CTableInfo(S_NO_TEMPLATES_LINKED, 'tablestripped');
		$template_tbl->setOddRowClass('form_odd_row');
		$template_tbl->setEvenRowClass('form_even_row');

		foreach($templates as $id => $temp_name){
			$frmHost->addVar('templates['.$id.']', $temp_name);
			$template_tbl->addRow(new CCol(array(
				new CCheckBox('templates_rem['.$id.']', 'no', null, $id),
				$temp_name))
			);
		}

		$footer = new CCol(array(
			new CButton('add_template', S_ADD,
				"return PopUp('popup.php?dstfrm=".$frmHost->getName().
				"&dstfld1=new_template&srctbl=templates&srcfld1=hostid&srcfld2=host".
				url_param($templates,false,'existed_templates')."',450,450)",
				'T'),
			SPACE,
			new CButton('unlink', S_UNLINK),
			SPACE,
			new CButton('unlink_and_clear', S_UNLINK_AND_CLEAR)
		));
		//$footer->setColSpan(2);

		$template_tbl->setFooter($footer);

		$template_wdgt = new CWidget();
		$template_wdgt->setClass('header');
		$template_wdgt->addHeader(S_LINKED_TEMPLATES);
		$template_wdgt->addItem($template_tbl);

// } TEMPLATES


// MACROS WIDGET {
		$macros_wdgt = get_macros_widget($_REQUEST['hostid']);
// } MACROS WIDGET


// PROFILE WIDGET {
		$profile_tbl = new CTable('', 'tablestripped');
		$profile_tbl->setOddRowClass('form_odd_row');
		$profile_tbl->setEvenRowClass('form_even_row');

		$profile_tbl->addRow(array(S_USE_PROFILE,new CCheckBox('useprofile',$useprofile,'submit()')));

		if($useprofile == 'yes'){
			$profile_tbl->addRow(array(S_DEVICE_TYPE,new CTextBox('devicetype',$devicetype,61)));
			$profile_tbl->addRow(array(S_NAME,new CTextBox('name',$name,61)));
			$profile_tbl->addRow(array(S_OS,new CTextBox('os',$os,61)));
			$profile_tbl->addRow(array(S_SERIALNO,new CTextBox('serialno',$serialno,61)));
			$profile_tbl->addRow(array(S_TAG,new CTextBox('tag',$tag,61)));
			$profile_tbl->addRow(array(S_MACADDRESS,new CTextBox('macaddress',$macaddress,61)));
			$profile_tbl->addRow(array(S_HARDWARE,new CTextArea('hardware',$hardware,60,4)));
			$profile_tbl->addRow(array(S_SOFTWARE,new CTextArea('software',$software,60,4)));
			$profile_tbl->addRow(array(S_CONTACT,new CTextArea('contact',$contact,60,4)));
			$profile_tbl->addRow(array(S_LOCATION,new CTextArea('location',$location,60,4)));
			$profile_tbl->addRow(array(S_NOTES,new CTextArea('notes',$notes,60,4)));
		}
		else{
			$frmHost->addVar('devicetype', $devicetype);
			$frmHost->addVar('name',$name);
			$frmHost->addVar('os',$os);
			$frmHost->addVar('serialno',$serialno);
			$frmHost->addVar('tag',	$tag);
			$frmHost->addVar('macaddress',$macaddress);
			$frmHost->addVar('hardware',$hardware);
			$frmHost->addVar('software',$software);
			$frmHost->addVar('contact',$contact);
			$frmHost->addVar('location',$location);
			$frmHost->addVar('notes',$notes);
		}

		$profile_wdgt = new CWidget();
		$profile_wdgt->setClass('header');
		$profile_wdgt->addHeader(S_PROFILE);
		$profile_wdgt->addItem($profile_tbl);
// } PROFILE WIDGET

// EXT PROFILE WIDGET {
		$ext_profile_tbl = new CTable('', 'tablestripped');
		$ext_profile_tbl->setOddRowClass('form_odd_row');
		$ext_profile_tbl->setEvenRowClass('form_even_row');
		$ext_profile_tbl->addRow(array(S_USE_EXTENDED_PROFILE,new CCheckBox('useprofile_ext',$useprofile_ext,'submit()','yes')));

		foreach($ext_profiles_fields as $prof_field => $caption){
			if($useprofile_ext == 'yes'){
				$ext_profile_tbl->addRow(array($caption,new CTextBox('ext_host_profiles['.$prof_field.']',$ext_host_profiles[$prof_field],40)));
			}
			else{
				$frmHost->addVar('ext_host_profiles['.$prof_field.']',	$ext_host_profiles[$prof_field]);
			}
		}

		$ext_profile_wdgt = new CWidget();
		$ext_profile_wdgt->setClass('header');
		$ext_profile_wdgt->addHeader(S_EXTENDED_HOST_PROFILE);
		$ext_profile_wdgt->addItem($ext_profile_tbl);
// } EXT PROFILE WIDGET

		$left_table = new CTable();
		$left_table->setCellPadding(4);
		$left_table->setCellSpacing(4);
		$left_table->addRow($host_wdgt);

		$right_table = new CTable();
		$right_table->setCellPadding(4);
		$right_table->setCellSpacing(4);
		$right_table->addRow($template_wdgt);
		$right_table->addRow($macros_wdgt);
		$right_table->addRow($profile_wdgt);
		$right_table->addRow($ext_profile_wdgt);


		$td_l = new CCol($left_table);
		$td_l->setAttribute('valign','top');
		$td_r = new CCol($right_table);
		$td_r->setAttribute('valign','top');

		$outer_table = new CTable();
		$outer_table->addRow(array($td_l, $td_r));

		$frmHost->addItem($outer_table);
		return $frmHost;
	}

// Insert host profile ReadOnly form
	function insert_host_profile_form(){

		$frmHostP = new CFormTable(S_HOST_PROFILE);

		$table_titles = array(
			'devicetype' => S_DEVICE_TYPE, 'name' => S_NAME, 'os' => S_OS, 'serialno' => S_SERIALNO,
			'tag' => S_TAG, 'macaddress' => S_MACADDRESS, 'hardware' => S_HARDWARE, 'software' => S_SOFTWARE,
			'contact' => S_CONTACT, 'location' => S_LOCATION, 'notes' => S_NOTES
		);

		$sql_fields = implode(', ', array_keys($table_titles)); //generate string of fields to get from DB

		$sql = 'SELECT '.$sql_fields.' FROM hosts_profiles WHERE hostid='.$_REQUEST['hostid'];
		$result = DBselect($sql);

		if($row = DBfetch($result)) {
			foreach($row as $key => $value) {
				if(!zbx_empty($value)){
					$frmHostP->addRow($table_titles[$key], new CSpan(zbx_str2links($value)));
				}
			}
		}
		else{
			$frmHostP->addSpanRow(S_PROFILE_FOR_THIS_HOST_IS_MISSING,"form_row_c");
		}
		$frmHostP->addItemToBottomRow(new CButtonCancel(url_param('groupid').url_param('prof_type')));

		return $frmHostP;
	}

// BEGIN: HOSTS PROFILE EXTENDED Section
	function insert_host_profile_ext_form(){

		$frmHostPA = new CFormTable(S_EXTENDED_HOST_PROFILE);

		$table_titles = array(
				'device_alias' => S_DEVICE_ALIAS, 'device_type' => S_DEVICE_TYPE, 'device_chassis' => S_DEVICE_CHASSIS, 'device_os' => S_DEVICE_OS,
				'device_os_short' => S_DEVICE_OS_SHORT, 'device_hw_arch' => S_DEVICE_HW_ARCH, 'device_serial' => S_DEVICE_SERIAL,
				'device_model' => S_DEVICE_MODEL, 'device_tag' => S_DEVICE_TAG, 'device_vendor' => S_DEVICE_VENDOR, 'device_contract' => S_DEVICE_CONTRACT,
				'device_who' => S_DEVICE_WHO, 'device_status' => S_DEVICE_STATUS, 'device_app_01' => S_DEVICE_APP_01, 'device_app_02' => S_DEVICE_APP_02,
				'device_app_03' => S_DEVICE_APP_03, 'device_app_04' => S_DEVICE_APP_04, 'device_app_05' => S_DEVICE_APP_05, 'device_url_1' => S_DEVICE_URL_1,
				'device_url_2' => S_DEVICE_URL_2, 'device_url_3' => S_DEVICE_URL_3, 'device_networks' => S_DEVICE_NETWORKS, 'device_notes' => S_DEVICE_NOTES,
				'device_hardware' => S_DEVICE_HARDWARE, 'device_software' => S_DEVICE_SOFTWARE, 'ip_subnet_mask' => S_IP_SUBNET_MASK, 'ip_router' => S_IP_ROUTER,
				'ip_macaddress' => S_IP_MACADDRESS, 'oob_ip' => S_OOB_IP, 'oob_subnet_mask' => S_OOB_SUBNET_MASK, 'oob_router' => S_OOB_ROUTER,
				'date_hw_buy' => S_DATE_HW_BUY, 'date_hw_install' => S_DATE_HW_INSTALL, 'date_hw_expiry' => S_DATE_HW_EXPIRY, 'date_hw_decomm' => S_DATE_HW_DECOMM,
				'site_street_1' => S_SITE_STREET_1, 'site_street_2' => S_SITE_STREET_2, 'site_street_3' => S_SITE_STREET_3, 'site_city' => S_SITE_CITY,
				'site_state' => S_SITE_STATE, 'site_country' => S_SITE_COUNTRY, 'site_zip' => S_SITE_ZIP, 'site_rack' => S_SITE_RACK,
				'site_notes' => S_SITE_NOTES, 'poc_1_name' => S_POC_1_NAME, 'poc_1_email' => S_POC_1_EMAIL, 'poc_1_phone_1' => S_POC_1_PHONE_1,
				'poc_1_phone_2' => S_POC_1_PHONE_2, 'poc_1_cell' => S_POC_1_CELL, 'poc_1_notes' => S_POC_1_NOTES, 'poc_2_name' => S_POC_2_NAME,
				'poc_2_email' => S_POC_2_EMAIL, 'poc_2_phone_1' => S_POC_2_PHONE_1, 'poc_2_phone_2' => S_POC_2_PHONE_2, 'poc_2_cell' => S_POC_2_CELL,
				'poc_2_screen' => S_POC_2_SCREEN, 'poc_2_notes' => S_POC_2_NOTES);

		$sql_fields = implode(', ', array_keys($table_titles)); //generate string of fields to get from DB
		$result = DBselect('SELECT '.$sql_fields.' FROM hosts_profiles_ext WHERE hostid='.$_REQUEST['hostid']);

		if($row = DBfetch($result)) {
			foreach($row as $key => $value) {
				if(!zbx_empty($value)) {
					$frmHostPA->addRow($table_titles[$key], new CSpan(zbx_str2links($value)));
				}
			}
		}
		else{
			$frmHostPA->addSpanRow('Extended Profile for this host is missing','form_row_c');
		}
		$frmHostPA->addItemToBottomRow(new CButtonCancel(url_param('groupid').url_param('prof_type')));
	return $frmHostPA;
	}
// END:   HOSTS PROFILE EXTENDED Section

	function import_map_form($rules){
		global $USER_DETAILS;

		$form = new CFormTable(S_IMPORT, null, 'post', 'multipart/form-data');
		$form->addRow(S_IMPORT_FILE, new CFile('import_file'));

		$table = new CTable();
		$table->setHeader(array(S_ELEMENT, S_UPDATE.SPACE.S_EXISTING, S_ADD.SPACE.S_MISSING), 'bold');

		$titles = array('maps' => S_MAP);
		if($USER_DETAILS['type'] == USER_TYPE_SUPER_ADMIN){
			$titles += array('icons' => S_ICON, 'background' => S_BACKGROUND);
		}

		foreach($titles as $key => $title){
			$cbExist = new CCheckBox('rules['.$key.'][exist]', isset($rules[$key]['exist']));

			if($key != 'maps')
				$cbExist->setAttribute('onclick', 'javascript: if(this.checked) return confirm(\'Images for all maps will be updated\')');

			$cbMissed = new CCheckBox('rules['.$key.'][missed]', isset($rules[$key]['missed']));

			$table->addRow(array($title, $cbExist, $cbMissed));
		}

		$form->addRow(S_RULES, $table);

		$form->addItemToBottomRow(new CButton('import', S_IMPORT));
		return $form;
	}

	function insert_map_form(){
		$frm_title = 'New system map';

		if(isset($_REQUEST['sysmapid'])){
			$options = array(
				'sysmapids' => $_REQUEST['sysmapid'],
				'output' => API_OUTPUT_EXTEND
			);
			$sysmaps = CMap::get($options);
			$row = reset($sysmaps);
			$frm_title = 'System map: "'.$row['name'].'"';
		}

		if(isset($_REQUEST['sysmapid']) && !isset($_REQUEST['form_refresh'])){
			$name		= $row['name'];
			$width		= $row['width'];
			$height		= $row['height'];
			$backgroundid	= $row['backgroundid'];
			$label_type	= $row['label_type'];
			$label_location	= $row['label_location'];
			$highlight =	$row['highlight'];
			$markelements = $row['markelements'];
			$expandproblem = $row['expandproblem'];
			$show_unack = $row['show_unack'];
		}
		else{
			$name		= get_request('name','');
			$width		= get_request('width',800);
			$height		= get_request('height',600);
			$backgroundid	= get_request('backgroundid',0);
			$label_type	= get_request('label_type',0);
			$label_location	= get_request('label_location',0);
			$highlight = get_request('highlight',0);
			$markelements = get_request('markelements',0);
			$expandproblem = get_request('expandproblem',0);
			$show_unack = get_request('show_unack', 0);
		}

		$frmMap = new CFormTable($frm_title,'sysmaps.php');
		$frmMap->setHelp('web.sysmaps.map.php');

		if(isset($_REQUEST['sysmapid']))
			$frmMap->addVar('sysmapid',$_REQUEST['sysmapid']);

		$frmMap->addRow(S_NAME,new CTextBox('name',$name,32));
		$frmMap->addRow(S_WIDTH,new CNumericBox('width',$width,5));
		$frmMap->addRow(S_HEIGHT,new CNumericBox('height',$height,5));

		$cmbImg = new CComboBox('backgroundid',$backgroundid);
		$cmbImg->addItem(0,S_NO_IMAGE.'...');

		$result=DBselect('SELECT * FROM images WHERE imagetype=2 AND '.DBin_node('imageid').' order by name');
		while($row=DBfetch($result)){
			$cmbImg->addItem(
				$row['imageid'],
				get_node_name_by_elid($row['imageid'], null, ': ').$row['name']
			);
		}

		$frmMap->addRow(S_BACKGROUND_IMAGE,$cmbImg);
		$frmMap->addRow(S_ICON_HIGHLIGHTING, new CCheckBox('highlight',$highlight,null,1));
		$frmMap->addRow(S_MARK_ELEMENTS_ON_TRIGGER_STATUS_CHANGE, new CCheckBox('markelements',$markelements,null,1));
		$frmMap->addRow(S_EXPAND_SINGLE_PROBLEM, new CCheckBox('expandproblem',$expandproblem,null,1));


		$cmbLabel = new CComboBox('label_type',$label_type);
		$cmbLabel->addItem(0,S_LABEL);
		$cmbLabel->addItem(1,S_IP_ADDRESS);
		$cmbLabel->addItem(2,S_ELEMENT_NAME);
		$cmbLabel->addItem(3,S_STATUS_ONLY);
		$cmbLabel->addItem(4,S_NOTHING);
		$frmMap->addRow(S_ICON_LABEL_TYPE,$cmbLabel);

		$cmbLocation = new CComboBox('label_location',$label_location);

		$cmbLocation->addItem(0,S_BOTTOM);
		$cmbLocation->addItem(1,S_LEFT);
		$cmbLocation->addItem(2,S_RIGHT);
		$cmbLocation->addItem(3,S_TOP);
		$frmMap->addRow(S_ICON_LABEL_LOCATION,$cmbLocation);

		$config = select_config();
		$cb = new CComboBox('show_unack', $show_unack);
		$cb->addItems(array(
			EXTACK_OPTION_ALL => S_O_ALL,
			EXTACK_OPTION_BOTH => S_O_SEPARATED,
			EXTACK_OPTION_UNACK => S_O_UNACKNOWLEDGED_ONLY,
		));
		$cb->setEnabled($config['event_ack_enable']);
		if(!$config['event_ack_enable']){
			$cb->setAttribute('title', S_EVENT_ACKNOWLEDGING_DISABLED);
		}
		$frmMap->addRow(S_PROBLEM_DISPLAY, $cb);

		$frmMap->addItemToBottomRow(new CButton('save',S_SAVE));

		if(isset($_REQUEST['sysmapid'])){
			$frmMap->addItemToBottomRow(SPACE);
			$frmMap->addItemToBottomRow(new CButtonDelete(S_DELETE_SYSTEM_MAP_Q,
					url_param('form').url_param('sysmapid')));
		}

		$frmMap->addItemToBottomRow(SPACE);
		$frmMap->addItemToBottomRow(new CButtonCancel());

		return $frmMap;
	}

	function get_regexp_form(){
		$frm_title = S_REGULAR_EXPRESSION;

		if(isset($_REQUEST['regexpid']) && !isset($_REQUEST["form_refresh"])){
			$sql = 'SELECT re.* '.
				' FROM regexps re '.
				' WHERE '.DBin_node('re.regexpid').
					' AND re.regexpid='.$_REQUEST['regexpid'];
			$regexp = DBfetch(DBSelect($sql));

			$frm_title .= ' ['.$regexp['name'].']';

			$rename			= $regexp['name'];
			$test_string	= $regexp['test_string'];

			$expressions = array();
			$sql = 'SELECT e.* '.
					' FROM expressions e '.
					' WHERE '.DBin_node('e.expressionid').
						' AND e.regexpid='.$regexp['regexpid'].
					' ORDER BY e.expression_type';

			$db_exps = DBselect($sql);
			while($exp = DBfetch($db_exps)){
				$expressions[] = $exp;
			}
		}
		else{
			$rename			= get_request('rename','');
			$test_string	= get_request('test_string','');

			$expressions 	= get_request('expressions',array());
		}

		$tblRE = new CTable('','formtable nowrap');

		$tblRE->addRow(array(S_NAME, new CTextBox('rename', $rename, 60)));
		$tblRE->addRow(array(S_TEST_STRING, new CTextArea('test_string', $test_string, 66, 5)));

		$tabExp = new CTableInfo();

		$td1 = new CCol(S_EXPRESSION);
		$td2 = new CCol(S_EXPECTED_RESULT);
		$td3 = new CCol(S_RESULT);

		$tabExp->setHeader(array($td1,$td2,$td3));

		$final_result = !empty($test_string);

		foreach($expressions as $id => $expression){

			$results = array();
			$paterns = array($expression['expression']);

			if(!empty($test_string)){
				if($expression['expression_type'] == EXPRESSION_TYPE_ANY_INCLUDED){
					$paterns = explode($expression['exp_delimiter'],$expression['expression']);
				}

				if(uint_in_array($expression['expression_type'], array(EXPRESSION_TYPE_TRUE,EXPRESSION_TYPE_FALSE))){
					if($expression['case_sensitive'])
//						$results[$id] = ereg($paterns[0],$test_string);
						$results[$id] = preg_match('/'.$paterns[0].'/',$test_string);
					else
//						$results[$id] = eregi($paterns[0],$test_string);
						$results[$id] = preg_match('/'.$paterns[0].'/i',$test_string);

					if($expression['expression_type'] == EXPRESSION_TYPE_TRUE)
						$final_result &= $results[$id];
					else
						$final_result &= !$results[$id];
				}
				else{
					$results[$id] = true;

					$tmp_result = false;
					if($expression['case_sensitive']){
						foreach($paterns as $pid => $patern){
							$tmp_result |= (zbx_strstr($test_string,$patern) !== false);
						}
					}
					else{
						foreach($paterns as $pid => $patern){
							$tmp_result |= (zbx_stristr($test_string,$patern) !== false);
						}
					}

					if(uint_in_array($expression['expression_type'], array(EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED)))
						$results[$id] &= $tmp_result;
					else if($expression['expression_type'] == EXPRESSION_TYPE_NOT_INCLUDED){
						$results[$id] &= !$tmp_result;
					}
					$final_result &= $results[$id];
				}
			}

			if(isset($results[$id]) && $results[$id])
				$exp_res = new CSpan(S_TRUE_BIG,'green bold');
			else
				$exp_res = new CSpan(S_FALSE_BIG,'red bold');

			$expec_result = expression_type2str($expression['expression_type']);
			if(EXPRESSION_TYPE_ANY_INCLUDED == $expression['expression_type'])
				$expec_result.=' ('.S_DELIMITER."='".$expression['exp_delimiter']."')";

			$tabExp->addRow(array(
				$expression['expression'],
				$expec_result,
				$exp_res
			));
		}

		$td = new CCol(S_COMBINED_RESULT, 'bold');
		$td->setColSpan(2);

		if ($final_result) {
			$final_result = new CSpan(S_TRUE_BIG, 'green bold');
		}
		else {
			$final_result = new CSpan(S_FALSE_BIG, 'red bold');
		}

		$tabExp->addRow(array(
			$td,
			$final_result
		));

		$tblRE->addRow(array(S_RESULT, $tabExp));

		$tblFoot = new CTableInfo(null);

		$td = new CCol(array(new CButton('save', S_SAVE)));
		$td->setColSpan(2);
		$td->addStyle('text-align: right;');

		$td->addItem(SPACE);
		$td->addItem(new CButton('test', S_TEST));

		if (isset($_REQUEST['regexpid'])) {
			$td->addItem(SPACE);
			$td->addItem(new CButton('clone', S_CLONE));

			$td->addItem(SPACE);
			$td->addItem(new CButtonDelete(S_DELETE_REGULAR_EXPRESSION_Q, url_param('form').url_param('config').url_param('regexpid').url_param('delete', false, 'go')));
		}

		$td->addItem(SPACE);
		$td->addItem(new CButtonCancel(url_param("regexpid")));

		$tblFoot->setFooter($td);

		return array($tblRE, $tblFoot);
	}

	function get_expressions_tab() {

		if (isset($_REQUEST['regexpid']) && !isset($_REQUEST["form_refresh"])) {
			$expressions = array();
			$sql = 'SELECT e.* '.
					' FROM expressions e '.
					' WHERE '.DBin_node('e.expressionid').
						' AND e.regexpid='.$_REQUEST['regexpid'].
					' ORDER BY e.expression_type';

			$db_exps = DBselect($sql);
			while($exp = DBfetch($db_exps)){
				$expressions[] = $exp;
			}
		}
		else{
			$expressions 	= get_request('expressions',array());
		}

		$tblExp = new CTableInfo();
		$tblExp->setHeader(array(
				new CCheckBox('all_expressions',null,'checkAll("Regular expression","all_expressions","g_expressionid");'),
				S_EXPRESSION,
				S_EXPECTED_RESULT,
				S_CASE_SENSITIVE,
				S_EDIT
			));

//		zbx_rksort($timeperiods);
		foreach($expressions as $id => $expression){

			$exp_result = expression_type2str($expression['expression_type']);
			if(EXPRESSION_TYPE_ANY_INCLUDED == $expression['expression_type'])
				$exp_result.=' ('.S_DELIMITER."='".$expression['exp_delimiter']."')";

			$tblExp->addRow(array(
				new CCheckBox('g_expressionid[]', 'no', null, $id),
				$expression['expression'],
				$exp_result,
				$expression['case_sensitive']?S_YES:S_NO,
				new CButton('edit_expressionid['.$id.']',S_EDIT)
				));


			$tblExp->addItem(new Cvar('expressions['.$id.'][expression]',		$expression['expression']));
			$tblExp->addItem(new Cvar('expressions['.$id.'][expression_type]',	$expression['expression_type']));
			$tblExp->addItem(new Cvar('expressions['.$id.'][case_sensitive]',	$expression['case_sensitive']));
			$tblExp->addItem(new Cvar('expressions['.$id.'][exp_delimiter]',	$expression['exp_delimiter']));
		}

		$buttons = array();
		if(!isset($_REQUEST['new_expression'])){
			$buttons[] = new CButton('new_expression',S_NEW);
			$buttons[] = new CButton('delete_expression',S_DELETE);
		}

		$td = new CCol($buttons);
		$td->setAttribute('colspan','5');
		$td->setAttribute('style','text-align: right;');


		$tblExp->setFooter($td);

	return $tblExp;
	}

	function get_expression_form(){
		$tblExp = new CTable();

		/* init new_timeperiod variable */
		$new_expression = get_request('new_expression', array());

		if(is_array($new_expression) && isset($new_expression['id'])){
			$tblExp->addItem(new Cvar('new_expression[id]',$new_expression['id']));
		}

		if(!is_array($new_expression)){
			$new_expression = array();
		}

		if(!isset($new_expression['expression']))			$new_expression['expression']		= '';
		if(!isset($new_expression['expression_type']))		$new_expression['expression_type']	= EXPRESSION_TYPE_INCLUDED;
		if(!isset($new_expression['case_sensitive']))		$new_expression['case_sensitive']	= 0;
		if(!isset($new_expression['exp_delimiter']))		$new_expression['exp_delimiter']	= ',';

		$tblExp->addRow(array(S_EXPRESSION, new CTextBox('new_expression[expression]',$new_expression['expression'],60)));

		$cmbType = new CComboBox('new_expression[expression_type]',$new_expression['expression_type'],'javascript: submit();');
		$cmbType->addItem(EXPRESSION_TYPE_INCLUDED,expression_type2str(EXPRESSION_TYPE_INCLUDED));
		$cmbType->addItem(EXPRESSION_TYPE_ANY_INCLUDED,expression_type2str(EXPRESSION_TYPE_ANY_INCLUDED));
		$cmbType->addItem(EXPRESSION_TYPE_NOT_INCLUDED,expression_type2str(EXPRESSION_TYPE_NOT_INCLUDED));
		$cmbType->addItem(EXPRESSION_TYPE_TRUE,expression_type2str(EXPRESSION_TYPE_TRUE));
		$cmbType->addItem(EXPRESSION_TYPE_FALSE,expression_type2str(EXPRESSION_TYPE_FALSE));

		$tblExp->addRow(array(S_EXPRESSION_TYPE,$cmbType));

		if(EXPRESSION_TYPE_ANY_INCLUDED == $new_expression['expression_type']){
			$cmbDelimiter = new CComboBox('new_expression[exp_delimiter]',$new_expression['exp_delimiter']);
			$cmbDelimiter->addItem(',',',');
			$cmbDelimiter->addItem('.','.');
			$cmbDelimiter->addItem('/','/');

			$tblExp->addRow(array(S_DELIMITER,$cmbDelimiter));
		}
		else{
			$tblExp->addItem(new Cvar('new_expression[exp_delimiter]',$new_expression['exp_delimiter']));
		}

		$chkbCase = new CCheckBox('new_expression[case_sensitive]', $new_expression['case_sensitive'],null,1);

		$tblExp->addRow(array(S_CASE_SENSITIVE,$chkbCase));

		$tblExpFooter = new CTableInfo($tblExp);

		$oper_buttons = array();

		$oper_buttons[] = new CButton('add_expression',isset($new_expression['id'])?S_SAVE:S_ADD);
		$oper_buttons[] = new CButton('cancel_new_expression',S_CANCEL);

		$td = new CCol($oper_buttons);
		$td->setAttribute('colspan',2);
		$td->setAttribute('style','text-align: right;');

		$tblExpFooter->setFooter($td);
// end of condition list preparation
	return $tblExpFooter;
	}

/**
* returns Ctable object with host header
*
* {@source}
* @access public
* @static
* @version 1
*
* @param string $hostid
* @param array $elemnts [items, triggers, graphs, applications]
* @return object
*/
	function get_header_host_table($hostid, $elements){
		$header_host_opt = array(
			'hostids' => $hostid,
			'extendoutput' => 1,
			'templated_hosts' => 1,
		);
		if(str_in_array('items', $elements))
			$header_host_opt['select_items'] = 1;
		if(str_in_array('triggers', $elements))
			$header_host_opt['select_triggers'] = 1;
		if(str_in_array('graphs', $elements))
			$header_host_opt['select_graphs'] = 1;
		if(str_in_array('applications', $elements))
			$header_host_opt['select_applications'] = 1;

		$header_host = CHost::get($header_host_opt);
		$header_host = array_pop($header_host);


		$description = array();
		if($header_host['proxy_hostid']){
			$proxy = get_host_by_hostid($header_host['proxy_hostid']);
			$description[] = $proxy['host'].':';
		}
		$description[] = $header_host['host'];

		if(str_in_array('items', $elements)){
			$items = array(new CLink(S_ITEMS, 'items.php?hostid='.$header_host['hostid']),
				' ('.count($header_host['items']).')');
		}
		if(str_in_array('triggers', $elements)){
			$triggers = array(new CLink(S_TRIGGERS, 'triggers.php?hostid='.$header_host['hostid']),
				' ('.count($header_host['triggers']).')');
		}
		if(str_in_array('graphs', $elements)){
			$graphs = array(new CLink(S_GRAPHS, 'graphs.php?hostid='.$header_host['hostid']),
				' ('.count($header_host['graphs']).')');
		}
		if(str_in_array('applications', $elements)){
			$applications = array(new CLink(S_APPLICATIONS, 'applications.php?hostid='.$header_host['hostid']),
				' ('.count($header_host['applications']).')');
		}

		$tbl_header_host = new CTable();
		if($header_host['status'] == HOST_STATUS_TEMPLATE){

			$tbl_header_host->addRow(array(
				new CLink(bold(S_TEMPLATE_LIST), 'templates.php?templateid='.$header_host['hostid'].url_param('groupid')),
				(str_in_array('applications', $elements) ? $applications : null),
				(str_in_array('items', $elements) ? $items : null),
				(str_in_array('triggers', $elements) ? $triggers : null),
				(str_in_array('graphs', $elements) ? $graphs : null),
				array(bold(S_TEMPLATE.': '), $description)
			));
		}
		else{
			$dns = empty($header_host['dns']) ? '-' : $header_host['dns'];
			$ip = empty($header_host['ip']) ? '-' : $header_host['ip'];
			$port = empty($header_host['port']) ? '-' : $header_host['port'];
			if(1 == $header_host['useip'])
				$ip = bold($ip);
			else
				$dns = bold($dns);

			switch($header_host['status']){
				case HOST_STATUS_MONITORED:
					$status=new CSpan(S_MONITORED, 'off');
					break;
				case HOST_STATUS_NOT_MONITORED:
					$status=new CSpan(S_NOT_MONITORED, 'off');
					break;
				default:
					$status=S_UNKNOWN;
			}

			if($header_host['available'] == HOST_AVAILABLE_TRUE)
				$available=new CSpan(S_AVAILABLE,'off');
			else if($header_host['available'] == HOST_AVAILABLE_FALSE)
				$available=new CSpan(S_NOT_AVAILABLE,'on');
			else if($header_host['available'] == HOST_AVAILABLE_UNKNOWN)
				$available=new CSpan(S_UNKNOWN,'unknown');

			$tbl_header_host->addRow(array(
				new CLink(bold(S_HOST_LIST), 'hosts.php?hostid='.$header_host['hostid'].url_param('groupid')),
				(str_in_array('applications', $elements) ? $applications : null),
				(str_in_array('items', $elements) ? $items : null),
				(str_in_array('triggers', $elements) ? $triggers : null),
				(str_in_array('graphs', $elements) ? $graphs : null),
				array(bold(S_HOST.': '),$description),
				array(bold(S_DNS.': '), $dns),
				array(bold(S_IP.': '), $ip),
				array(bold(S_PORT.': '), $port),
				array(bold(S_STATUS.': '), $status),
				array(bold(S_AVAILABILITY.': '), $available)
			));
		}
		$tbl_header_host->setClass('infobox');

		return $tbl_header_host;
	}

	function get_macros_widget($hostid = null){

		if(isset($_REQUEST['form_refresh'])){
			$macros = get_request('macros', array());
		}
		else if($hostid > 0){
			$macros = CUserMacro::get(array('output' => API_OUTPUT_EXTEND, 'hostids' => $hostid));
			order_result($macros, 'macro');
		}
		else if($hostid === null){
			$macros = CUserMacro::get(array('output' => API_OUTPUT_EXTEND, 'globalmacro' => 1));
			order_result($macros, 'macro');
		}
		else{
			$macros = array();
		}

		if(empty($macros)){
			$macros = array(0 => array('macro' => '', 'value' => ''));
		}

		$macros_tbl = new CTable(SPACE, 'formElementTable');
		$macros_tbl->setAttribute('id', 'tbl_macros');
		$macros_tbl->addRow(array(SPACE, S_MACRO, SPACE, S_VALUE));

		insert_js('
			function addMacroRow(){

				if(typeof(addMacroRow.macro_count) == "undefined"){
					addMacroRow.macro_count = '.count($macros).';
				}

				var tr = document.createElement("tr");
				tr.className = (addMacroRow.macro_count % 2) ? "form_even_row" : "form_odd_row";

				var td1 = document.createElement("td");
				tr.appendChild(td1);

				var cb = document.createElement("input");
				cb.setAttribute("type", "checkbox");
				cb.className = "checkbox";
				td1.appendChild(cb);
				td1.appendChild(document.createTextNode(" "));

				var td2 = document.createElement("td");
				tr.appendChild(td2);

				var text1 = document.createElement("input");
				text1.setAttribute("type", "text");
				text1.setAttribute("name", "macros["+addMacroRow.macro_count+"][macro]");
				text1.className = "biginput";
				text1.setAttribute("size",30);
				text1.setAttribute("placeholder","{$MACRO}");
				text1.setAttribute("style", "text-transform:uppercase;");
				td2.appendChild(text1);
				td2.appendChild(document.createTextNode(" "));

				var td3 = document.createElement("td");
				tr.appendChild(td3);

				var span = document.createElement("span");
				span.innerHTML = "&rArr;";
				span.setAttribute("style", "vertical-align:top;");
				td3.appendChild(span);

				var td4 = document.createElement("td");
				tr.appendChild(td4);

				var text2 = document.createElement("input");
				text2.setAttribute("type", "text");
				text2.setAttribute("placeholder","<'.S_VALUE.'>");
				text2.setAttribute("name","macros["+addMacroRow.macro_count+"][value]");
				text2.className = "biginput";
				text2.setAttribute("size",40);
				td4.appendChild(text2);

				var sd = $("row_new_macro").insert({before : tr});
				addMacroRow.macro_count++;
			}
		');

		$macros = array_values($macros);
		foreach($macros as $macroid => $macro){
			$text1 = new CTextBox('macros['.$macroid.'][macro]', $macro['macro'], 30);
			$text1->setAttribute('placeholder', '{$MACRO}');
			$text1->setAttribute('style', 'text-transform:uppercase;');
			$text2 = new CTextBox('macros['.$macroid.'][value]', $macro['value'], 40);
			$text2->setAttribute('placeholder', '<'.S_VALUE.'>');
			$span = new CSpan(RARR);
			$span->addStyle('vertical-align:top;');
			$checkbox = new CCheckBox();
			if (empty($macro['macro']) || (!empty($macro['type']) && $macro['type'] == 'new')) {
				$checkbox->setAttribute('value', 'no');
			}

			$macros_tbl->addRow(array($checkbox, $text1, $span, $text2));
		}

		$script = '	$$("#tbl_macros input:checked").each(function(obj) {
						$(obj.parentNode.parentNode).remove();
						if ($(obj).value == \'yes\') {
							if (typeof(deleted_macro_cnt) == \'undefined\') {
								deleted_macro_cnt = 1;
							}
							else {
								deleted_macro_cnt++;
							}
						}
					});';
		$delete_btn = new CButton('macros_del', S_DELETE_SELECTED, $script);
		$delete_btn->setType('button');

		$add_button = new CButton('macro_add', S_ADD, 'javascript: addMacroRow()');
		$add_button->setType('button');

		$buttonRow = new CRow();
		$buttonRow->setAttribute('id', 'row_new_macro');

		$col = new CCol(array($add_button, SPACE, $delete_btn));
		$col->setAttribute('colspan', 4);
		$buttonRow->addItem($col);
		$macros_tbl->addRow($buttonRow);

		$footer = null;
		if($hostid === null){
			$footer = array(new CButton('save', S_SAVE, "if (deleted_macro_cnt > 0) return confirm('".S_ARE_YOU_SURE_YOU_WANT_TO_DELETE." '+deleted_macro_cnt+' ".S_MACROS_ES."?');"));
		}

		return new CFormElement(S_MACROS, $macros_tbl, $footer);

	}

?>

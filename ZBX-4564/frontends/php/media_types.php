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
require_once('include/config.inc.php');
require_once('include/media.inc.php');
require_once('include/forms.inc.php');

$page['title'] = 'S_MEDIA_TYPES';
$page['file'] = 'media_types.php';
$page['hist_arg'] = array();

include_once('include/page_header.php');

?>
<?php
	$fields=array(
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION

// media form
		'media_types'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'mediatypeid'=>		array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,'(isset({form})&&({form}=="update"))'),
		'type'=>			array(T_ZBX_INT, O_OPT,	NULL,	IN(implode(',',array_keys(media_type2str()))),'(isset({save}))'),
		'description'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,'(isset({save}))'),
		'smtp_server'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,'isset({type})&&({type}=='.MEDIA_TYPE_EMAIL.')&&isset({save})'),
		'smtp_helo'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,'isset({type})&&({type}=='.MEDIA_TYPE_EMAIL.')&&isset({save})'),
		'smtp_email'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,'isset({type})&&({type}=='.MEDIA_TYPE_EMAIL.')&&isset({save})'),
		'exec_path'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,'isset({type})&&({type}=='.MEDIA_TYPE_EXEC.')&&isset({save})'),
		'gsm_modem'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,'isset({type})&&({type}=='.MEDIA_TYPE_SMS.')&&isset({save})'),
		'username'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,'isset({type})&&({type}=='.MEDIA_TYPE_JABBER.'||{type}=='.MEDIA_TYPE_EZ_TEXTING.')&&isset({save})'),
		'password'=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL, NULL),
/* actions */
		'save'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'go'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
/* other */
		'form'=>			array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);

	check_fields($fields);
	validate_sort_and_sortorder('description',ZBX_SORT_UP);
?>
<?php

// MEDIATYPE ACTIONS
	$_REQUEST['go'] = get_request('go', 'none');

	if(isset($_REQUEST['save'])){
		$mediatype = array(
			'type' => $_REQUEST['type'],
			'description' => $_REQUEST['description'],
			'smtp_server' => get_request('smtp_server'),
			'smtp_helo' => get_request('smtp_helo'),
			'smtp_email' => get_request('smtp_email'),
			'exec_path' => get_request('exec_path'),
			'gsm_modem' => get_request('gsm_modem'),
			'username' => get_request('username'),
			'passwd' => get_request('password')
		);
		
		if(is_null($mediatype['passwd'])) unset($mediatype['passwd']);

		if(isset($_REQUEST['mediatypeid'])){
			$action = AUDIT_ACTION_UPDATE;
			$mediatype['mediatypeid'] = $_REQUEST['mediatypeid'];

			$result = CMediatype::update($mediatype);
			show_messages($result, S_MEDIA_TYPE_UPDATED, S_MEDIA_TYPE_WAS_NOT_UPDATED);
		}
		else{
			$action = AUDIT_ACTION_ADD;
			$result = CMediatype::create($mediatype);
			show_messages($result, S_ADDED_NEW_MEDIA_TYPE, S_NEW_MEDIA_TYPE_WAS_NOT_ADDED);
		}

		if($result){
			add_audit($action, AUDIT_RESOURCE_MEDIA_TYPE,'Media type ['.$_REQUEST['description'].']');
			unset($_REQUEST['form']);
		}
	}
	else if(isset($_REQUEST['delete'])&&isset($_REQUEST['mediatypeid'])){
		$result = CMediatype::delete($_REQUEST['mediatypeid']);
		show_messages($result, S_MEDIA_TYPE_DELETED, S_MEDIA_TYPE_WAS_NOT_DELETED);

		if($result){
			unset($_REQUEST['form']);
		}
	}
// GO ACTIONS
// ---------------------------------------------------------------------------
	else if($_REQUEST['go'] == 'delete'){
		$go_result = true;
		$media_types = get_request('media_types', array());

		$go_result = CMediatype::delete($media_types);

		if($go_result) unset($_REQUEST['form']);
		show_messages($go_result, S_MEDIA_TYPE_DELETED, S_MEDIA_TYPE_WAS_NOT_DELETED);
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}

?>
<?php
	$medias_wdgt = new CWidget();

	$form = new CForm(null, 'get');

	if(!isset($_REQUEST['form']))
		$form->addItem(new CButton('form', S_CREATE_MEDIA_TYPE));

	$medias_wdgt->addPageHeader(S_CONFIGURATION_OF_MEDIA_TYPES_BIG, $form);

?>
<?php
	if(isset($_REQUEST['form'])){
		if(isset($_REQUEST['mediatypeid'])){
			$options = array(
				'mediatypeids' => $_REQUEST['mediatypeid'],
				'output' => API_OUTPUT_EXTEND,
			);
			$mediatypes = CMediatype::get($options);
			$mediatype = reset($mediatypes);
			$password = $mediatype['passwd'];
		}
		
		if(isset($_REQUEST['mediatypeid']) && !isset($_REQUEST['form_refresh'])){
			$mediatypeid = $mediatype['mediatypeid'];
			$type = $mediatype['type'];
			$description = $mediatype['description'];
			$smtp_server = $mediatype['smtp_server'];
			$smtp_helo = $mediatype['smtp_helo'];
			$smtp_email = $mediatype['smtp_email'];
			$exec_path = $mediatype['exec_path'];
			$gsm_modem = $mediatype['gsm_modem'];
			$username = $mediatype['username'];
		}
		else{
			$type = get_request('type', 0);
			$description = get_request('description', '');
			$smtp_server = get_request('smtp_server', 'localhost');
			$smtp_helo = get_request('smtp_helo', 'localhost');
			$smtp_email = get_request('smtp_email', 'zabbix@localhost');
			$exec_path = get_request('exec_path', '');
			$gsm_modem = get_request('gsm_modem', '/dev/ttyS0');

			$default_username = ($type == MEDIA_TYPE_EZ_TEXTING) ? 'username' : 'user@server';
			$username = get_request('username', $default_username);
		}

		$frmMedia = new CFormTable(S_MEDIA);
//		$frmMeadia->setHelp('web.config.medias.php');

		if(isset($_REQUEST['mediatypeid'])){
			$frmMedia->addVar('mediatypeid', $_REQUEST['mediatypeid']);
		}

		$frmMedia->addRow(S_DESCRIPTION, new CTextBox('description', $description, 30));

		$cmbType = new CComboBox('type', $type, 'submit()');
		$cmbType->addItems(array(
			MEDIA_TYPE_EMAIL => S_EMAIL,
			MEDIA_TYPE_EXEC => S_SCRIPT,
			MEDIA_TYPE_SMS => S_SMS,
			MEDIA_TYPE_JABBER => S_JABBER,
		));
		$cmbType->addItemsInGroup(S_COMMERCIAL, array(MEDIA_TYPE_EZ_TEXTING => S_EZ_TEXTING));
		
		$row = array($cmbType);
		if($type == MEDIA_TYPE_EZ_TEXTING){
			$ez_texting_link = new CLink('https://app.eztexting.com', 'https://app.eztexting.com/', null, null, 'nosid');
			$ez_texting_link->setTarget('_blank');
			$row[] = $ez_texting_link;
		}
		$frmMedia->addRow(S_TYPE, $row);

		if($type == MEDIA_TYPE_EMAIL){
			$frmMedia->addRow(S_SMTP_SERVER,new CTextBox('smtp_server', $smtp_server, 30));
			$frmMedia->addRow(S_SMTP_HELO,new CTextBox('smtp_helo', $smtp_helo, 30));
			$frmMedia->addRow(S_SMTP_EMAIL,new CTextBox('smtp_email', $smtp_email, 30));
		}
		else if($type == MEDIA_TYPE_SMS){
			$frmMedia->addRow(S_GSM_MODEM,new CTextBox('gsm_modem', $gsm_modem, 50));
		}
		else if($type == MEDIA_TYPE_EXEC){
			$frmMedia->addRow(S_SCRIPT_NAME,new CTextBox('exec_path', $exec_path, 50));
		}
		else if($type == MEDIA_TYPE_JABBER || $type == MEDIA_TYPE_EZ_TEXTING){
			if(isset($_REQUEST['mediatypeid']) && !empty($password)){
				$chPass_btn = new CButton('chPass_btn', S_CHANGE_PASSWORD, 'this.style.display="none"; $("password").enable().show().focus();', false);
				$pass_txt = new CPassBox('password', '', 30);
				$pass_txt->setAttribute('disabled', 'disabled');
				$pass_txt->addStyle('display: none;');
				$pass_fields = array($chPass_btn, $pass_txt);
			}
			else{
				$pass_fields = new CPassBox('password', '', 30);
			}

			if($type == MEDIA_TYPE_JABBER){
				$frmMedia->addRow(S_JABBER_IDENTIFIER, new CTextBox('username', $username, 30));
				$frmMedia->addRow(S_PASSWORD, $pass_fields);
			}
			else{
				$frmMedia->addRow(S_USERNAME, new CTextBox('username', $username, 30));
				$frmMedia->addRow(S_PASSWORD, $pass_fields);
				$limit_cb = new CComboBox('exec_path', $exec_path);
				$limit_cb->addItems(array(
					EZ_TEXTING_LIMIT_USA => S_EZ_TEXTING_USA, 
					EZ_TEXTING_LIMIT_CANADA => S_EZ_TEXTING_CANADA,
				));
				$frmMedia->addRow(S_MESSAGE_TEXT_LIMIT, $limit_cb);
			}
		}

		$frmMedia->addItemToBottomRow(new CButton('save', S_SAVE));
		if(isset($_REQUEST['mediatypeid'])){
			$frmMedia->addItemToBottomRow(array(SPACE, new CButtonDelete(S_DELETE_SELECTED_MEDIA,
				url_param('form').url_param('mediatypeid'))));
		}
		$frmMedia->addItemToBottomRow(array(SPACE, new CButtonCancel()));

		$medias_wdgt->addItem($frmMedia);
	}
	else{
		$numrows = new CDiv();
		$numrows->setAttribute('name','numrows');

		$medias_wdgt->addHeader(S_MEDIA_TYPES_BIG);
		$medias_wdgt->addHeader($numrows);

		$form = new CForm();
		$form->setName('frm_media_types');

		$table=new CTableInfo(S_NO_MEDIA_TYPES_DEFINED);
		$table->setHeader(array(
			new CCheckBox('all_media_types', NULL, "checkAll('".$form->getName()."','all_media_types','media_types');"),
			make_sorting_header(S_DESCRIPTION,'description'),
			make_sorting_header(S_TYPE,'type'),
			S_DETAILS
		));

// Mediatype table
		$sortfield = getPageSortField('description');
		$sortorder = getPageSortOrder();

		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'editable' => 1,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => ($config['search_limit']+1)
		);
		$mediatypes = CMediatype::get($options);
		order_result($mediatypes, $sortfield, $sortorder);

		$paging = getPagingLine($mediatypes);

		foreach($mediatypes as $mnum => $mediatype){
			switch($mediatype['type']){
				case MEDIA_TYPE_EMAIL:
					$details =
						S_SMTP_SERVER.': "'.$mediatype['smtp_server'].'", '.
						S_SMTP_HELO.': "'.$mediatype['smtp_helo'].'", '.
						S_SMTP_EMAIL.': "'.$mediatype['smtp_email'].'"';
					break;
				case MEDIA_TYPE_EXEC:
					$details = S_SCRIPT_NAME.': "'.$mediatype['exec_path'].'"';
					break;
				case MEDIA_TYPE_SMS:
					$details = S_GSM_MODEM.': "'.$mediatype['gsm_modem'].'"';
					break;
				case MEDIA_TYPE_JABBER:
					$details = S_JABBER_IDENTIFIER.': "'.$mediatype['username'].'"';
					break;
				case MEDIA_TYPE_EZ_TEXTING:
					$details = S_USERNAME.': "'.$mediatype['username'].'"';
					break;
				default:
					$details = '';
			}

			$table->addRow(array(
				new CCheckBox('media_types['.$mediatype['mediatypeid'].']',NULL,NULL,$mediatype['mediatypeid']),
				new CLink($mediatype['description'],'?form=update&mediatypeid='.$mediatype['mediatypeid']),
				media_type2str($mediatype['type']),
				$details
			));
		}

//----- GO ------
		$goBox = new CComboBox('go');

		$goOption = new CComboItem('delete',S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_MEDIATYPES_Q);
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO.' (0)');
		$goButton->setAttribute('id','goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "media_types";');

		$footer = get_table_header(array($goBox, $goButton));

		$form->addItem(array($paging, $table, $paging, $footer));
		$medias_wdgt->addItem($form);
	}

	$medias_wdgt->show();
?>
<?php

include_once('include/page_footer.php');
?>

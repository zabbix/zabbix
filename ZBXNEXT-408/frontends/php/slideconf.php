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
require_once('include/screens.inc.php');
require_once('include/forms.inc.php');
require_once('include/maps.inc.php');

$page['type'] = detect_page_type(PAGE_TYPE_HTML);
$page['title'] = 'S_CONFIGURATION_OF_SLIDESHOWS';
$page['file'] = 'slideconf.php';
$page['hist_arg'] = array();

include_once('include/page_header.php');

?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'shows'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),

		'slideshowid'=>	array(T_ZBX_INT, O_NO,	 P_SYS,	DB_ID,			'(isset({form})&&({form}=="update"))'),
		'name'=>		array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,		'isset({save})'),
		'delay'=>		array(T_ZBX_INT, O_OPT,  null,	BETWEEN(1,86400), 'isset({save})'),

		'steps'=>		array(null,	O_OPT,	null,	null,	null),
		'new_step'=>	array(null,	O_OPT,	null,	null,	null),

		'move_up'=>		array(T_ZBX_INT, O_OPT,  P_ACT,  BETWEEN(0,65534), null),
		'move_down'=>	array(T_ZBX_INT, O_OPT,  P_ACT,  BETWEEN(0,65534), null),

		'edit_step'=>		array(T_ZBX_INT, O_OPT,  P_ACT,  BETWEEN(0,65534), null),
		'add_step'=>		array(T_ZBX_STR, O_OPT,  P_ACT,  null, null),
		'cancel_step'=>		array(T_ZBX_STR, O_OPT,  P_ACT,  null, null),

		'sel_step'=>		array(T_ZBX_INT, O_OPT,  P_ACT,  BETWEEN(0,65534), null),
		'del_sel_step'=>	array(T_ZBX_STR, O_OPT,  P_ACT,  null, null),

// actions
		'go'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'clone'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel'=>		array(T_ZBX_STR, O_OPT, P_SYS,			null,	null),
		'form'=>		array(T_ZBX_STR, O_OPT, P_SYS,			null,	null),
		'form_refresh'=>array(T_ZBX_INT, O_OPT,	null,			null,	null),
	);

	check_fields($fields);
	validate_sort_and_sortorder('s.name',ZBX_SORT_UP);

	if(isset($_REQUEST['slideshowid'])){
		if(!slideshow_accessible($_REQUEST['slideshowid'], PERM_READ_WRITE))
			access_deny();
	}
?>
<?php
	$_REQUEST['go'] = get_request('go', 'none');

	if(isset($_REQUEST['slideshowid'])){
		if(!slideshow_accessible($_REQUEST['slideshowid'], PERM_READ_WRITE))
			access_deny();
	}

	if(isset($_REQUEST['clone']) && isset($_REQUEST['slideshowid'])){
		unset($_REQUEST['slideshowid']);
		$_REQUEST['form'] = 'clone';
	}
	else if(isset($_REQUEST['save'])){
		$slides = get_request('steps', array());

		if(isset($_REQUEST['slideshowid'])){ /* update */
			DBstart();
			$result = update_slideshow($_REQUEST['slideshowid'],$_REQUEST['name'],$_REQUEST['delay'],$slides);
			$result = DBend($result);

			$audit_action = AUDIT_ACTION_UPDATE;
			show_messages($result, S_SLIDESHOW_UPDATED, S_CANNOT_UPDATE_SLIDESHOW);
		}
		else{ /* add */
			DBstart();
			$slideshowid = add_slideshow($_REQUEST['name'],$_REQUEST['delay'],$slides);
			$result = DBend($slideshowid);

			$audit_action = AUDIT_ACTION_ADD;
			show_messages($result, S_SLIDESHOW_ADDED, S_CANNOT_ADD_SLIDESHOW);
		}

		if($result){
			add_audit($audit_action,AUDIT_RESOURCE_SLIDESHOW," Name [".$_REQUEST['name']."] ");
			unset($_REQUEST['form'], $_REQUEST['slideshowid']);
		}
	}
	else if(isset($_REQUEST['cancel_step'])){
		unset($_REQUEST['add_step'], $_REQUEST['new_step']);
	}
	else if(isset($_REQUEST['add_step'])){
		if(isset($_REQUEST['new_step'])){
			if(isset($_REQUEST['new_step']['sid']))
				$_REQUEST['steps'][$_REQUEST['new_step']['sid']] = $_REQUEST['new_step'];
			else if($_REQUEST['new_step']['screenid']>0)
				$_REQUEST['steps'][] = $_REQUEST['new_step'];

			unset($_REQUEST['add_step'], $_REQUEST['new_step']);
		}
		else{
			$_REQUEST['new_step'] = array();
		}
	}
	else if(isset($_REQUEST['edit_step'])){
		$_REQUEST['new_step'] = $_REQUEST['steps'][$_REQUEST['edit_step']];
		$_REQUEST['new_step']['sid'] = $_REQUEST['edit_step'];
	}
	else if(isset($_REQUEST['del_sel_step'])&&isset($_REQUEST['sel_step'])&&is_array($_REQUEST['sel_step'])){
		foreach($_REQUEST['sel_step'] as $sid)
			if(isset($_REQUEST['steps'][$sid]))
				unset($_REQUEST['steps'][$sid]);
	}
	else if(isset($_REQUEST['move_up']) && isset($_REQUEST['steps'][$_REQUEST['move_up']])){
		$new_id = $_REQUEST['move_up'] - 1;

		if(isset($_REQUEST['steps'][$new_id])){
			$tmp = $_REQUEST['steps'][$new_id];
			$_REQUEST['steps'][$new_id] = $_REQUEST['steps'][$_REQUEST['move_up']];
			$_REQUEST['steps'][$_REQUEST['move_up']] = $tmp;
		}
	}
	else if(isset($_REQUEST['move_down']) && isset($_REQUEST['steps'][$_REQUEST['move_down']])){
		$new_id = $_REQUEST['move_down'] + 1;

		if(isset($_REQUEST['steps'][$new_id])){
			$tmp = $_REQUEST['steps'][$new_id];
			$_REQUEST['steps'][$new_id] = $_REQUEST['steps'][$_REQUEST['move_down']];
			$_REQUEST['steps'][$_REQUEST['move_down']] = $tmp;
		}
	}
	else if(isset($_REQUEST['delete'])&&isset($_REQUEST['slideshowid'])){
		if($slideshow = get_slideshow_by_slideshowid($_REQUEST['slideshowid'])){

			DBstart();
				delete_slideshow($_REQUEST['slideshowid']);
			$result = DBend();

			show_messages($result, S_SLIDESHOW_DELETED, S_CANNOT_DELETE_SLIDESHOW);
			add_audit_if($result, AUDIT_ACTION_DELETE,AUDIT_RESOURCE_SLIDESHOW," Name [".$slideshow['name']."] ");
		}
		unset($_REQUEST['slideshowid']);
		unset($_REQUEST["form"]);
	}
	else if($_REQUEST['go'] == 'delete'){
		$go_result = true;
		$shows = get_request('shows', array());

		DBstart();
		foreach($shows as $showid){
			$go_result &= delete_slideshow($showid);
			if(!$go_result) break;
		}
		$go_result = DBend($go_result);

		if($go_result){
			unset($_REQUEST["form"]);
		}
		show_messages($go_result, S_SLIDESHOW_DELETED, S_CANNOT_DELETE_SLIDESHOW);
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}
?>
<?php
	$form = new CForm(null, 'get');
	$form->addItem(new CButton("form", S_CREATE_SLIDESHOW));

	$slide_wdgt = new CWidget();
	$slide_wdgt->addPageHeader(S_CONFIGURATION_OF_SLIDESHOWS_BIG, $form);

	if(isset($_REQUEST['form'])){
		$slide_wdgt->addItem(insert_slideshow_form());
	}
	else{

		$form = new CForm();
		$form->setName('shows');

		$numrows = new CDiv();
		$numrows->setAttribute('name','numrows');

		$slide_wdgt->addHeader(S_SLIDESHOWS_BIG);
		$slide_wdgt->addHeader($numrows);

		$table = new CTableInfo(S_NO_SLIDESHOWS_DEFINED);
		$table->setHeader(array(
			new CCheckBox('all_shows',NULL,"checkAll('".$form->getName()."','all_shows','shows');"),
			make_sorting_header(S_NAME,'s.name'),
			make_sorting_header(S_DELAY,'s.delay'),
			make_sorting_header(S_COUNT_OF_SLIDES,'cnt')
			));



		$sql = 'SELECT s.slideshowid, s.name, s.delay, count(sl.slideshowid) as cnt '.
			' FROM slideshows s '.
				' LEFT JOIN slides sl ON sl.slideshowid=s.slideshowid '.
			' WHERE '.DBin_node('s.slideshowid').
			' GROUP BY s.slideshowid,s.name,s.delay '.
			order_by('s.name,s.delay,cnt','s.slideshowid');
		$db_slides = DBselect($sql);

		// gathering all data we got from database in array, so we can feed it to pagination function
		$slides_arr = array();
		while($slide_data = DBfetch($db_slides)){
			$slides_arr[] = $slide_data;
		}

		// getting paging element
		$paging = getPagingLine($slides_arr);

		foreach($slides_arr as $slide_data){
			if(!slideshow_accessible($slide_data['slideshowid'], PERM_READ_WRITE)) continue;

			$table->addRow(array(
				new CCheckBox('shows['.$slide_data['slideshowid'].']', NULL, NULL, $slide_data['slideshowid']),
				new CLink($slide_data['name'],'?config=1&form=update&slideshowid='.$slide_data['slideshowid'],
					'action'),
				$slide_data['delay'],
				$slide_data['cnt']
				));


		}

		// adding paging to widget
		$table->addRow(new CCol($paging));
		$slide_wdgt->addItem($paging);

// goBox
		$goBox = new CComboBox('go');

		$goOption = new CComboItem('delete', S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_SLIDESHOWS_Q);
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO);
		$goButton->setAttribute('id','goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "shows";');

		$table->setFooter(new CCol(array($goBox, $goButton)));
//---------
		$form->addItem($table);

		$slide_wdgt->addItem($form);
	}

	$slide_wdgt->show();
?>
<?php

include_once('include/page_footer.php');

?>

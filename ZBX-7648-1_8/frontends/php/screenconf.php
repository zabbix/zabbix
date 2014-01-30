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
require_once('include/ident.inc.php');
require_once('include/forms.inc.php');
require_once('include/maps.inc.php');

if(isset($_REQUEST['go']) && ($_REQUEST['go'] == 'export') && isset($_REQUEST['screens'])){
	$EXPORT_DATA = true;

	$page['type'] = detect_page_type(PAGE_TYPE_XML);
	$page['file'] = 'zbx_screens_export.xml';

	require_once('include/export.inc.php');
}
else{
	$EXPORT_DATA = false;

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	$page['title'] = 'S_CONFIGURATION_OF_SCREENS';
	$page['file'] = 'screenconf.php';
	$page['hist_arg'] = array();
}

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'screens'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),

		'screenid'=>	array(T_ZBX_INT, O_NO,	 P_SYS,	DB_ID,			'(isset({form})&&({form}=="update"))'),
		'name'=>		array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,		'isset({save})'),
		'hsize'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(1,100),	'isset({save})'),
		'vsize'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(1,100),	'isset({save})'),

// actions
		'go'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
		'clone'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	null,	null,	null),

// Import
		'rules' =>			array(T_ZBX_STR, O_OPT,	null,	DB_ID,		null),
		'import' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL)
	);

	check_fields($fields);
	validate_sort_and_sortorder('s.name',ZBX_SORT_UP);

	$config_scr = $_REQUEST['config'] = get_request('config', 0);

	CProfile::update('web.screenconf.config', $_REQUEST['config'],PROFILE_TYPE_INT);

	if(isset($_REQUEST['screenid'])){
		$r = CScreen::get(array(
			'screenids' => $_REQUEST['screenid'],
			'editable' => 1,
			'output' => API_OUTPUT_SHORTEN
		));
		if(empty($r))
			access_deny();
	}
?>
<?php
// EXPORT ///////////////////////////////////
	if($EXPORT_DATA){
		$screens = get_request('screens', array());

		$options = array(
			'screenids' => $screens,
			'select_screenitems' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		);
		$screens = CScreen::get($options);

		prepareScreenExport($screens);

		$xml = zbxXML::arrayToXML(array('screens' => $screens));
		print($xml);

		exit();
	}

// IMPORT ///////////////////////////////////
	$rules = get_request('rules', array());
	if(!isset($_FILES['import_file'])){
		foreach(array('screen') as $key){
			$rules[$key]['exist'] = 1;
			$rules[$key]['missed'] = 1;
		}
	}


	if(isset($_FILES['import_file']) && is_file($_FILES['import_file']['tmp_name'])){
		require_once('include/export.inc.php');
		DBstart();

		$result = zbxXML::import($_FILES['import_file']['tmp_name']);
		if($result) $result = zbxXML::parseScreen($rules);

		$result = DBend($result);
		show_messages($result, S_IMPORTED.SPACE.S_SUCCESSEFULLY_SMALL, S_IMPORT.SPACE.S_FAILED_SMALL);
	}

?>
<?php
	$_REQUEST['go'] = get_request('go', 'none');

		if(isset($_REQUEST['clone']) && isset($_REQUEST['screenid'])){
			unset($_REQUEST['screenid']);
			$_REQUEST['form'] = 'clone';
		}
		else if(isset($_REQUEST['save'])){
			if(isset($_REQUEST['screenid'])){
				$screen = array(
					'screenid' => $_REQUEST['screenid'],
					'name' => $_REQUEST['name'],
					'hsize' => $_REQUEST['hsize'],
					'vsize' => $_REQUEST['vsize']
				);
				$result = CScreen::update($screen);

				$audit_action = AUDIT_ACTION_UPDATE;
				show_messages($result, S_SCREEN_UPDATED, S_CANNOT_UPDATE_SCREEN);
			}
			else{
				if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
					access_deny();

				$screen = array(
					'name' => $_REQUEST['name'],
					'hsize' => $_REQUEST['hsize'],
					'vsize' => $_REQUEST['vsize']
				);
				$result = CScreen::create($screen);

				$audit_action = AUDIT_ACTION_ADD;
				show_messages($result, S_SCREEN_ADDED, S_CANNOT_ADD_SCREEN);
			}
			if($result){
				add_audit($audit_action,AUDIT_RESOURCE_SCREEN,' Name ['.$_REQUEST['name'].'] ');
				unset($_REQUEST['form']);
				unset($_REQUEST['screenid']);
			}
		}
		if(isset($_REQUEST['delete']) && isset($_REQUEST['screenid']) || ($_REQUEST['go'] == 'delete')){
			$screenids = get_request('screens', array());
			if(isset($_REQUEST['screenid'])){
				$screenids[] = $_REQUEST['screenid'];
			}
			$screens = CScreen::get(array('screenids' => $screenids, 'output' => API_OUTPUT_EXTEND, 'editable => 1'));

			$go_result = CScreen::delete($screenids);

			if($go_result){
				unset($_REQUEST['screenid'], $_REQUEST['form']);
				foreach($screens as $screen){
					add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCREEN,
						$screen['screenid'],
						$screen['name'],
						null,null,null);
				}
			}

			show_messages($go_result, S_SCREEN_DELETED, S_CANNOT_DELETE_SCREEN);
		}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}
?>
<?php
	$form = new CForm(null, 'get');

		$form->addItem(new CButton("form", S_CREATE_SCREEN));
		$form->addItem(new CButton('form', S_IMPORT_SCREEN));

	$screen_wdgt = new CWidget();
	$screen_wdgt->addPageHeader(S_CONFIGURATION_OF_SCREENS_BIG, $form);

		if(isset($_REQUEST['form'])){
			if($_REQUEST['form'] == S_IMPORT_SCREEN)
				$screen_wdgt->addItem(import_screen_form($rules));
			else if(($_REQUEST['form'] == S_CREATE_SCREEN) || ($_REQUEST['form'] == 'update'))
				$screen_wdgt->addItem(insert_screen_form());
		}
		else{
			$form = new CForm();
			$form->setName('frm_screens');

			$numrows = new CDiv();
			$numrows->setAttribute('name', 'numrows');

			$screen_wdgt->addHeader(S_SCREENS_BIG);
			$screen_wdgt->addHeader($numrows);

			$table = new CTableInfo(S_NO_SCREENS_DEFINED);
			$table->setHeader(array(
				new CCheckBox('all_screens', NULL, "checkAll('".$form->getName()."','all_screens','screens');"),
				make_sorting_header(S_NAME, 'name'),
				S_DIMENSION_COLS_ROWS,
				S_SCREEN)
			);

			$sortfield = getPageSortField('name');
			$sortorder = getPageSortOrder();
			$options = array(
//				'select_screenitems' => API_OUTPUT_EXTEND,
				'editable' => 1,
				'output' => API_OUTPUT_EXTEND,
				'sortfield' => $sortfield,
				'sortorder' => $sortorder,
				'limit' => ($config['search_limit']+1)
			);

			$screens = CScreen::get($options);

			order_result($screens, $sortfield, $sortorder);
			$paging = getPagingLine($screens);

			foreach($screens as $num => $screen){
				$table->addRow(array(
					new CCheckBox('screens['.$screen['screenid'].']', NULL, NULL, $screen['screenid']),
					new CLink($screen["name"],'screenedit.php?screenid='.$screen['screenid']),
					$screen['hsize'].' x '.$screen['vsize'],
					new CLink(S_EDIT,'?config=0&form=update&screenid='.$screen['screenid'])
				));
			}

//goBox
			$goBox = new CComboBox('go');
			$goBox->addItem('export', S_EXPORT_SELECTED);

			$goOption = new CComboItem('delete', S_DELETE_SELECTED);
			$goOption->setAttribute('confirm', S_DELETE_SELECTED_SCREENS_Q);
			$goBox->addItem($goOption);

			// goButton name is necessary!!!
			$goButton = new CButton('goButton', S_GO);
			$goButton->setAttribute('id', 'goButton');

			zbx_add_post_js('chkbxRange.pageGoName = "screens";');
//---------
			$footer = get_table_header(array($goBox, $goButton));

			$table = array($paging, $table, $paging, $footer);
			$form->addItem($table);

			$screen_wdgt->addItem($form);
		}

	$screen_wdgt->show();

include_once('include/page_footer.php');
?>

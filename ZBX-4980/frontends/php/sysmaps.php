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
require_once('include/maps.inc.php');
require_once('include/ident.inc.php');
require_once('include/forms.inc.php');

if(isset($_REQUEST['go']) && ($_REQUEST['go'] == 'export') && isset($_REQUEST['maps'])){
	$EXPORT_DATA = true;

	$page['type'] = detect_page_type(PAGE_TYPE_XML);
	$page['file'] = 'zbx_maps_export.xml';

	require_once('include/export.inc.php');
}
else{
	$EXPORT_DATA = false;

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	$page['title'] = 'S_NETWORK_MAPS';
	$page['file'] = 'sysmaps.php';
	$page['hist_arg'] = array();
}

include_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'maps'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'sysmapid'=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,NULL),
		'name'=>			array(T_ZBX_STR, O_OPT,	 NULL,	NOT_EMPTY,			'isset({save})'),
		'width'=>			array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,65535),	'isset({save})'),
		'height'=>			array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,65535),	'isset({save})'),
		'backgroundid'=>	array(T_ZBX_INT, O_OPT,	 NULL,	DB_ID,				'isset({save})'),
		'expandproblem'=>	array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,1),		null),
		'markelements'=>	array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,1),		null),
		'show_unack'=>	        array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,2),		null),
		'highlight'=>		array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,1),		null),
		'label_type'=>		array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,4),		'isset({save})'),
		'label_location'=>	array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,3),		'isset({save})'),
// Actions
		'save'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>			array(T_ZBX_STR, O_OPT, P_SYS, NULL,	NULL),
		'go'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
// Form
		'form'=>			array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL),
// Import
		'rules' =>			array(T_ZBX_STR, O_OPT,	null,	DB_ID,		null),
		'import' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL)
	);

	check_fields($fields);
	validate_sort_and_sortorder('name', ZBX_SORT_UP);

	if(isset($_REQUEST['sysmapid'])){
		$options = array(
			'sysmapids' => $_REQUEST['sysmapid'],
			'editable' => 1,
			'extendoutput' => 1,
		);
		$maps = CMap::get($options);

		if(empty($maps)) access_deny();
		else $sysmap = reset($maps);
	}
?>
<?php
// EXPORT ///////////////////////////////////

	if($EXPORT_DATA){
		$maps = get_request('maps', array());

		$options = array(
			'sysmapids' => $maps,
			'select_selements' => API_OUTPUT_EXTEND,
			'select_links' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		);
		$sysmaps = CMap::get($options);

		$options = array(
			'sysmapids' => zbx_objectValues($sysmaps, 'sysmapid'),
			'output' => API_OUTPUT_EXTEND,
			'select_image' => 1
		);
		$images = CImage::get($options);


		prepareMapExport($sysmaps);
		$images = prepareImageExport($images);
		$sysmaps = array('images' => $images, 'sysmaps' => $sysmaps);

		$xml = zbxXML::arrayToXML($sysmaps);
		print($xml);

		exit();
	}

// IMPORT ///////////////////////////////////
	$rules = get_request('rules', array());
	if(!isset($_FILES['import_file'])){
		foreach(array('maps', 'icons', 'background') as $key){
			$rules[$key]['exist'] = 1;
			$rules[$key]['missed'] = 1;
		}
	}

	if(isset($_FILES['import_file']) && is_file($_FILES['import_file']['tmp_name'])){
		require_once('include/export.inc.php');
		DBstart();

		$result = zbxXML::import($_FILES['import_file']['tmp_name']);
		if($result) $result = zbxXML::parseMap($rules);

		$result = DBend($result);
		show_messages($result, S_IMPORTED.SPACE.S_SUCCESSEFULLY_SMALL, S_IMPORT.SPACE.S_FAILED_SMALL);
	}

?>
<?php
	$_REQUEST['go'] = get_request('go', 'none');

	if(isset($_REQUEST['save'])){
		if(isset($_REQUEST['sysmapid'])){
// TODO check permission by new value.
			$map = array(
				'sysmapid' => $_REQUEST['sysmapid'],
				'name' => $_REQUEST['name'],
				'width' => $_REQUEST['width'],
				'height' => $_REQUEST['height'],
				'backgroundid' => $_REQUEST['backgroundid'],
				'highlight' => get_request('highlight', 0),
				'markelements' => get_request('markelements', 0),
				'expandproblem' => get_request('expandproblem', 0),
				'label_type' => $_REQUEST['label_type'],
				'label_location' => $_REQUEST['label_location'],
				'show_unack' => get_request('show_unack', 0),
			);

			DBstart();
			$result = CMap::update($map);
			$result = DBend($result);

			add_audit_if($result,AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_MAP,'Name ['.$_REQUEST['name'].']');
			show_messages($result,S_MAP_UPDATED,S_CANNOT_UPDATE_MAP);
		}
		else {
			if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
				access_deny();

			$map = array(
				'name' => $_REQUEST['name'],
				'width' => $_REQUEST['width'],
				'height' => $_REQUEST['height'],
				'backgroundid' => $_REQUEST['backgroundid'],
				'highlight' => get_request('highlight', 0),
				'markelements' => get_request('markelements', 0),
				'expandproblem' => get_request('expandproblem', 0),
				'label_type' => $_REQUEST['label_type'],
				'label_location' => $_REQUEST['label_location'],
				'show_unack' => get_request('show_unack', 0),
			);

			DBstart();
			$result = CMap::create($map);
			$result = DBend($result);

			add_audit_if($result,AUDIT_ACTION_ADD,AUDIT_RESOURCE_MAP,'Name ['.$_REQUEST['name'].']');
			show_messages($result,S_MAP_ADDED,S_CANNOT_ADD_MAP);
		}
		if($result){
			unset($_REQUEST['form']);
		}
	}
	else if((isset($_REQUEST['delete'])&&isset($_REQUEST['sysmapid'])) || ($_REQUEST['go'] == 'delete')){
		$sysmapids = get_request('maps', array());
		if(isset($_REQUEST['sysmapid'])){
			$sysmapids[] = $_REQUEST['sysmapid'];
		}

		$maps = CMap::get(array('sysmapids' => $sysmapids, 'output' => API_OUTPUT_EXTEND, 'editable => 1'));
		$go_result = CMap::delete($sysmapids);

		show_messages($go_result, S_MAP_DELETED, S_CANNOT_DELETE_MAP);
		if($go_result){
			unset($_REQUEST['form']);
			foreach($maps as $map){
				add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_MAP,
					$map['sysmapid'],
					$map['name'],
					null,null,null);
			}
		}
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}

?>
<?php
	$form = new CForm(null, 'get');
	$form->addItem(new CButton('form', S_CREATE_MAP));
	$form->addItem(new CButton('form', S_IMPORT_MAP));

	$map_wdgt = new CWidget();
	$map_wdgt->addPageHeader(S_CONFIGURATION_OF_NETWORK_MAPS, $form);


	if(isset($_REQUEST['form'])){
		if($_REQUEST['form'] == S_IMPORT_MAP)
			$map_wdgt->addItem(import_map_form($rules));
		else if(($_REQUEST['form'] == S_CREATE_MAP) || ($_REQUEST['form'] == 'update'))
			$map_wdgt->addItem(insert_map_form());
	}
	else{
		$form = new CForm();
		$form->setName('frm_maps');

		$numrows = new CDiv();
		$numrows->setAttribute('name','numrows');

		$map_wdgt->addHeader(S_MAPS_BIG);
		$map_wdgt->addHeader($numrows);

		$table = new CTableInfo(S_NO_MAPS_DEFINED);
		$table->setHeader(array(
			new CCheckBox('all_maps',NULL,"checkAll('".$form->getName()."','all_maps','maps');"),
			make_sorting_header(S_NAME,'name'),
			make_sorting_header(S_WIDTH,'width'),
			make_sorting_header(S_HEIGHT,'height'),
			S_EDIT));


		$sortfield = getPageSortField('name');
		$sortorder = getPageSortOrder();

		$options = array(
			'editable' => 1,
			'extendoutput' => 1,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => ($config['search_limit']+1)
		);
		$maps = CMap::get($options);

// sorting
		order_result($maps, $sortfield, $sortorder);
		$paging = getPagingLine($maps);
//-------

		foreach($maps as $mnum => $map){
			$table->addRow(array(
				new CCheckBox('maps['.$map['sysmapid'].']', NULL, NULL, $map['sysmapid']),
				new CLink($map['name'], 'sysmap.php?sysmapid='.$map['sysmapid']),
				$map['width'],
				$map['height'],
				new CLink(S_EDIT, 'sysmaps.php?form=update&sysmapid='.$map['sysmapid'].'#form')
				));
		}

// goBox
		$goBox = new CComboBox('go');
		$goBox->addItem('export', S_EXPORT_SELECTED);
		$goOption = new CComboItem('delete', S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_MAPS_Q);

		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO);
		$goButton->setAttribute('id','goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "maps";');

		$footer = get_table_header(array($goBox, $goButton));
		$table = array($paging, $table, $paging, $footer);

		$form->addItem($table);
		$map_wdgt->addItem($form);
	}

	$map_wdgt->show();
?>
<?php

include_once('include/page_footer.php');

?>

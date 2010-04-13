<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
	require_once('include/forms.inc.php');

	$page['title'] = 'S_EXPORT_IMPORT';
	$page['file'] = 'import.php';
	$page['hist_arg'] = array();


include_once('include/page_header.php');

	$fields=array(
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
		'groupid'=>	array(T_ZBX_INT, O_OPT,	P_SYS,  DB_ID,			null),
		'rules' =>			array(T_ZBX_STR, O_OPT,	null,	DB_ID,		null),
		'form_refresh' =>	array(T_ZBX_INT, O_OPT,	null,	null,		null),
// Actions
		'go' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL, NULL),
// form
		'import' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL)
	);

	check_fields($fields);
?>
<?php

	$rules = get_request('rules', array());
	if(!isset($_REQUEST['form_refresh'])){
		foreach(array('host', 'template', 'item', 'trigger', 'graph') as $key){
			$rules[$key]['exist'] = 1;
			$rules[$key]['missed'] = 1;
		}
	}

	if(isset($_FILES['import_file']) && is_file($_FILES['import_file']['tmp_name'])){
		require_once('include/export.inc.php');
		DBstart();
		$result = zbxXML::import($_FILES['import_file']['tmp_name']);
		$result &= zbxXML::parseMain($rules);
		$result = DBend($result);
		show_messages($result, S_IMPORTED.SPACE.S_SUCCESSEFULLY_SMALL, S_IMPORT.SPACE.S_FAILED_SMALL);
	}

	$header_form = new CForm();
	$header_form->setMethod('get');

	$cmbConf = new CComboBox('config', 'import.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
		$cmbConf->addItem('export.php', S_EXPORT);
		$cmbConf->addItem('import.php', S_IMPORT);
	$header_form->addItem($cmbConf);
	$header_form->addVar('groupid', get_request('groupid', 0));


	$import_wdgt = new CWidget();
	$import_wdgt->addPageHeader(S_IMPORT_BIG, $header_form);

	
	$form = new CFormTable(S_IMPORT, null, 'post', 'multipart/form-data');
	$form->addRow(S_IMPORT_FILE, new CFile('import_file'));

	$table = new CTable();
	$table->setHeader(array(S_ELEMENT, S_UPDATE.SPACE.S_EXISTING, S_ADD.SPACE.S_MISSING), 'bold');

	$titles = array('host' => S_HOST, 'template' => S_TEMPLATE_LINKAGE, 'item' => S_ITEM, 'trigger' => S_TRIGGER, 'graph' => S_GRAPH);
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
	
	$import_wdgt->addItem($form);
	$import_wdgt->show();


include_once('include/page_footer.php');
?>
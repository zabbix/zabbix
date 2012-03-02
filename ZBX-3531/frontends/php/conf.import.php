<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


$page['file'] = 'conf.import.php';
$page['title'] = _('Configuration import');

require_once 'include/config.inc.php';
require_once 'include/page_header.php';

$fields = array(
	'rules' => array(T_ZBX_STR, O_OPT, null, null,	null),
	'import' => array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'rules_preset' => array(T_ZBX_STR, O_OPT, null, null, null),
	'form_refresh' => array(T_ZBX_INT, O_OPT, null, null, null)
);
check_fields($fields);


$data['rules'] = array(
	'groups' => array('missed' => false),
	'hosts' => array('exist' => false, 'missed' => false),
	'templates' => array('exist' => false, 'missed' => false),
	'template_linkages' => array('missed' => false),
	'items' => array('exist' => false, 'missed' => false),
	'discoveryrules' => array('exist' => false, 'missed' => false),
	'triggers' => array('exist' => false, 'missed' => false),
	'graphs' => array('exist' => false, 'missed' => false),
	'screens' => array('exist' => false, 'missed' => false),
	'maps' => array('exist' => false, 'missed' => false),
	'images' => array('exist' => false, 'missed' => false)
);
if (isset($_REQUEST['rules_preset']) && !isset($_REQUEST['rules'])) {
	switch ($_REQUEST['rules_preset']) {
		case 'host':
			$data['rules']['groups'] = array('missed' => true);
			$data['rules']['hosts'] = array('exist' => true, 'missed' => true);
			$data['rules']['items'] = array('exist' => true, 'missed' => true);
			$data['rules']['discoveryrules'] = array('exist' => true, 'missed' => true);
			$data['rules']['triggers'] = array('exist' => true, 'missed' => true);
			$data['rules']['graphs'] = array('exist' => true, 'missed' => true);
			$data['rules']['template_linkages'] = array('missed' => true);
			break;

		case 'template':
			$data['rules']['groups'] = array('missed' => true);
			$data['rules']['templates'] = array('exist' => true, 'missed' => true);
			$data['rules']['items'] = array('exist' => true, 'missed' => true);
			$data['rules']['discoveryrules'] = array('exist' => true, 'missed' => true);
			$data['rules']['triggers'] = array('exist' => true, 'missed' => true);
			$data['rules']['graphs'] = array('exist' => true, 'missed' => true);
			$data['rules']['template_linkages'] = array('missed' => true);
			break;

		case 'map':
			$data['rules']['maps'] = array('exist' => true, 'missed' => true);
			break;

		case 'screen':
			$data['rules']['screens'] = array('exist' => true, 'missed' => true);
			break;

	}
}
if (isset($_REQUEST['rules'])) {
	$requestRules = get_request('rules', array());
	// if form was submitted with some checkboxes unchecked, those values aare not submitted
	// so that we set missing values to false
	foreach ($data['rules'] as $ruleName => $rule) {

		if (!isset($requestRules[$ruleName])) {
			if (isset($rule['exist'])) {
				$requestRules[$ruleName]['exist'] = false;
			}
			if (isset($rule['missed'])) {
				$requestRules[$ruleName]['missed'] = false;
			}
		}
		elseif (!isset($requestRules[$ruleName]['exist']) && isset($rule['exist'])){
			$requestRules[$ruleName]['exist'] = false;
		}
		elseif (!isset($requestRules[$ruleName]['missed']) && isset($rule['missed'])){
			$requestRules[$ruleName]['missed'] = false;
		}
	}
	$data['rules'] = $requestRules;
}

if (isset($_FILES['import_file']) && is_file($_FILES['import_file']['tmp_name'])) {
	// required for version 1.8 import
	require_once dirname(__FILE__).'/include/export.inc.php';

	try {
		$fileExtension = pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION);
		$importReader = CImportReaderFactory::getReader($fileExtension);
		$fileContent = file_get_contents($_FILES['import_file']['tmp_name']);

		$configurationImport = new CConfigurationImport($fileContent, $data['rules']);
		$configurationImport->setReader($importReader);

		$configurationImport->import();
		show_messages(true, _('Imported successfully'));
	}
	catch (Exception $e) {
		error($e->getMessage());
		show_messages(false, null, _('Import failed'));
	}
}

$view = new CView('conf.import', $data);
$view->render();
$view->show();

require_once('include/page_footer.php');

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


require_once 'include/config.inc.php';
require_once 'include/page_header.php';

$fields = array(
	'rules' => array(T_ZBX_STR, O_OPT, null, null,	null),
	'import' => array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'form_refresh' => array(T_ZBX_INT, O_OPT, null, null, null)

);
check_fields($fields);


$data['rules'] = array(
	'groups' => array('missed' => true),
	'hosts' => array('exist' => true, 'missed' => true),
	'templates' => array('exist' => true, 'missed' => true),
	'template_linkages' => array('missed' => true),
	'items' => array('exist' => true, 'missed' => true),
	'discoveryrules' => array('exist' => true, 'missed' => true),
	'triggers' => array('exist' => true, 'missed' => true),
	'graphs' => array('exist' => true, 'missed' => true),
	'screens' => array('exist' => true, 'missed' => true),
	'maps' => array('exist' => true, 'missed' => true),
	'images' => array('exist' => false, 'missed' => false)
);
if (isset($_REQUEST['form_refresh'])) {
	$requestRules = get_request('rules', array());
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
	try {
		$configurationImport = new CConfigurationImport($_FILES['import_file'], get_request('rules', array()));
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

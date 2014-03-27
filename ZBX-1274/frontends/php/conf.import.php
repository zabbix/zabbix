<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';

$page['file'] = 'conf.import.php';
$page['title'] = _('Configuration import');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);
$page['hist_arg'] = array();

ob_start();

require_once dirname(__FILE__).'/include/page_header.php';

$fields = array(
	'rules' => array(T_ZBX_STR, O_OPT, null, null, null),
	'import' => array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'rules_preset' => array(T_ZBX_STR, O_OPT, null, null, null),
	'cancel' => array(T_ZBX_STR, O_OPT, P_SYS, null, null),
	'form_refresh' => array(T_ZBX_INT, O_OPT, null, null, null)
);
check_fields($fields);


if (isset($_REQUEST['cancel'])) {
	ob_end_clean();
	redirect(CWebUser::$data['last_page']['url']);
}
ob_end_flush();


$data['rules'] = array(
	'groups' => array('createMissing' => false),
	'hosts' => array('updateExisting' => false, 'createMissing' => false),
	'templates' => array('updateExisting' => false, 'createMissing' => false),
	'templateScreens' => array('updateExisting' => false, 'createMissing' => false),
	'templateLinkage' => array('createMissing' => false),
	'items' => array('updateExisting' => false, 'createMissing' => false),
	'discoveryRules' => array('updateExisting' => false, 'createMissing' => false),
	'triggers' => array('updateExisting' => false, 'createMissing' => false),
	'graphs' => array('updateExisting' => false, 'createMissing' => false),
	'screens' => array('updateExisting' => false, 'createMissing' => false),
	'maps' => array('updateExisting' => false, 'createMissing' => false),
	'images' => array('updateExisting' => false, 'createMissing' => false)
);
// rules presets
if (isset($_REQUEST['rules_preset']) && !isset($_REQUEST['rules'])) {
	switch ($_REQUEST['rules_preset']) {
		case 'host':
			$data['rules']['groups'] = array('createMissing' => true);
			$data['rules']['hosts'] = array('updateExisting' => true, 'createMissing' => true);
			$data['rules']['items'] = array('updateExisting' => true, 'createMissing' => true);
			$data['rules']['discoveryRules'] = array('updateExisting' => true, 'createMissing' => true);
			$data['rules']['triggers'] = array('updateExisting' => true, 'createMissing' => true);
			$data['rules']['graphs'] = array('updateExisting' => true, 'createMissing' => true);
			$data['rules']['templateLinkage'] = array('createMissing' => true);
			break;

		case 'template':
			$data['rules']['groups'] = array('createMissing' => true);
			$data['rules']['templates'] = array('updateExisting' => true, 'createMissing' => true);
			$data['rules']['templateScreens'] = array('updateExisting' => true, 'createMissing' => true);
			$data['rules']['items'] = array('updateExisting' => true, 'createMissing' => true);
			$data['rules']['discoveryRules'] = array('updateExisting' => true, 'createMissing' => true);
			$data['rules']['triggers'] = array('updateExisting' => true, 'createMissing' => true);
			$data['rules']['graphs'] = array('updateExisting' => true, 'createMissing' => true);
			$data['rules']['templateLinkage'] = array('createMissing' => true);
			break;

		case 'map':
			$data['rules']['maps'] = array('updateExisting' => true, 'createMissing' => true);
			break;

		case 'screen':
			$data['rules']['screens'] = array('updateExisting' => true, 'createMissing' => true);
			break;

	}
}
if (isset($_REQUEST['rules'])) {
	$requestRules = get_request('rules', array());
	// if form was submitted with some checkboxes unchecked, those values are not submitted
	// so that we set missing values to false
	foreach ($data['rules'] as $ruleName => $rule) {
		if (!isset($requestRules[$ruleName])) {
			if (isset($rule['updateExisting'])) {
				$requestRules[$ruleName]['updateExisting'] = false;
			}
			if (isset($rule['createMissing'])) {
				$requestRules[$ruleName]['createMissing'] = false;
			}
		}
		elseif (!isset($requestRules[$ruleName]['updateExisting']) && isset($rule['updateExisting'])){
			$requestRules[$ruleName]['updateExisting'] = false;
		}
		elseif (!isset($requestRules[$ruleName]['createMissing']) && isset($rule['createMissing'])){
			$requestRules[$ruleName]['createMissing'] = false;
		}
	}
	$data['rules'] = $requestRules;
}

if (isset($_FILES['import_file'])) {
	try {
		$file = new CUploadFile($_FILES['import_file']);

		DBstart();

		$importFormat = CImportReaderFactory::fileExt2ImportFormat($file->getExtension());
		$importReader = CImportReaderFactory::getReader($importFormat);

		$configurationImport = new CConfigurationImport($file->getContent(), $data['rules']);
		$configurationImport->setReader($importReader);
		$configurationImport->import();

		DBend();

		show_messages(true, _('Imported successfully'));
	}
	catch (Exception $e) {
		DBend(false);
		error($e->getMessage());
		show_messages(false, null, _('Import failed'));
	}
}

$view = new CView('conf.import', $data);
$view->render();
$view->show();

require_once dirname(__FILE__).'/include/page_footer.php';

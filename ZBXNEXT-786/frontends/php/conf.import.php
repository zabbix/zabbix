<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

ob_start();

require_once dirname(__FILE__).'/include/page_header.php';

$fields = [
	'rules' => [T_ZBX_STR, O_OPT, null, null, null],
	'import' => [T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null],
	'rules_preset' => [T_ZBX_STR, O_OPT, null, null, null],
	'cancel' => [T_ZBX_STR, O_OPT, P_SYS, null, null],
	'form_refresh' => [T_ZBX_INT, O_OPT, null, null, null]
];
check_fields($fields);


if (isset($_REQUEST['cancel'])) {
	ob_end_clean();
	redirect(CWebUser::$data['last_page']['url']);
}
ob_end_flush();

$data['rules'] = [
	'groups' => ['createMissing' => false],
	'hosts' => ['updateExisting' => false, 'createMissing' => false],
	'templates' => ['updateExisting' => false, 'createMissing' => false],
	'templateScreens' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
	'templateLinkage' => ['createMissing' => false],
	'applications' => ['createMissing' => false, 'deleteMissing' => false],
	'items' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
	'discoveryRules' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
	'triggers' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
	'graphs' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
	'screens' => ['updateExisting' => false, 'createMissing' => false],
	'maps' => ['updateExisting' => false, 'createMissing' => false],
	'images' => ['updateExisting' => false, 'createMissing' => false]
];

// rules presets
if (isset($_REQUEST['rules_preset']) && !isset($_REQUEST['rules'])) {
	switch ($_REQUEST['rules_preset']) {
		case 'host':
			$data['rules']['groups'] = ['createMissing' => true];
			$data['rules']['hosts'] = ['updateExisting' => true, 'createMissing' => true];
			$data['rules']['applications'] = [
				'createMissing' => true,
				'deleteMissing' => false
			];
			$data['rules']['items'] = [
				'updateExisting' => true,
				'createMissing' => true,
				'deleteMissing' => false
			];
			$data['rules']['discoveryRules'] = [
				'updateExisting' => true,
				'createMissing' => true,
				'deleteMissing' => false
			];
			$data['rules']['triggers'] = [
				'updateExisting' => true,
				'createMissing' => true,
				'deleteMissing' => false
			];
			$data['rules']['graphs'] = [
				'updateExisting' => true,
				'createMissing' => true,
				'deleteMissing' => false
			];
			$data['rules']['templateLinkage'] = ['createMissing' => true];
			break;

		case 'template':
			$data['rules']['groups'] = ['createMissing' => true];
			$data['rules']['templates'] = ['updateExisting' => true, 'createMissing' => true];
			$data['rules']['templateScreens'] = [
				'updateExisting' => true,
				'createMissing' => true,
				'deleteMissing' => false
			];
			$data['rules']['applications'] = [
				'createMissing' => true,
				'deleteMissing' => false
			];
			$data['rules']['items'] = [
				'updateExisting' => true,
				'createMissing' => true,
				'deleteMissing' => false
			];
			$data['rules']['discoveryRules'] = [
				'updateExisting' => true,
				'createMissing' => true,
				'deleteMissing' => false
			];
			$data['rules']['triggers'] = [
				'updateExisting' => true,
				'createMissing' => true,
				'deleteMissing' => false
			];
			$data['rules']['graphs'] = [
				'updateExisting' => true,
				'createMissing' => true,
				'deleteMissing' => false
			];
			$data['rules']['templateLinkage'] = ['createMissing' => true];
			break;

		case 'map':
			$data['rules']['maps'] = ['updateExisting' => true, 'createMissing' => true];
			break;

		case 'screen':
			$data['rules']['screens'] = ['updateExisting' => true, 'createMissing' => true];
			break;

	}
}

if (isset($_REQUEST['rules'])) {
	$requestRules = getRequest('rules', []);
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

			if (isset($rule['deleteMissing'])) {
				$requestRules[$ruleName]['deleteMissing'] = false;
			}
		}

		if (!isset($requestRules[$ruleName]['updateExisting']) && isset($rule['updateExisting'])) {
			$requestRules[$ruleName]['updateExisting'] = false;
		}

		if (!isset($requestRules[$ruleName]['createMissing']) && isset($rule['createMissing'])) {
			$requestRules[$ruleName]['createMissing'] = false;
		}

		if (!isset($requestRules[$ruleName]['deleteMissing']) && isset($rule['deleteMissing'])) {
			$requestRules[$ruleName]['deleteMissing'] = false;
		}
	}

	$data['rules'] = $requestRules;
}

if (isset($_FILES['import_file'])) {
	$result = false;

	// CUploadFile throws exceptions, so we need to catch them
	try {
		$file = new CUploadFile($_FILES['import_file']);

		$result = API::Configuration()->import([
			'format' => CImportReaderFactory::fileExt2ImportFormat($file->getExtension()),
			'source' => $file->getContent(),
			'rules' => $data['rules']
		]);
	}
	catch (Exception $e) {
		error($e->getMessage());
	}

	show_messages($result, _('Imported successfully'), _('Import failed'));
}

$view = new CView('conf.import', $data);
$view->render();
$view->show();

require_once dirname(__FILE__).'/include/page_footer.php';

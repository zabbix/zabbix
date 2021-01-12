<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

$page['file'] = 'screen.import.php';
$page['title'] = _('Configuration import');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

$fields = [
	'rules' => [T_ZBX_STR, O_OPT, null, null, null],
	'import' => [T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null],
	'rules_preset' => [T_ZBX_STR, O_OPT, null, null, null]
];
check_fields($fields);

$data = [
	'rules' => [
		'groups' => ['createMissing' => false],
		'hosts' => ['updateExisting' => false, 'createMissing' => false],
		'templates' => ['updateExisting' => false, 'createMissing' => false],
		'templateScreens' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
		'templateLinkage' => ['createMissing' => false, 'deleteMissing' => false],
		'applications' => ['createMissing' => false, 'deleteMissing' => false],
		'items' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
		'discoveryRules' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
		'triggers' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
		'graphs' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
		'httptests' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
		'screens' => ['updateExisting' => true, 'createMissing' => true],
		'maps' => ['updateExisting' => false, 'createMissing' => false],
		'images' => ['updateExisting' => false, 'createMissing' => false],
		'mediaTypes' => ['updateExisting' => false, 'createMissing' => false],
		'valueMaps' => ['updateExisting' => false, 'createMissing' => false]
	],
	'rules_preset' => getRequest('rules_preset')
];

if (hasRequest('import')) {
	$request_rules = getRequest('rules', []);

	foreach ($data['rules'] as $rule_name => $rule) {
		if (!array_key_exists($rule_name, $request_rules)) {
			$request_rules[$rule_name] = [];
		}

		foreach (['updateExisting', 'createMissing', 'deleteMissing'] as $option) {
			if (array_key_exists($option, $request_rules[$rule_name])) {
				$request_rules[$rule_name][$option] = true;
			}
			elseif (array_key_exists($option, $rule)) {
				$request_rules[$rule_name][$option] = false;
			}
		}
	}

	$data['rules'] = $request_rules;

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

			if ($result) {
				CPagerHelper::resetPage();
			}
		}
		catch (Exception $e) {
			error($e->getMessage());
		}

		show_messages($result, _('Imported successfully'), _('Import failed'));
	}
}

$data['backurl'] = (new CUrl('screenconf.php'))
	->setArgument('page', CPagerHelper::loadPage('screenconf.php', null))
	->getUrl();

echo (new CView('conf.import', $data))->getOutput();

require_once dirname(__FILE__).'/include/page_footer.php';

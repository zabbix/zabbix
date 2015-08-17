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

include dirname(__FILE__).'/js/conf.import.js.php';

$rulesTable = (new CTable())
	->setHeader(['', _('Update existing'), _('Create new'), _('Delete missing')]);

$titles = [
	'groups' => _('Groups'),
	'hosts' => _('Hosts'),
	'templates' => _('Templates'),
	'templateScreens' => _('Template screens'),
	'templateLinkage' => _('Template linkage'),
	'applications' => _('Applications'),
	'items' => _('Items'),
	'discoveryRules' => _('Discovery rules'),
	'triggers' => _('Triggers'),
	'graphs' => _('Graphs'),
	'screens' => _('Screens'),
	'maps' => _('Maps'),
	'images' => _('Images')
];

foreach ($titles as $key => $title) {
	$cbExist = null;
	$cbMissed = null;
	$cbDeleted = null;

	if (array_key_exists('updateExisting', $data['rules'][$key])) {
		$cbExist = (new CCheckBox('rules['.$key.'][updateExisting]'))
			->setChecked($data['rules'][$key]['updateExisting']);

		if ($key == 'images') {
			if (CWebUser::$data['type'] != USER_TYPE_SUPER_ADMIN) {
				continue;
			}

			$cbExist->onClick('if (this.checked) return confirm(\''._('Images for all maps will be updated!').'\')');
		}
	}

	if (array_key_exists('createMissing', $data['rules'][$key])) {
		$cbMissed = (new CCheckBox('rules['.$key.'][createMissing]'))
			->setChecked($data['rules'][$key]['createMissing']);
	}

	if (array_key_exists('deleteMissing', $data['rules'][$key])) {
		$cbDeleted = (new CCheckBox('rules['.$key.'][deleteMissing]'))
			->setChecked($data['rules'][$key]['deleteMissing'])
			->addClass('deleteMissing');
	}

	$rulesTable->addRow([
		$title,
		(new CCol($cbExist))->addClass(ZBX_STYLE_CENTER),
		(new CCol($cbMissed))->addClass(ZBX_STYLE_CENTER),
		(new CCol($cbDeleted))->addClass(ZBX_STYLE_CENTER)
	]);
}

// form list
$importFormList = (new CFormList())
	->addRow(_('Import file'), (new CFile('import_file'))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))
	->addRow(_('Rules'), new CDiv($rulesTable));

// tab
$importTab = (new CTabView())->addTab('importTab', _('Import'), $importFormList);

// form
$importTab->setFooter(makeFormFooter(
	new CSubmit('import', _('Import')),
	[new CRedirectButton(_('Cancel'), $data['backurl'])]
));

$form = (new CForm('post', null, 'multipart/form-data'))
	->addVar('backurl', $data['backurl'])
	->addItem($importTab);

// widget
$importWidget = (new CWidget())->addItem($form);

return $importWidget;

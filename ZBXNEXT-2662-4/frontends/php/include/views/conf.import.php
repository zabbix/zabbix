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
	->setHeader(['', _('Update existing'), _('Create new'), _('Delete missing')], 'bold');

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

$rules = $this->get('rules');

foreach ($titles as $key => $title) {
	$cbExist = null;
	$cbMissed = null;
	$cbDeleted = null;

	if (isset($rules[$key]['updateExisting'])) {
		$cbExist = (new CCheckBox('rules['.$key.'][updateExisting]'))
			->setChecked($rules[$key]['updateExisting'] == 1);

		if ($key == 'images') {
			if (CWebUser::$data['type'] != USER_TYPE_SUPER_ADMIN) {
				continue;
			}

			$cbExist->onClick('if (this.checked) return confirm(\''._('Images for all maps will be updated!').'\')');
		}
	}

	if (isset($rules[$key]['createMissing'])) {
		$cbMissed = (new CCheckBox('rules['.$key.'][createMissing]'))
			->setChecked($rules[$key]['createMissing'] == 1);
	}

	if (isset($rules[$key]['deleteMissing'])) {
		$cbDeleted = (new CCheckBox('rules['.$key.'][deleteMissing]'))
			->setChecked($rules[$key]['deleteMissing'] == 1)
			->addClass('input checkbox pointer deleteMissing');
	}

	$rulesTable->addRow([
		$title,
		(new CCol($cbExist))->addClass('center'),
		(new CCol($cbMissed))->addClass('center'),
		(new CCol($cbDeleted))->addClass('center')
	]);
}

// form list
$importFormList = (new CFormList('proxyFormList'))
	->addRow(_('Import file'), (new CFile('import_file'))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))
	->addRow(_('Rules'), new CDiv($rulesTable));

// tab
$importTab = (new CTabView())->addTab('importTab', _('Import'), $importFormList);

// form
$importForm = new CForm('post', null, 'multipart/form-data');
$importTab->setFooter(makeFormFooter(
	new CSubmit('import', _('Import')),
	[new CButtonCancel()]
));

$importForm->addItem($importTab);

// widget
$importWidget = (new CWidget())->addItem($importForm);

return $importWidget;

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


$form = new CFormTable(_('Import'), null, 'post', 'multipart/form-data');
$form->addRow(_('Import file'), new CFile('import_file'));

$table = new CTable();
$table->setHeader(array(SPACE, _('Update existing'), _('Add missing')), 'bold');

$titles = array(
	'groups' => _('Groups'),
	'hosts' => _('Hosts'),
	'templates' => _('Templates'),
	'templateScreens' => _('Template screens'),
	'templateLinkage' => _('Template linkage'),
	'items' => _('Items'),
	'discoveryRules' => _('Discovery rules'),
	'triggers' => _('Triggers'),
	'graphs' => _('Graphs'),
	'screens' => _('Screens'),
	'maps' => _('Maps'),
	'images' => _('Images')
);
$rules = $this->get('rules');
foreach ($titles as $key => $title) {
	$cbExist = $cbMissed = SPACE;

	if (isset($rules[$key]['updateExisting'])) {
		$cbExist = new CCheckBox('rules['.$key.'][updateExisting]', $rules[$key]['updateExisting'], null, 1);

		if ($key == 'images') {
			if (CWebUser::$data['type'] != USER_TYPE_SUPER_ADMIN) {
				continue;
			}

			$cbExist->setAttribute('onclick', 'if (this.checked) return confirm(\''._('Images for all maps will be updated!').'\')');
		}
	}

	if (isset($rules[$key]['createMissing'])) {
		$cbMissed = new CCheckBox('rules['.$key.'][createMissing]', $rules[$key]['createMissing'], null, 1);
	}

	$table->addRow(array($title, $cbExist, $cbMissed));
}

$form->addRow(_('Rules'), $table);
$form->addItemToBottomRow(new CSubmit('import', _('Import')));


$importWidget = new CWidget();
$importWidget->addItem($form);

return $importWidget;

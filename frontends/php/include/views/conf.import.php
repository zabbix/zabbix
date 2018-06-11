<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	'httptests' => _('Web scenarios'),
	'screens' => _('Screens'),
	'maps' => _('Maps')
];

$user_type = CWebUser::getType();

if ($user_type == USER_TYPE_SUPER_ADMIN) {
	$titles['images'] = _('Images');
	$titles['valueMaps'] = _('Value mappings');
}

foreach ($titles as $key => $title) {
	$cbExist = null;
	$cbMissed = null;
	$cbDeleted = null;

	if (array_key_exists('updateExisting', $data['rules'][$key])) {
		$cbExist = (new CCheckBox('rules['.$key.'][updateExisting]'))
			->setChecked($data['rules'][$key]['updateExisting']);

		if ($key !== 'maps' && $key !== 'screens' && $user_type != USER_TYPE_SUPER_ADMIN
				&& $user_type != USER_TYPE_ZABBIX_ADMIN) {
			$cbExist->setAttribute('disabled', 'disabled');
		}

		if ($key === 'images') {
			$cbExist->onClick('updateWarning(this, '.CJs::encodeJson(_('Images for all maps will be updated!')).')');
		}

		if ($key === 'valueMaps') {
			$cbExist->onClick(
				'updateWarning(this, '.CJs::encodeJson(_('Value mappings for value maps will be updated!')).')'
			);
		}
	}

	if (array_key_exists('createMissing', $data['rules'][$key])) {
		$cbMissed = (new CCheckBox('rules['.$key.'][createMissing]'))
			->setChecked($data['rules'][$key]['createMissing']);
	}

	if ($key !== 'maps' && $key !== 'screens' && $user_type != USER_TYPE_SUPER_ADMIN
			&& $user_type != USER_TYPE_ZABBIX_ADMIN) {
		$cbMissed->setAttribute('disabled', 'disabled');
	}

	if (array_key_exists('deleteMissing', $data['rules'][$key])) {
		$cbDeleted = (new CCheckBox('rules['.$key.'][deleteMissing]'))
			->setChecked($data['rules'][$key]['deleteMissing'])
			->addClass('deleteMissing');

		if ($key !== 'maps' && $key !== 'screens' && $user_type != USER_TYPE_SUPER_ADMIN
				&& $user_type != USER_TYPE_ZABBIX_ADMIN) {
			$cbDeleted->setAttribute('disabled', 'disabled');
		}
	}

	$rulesTable->addRow([
		$title,
		(new CCol($cbExist))->addClass(ZBX_STYLE_CENTER),
		(new CCol($cbMissed))->addClass(ZBX_STYLE_CENTER),
		(new CCol($cbDeleted))->addClass(ZBX_STYLE_CENTER)
	]);
}

// form list
$form_list = (new CFormList())
	->addRow((new CLabel(_('Import file'), 'import_file'))->setAsteriskMark(),
		(new CFile('import_file'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(_('Rules'), new CDiv($rulesTable));

// tab
$tab_view = (new CTabView())->addTab('importTab', _('Import'), $form_list);

// form
$tab_view->setFooter(makeFormFooter(
	new CSubmit('import', _('Import')),
	[new CRedirectButton(_('Cancel'), $data['backurl'])]
));

$form = (new CForm('post', null, 'multipart/form-data'))
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('backurl', $data['backurl'])
	->addItem($tab_view);

// widget
return (new CWidget())
	->setTitle(_('Import'))
	->addItem($form);

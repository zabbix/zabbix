<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


$widget = (new CWidget())
	->setTitle(_('Modules'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu());

// create form
$form = (new CForm())
	->setName('module-form')
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', 'module.update')
		->setArgument('moduleids[]', $data['moduleid'])
		->getUrl()
	)
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE);

// create module tab
$module_tab = (new CFormList())
	->addRow(_('Name'), $data['name'])
	->addRow(_('Version'), $data['version'])
	->addRow(_('Author'), $data['author'] === '' ? '-' : $data['author'])
	->addRow(_('Description'), $data['description'] === '' ? '-' : $data['description'])
	->addRow(_('Directory'), $data['relative_path'])
	->addRow(_('Namespace'), $data['namespace'])
	->addRow(_('Homepage'), $data['url'] === '' ? '-' : $data['url'])
	->addRow(_('Enabled'),
		(new CCheckBox('status', MODULE_STATUS_ENABLED))
			->setChecked($data['status'] == MODULE_STATUS_ENABLED)
	);

// create tabs
$tabs = (new CTabView())
	->addTab('moduleTab', _('Module'), $module_tab);

if (!hasRequest('form_refresh')) {
	$tabs->setSelected(0);
}

$tabs->setFooter(makeFormFooter(
	(new CSubmitButton(_('Update')))->setId('update'),
	[
		(new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
			->setArgument('action', 'module.list')
			->setArgument('page', CPagerHelper::loadPage('module.list', null))
		))->setId('cancel')
	]
));

// append tabs to form
$form->addItem($tabs);

// append form to widget
$widget->addItem($form);

$widget->show();

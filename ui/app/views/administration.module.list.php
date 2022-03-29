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


if ($data['uncheck']) {
	uncheckTableRows('modules');
}

$widget = (new CWidget())
	->setTitle(_('Modules'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_MODULE_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CForm())
				->addVar('action', 'module.scan')
				->addItem((new CList())
					->addItem(new CSubmit('form', _('Scan directory')))
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem(
		(new CFilter())
			->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'module.list'))
			->addVar('action', 'module.list')
			->setProfile($data['filter_profile'])
			->setActiveTab($data['filter_active_tab'])
			->addFilterTab(_('Filter'), [
				(new CFormList())->addRow(_('Name'),
					(new CTextBox('filter_name', $data['filter']['name']))
						->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
						->setAttribute('autofocus', 'autofocus')
				),
				(new CFormList())->addRow(_('Status'),
					(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
						->addValue(_('Any'), -1)
						->addValue(_('Enabled'), MODULE_STATUS_ENABLED)
						->addValue(_('Disabled'), MODULE_STATUS_DISABLED)
						->setModern(true)
				)
			])
	);

// create form
$form = (new CForm())->setName('module-form');

// create table
$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_modules'))
				->onClick("checkAll('".$form->getName()."', 'all_modules', 'moduleids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'module.list')
				->getUrl()
		),
		_('Version'),
		_('Author'),
		_('Description'),
		_('Status')
	]);

foreach ($data['modules'] as $moduleid => $module) {
	$name = new CLink($module['name'],
		(new CUrl('zabbix.php'))
			->setArgument('action', 'module.edit')
			->setArgument('moduleid', $moduleid)
			->getUrl()
	);

	$status_url = (new CUrl('zabbix.php'))
		->setArgument('action', ($module['status'] == MODULE_STATUS_ENABLED) ? 'module.disable' : 'module.enable')
		->setArgument('moduleids[]', $moduleid)
		->getUrl();

	if ($module['status'] == MODULE_STATUS_ENABLED) {
		$status = (new CLink(_('Enabled'), $status_url))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_GREEN)
			->addSID();
	}
	else {
		$status = (new CLink(_('Disabled'), $status_url))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_RED)
			->addSID();
	}

	// append table row
	$table->addRow([
		new CCheckBox('moduleids['.$moduleid.']', $moduleid),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		$module['version'],
		$module['author'],
		$module['description'],
		$status
	]);
}

// append table to form
$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'moduleids', [
		'module.enable' => ['name' => _('Enable'), 'confirm' => _('Enable selected modules?')],
		'module.disable' => ['name' => _('Disable'), 'confirm' => _('Disable selected modules?')]
	], 'modules')
]);

// append form to widget
$widget->addItem($form);

$widget->show();

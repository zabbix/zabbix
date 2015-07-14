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


require_once dirname(__FILE__).'/js/administration.general.housekeeper.edit.js.php';

$widget = (new CWidget())
	->setTitle(_('Housekeeping'))
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())->addItem(makeAdministrationGeneralMenu('adm.housekeeper.php')))
	);

$houseKeeperTab = (new CFormList())
	->addRow(new CTag('h4', true, _('Events and alerts')))
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_events_mode'),
		(new CCheckBox('hk_events_mode'))->setChecked($data['hk_events_mode'] == 1)
	)
	->addRow(
		new CLabel(_('Trigger data storage period (in days)'), 'hk_events_trigger'),
		(new CNumericBox('hk_events_trigger', $data['hk_events_trigger'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setEnabled($data['hk_events_mode'] == 1)
	)
	->addRow(
		new CLabel(_('Internal data storage period (in days)'), 'hk_events_internal'),
		(new CNumericBox('hk_events_internal', $data['hk_events_internal'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setEnabled($data['hk_events_mode'] == 1)
	)
	->addRow(
		new CLabel(_('Network discovery data storage period (in days)'), 'hk_events_discovery'),
		(new CNumericBox('hk_events_discovery', $data['hk_events_discovery'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setEnabled($data['hk_events_mode'] == 1)
	)
	->addRow(
		new CLabel(_('Auto-registration data storage period (in days)'), 'hk_events_autoreg'),
		(new CNumericBox('hk_events_autoreg', $data['hk_events_autoreg'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setEnabled($data['hk_events_mode'] == 1)
	)
	->addRow()
	->addRow(new CTag('h4', true, _('IT services')))
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_services_mode'),
		(new CCheckBox('hk_services_mode'))->setChecked($data['hk_services_mode'] == 1)
	)
	->addRow(
		new CLabel(_('Data storage period (in days)'), 'hk_services'),
		(new CNumericBox('hk_services', $data['hk_services'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setEnabled($data['hk_services_mode'] == 1)
	)
	->addRow()
	->addRow(new CTag('h4', true, _('Audit')))
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_audit_mode'),
		(new CCheckBox('hk_audit_mode'))->setChecked($data['hk_audit_mode'] == 1)
	)
	->addRow(
		new CLabel(_('Data storage period (in days)'), 'hk_audit'),
		(new CNumericBox('hk_audit', $data['hk_audit'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setEnabled($data['hk_audit_mode'] == 1)
	)
	->addRow()
	->addRow(new CTag('h4', true, _('User sessions')))
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_sessions_mode'),
		(new CCheckBox('hk_sessions_mode'))->setChecked($data['hk_sessions_mode'] == 1)
	)
	->addRow(
		new CLabel(_('Data storage period (in days)'), 'hk_sessions'),
		(new CNumericBox('hk_sessions', $data['hk_sessions'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setEnabled($data['hk_sessions_mode'] == 1)
	)
	->addRow()
	->addRow(new CTag('h4', true, _('History')))
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_history_mode'),
		(new CCheckBox('hk_history_mode'))->setChecked($data['hk_history_mode'] == 1)
	)
	->addRow(
		new CLabel(_('Override item history period'), 'hk_history_global'),
		(new CCheckBox('hk_history_global'))->setChecked($data['hk_history_global'] == 1)
	)
	->addRow(
		new CLabel(_('Data storage period (in days)'), 'hk_history'),
		(new CNumericBox('hk_history', $data['hk_history'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setEnabled($data['hk_history_global'] == 1)
	)
	->addRow()
	->addRow(new CTag('h4', true, _('Trends')))
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_trends_mode'),
		(new CCheckBox('hk_trends_mode'))->setChecked($data['hk_trends_mode'] == 1)
	)
	->addRow(
		new CLabel(_('Override item trend period'), 'hk_trends_global'),
		(new CCheckBox('hk_trends_global'))->setChecked($data['hk_trends_global'] == 1)
	)
	->addRow(
		new CLabel(_('Data storage period (in days)'), 'hk_trends'),
		(new CNumericBox('hk_trends', $data['hk_trends'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setEnabled($data['hk_trends_global'] == 1)
	);

$houseKeeperView = (new CTabView())
	->addTab('houseKeeper', _('Housekeeping'), $houseKeeperTab)
	->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[new CButton('resetDefaults', _('Reset defaults'))]
	));

$widget->addItem((new CForm())->addItem($houseKeeperView));

return $widget;

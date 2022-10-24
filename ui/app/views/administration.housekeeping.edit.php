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


/**
 * @var CView $this
 */

$this->includeJsFile('administration.housekeeping.edit.js.php');

$widget = (new CWidget())
	->setTitle(_('Housekeeping'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu());

$form = (new CForm())
	->setId('housekeeping')
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', 'housekeeping.update')
		->getUrl()
	)
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE);

$house_keeper_tab = (new CFormList())
	->addRow(new CTag('h4', true, _('Events and alerts')))
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_events_mode'),
		(new CCheckBox('hk_events_mode'))
			->setChecked($data['hk_events_mode'] == 1)
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(
		(new CLabel(_('Trigger data storage period'), 'hk_events_trigger'))->setAsteriskMark(),
		(new CTextBox('hk_events_trigger', $data['hk_events_trigger']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_events_mode'] == 1)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Internal data storage period'), 'hk_events_internal'))->setAsteriskMark(),
		(new CTextBox('hk_events_internal', $data['hk_events_internal']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_events_mode'] == 1)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Network discovery data storage period'), 'hk_events_discovery'))
			->setAsteriskMark(),
		(new CTextBox('hk_events_discovery', $data['hk_events_discovery']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_events_mode'] == 1)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Autoregistration data storage period'), 'hk_events_autoreg'))
			->setAsteriskMark(),
		(new CTextBox('hk_events_autoreg', $data['hk_events_autoreg']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_events_mode'] == 1)
			->setAriaRequired()
	)
	->addRow(null)
	->addRow(new CTag('h4', true, _('Services')))
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_services_mode'),
		(new CCheckBox('hk_services_mode'))->setChecked($data['hk_services_mode'] == 1)
	)
	->addRow(
		(new CLabel(_('Data storage period'), 'hk_services'))
			->setAsteriskMark(),
		(new CTextBox('hk_services', $data['hk_services']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_services_mode'] == 1)
			->setAriaRequired()
	)
	->addRow(null)
	->addRow(new CTag('h4', true, _('Audit')))
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_audit_mode'),
		(new CCheckBox('hk_audit_mode'))->setChecked($data['hk_audit_mode'] == 1)
	)
	->addRow(
		(new CLabel(_('Data storage period'), 'hk_audit'))
			->setAsteriskMark(),
		(new CTextBox('hk_audit', $data['hk_audit']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_audit_mode'] == 1)
			->setAriaRequired()
	)
	->addRow(null)
	->addRow(new CTag('h4', true, _('User sessions')))
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_sessions_mode'),
		(new CCheckBox('hk_sessions_mode'))->setChecked($data['hk_sessions_mode'] == 1)
	)
	->addRow(
		(new CLabel(_('Data storage period'), 'hk_sessions'))
			->setAsteriskMark(),
		(new CTextBox('hk_sessions', $data['hk_sessions']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_sessions_mode'] == 1)
			->setAriaRequired()
	)
	->addRow(null)
	->addRow(new CTag('h4', true, _('History')))
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_history_mode'),
		(new CCheckBox('hk_history_mode'))->setChecked($data['hk_history_mode'] == 1)
	)
	->addRow(
		new CLabel(_('Override item history period'), 'hk_history_global'),
		[
			(new CCheckBox('hk_history_global'))->setChecked($data['hk_history_global'] == 1),
			array_key_exists('hk_needs_override_history', $data)
				? new CSpan([
					' ',
					makeWarningIcon(
						_('This setting should be enabled, because history tables contain compressed chunks.')
					)
						->addClass('js-hk-history-warning')
						->addStyle('display: none;')]
				)
				: null
		]
	)
	->addRow(
		(new CLabel(_('Data storage period'), 'hk_history'))
			->setAsteriskMark(),
		(new CTextBox('hk_history', $data['hk_history']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_history_global'] == 1)
			->setAriaRequired()
	)
	->addRow(null)
	->addRow(new CTag('h4', true, _('Trends')))
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_trends_mode'),
		(new CCheckBox('hk_trends_mode'))->setChecked($data['hk_trends_mode'] == 1)
	)
	->addRow(
		new CLabel(_('Override item trend period'), 'hk_trends_global'),
		[
			(new CCheckBox('hk_trends_global'))->setChecked($data['hk_trends_global'] == 1),
			array_key_exists('hk_needs_override_trends', $data)
				? new CSpan([
					' ',
					makeWarningIcon(
						_('This setting should be enabled, because trend tables contain compressed chunks.')
					)
						->addClass('js-hk-trends-warning')
						->addStyle('display: none;')]
				)
				: null
		]
	)
	->addRow(
		(new CLabel(_('Data storage period'), 'hk_trends'))
			->setAsteriskMark(),
		(new CTextBox('hk_trends', $data['hk_trends']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_trends_global'] == 1)
			->setAriaRequired()
	);

	if ($data['db_extension'] == ZBX_DB_EXTENSION_TIMESCALEDB) {
		$house_keeper_tab
			->addRow(null)
			->addRow(new CTag('h4', true, _('History and trends compression')))
			->addRow(
				new CLabel(_('Enable compression'), 'compression_status'),
				(new CCheckBox('compression_status'))
					->setChecked($data['compression_status'] == 1)
					->setEnabled($data['compression_availability'] == 1)
			)
			->addRow(
				(new CLabel(_('Compress records older than'), 'compress_older'))
					->setAsteriskMark(),
				(new CTextBox('compress_older', $data['compress_older']))
					->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
					->setEnabled($data['compression_availability'] == 1 && $data['compression_status'] == 1)
					->setAriaRequired()
			);

		if ($data['compression_availability'] == 0) {
			$house_keeper_tab->addRow('',
				(new CSpan(_('Compression is not available due to incompatible DB version')))->addClass(ZBX_STYLE_RED)
			);
		}
	}

$house_keeper_view = (new CTabView())
	->addTab('houseKeeper', _('Housekeeping'), $house_keeper_tab)
	->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[new CButton('resetDefaults', _('Reset defaults'))]
	));

$widget
	->addItem($form->addItem($house_keeper_view))
	->show();

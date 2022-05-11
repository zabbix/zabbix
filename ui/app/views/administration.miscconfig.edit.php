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

$this->includeJsFile('administration.miscconfig.edit.js.php');

$widget = (new CWidget())
	->setTitle(_('Other configuration parameters'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu());

$from_list = (new CFormList())
	->addRow(new CLabel(_('Frontend URL'), 'url'),
		(new CTextBox('url', $data['url'], false, DB::getFieldLength('config', 'url')))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAttribute('placeholder', _('Example: https://localhost/zabbix/ui/'))
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow((new CLabel(_('Group for discovered hosts'), 'discovery_groupid'))->setAsteriskMark(),
		(new CMultiSelect([
			'name' => 'discovery_groupid',
			'object_name' => 'hostGroup',
			'data' => $data['discovery_group_data'],
			'multiple' => false,
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'name',
					'dstfrm' => 'otherForm',
					'dstfld1' => 'discovery_groupid',
					'normal_only' => '1',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
	)
	->addRow(_('Default host inventory mode'),
		(new CRadioButtonList('default_inventory_mode', (int) $data['default_inventory_mode']))
			->addValue(_('Disabled'), HOST_INVENTORY_DISABLED)
			->addValue(_('Manual'), HOST_INVENTORY_MANUAL)
			->addValue(_('Automatic'), HOST_INVENTORY_AUTOMATIC)
			->setModern(true)
	)
	->addRow(_('User group for database down message'),
		(new CMultiSelect([
			'name' => 'alert_usrgrpid',
			'object_name' => 'usersGroups',
			'data' => $data['alert_usrgrp_data'],
			'multiple' => false,
			'popup' => [
				'parameters' => [
					'srctbl' => 'usrgrp',
					'srcfld1' => 'name',
					'dstfrm' => 'otherForm',
					'dstfld1' => 'alert_usrgrpid',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
	)
	->addRow(_('Log unmatched SNMP traps'),
		(new CCheckBox('snmptrap_logging'))
			->setUncheckedValue('0')
			->setChecked($data['snmptrap_logging'] == 1)
	)
	->addRow((new CTag('h4', true, _('Authorization')))->addClass('input-section-header'))
	->addRow((new CLabel(_('Login attempts'), 'login_attempts'))->setAsteriskMark(),
		(new CNumericBox('login_attempts', $data['login_attempts'], 2))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Login blocking interval'), 'login_block'))->setAsteriskMark(),
		(new CTextBox('login_block', $data['login_block'], false, DB::getFieldLength('config', 'login_block')))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CTag('h4', true, _('Security')))->addClass('input-section-header'))
	->addRow(_('Validate URI schemes'),
		(new CCheckBox('validate_uri_schemes'))
			->setUncheckedValue('0')
			->setChecked($data['validate_uri_schemes'] == 1)
	)
	->addRow((new CLabel(_('Valid URI schemes'), 'uri_valid_schemes')),
		(new CTextBox('uri_valid_schemes', $data['uri_valid_schemes'], false,
			DB::getFieldLength('config', 'uri_valid_schemes')
		))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setEnabled($data['validate_uri_schemes'] == 1)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('X-Frame-Options HTTP header'), 'x_frame_options'))->setAsteriskMark(),
		(new CTextBox('x_frame_options', $data['x_frame_options'], false,
			DB::getFieldLength('config', 'x_frame_options')
		))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAriaRequired()
	)
	->addRow(_('Use iframe sandboxing'),
		(new CCheckBox('iframe_sandboxing_enabled'))
			->setUncheckedValue('0')
			->setChecked($data['iframe_sandboxing_enabled'] == 1)
	)
	->addRow((new CLabel(_('Iframe sandboxing exceptions'), 'iframe_sandboxing_exceptions')),
		(new CTextBox('iframe_sandboxing_exceptions', $data['iframe_sandboxing_exceptions'], false,
			DB::getFieldLength('config', 'iframe_sandboxing_exceptions')
		))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setEnabled($data['iframe_sandboxing_enabled'] == 1)
			->setAriaRequired()
	)
	->addRow((new CTag('h4', true, _('Communication with Zabbix server')))->addClass('input-section-header'))
	->addRow(
		(new CLabel(_('Network timeout'), 'socket_timeout'))->setAsteriskMark(),
		(new CTextBox('socket_timeout', $data['socket_timeout'], false, DB::getFieldLength('config', 'socket_timeout')))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Connection timeout'), 'connect_timeout'))->setAsteriskMark(),
		(new CTextBox('connect_timeout', $data['connect_timeout'], false,
			DB::getFieldLength('config', 'connect_timeout')
		))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Network timeout for media type test'), 'media_type_test_timeout'))->setAsteriskMark(),
		(new CTextBox('media_type_test_timeout', $data['media_type_test_timeout'], false,
			DB::getFieldLength('config', 'media_type_test_timeout')
		))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Network timeout for script execution'), 'script_timeout'))->setAsteriskMark(),
		(new CTextBox('script_timeout', $data['script_timeout'], false, DB::getFieldLength('config', 'script_timeout')))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Network timeout for item test'), 'item_test_timeout'))->setAsteriskMark(),
		(new CTextBox('item_test_timeout', $data['item_test_timeout'], false,
			DB::getFieldLength('config', 'item_test_timeout')
		))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Network timeout for scheduled report test'), 'report_test_timeout'))->setAsteriskMark(),
		(new CTextBox('report_test_timeout', $data['report_test_timeout'], false,
			DB::getFieldLength('config', 'report_test_timeout')
		))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	);

$form = (new CForm())
	->setName('otherForm')
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', 'miscconfig.update')
		->getUrl()
	)
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addItem(
		(new CTabView())
			->addTab('other', _('Other parameters'), $from_list)
			->setFooter(makeFormFooter(
				new CSubmit('update', _('Update')),
				[new CButton('resetDefaults', _('Reset defaults'))]
			))
	);

$widget
	->addItem($form)
	->show();

<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 * @var array $data
 */

$this->includeJsFile('administration.miscconfig.edit.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Other configuration parameters'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_MISCCONFIG_EDIT));

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
					'normal_only' => '1'
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
		(new CNumericBox('login_attempts', $data['login_attempts'], 2, false, false, false))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Login blocking interval'), 'login_block'))->setAsteriskMark(),
		(new CTextBox('login_block', $data['login_block'], false, DB::getFieldLength('config', 'login_block')))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CTag('h4', true, _('Storage of secrets')))->addClass('input-section-header'))
	->addRow(_('Vault provider'),
		(new CRadioButtonList('vault_provider', (int) $data['vault_provider']))
			->addValue(_('HashiCorp Vault'), ZBX_VAULT_TYPE_HASHICORP)
			->addValue(_('CyberArk Vault'), ZBX_VAULT_TYPE_CYBERARK)
			->setModern(true)
	)
	->addRow((new CTag('h4', true, _('Security')))->addClass('input-section-header'))
	->addRow(
		new CLabel(_('Validate URI schemes'), 'validate_uri_schemes'),
		[
			(new CCheckBox('validate_uri_schemes'))
				->setUncheckedValue('0')
				->setChecked($data['validate_uri_schemes'] == 1),
			(new CTextBox('uri_valid_schemes', $data['uri_valid_schemes'], false,
				DB::getFieldLength('config', 'uri_valid_schemes')
			))
				->setAttribute('placeholder', _('Valid URI schemes'))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setEnabled($data['validate_uri_schemes'] == 1)
				->setAriaRequired()
		]
	)
	->addRow(
		(new CLabel([_('Use X-Frame-Options HTTP header'),
			makeHelpIcon([
				_('X-Frame-Options HTTP header supported values:'),
				(new CList([
					_s('%1$s or %2$s - allows the page to be displayed only in a frame on the same origin as the page itself',
						'SAMEORIGIN', "'self'"
					),
					_s('%1$s or %2$s - prevents the page from being displayed in a frame, regardless of the site attempting to do so',
						'DENY', "'none'"
					),
					_s('a string of space-separated hostnames; adding %1$s to the list allows the page to be displayed in a frame on the same origin as the page itself',
						"'self'"
					)
				]))->addClass(ZBX_STYLE_LIST_DASHED),
				BR(),
				_s('Note that %1$s or %2$s will be regarded as hostnames if used without single quotes.', "'self'",
					"'none'"
				)
			])
		], 'x_frame_header_enabled'))->setAsteriskMark(),
		[
			(new CCheckBox('x_frame_header_enabled'))
				->setUncheckedValue('0')
				->setChecked($data['x_frame_header_enabled'] == 1),
			(new CTextBox('x_frame_options', $data['x_frame_options'], false,
				DB::getFieldLength('config', 'x_frame_options')
			))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAttribute('placeholder', _('X-Frame-Options HTTP header'))
				->setAriaRequired()
				->setEnabled($data['x_frame_header_enabled'] == 1)
		]
	)
	->addRow(
		new CLabel(_('Use iframe sandboxing'), 'iframe_sandboxing_enabled'),
		[
			(new CCheckBox('iframe_sandboxing_enabled'))
				->setUncheckedValue('0')
				->setChecked($data['iframe_sandboxing_enabled'] == 1),
			(new CTextBox('iframe_sandboxing_exceptions', $data['iframe_sandboxing_exceptions'], false,
				DB::getFieldLength('config', 'iframe_sandboxing_exceptions')
			))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAttribute('placeholder', _('Iframe sandboxing exceptions'))
				->setEnabled($data['iframe_sandboxing_enabled'] == 1)
				->setAriaRequired()
		]
	);

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('miscconfig')))->removeId())
	->setId('miscconfig-form')
	->setName('otherForm')
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', 'miscconfig.update')
		->getUrl()
	)
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addItem(
		(new CTabView())
			->addTab('other', _('Other parameters'), $from_list)
			->setFooter(makeFormFooter(
				new CSubmit('update', _('Update')),
				[new CButton('resetDefaults', _('Reset defaults'))]
			))
	);

$html_page
	->addItem($form)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'default_inventory_mode' => DB::getDefault('config', 'default_inventory_mode'),
		'iframe_sandboxing_enabled' => DB::getDefault('config', 'iframe_sandboxing_enabled'),
		'iframe_sandboxing_exceptions' => DB::getDefault('config', 'iframe_sandboxing_exceptions'),
		'login_attempts' => DB::getDefault('config', 'login_attempts'),
		'login_block' => DB::getDefault('config', 'login_block'),
		'snmptrap_logging' => DB::getDefault('config', 'snmptrap_logging'),
		'uri_valid_schemes' => DB::getDefault('config', 'uri_valid_schemes'),
		'url' => DB::getDefault('config', 'url'),
		'validate_uri_schemes' => DB::getDefault('config', 'validate_uri_schemes'),
		'vault_provider' => DB::getDefault('config', 'vault_provider'),
		'x_frame_options' => DB::getDefault('config', 'x_frame_options')
	]).');
'))
	->setOnDocumentReady()
	->show();

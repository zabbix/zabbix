<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

$this->includeJsFile('administration.apm.db.edit.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('APM'))
	->setTitleSubmenu(getAdministrationDataSourceSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_DATA_SOURCE_APM_EDIT));

$apm_tab = (new CFormGrid())
	->addItem([
		(new CLabel([
			_('Enable global data source'),
			makeHelpIcon(_('The global data source will be used by the Frontend, Server, and Proxies, unless explicitly overriden in the respective configuration files.'))
		], 'status')),
		new CFormField(
			(new CCheckBox('status'))
				->setUncheckedValue('0')
				->setChecked($data['show_fields']),
		)
	])
	->addItem([
		(new CLabel(_('Database type'), 'type'))
			->addClass('js-type')
			->setAsteriskMark($data['is_type_sql'])
			->addClass($data['show_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CSelect('type'))
				->addOptions([
					(new CSelectOption(ZBX_DB_CLICKHOUSE, _('ClickHouse'))),
					(new CSelectOption(ZBX_DB_MYSQL, _('MySQL'))),
					(new CSelectOption(ZBX_DB_POSTGRESQL, _('PostgreSQL'))),
					(new CSelectOption(ZBX_DB_ELASTICSEARCH, _('Elasticsearch')))
				])
				->setValue($data['type'])
		))
			->addClass('js-type')
			->addClass($data['show_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel([
			_('Host'),
			makeHelpIcon([
				_('Enter one or more values as host:port or [host]:port (IPv6), separated by commas.'),
				BR(),
				_('If no port is specified, the "Database port" value is used.')
			])
				->addClass('js-host-help')
				->addClass($data['is_type_postgresql'] ? null : ZBX_STYLE_DISPLAY_NONE)
		], 'host'))
			->setAsteriskMark()
			->addClass('js-host')
			->addClass($data['show_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField([
			(new CTextBox('host', $data['host']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setReadonly($data['host'] !== ''),
			(new CDiv())
				->addClass(ZBX_STYLE_FORM_INPUT_MARGIN)
				->addClass('js-change-host'),
			(new CButton('change_host', _('Change host')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->addClass('js-change-host')
		]))
			->addClass('js-host')
			->addClass($data['show_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Port'), 'port'))
			->addClass('js-port')
			->addClass($data['show_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField([
			(new CTextBox('port', $data['port']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
			(new CDiv())
				->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CSpan(_('0 - use default port')))
				->addClass(ZBX_STYLE_GREY)
		]))->addClass('js-port')
			->addClass($data['show_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Authentication'), 'authentication'))
			->addClass('js-authentication')
			->addClass($data['show_authentication_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CRadioButtonList('authentication', (int) $data['authentication']))
				->addValue(_('None'), ELASTICSEARCH_AUTH_NONE)
				->addValue(_('Basic'), ELASTICSEARCH_AUTH_BASIC)
				->addValue(_('API key'), ELASTICSEARCH_AUTH_API_KEY)
				->setModern()
		))
			->addClass('js-authentication')
			->addClass($data['show_authentication_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('API key'), 'api_key'))
			->setAsteriskMark()
			->addClass('js-api-key')
			->addClass($data['show_api_key_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField([
			(new CTextBox('api_key'))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->addClass($data['has_api_key'] ? ZBX_STYLE_DISPLAY_NONE : null),
			makeWarningIcon(_('The previous API key was cleared due to a host change. Please enter the new API key.'))
				->addClass('js-api-key-warning')
				->addClass(ZBX_STYLE_DISPLAY_NONE),
			(new CButton('change_api_key', _('Change API key')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->addClass($data['has_api_key'] ? null : ZBX_STYLE_DISPLAY_NONE)
		]))
			->addClass('js-api-key')
			->addClass($data['show_api_key_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Name'), 'database'))
			->addClass('js-database')
			->setAsteriskMark($data['is_type_sql'])
			->addClass($data['show_database_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CTextBox('database', $data['database']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAriaRequired()
		))
			->addClass('js-database')
			->addClass($data['show_database_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Schema'), 'schema'))
			->addClass('js-schema')
			->addClass($data['show_schema_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CTextBox('schema', $data['schema']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
		))
			->addClass('js-schema')
			->addClass($data['show_schema_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Username'), 'username'))
			->addClass('js-username')
			->addClass($data['show_user_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CTextBox('username', $data['username']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
		))
			->addClass('js-username')
			->addClass($data['show_user_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Password'), 'password'))
			->addClass('js-password')
			->addClass($data['show_user_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField([
			(new CPassBox('password'))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->addClass($data['has_password'] ? ZBX_STYLE_DISPLAY_NONE : null),
			makeWarningIcon(_('The previous password was cleared due to a host change. Please enter the new password.'))
				->addClass('js-password-warning')
				->addClass(ZBX_STYLE_DISPLAY_NONE),
			(new CButton('change_password', _('Change password')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->addClass($data['has_password'] ? null : ZBX_STYLE_DISPLAY_NONE)
		]))
			->addClass('js-password')
			->addClass($data['show_user_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Use secure connection (TLS)'), 'encryption'))
			->addClass('js-encryption')
			->addClass($data['show_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CCheckBox('encryption'))
				->setUncheckedValue('0')
				->setChecked($data['encryption'] == 1),
		))
			->addClass('js-encryption')
			->addClass($data['show_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Verify server certificate'), 'verify_peer'))
			->addClass('js-verify-peer')
			->addClass($data['show_encryption_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CCheckBox('verify_peer'))
				->setUncheckedValue('0')
				->setChecked($data['verify_peer'] == 1),
		))
			->addClass('js-verify-peer')
			->addClass($data['show_encryption_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('CA certificate file'), 'ca_file'))
			->addClass('js-ca-file')
			->addClass($data['show_verify_peer'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CTextBox('ca_file', $data['ca_file']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
		))
			->addClass('js-ca-file')
			->addClass($data['show_verify_peer'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Client private key file'), 'key_file'))
			->addClass('js-key-file')
			->addClass($data['show_key_file_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CTextBox('key_file', $data['key_file']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
		))
			->addClass('js-key-file')
			->addClass($data['show_key_file_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Client certificate file'), 'cert_file'))
			->addClass('js-cert-file')
			->addClass($data['show_cert_file_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CTextBox('cert_file', $data['cert_file']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
		))
			->addClass('js-cert-file')
			->addClass($data['show_cert_file_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Verify hostname'), 'verify_host'))
			->addClass('js-verify-host')
			->addClass($data['show_encryption_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CCheckBox('verify_host'))
				->setUncheckedValue('0')
				->setChecked($data['verify_host'] == 1),
		))
			->addClass('js-verify-host')
			->addClass($data['show_encryption_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	]);

$apm_view = (new CTabView())
	->addTab('apm', _('APM'), $apm_tab)
	->setFooter(makeFormFooter(
		(new CSubmit('', _('Update')))->addClass('js-submit')
	));

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('apm')))->removeId())
	->setId('apm-form')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addItem($apm_view)
	->addVar('change_password', $data['change_password'])
	->addVar('change_api_key', $data['change_api_key']);

$html_page
	->addItem($form)
	->show();

(new CScriptTag(
	'view.init('.json_encode([
		'rules' => $data['js_validation_rules'],
		'default_values' => $data['default_values'],
		'has_api_key' => $data['has_api_key'],
		'has_password' => $data['has_password']
	]).');'
))
	->setOnDocumentReady()
	->show();

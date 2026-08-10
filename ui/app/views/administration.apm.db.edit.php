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
				->setUncheckedValue(APM_GLOBAL_DB_STATUS_NOT_CONFIGURED)
				->setChecked($data['values']['status'] === APM_GLOBAL_DB_STATUS_CONFIGURED),
		)
	])
	->addItem([
		(new CLabel(_('Database type'), 'type'))
			->addClass('js-db-type'),
		(new CFormField(
			_('ClickHouse')
		))->addClass('js-db-type')
	])
	->addItem([
		(new CLabel(_('URL'), 'url'))
			->setAsteriskMark()
			->addClass('js-url'),
		(new CFormField(
			(new CTextBox('url', $data['values']['url']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAttribute('maxlength', 2048)
		))->addClass('js-url')
	])
	->addItem([
		(new CLabel(_('Authentication'), 'authentication_type'))
			->setAsteriskMark()
			->addClass('js-auth-type'),
		(new CFormField(
			(new CRadioButtonList('authentication_type', (int) $data['values']['authentication_type']))
				->addValue(_('Username and password'), APM_GLOBAL_DB_AUTHTYPE_PASSWORD)
				->addValue(_('Vault path'), APM_GLOBAL_DB_AUTHTYPE_VAULT)
				->addValue(_('None'), APM_GLOBAL_DB_AUTHTYPE_NONE)
				->setModern()
		))->addClass('js-auth-type')
	])
	->addItem([
		(new CLabel(_('Username'), 'username'))
			->setAsteriskMark()
			->addClass('js-username'),
		(new CFormField(
			(new CTextBox('username', $data['values']['username']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
		))->addClass('js-username')
	])
	->addItem([
		(new CLabel(_('Password'), 'password'))
			->addClass('js-password'),
		(new CFormField([
			(new CPassBox('password'))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
			makeWarningIcon(_('The previous password was cleared due to a url change. Please enter the new password.'))
				->addClass('js-password-warning'),
			(new CButton('change_password', _('Change password')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->addClass('js-change-password')
		]))->addClass('js-password')
	])
	->addItem([
		(new CLabel(_('Vault path'), 'vault_path'))
			->setAsteriskMark()
			->addClass('js-vault-path'),
		(new CFormField(
			(new CTextBox('vault_path', $data['values']['vault_path']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
		))->addClass('js-vault-path')
	])
	->addItem([
		(new CLabel(_('Database'), 'db'))
			->addClass('js-database'),
		(new CFormField(
			(new CTextBox('db', $data['values']['db']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
		))->addClass('js-database')
	])
	->addItem([
		(new CLabel(_('Verify server certificate'), 'ssl_verify_peer'))
			->addClass('js-ssl-verify-peer'),
		(new CFormField(
			(new CCheckBox('ssl_verify_peer'))
				->setUncheckedValue(APM_GLOBAL_DB_VERIFY_PEER_DISABLED)
				->setChecked($data['values']['ssl_verify_peer'] == APM_GLOBAL_DB_VERIFY_PEER_ENABLED),
		))->addClass('js-ssl-verify-peer')
	])
	->addItem([
		(new CLabel(_('CA certificate file'), 'ssl_ca_location'))
			->addClass('js-ssl-ca-location'),
		(new CFormField(
			(new CTextBox('ssl_ca_location', $data['values']['ssl_ca_location']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAttribute('maxlength', 2048)
		))->addClass('js-ssl-ca-location')
	])
	->addItem([
		(new CLabel(_('Client certificate file'), 'ssl_cert_file'))
			->addClass('js-ssl-cert-file'),
		(new CFormField(
			(new CTextBox('ssl_cert_file', $data['values']['ssl_cert_file']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAttribute('maxlength', 2048)
		))->addClass('js-ssl-cert-file')
	])
	->addItem([
		(new CLabel(_('Client private key file'), 'ssl_key_file'))
			->addClass('js-ssl-key-file'),
		(new CFormField(
			(new CTextBox('ssl_key_file', $data['values']['ssl_key_file']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAttribute('maxlength', 2048)
		))->addClass('js-ssl-key-file')
	])
	->addItem([
		(new CLabel(_('Client private key password'), 'ssl_key_password'))
			->addClass('js-ssl-key-password'),
		(new CFormField([
			(new CPassBox('ssl_key_password'))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
			(new CButton('change_ssl_key_password', _('Change password')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->addClass('js-change-ssl-key-password')
		]))->addClass('js-ssl-key-password')
	])
	->addItem([
		(new CLabel(_('Verify hostname'), 'ssl_verify_host'))
			->addClass('js-ssl-verify-host'),
		(new CFormField(
			(new CCheckBox('ssl_verify_host'))
				->setUncheckedValue(APM_GLOBAL_DB_VERIFY_HOST_DISABLED)
				->setChecked($data['values']['ssl_verify_host'] == APM_GLOBAL_DB_VERIFY_HOST_ENABLED),
		))->addClass('js-ssl-verify-host')
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
	->setAttribute('hidden', '');

$html_page
	->addItem($form)
	->show();

(new CScriptTag(
	'view.init('.json_encode([
		'rules' => $data['js_validation_rules'],
		'default_values' => $data['default_values']
	]).');'
))
	->setOnDocumentReady()
	->show();

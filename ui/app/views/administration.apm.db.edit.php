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
			->addClass('js-db-type')
			->addClass($data['show_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			_('ClickHouse')
		))
			->addClass('js-db-type')
			->addClass($data['show_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('URL'), 'url'))
			->setAsteriskMark()
			->addClass('js-url')
			->addClass($data['show_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CTextBox('url', $data['url']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAttribute('maxlength', 2048)
		))
			->addClass('js-url')
			->addClass($data['show_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Authentication'), 'authentication_type'))
			->setAsteriskMark()
			->addClass('js-auth-type')
			->addClass($data['show_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CRadioButtonList('authentication_type', (int) $data['authentication_type']))
				->addValue(_('Username and password'), APM_AUTH_TYPE_PASSWORD)
				->addValue(_('Vault path'), APM_AUTH_TYPE_VAULT_PATH)
				->addValue(_('None'), APM_AUTH_TYPE_NONE)
				->setModern()
		))
			->addClass('js-auth-type')
			->addClass($data['show_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Username'), 'username'))
			->setAsteriskMark()
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
			makeWarningIcon(_('The previous password was cleared due to a url change. Please enter the new password.'))
				->addClass('js-password-warning')
				->addClass(ZBX_STYLE_DISPLAY_NONE),
			(new CButton('change_password', _('Change password')))
				->removeId()
				->removeAttribute('name')
				->addClass(ZBX_STYLE_BTN_GREY)
				->addClass('js-change-password')
				->addClass($data['has_password'] ? null : ZBX_STYLE_DISPLAY_NONE)
		]))
			->addClass('js-password')
			->addClass($data['show_user_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Vault path'), 'vault_path'))
			->setAsteriskMark()
			->addClass('js-vault-path')
			->addClass($data['show_vault_path'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CTextBox('vault_path', $data['vault_path']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
		))
			->addClass('js-vault-path')
			->addClass($data['show_vault_path'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Database'), 'db'))
			->addClass('js-database')
			->addClass($data['show_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CTextBox('db', $data['db']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
		))
			->addClass('js-database')
			->addClass($data['show_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Verify server certificate'), 'ssl_verify_peer'))
			->addClass('js-ssl-verify-peer')
			->addClass($data['show_ssl_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CCheckBox('ssl_verify_peer'))
				->setUncheckedValue('0')
				->setChecked($data['ssl_verify_peer'] == 1),
		))
			->addClass('js-ssl-verify-peer')
			->addClass($data['show_ssl_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('CA certificate file'), 'ssl_ca_location'))
			->addClass('js-ssl-ca-location')
			->addClass($data['show_ssl_verify_peer_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CTextBox('ssl_ca_location', $data['ssl_ca_location']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAttribute('maxlength', 2048)
		))
			->addClass('js-ssl-ca-location')
			->addClass($data['show_ssl_verify_peer_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Client certificate file'), 'ssl_cert_file'))
			->addClass('js-cert-file')
			->addClass($data['show_ssl_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CTextBox('ssl_cert_file', $data['ssl_cert_file']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAttribute('maxlength', 2048)
		))
			->addClass('js-cert-file')
			->addClass($data['show_ssl_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Client private key file'), 'ssl_key_file'))
			->addClass('js-ssl-key-file')
			->addClass($data['show_ssl_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CTextBox('ssl_key_file', $data['ssl_key_file']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAttribute('maxlength', 2048)
		))
			->addClass('js-ssl-key-file')
			->addClass($data['show_ssl_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Client private key file password'), 'ssl_key_password'))
			->addClass('js-ssl-key-password')
			->addClass($data['show_ssl_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CTextBox('ssl_key_password', $data['ssl_key_password']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
		))
			->addClass('js-ssl-key-password')
			->addClass($data['show_ssl_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('Verify hostname'), 'ssl_verify_host'))
			->addClass('js-ssl-verify-host')
			->addClass($data['show_ssl_verify_peer_fields'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CCheckBox('ssl_verify_host'))
				->setUncheckedValue('0')
				->setChecked($data['ssl_verify_host'] == 1),
		))
			->addClass('js-ssl-verify-host')
			->addClass($data['show_ssl_verify_peer_fields'] ? null : ZBX_STYLE_DISPLAY_NONE)
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
	->addVar('change_password', $data['change_password']);

$html_page
	->addItem($form)
	->show();

(new CScriptTag(
	'view.init('.json_encode([
		'rules' => $data['js_validation_rules'],
		'default_values' => $data['default_values'],
		'has_password' => $data['has_password']
	]).');'
))
	->setOnDocumentReady()
	->show();

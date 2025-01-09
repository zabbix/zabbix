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
 */

$this->includeJsFile('administration.audit.settings.edit.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Audit log'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_AUDITLOG_EDIT));

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('audit')))->removeId())
	->setId('audit-settings')
	->setAction(
		(new CUrl('zabbix.php'))
			->setArgument('action', 'audit.settings.update')
			->getUrl()
	)
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID);

$audit_settings_tab = (new CFormGrid())
	->addItem([
		new CLabel(_('Enable audit logging'), 'auditlog_enabled'),
		new CFormField((new CCheckBox('auditlog_enabled'))->setChecked($data['auditlog_enabled'] == 1))
	])
	->addItem([
		new CLabel([
			_('Log system actions'),
			makeHelpIcon(_('Log changes by low-level discovery, network discovery and autoregistration'))
		], 'auditlog_mode'),
		new CFormField(
			(new CCheckBox('auditlog_mode'))
				->setEnabled($data['auditlog_enabled'] == 1)
				->setChecked($data['auditlog_mode'] == 1)
		)
	])
	->addItem([
		new CLabel(_('Enable internal housekeeping'), 'hk_audit_mode'),
		new CFormField((new CCheckBox('hk_audit_mode'))->setChecked($data['hk_audit_mode'] == 1))
	])
	->addItem([
		(new CLabel(_('Data storage period'), 'hk_audit'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('hk_audit', $data['hk_audit'], false, DB::getFieldLength('config', 'hk_audit')))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setEnabled($data['hk_audit_mode'] == 1)
				->setAriaRequired()
		)
	]);

$form->addItem(
	(new CTabView())
		->addTab('audit-settings', _('Audit log'), $audit_settings_tab)
		->setFooter(makeFormFooter(
			new CSubmit('update', _('Update')),
			[new CButton('resetDefaults', _('Reset defaults'))]
		))
);

$html_page
	->addItem($form)
	->show();

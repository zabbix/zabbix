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

$this->includeJsFile('administration.audit.settings.edit.js.php');

$widget = (new CWidget())
	->setTitle(_('Audit log'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_AUDIT_SETTINGS_EDIT));

$form = (new CForm())
	->setId('audit-settings')
	->setAction(
		(new CUrl('zabbix.php'))
			->setArgument('action', 'audit.settings.update')
			->getUrl()
	)
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE);

$audit_settings_tab = (new CFormGrid())
	->addItem([
		new CLabel(_('Enable audit logging'), 'auditlog_enabled'),
		new CFormField((new CCheckBox('auditlog_enabled'))->setChecked($data['auditlog_enabled'] == 1))
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

$widget
	->addItem($form)
	->show();

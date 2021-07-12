<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
	->setTitleSubmenu(getAdministrationGeneralSubmenu());

$form = (new CForm())
	->setId('audit-settings')
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', 'audit.settings.update')
		->getUrl()
	)
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE);

$audit_settings_tab = (new CFormList())
	->addRow(
		new CLabel(_('Enable audit logging'), 'audit_logging_enabled'),
		(new CCheckBox('audit_logging_enabled'))->setChecked($data['audit_logging_enabled'] == 1)
	)
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_audit_mode'),
		(new CCheckBox('hk_audit_mode'))->setChecked($data['hk_audit_mode'] == 1)
	)
	->addRow(
		(new CLabel(_('Data storage period'), 'hk_audit'))
			->setAsteriskMark(),
		(new CTextBox('hk_audit', $data['hk_audit'], false, DB::getFieldLength('config', 'hk_audit')))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_audit_mode'] == 1)
			->setAriaRequired()
	);

$audit_settings_view = (new CTabView())
	->addTab('audit-settings', _('Audit log'), $audit_settings_tab)
	->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[new CButton('resetDefaults', _('Reset defaults'))]
	));

$widget
	->addItem($form->addItem($audit_settings_view))
	->show();

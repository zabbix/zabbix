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
 * @var CPartial $this
 * @var array $data
 */

$form = (new CForm())
	->setId('templategroupForm')
	->setName('templategroupForm')
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('groupid', $data['groupid'])
	->addItem((new CInput('submit'))->addStyle('display: none;'));

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Group name'), 'name'))->setAsteriskMark(),
		new CFormField((new CTextBox('name', $data['name']))
			->setAttribute('autofocus', 'autofocus')
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
		)
	]);

if ($data['groupid'] != 0 && CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
	$form_grid->addItem([
		new CFormField((new CCheckBox('subgroups'))
			->setLabel(_('Apply permissions to all subgroups'))
			->setChecked($data['subgroups']))
	]);
}

$tabs = (new CTabView(['id' => 'templategroup-tabs']))->addTab('templategroup-tab', _('Template group'), $form_grid);

if (array_key_exists('buttons', $data)) {
	$primary_btn = array_shift($data['buttons']);
	$tabs->setFooter(makeFormFooter($primary_btn, $data['buttons']));
}

$form
	->addItem($tabs)
	->show();

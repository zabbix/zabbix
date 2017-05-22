<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
$edit_form = (new CForm())
	->setName('dashboard_form')
	->setAttribute('style', 'display: none;');

$user_multiselect = (new CMultiSelect([
	'name' => 'userid',
	'selectedLimit' => 1,
	'objectName' => 'users',
	'disabled' => (CWebUser::getType() != USER_TYPE_SUPER_ADMIN && CWebUser::getType() != USER_TYPE_ZABBIX_ADMIN),
	'popup' => [
		'parameters' => 'srctbl=users&dstfrm='.$edit_form->getName().'&dstfld1=userid&srcfld1=userid&srcfld2=fullname'
	]
]))
	->setAttribute(
		'data-default-owner',
		CJs::encodeJson($data['dashboard']['owner'])
	)
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$edit_form->addItem((new CFormList())
	->addRow(_('Owner'), $user_multiselect)
	->addRow(_('Name'),
		(new CTextBox('name', $data['dashboard']['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
	)
);

return $edit_form;


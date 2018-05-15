<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


$this->addJsFile('multiselect.js');
$this->includeJSfile('app/views/monitoring.dashboard.edit_form.js.php');

$form = (new CForm())
	->cleanItems()
	->setName('dashboard_form')
	->setAttribute('style', 'display: none;');

$multiselect = (new CMultiSelect([
	'name' => 'userid',
	'object_name' => 'users',
	'disabled' => in_array(CWebUser::getType(), [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN]),
	'multiple' => false,
	'popup' => [
		'parameters' => [
			'srctbl' => 'users',
			'srcfld1' => 'userid',
			'srcfld2' => 'fullname',
			'dstfrm' => $form->getName(),
			'dstfld1' => 'userid'
		]
	],
	'call_post_event' => true
]))
	->setAttribute('data-default-owner', CJs::encodeJson($data['dashboard']['owner']))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAriaRequired();

$form->addItem((new CFormList())
	->addRow((new CLabel(_('Owner'), 'userid_ms'))->setAsteriskMark(), $multiselect)
	->addRow((new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['dashboard']['name'], false, DB::getFieldLength('dashboard', 'name')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	)
);

if ($data['dashboard']['dashboardid'] == 0) {
	// Edit Form should be opened after multiselect initialization
	$this->addPostJS(
		'jQuery(document).on("'.$multiselect->getJsEventName().'", function() {'.
			'showEditMode();'.
			'dashbrd_config();'.
		'});'
	);
}

return $form;

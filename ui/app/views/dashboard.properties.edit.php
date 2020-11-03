<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

$form = (new CForm())
	->cleanItems()
	->setName('dashboard_properties_form')
	->addItem(getMessages());

$form_list = new CFormList();

$script_inline = '';

if (!$data['dashboard']['template']) {
	$owner_select = (new CMultiSelect([
		'name' => 'userid',
		'object_name' => 'users',
		'data' => [$data['dashboard']['owner']],
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
		]
	]))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired();

	$form_list->addRow((new CLabel(_('Owner'), 'userid_ms'))->setAsteriskMark(), $owner_select);

	$script_inline .= $owner_select->getPostJS();
}

$form_list->addRow((new CLabel(_('Name'), 'name'))->setAsteriskMark(),
	(new CTextBox('name', $data['dashboard']['name'], false, DB::getFieldLength('dashboard', 'name')))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired()
		->setAttribute('autofocus', 'autofocus')
);

$form->addItem($form_list);

$output = [
	'header' => _('Dashboard properties'),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Apply'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'dashboard.applyProperties(overlay);'
		]
	]
];

if ($script_inline !== '') {
	$output['script_inline'] = $script_inline;
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

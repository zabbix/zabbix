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

$form = (new CForm())
	->cleanItems()
	->setName('dashboard_properties_form')
	->addItem(getMessages());

// Submit button is needed to enable submit event on Enter on inputs.
$form->addItem((new CInput('submit', 'dashboard_properties_submit'))->addStyle('display: none;'));

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

$display_period_select = (new CSelect('display_period'))
	->setValue($data['dashboard']['display_period'])
	->setFocusableElementId('display_period')
	->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);

foreach (DASHBOARD_DISPLAY_PERIODS as $period) {
	$display_period_select->addOption(new CSelectOption($period, secondsToPeriod($period)));
}

$form_list->addRow(new CLabel(_('Default page display period'), 'display_period'), $display_period_select);

$form_list->addRow(new CLabel(_('Start slideshow automatically'), 'auto_start'),
	(new CCheckBox('auto_start'))->setChecked($data['dashboard']['auto_start'] == 1)
);

$form->addItem($form_list);

$output = [
	'header' => _('Dashboard properties'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DASHBOARD_PROPERTIES_EDIT),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Apply'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'ZABBIX.Dashboard.applyProperties();'
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

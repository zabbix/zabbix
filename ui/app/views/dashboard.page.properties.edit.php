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
	->setName('dashboard_page_properties_form')
	->addItem(getMessages());

// Submit button is needed to enable submit event on Enter on inputs.
$form->addItem((new CInput('submit', 'dashboard_page_properties_submit'))->addStyle('display: none;'));

$form_list = new CFormList();

$form_list->addRow(new CLabel(_('Name'), 'name'),
	(new CTextBox('name', $data['dashboard_page']['name'], false, DB::getFieldLength('dashboard_page', 'name')))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAttribute('autofocus', 'autofocus')
);

$display_period_select = (new CSelect('display_period'))
	->setValue($data['dashboard_page']['display_period'])
	->setFocusableElementId('display_period')
	->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);

$display_period_select->addOption(
	new CSelectOption(0, _s('Default (%1$s)', secondsToPeriod($data['dashboard']['display_period'])))
);

foreach (DASHBOARD_DISPLAY_PERIODS as $period) {
	$display_period_select->addOption(new CSelectOption($period, secondsToPeriod($period)));
}

$form_list->addRow(new CLabel(_('Page display period'), 'display_period'), $display_period_select);

$form->addItem($form_list);

$output = [
	'header' => _('Dashboard page properties'),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Apply'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'ZABBIX.Dashboard.applyDashboardPageProperties();'
		]
	],
	'data' => [
		'unique_id' => $data['dashboard_page']['unique_id']
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

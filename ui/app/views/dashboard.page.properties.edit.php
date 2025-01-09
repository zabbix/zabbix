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

$form = (new CForm())
	->setName('dashboard_page_properties_form')
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

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
	'doc_url' => CDocHelper::getUrl(CDocHelper::DASHBOARDS_PAGE_PROPERTIES_EDIT),
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

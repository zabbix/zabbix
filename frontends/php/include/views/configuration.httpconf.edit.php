<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


$widget = (new CWidget())->setTitle(_('Web monitoring'));

// append host summary to widget header
if (!empty($this->data['hostid'])) {
	$widget->addItem(get_header_host_table('web', $this->data['hostid']));
}

// create form
$http_form = (new CForm())
	->setName('httpForm')
	->setId('httpForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', $this->data['form'])
	->addVar('hostid', $this->data['hostid'])
	->addVar('templated', $this->data['templated']);

if (!empty($this->data['httptestid'])) {
	$http_form->addVar('httptestid', $this->data['httptestid']);
}

/*
 * Scenario tab
 */
$http_form_list = new CFormList();

// Parent http tests
if (!empty($this->data['templates'])) {
	$http_form_list->addRow(_('Parent web scenarios'), $this->data['templates']);
}

// Name
$name_text_box = (new CTextBox('name', $this->data['name'], $this->data['templated'], 64))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAriaRequired();
if (!$this->data['templated']) {
	$name_text_box->setAttribute('autofocus', 'autofocus');
}
$http_form_list->addRow((new CLabel(_('Name'), 'name'))->setAsteriskMark(), $name_text_box);

// Application
if ($this->data['application_list']) {
	$applications = zbx_array_merge([''], $this->data['application_list']);
	$http_form_list->addRow(_('Application'),
		new CComboBox('applicationid', $this->data['applicationid'], null, $applications)
	);
}
else {
	$http_form_list->addRow(_('Application'), new CSpan(_('No applications found.')));
}

// New application
$http_form_list
	->addRow(new CLabel(_('New application'), 'new_application'),
		(new CSpan(
			(new CTextBox('new_application', $this->data['new_application']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->addClass(ZBX_STYLE_FORM_NEW_GROUP)
	)
	->addRow((new CLabel(_('Update interval'), 'delay'))->setAsteriskMark(),
		(new CTextBox('delay', $data['delay']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Attempts'), 'retries'))->setAsteriskMark(),
		(new CNumericBox('retries', $this->data['retries'], 2))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);

$agent_combo_box = new CComboBox('agent', $this->data['agent']);

$user_agents_all = userAgents();
$user_agents_all[_('Others')][ZBX_AGENT_OTHER] = _('other').' ...';

foreach ($user_agents_all as $user_agent_group => $user_agents) {
	$agent_combo_box->addItemsInGroup($user_agent_group, $user_agents);
}

$http_form_list->addRow(_('Agent'), $agent_combo_box);

$http_form_list->addRow(_('User agent string'),
	(new CTextBox('agent_other', $this->data['agent_other']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_agent_other'
);

// append HTTP proxy to form list
$http_form_list
	->addRow(_('HTTP proxy'),
		(new CTextBox('http_proxy', $this->data['http_proxy'], false, 255))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', '[protocol://][user[:password]@]proxy.example.com[:port]'));

$http_form_list->addRow(_('Variables'), (new CDiv(
	(new CTable())
		->addClass('httpconf-dynamic-row')
		->setAttribute('data-type', 'variables')
		->setAttribute('style', 'width: 100%;')
		->setHeader(['', _('Name'), '', _('Value'), ''])
		->addRow((new CRow([
			(new CCol(
				(new CButton(null, _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
			))->setColSpan(5)
		])))
))
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->setAttribute('style', 'min-width: ' . ZBX_TEXTAREA_BIG_WIDTH . 'px;')
);

$http_form_list->addRow(_('Headers'), (new CDiv(
	(new CTable())
		->addClass('httpconf-dynamic-row')
		->setAttribute('data-type', 'headers')
		->setAttribute('style', 'width: 100%;')
		->setHeader(['', _('Name'), '', _('Value'), ''])
		->addRow((new CRow([
			(new CCol(
				(new CButton(null, _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
			))->setColSpan(5)
		])))
))
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->setAttribute('style', 'min-width: ' . ZBX_TEXTAREA_BIG_WIDTH . 'px;')
);

$http_form_list->addRow(_('Enabled'), (new CCheckBox('status'))->setChecked(!$this->data['status']));

/*
 * Authentication tab
 */
$http_authentication_form_list = new CFormList();

// Authentication type
$http_authentication_form_list->addRow(_('HTTP authentication'),
	new CComboBox('authentication', $this->data['authentication'], null, httptest_authentications())
);

$http_authentication_form_list
	->addRow(new CLabel(_('User'), 'http_user'),
		(new CTextBox('http_user', $this->data['http_user'], false, 64))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(new CLabel(_('Password'), 'http_password'),
		(new CTextBox('http_password', $this->data['http_password'], false, 64))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('SSL verify peer'),
		(new CCheckBox('verify_peer'))->setChecked($this->data['verify_peer'] == 1)
	)
	->addRow(_('SSL verify host'),
		(new CCheckBox('verify_host'))->setChecked($this->data['verify_host'] == 1)
	)
	->addRow(_('SSL certificate file'),
		(new CTextBox('ssl_cert_file', $this->data['ssl_cert_file'], false, 255))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('SSL key file'),
		(new CTextBox('ssl_key_file', $this->data['ssl_key_file'], false, 255))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('SSL key password'),
		(new CTextBox('ssl_key_password', $this->data['ssl_key_password'], false, 64))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

/*
 * Step tab
 */
$http_step_form_list = new CFormList();
$steps_table = (new CTable())
	->addClass('httpconf-steps-dynamic-row')
	->setHeader([
		(new CColHeader())->setWidth('15'),
		(new CColHeader())->setWidth('15'),
		(new CColHeader(_('Name')))->setWidth('150'),
		(new CColHeader(_('Timeout')))->setWidth('50'),
		(new CColHeader(_('URL')))->setWidth('200'),
		(new CColHeader(_('Required')))->setWidth('75'),
		(new CColHeader(_('Status codes')))
			->addClass(ZBX_STYLE_NOWRAP)
			->setWidth('90'),
		(new CColHeader(_('Action')))->setWidth('50')
	]);

if (!$this->data['templated']) {
	$steps_table->addRow(
		(new CCol(
			(new CButton(null, _('Add')))
				->addClass('element-table-add')
				->addClass(ZBX_STYLE_BTN_LINK)
		))->setColSpan(8)
	);
}
else {
	$steps_table->addRow(
		(new CCol(null))->setColSpan(8)->addClass('element-table-add')
	);
}

$http_step_form_list->addRow((new CLabel(_('Steps'), $steps_table->getId()))->setAsteriskMark(),
	(new CDiv($steps_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAriaRequired()
);

// append tabs to form
$http_tab = (new CTabView())
	->addTab('scenarioTab', _('Scenario'), $http_form_list)
	->addTab('stepTab', _('Steps'), $http_step_form_list)
	->addTab('authenticationTab', _('Authentication'), $http_authentication_form_list);
if (!$this->data['form_refresh']) {
	$http_tab->setSelected(0);
}

// append buttons to form
if (!empty($this->data['httptestid'])) {
	$buttons = [new CSubmit('clone', _('Clone'))];

	if ($this->data['host']['status'] == HOST_STATUS_MONITORED
			|| $this->data['host']['status'] == HOST_STATUS_NOT_MONITORED) {

		$buttons[] = new CButtonQMessage(
			'del_history',
			_('Clear history and trends'),
			_('History clearing can take a long time. Continue?')
		);
	}

	$buttons[] = (new CButtonDelete(_('Delete web scenario?'), url_params(['form', 'httptestid', 'hostid'])))
		->setEnabled(!$data['templated']);
	$buttons[] = new CButtonCancel();

	$http_tab->setFooter(makeFormFooter(new CSubmit('update', _('Update')), $buttons));
}
else {
	$http_tab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$http_form->addItem($http_tab);
$widget->addItem($http_form);

$this->data['scenario_tab_data'] = [
	'agent_visibility' => [],
	'pairs' => [
		'variables' => [],
		'headers' => []
	]
];

foreach ($data['pairs'] as $field) {
	zbx_subarray_push($this->data['scenario_tab_data']['pairs'], $field['type'], $field);
}

zbx_subarray_push($this->data['scenario_tab_data']['agent_visibility'], ZBX_AGENT_OTHER, 'agent_other');
zbx_subarray_push($this->data['scenario_tab_data']['agent_visibility'], ZBX_AGENT_OTHER, 'row_agent_other');

require_once dirname(__FILE__).'/js/configuration.httpconf.edit.js.php';

return $widget;

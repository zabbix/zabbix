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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


$widget = (new CWidget())->setTitle(_('Web monitoring'));

// append host summary to widget header
if (!empty($this->data['hostid'])) {
	$widget->addItem(get_header_host_table('web', $this->data['hostid']));
}

// create form
$httpForm = (new CForm())
	->setName('httpForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', $this->data['form'])
	->addVar('hostid', $this->data['hostid'])
	->addVar('steps', $this->data['steps'])
	->addVar('templated', $this->data['templated']);

if (!empty($this->data['httptestid'])) {
	$httpForm->addVar('httptestid', $this->data['httptestid']);
}

/*
 * Scenario tab
 */
$httpFormList = new CFormList('httpFormList');

// Parent http tests
if (!empty($this->data['templates'])) {
	$httpFormList->addRow(_('Parent web scenarios'), $this->data['templates']);
}

// Name
$nameTextBox = (new CTextBox('name', $this->data['name'], $this->data['templated'], 64))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
if (!$this->data['templated']) {
	$nameTextBox->setAttribute('autofocus', 'autofocus');
}
$httpFormList->addRow(_('Name'), $nameTextBox);

// Application
if ($this->data['application_list']) {
	$applications = zbx_array_merge([''], $this->data['application_list']);
	$httpFormList->addRow(_('Application'),
		new CComboBox('applicationid', $this->data['applicationid'], null, $applications)
	);
}
else {
	$httpFormList->addRow(_('Application'), new CSpan(_('No applications found.')));
}

// New application
$httpFormList
	->addRow(new CLabel(_('New application'), 'new_application'),
		(new CSpan(
			(new CTextBox('new_application', $this->data['new_application']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->addClass(ZBX_STYLE_FORM_NEW_GROUP)
	)
	->addRow(_('Update interval'),
		(new CTextBox('delay', $data['delay']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	)
	->addRow(_('Attempts'),
		(new CNumericBox('retries', $this->data['retries'], 2))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);

$agentComboBox = new CComboBox('agent', $this->data['agent']);

$userAgentsAll = userAgents();
$userAgentsAll[_('Others')][ZBX_AGENT_OTHER] = _('other').' ...';

foreach ($userAgentsAll as $userAgentGroup => $userAgents) {
	$agentComboBox->addItemsInGroup($userAgentGroup, $userAgents);
}

$httpFormList->addRow(_('Agent'), $agentComboBox);

$httpFormList->addRow(_('User agent string'),
	(new CTextBox('agent_other', $this->data['agent_other']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_agent_other'
);

// append HTTP proxy to form list
$httpFormList
	->addRow(_('HTTP proxy'),
		(new CTextBox('http_proxy', $this->data['http_proxy'], false, 255))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', 'http://[user[:password]@]proxy.example.com[:port]'));

$pair_tables = [
	['id' => 'variables', 'label' => _('Variables'), 'class' => 'pair-container'],
	['id' => 'headers', 'label' => _('Headers'), 'class' => 'pair-container pair-container-sortable']
];

foreach ($pair_tables as $pair_table){
	$pair_tab = (new CTable())
		->setId($pair_table['id'])
		->addClass($pair_table['class'])
		->setAttribute('style', 'width: 100%;')
		->setHeader(['', _('Name'), '', _('Value'), ''])
		->addRow((new CRow([
			(new CCol(
				(new CButton(null, _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('pairs-control-add')
					->setAttribute('data-type', $pair_table['id'])
			))->setColSpan(5)
		]))->setId($pair_table['id'].'_footer'));

	$httpFormList->addRow($pair_table['label'],
		(new CDiv($pair_tab))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('data-type', $pair_table['id'])
			->setAttribute('style', 'min-width: ' . ZBX_TEXTAREA_BIG_WIDTH . 'px;')
	);
}

$httpFormList->addRow(_('Enabled'), (new CCheckBox('status'))->setChecked(!$this->data['status']));

zbx_add_post_js('pairManager.add('.CJs::encodeJson($this->data['pairs']).');');

/*
 * Authentication tab
 */
$httpAuthenticationFormList = new CFormList('httpAuthenticationFormList');

// Authentication type
$httpAuthenticationFormList->addRow(_('HTTP authentication'),
	new CComboBox('authentication', $this->data['authentication'], null, httptest_authentications())
);

$httpAuthenticationFormList
	->addRow(_('User'),
		(new CTextBox('http_user', $this->data['http_user'], false, 64))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Password'),
		(new CTextBox('http_password', $this->data['http_password'], false, 64))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
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
$httpStepFormList = new CFormList('httpFormList');
$stepsTable = (new CTable())
	->setId('httpStepTable')
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

$i = 1;
foreach ($this->data['steps'] as $stepid => $step) {
	if (!isset($step['name'])) {
		$step['name'] = '';
	}
	if (!isset($step['timeout'])) {
		$step['timeout'] = DB::getDefault('httpstep', 'timeout');
	}
	if (!isset($step['url'])) {
		$step['url'] = '';
	}

	$numSpan = (new CSpan($i++.':'))
		->addClass('rowNum')
		->setId('current_step_'.$stepid);

	$name = (new CLink($step['name'], 'javascript:void(0);'))
		->setId('name_'.$stepid)
		->setAttribute('name_step', $stepid);

	if (mb_strlen($step['url']) > 70) {
		$start = mb_substr($step['url'], 0, 35);
		$end = mb_substr($step['url'], mb_strlen($step['url']) - 25, 25);
		$url = (new CSpan($start.SPACE.'...'.SPACE.$end))
			->setHint($step['url']);
	}
	else {
		$url = $step['url'];
	}

	if ($this->data['templated']) {
		$dragHandler = '';
		$removeButton = '';
	}
	else {
		$dragHandler = (new CCol(
			(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
		))->addClass(ZBX_STYLE_TD_DRAG_ICON);
		$removeButton = (new CButton('remove_'.$stepid, _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->onClick('javascript: removeStep(this);')
			->setAttribute('remove_step', $stepid);
	}

	$stepsTable->addRow(
		(new CRow([
			$dragHandler,
			$numSpan,
			$name,
			$step['timeout'],
			$url,
			htmlspecialchars($step['required']),
			$step['status_codes'],
			(new CCol($removeButton))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('sortable')
			->setId('steps_'.$stepid)
	);
}

if (!$this->data['templated']) {
	$stepsTable->addRow(
		(new CCol(
			(new CButton('add_step', _('Add')))->addClass(ZBX_STYLE_BTN_LINK)
		))->setColSpan(8)
	);
}

$httpStepFormList->addRow(_('Steps'),
	(new CDiv($stepsTable))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
);

// append tabs to form
$httpTab = (new CTabView())
	->addTab('scenarioTab', _('Scenario'), $httpFormList)
	->addTab('stepTab', _('Steps'), $httpStepFormList)
	->addTab('authenticationTab', _('Authentication'), $httpAuthenticationFormList);
if (!$this->data['form_refresh']) {
	$httpTab->setSelected(0);
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

	$httpTab->setFooter(makeFormFooter(new CSubmit('update', _('Update')), $buttons));
}
else {
	$httpTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$httpForm->addItem($httpTab);
$widget->addItem($httpForm);

$this->data['agentVisibility'] = [];
zbx_subarray_push($this->data['agentVisibility'], ZBX_AGENT_OTHER, 'agent_other');
zbx_subarray_push($this->data['agentVisibility'], ZBX_AGENT_OTHER, 'row_agent_other');

require_once dirname(__FILE__).'/js/configuration.httpconf.edit.js.php';

return $widget;

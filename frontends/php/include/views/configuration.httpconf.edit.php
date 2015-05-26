<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


$httpWidget = (new CWidget())->setTitle(_('Web monitoring'));

// append host summary to widget header
if (!empty($this->data['hostid'])) {
	$httpWidget->addItem(get_header_host_table('web', $this->data['hostid']));
}

// create form
$httpForm = new CForm();
$httpForm->setName('httpForm');
$httpForm->addVar('form', $this->data['form']);
$httpForm->addVar('hostid', $this->data['hostid']);
$httpForm->addVar('steps', $this->data['steps']);
$httpForm->addVar('templated', $this->data['templated']);

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
$nameTextBox = new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE, $this->data['templated'], 64);
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
$httpFormList->addRow(_('New application'),
	new CTextBox('new_application', $this->data['new_application'], ZBX_TEXTBOX_STANDARD_SIZE), false, null, 'new'
);

// update interval
$httpFormList->addRow(_('Update interval (in sec)'), new CNumericBox('delay', $this->data['delay'], 5));

// number of retries
$httpFormList->addRow(_('Retries'), new CNumericBox('retries', $this->data['retries'], 2));

$agentComboBox = new CComboBox('agent', $this->data['agent']);

$userAgentsAll = userAgents();
$userAgentsAll[_('Others')][ZBX_AGENT_OTHER] = _('other').' ...';

foreach ($userAgentsAll as $userAgentGroup => $userAgents) {
	$agentComboBox->addItemsInGroup($userAgentGroup, $userAgents);
}

$httpFormList->addRow(_('Agent'), $agentComboBox);

$httpFormList->addRow(_('User agent string'),
	new CTextBox('agent_other', $this->data['agent_other'], ZBX_TEXTBOX_STANDARD_SIZE), false, 'row_agent_other'
);

// append HTTP proxy to form list
$httpProxyTextBox = new CTextBox('http_proxy', $this->data['http_proxy'], ZBX_TEXTBOX_STANDARD_SIZE, false, 255);
$httpProxyTextBox->setAttribute('placeholder', 'http://[user[:password]@]proxy.example.com[:port]');
$httpFormList->addRow(_('HTTP proxy'), $httpProxyTextBox);

// append variables to form list
$httpFormList->addRow(_('Variables'), new CTextArea('variables', $this->data['variables']));

// append headers to form list
$httpFormList->addRow(_('Headers'), new CTextArea('headers', $this->data['headers']));

// status
$httpFormList->addRow(_('Enabled'), new CCheckBox('status', !$this->data['status']));

/*
 * Authentication tab
 */
$httpAuthenticationFormList = new CFormList('httpAuthenticationFormList');

// Authentication type
$httpAuthenticationFormList->addRow(_('HTTP authentication'),
	new CComboBox('authentication', $this->data['authentication'], null, httptest_authentications())
);

$httpAuthenticationUserTB = new CTextBox('http_user', $this->data['http_user'], ZBX_TEXTBOX_STANDARD_SIZE, false, 64);
$httpAuthenticationPasswordTB = new CTextBox('http_password', $this->data['http_password'], ZBX_TEXTBOX_STANDARD_SIZE, false, 64);

$authenticationInputsHidden = $this->data['authentication'] == HTTPTEST_AUTH_NONE;

if ($authenticationInputsHidden) {
	$httpAuthenticationUserTB->setAttribute('disabled', true);
	$httpAuthenticationPasswordTB->setAttribute('disabled', true);
}

$httpAuthenticationFormList->addRow(_('User'), $httpAuthenticationUserTB, $authenticationInputsHidden);
$httpAuthenticationFormList->addRow(_('Password'), $httpAuthenticationPasswordTB, $authenticationInputsHidden);

// SSL verify peer checkbox
$httpAuthenticationFormList->addRow(_('SSL verify peer'), new CCheckBox('verify_peer', $this->data['verify_peer']));

// SSL verify host checkbox
$httpAuthenticationFormList->addRow(_('SSL verify host'), new CCheckBox('verify_host', $this->data['verify_host']));

// SSL certificate file
$httpAuthenticationFormList->addRow(
	_('SSL certificate file'),
	new CTextBox('ssl_cert_file', $this->data['ssl_cert_file'], ZBX_TEXTBOX_STANDARD_SIZE, false, 255)
);

// SSL key file
$httpAuthenticationFormList->addRow(
	_('SSL key file'),
	new CTextBox('ssl_key_file', $this->data['ssl_key_file'], ZBX_TEXTBOX_STANDARD_SIZE, false, 255)
);

// SSL key password
$httpAuthenticationFormList->addRow(
	_('SSL key password'),
	new CTextBox('ssl_key_password', $this->data['ssl_key_password'], ZBX_TEXTBOX_STANDARD_SIZE, false, 64)
);

/*
 * Step tab
 */
$httpStepFormList = new CFormList('httpFormList');
$stepsTable = (new CTable())->
	addClass('formElementTable')->
	setAttribute('style', 'min-width: 500px;')->
	setAttribute('id', 'httpStepTable');

$stepsTable->setHeader([
	(new CCol(SPACE))->setWidth('15'),
	(new CCol(SPACE))->setWidth('15'),
	(new CCol(_('Name')))->setWidth('150'),
	(new CCol(_('Timeout')))->setWidth('50'),
	(new CCol(_('URL')))->setWidth('200'),
	(new CCol(_('Required')))->setWidth('50'),
	(new CCol(_('Status codes')))->addClass(ZBX_STYLE_NOWRAP)->setWidth('90'),
	(new CCol(''))->setWidth('50')
]);

$i = 1;
foreach ($this->data['steps'] as $stepid => $step) {
	if (!isset($step['name'])) {
		$step['name'] = '';
	}
	if (!isset($step['timeout'])) {
		$step['timeout'] = 15;
	}
	if (!isset($step['url'])) {
		$step['url'] = '';
	}
	if (!isset($step['posts'])) {
		$step['posts'] = '';
	}
	if (!isset($step['required'])) {
		$step['required'] = '';
	}

	$numSpan = (new CSpan($i++.':'))->
		addClass('rowNum')->
		setAttribute('id', 'current_step_'.$stepid);

	$name = (new CSpan($step['name'], 'link'))->
		setAttribute('id', 'name_'.$stepid)->
		setAttribute('name_step', $stepid);

	if (mb_strlen($step['url']) > 70) {
		$start = mb_substr($step['url'], 0, 35);
		$end = mb_substr($step['url'], mb_strlen($step['url']) - 25, 25);
		$url = new CSpan($start.SPACE.'...'.SPACE.$end);
		$url->setHint($step['url']);
	}
	else {
		$url = $step['url'];
	}

	if ($this->data['templated']) {
		$removeButton = SPACE;
		$dragHandler = SPACE;
	}
	else {
		$removeButton = new CButton('remove_'.$stepid, _('Remove'), 'javascript: removeStep(this);', 'link_menu');
		$removeButton->setAttribute('remove_step', $stepid);
		$dragHandler = new CSpan(null, 'ui-icon ui-icon-arrowthick-2-n-s move');
	}

	$row = (new CRow([
		$dragHandler,
		$numSpan,
		$name,
		$step['timeout'].SPACE._('sec'),
		$url,
		htmlspecialchars($step['required']),
		$step['status_codes'],
		$removeButton]))->
			addClass('sortable')->
			setId('steps_'.$stepid);

	$stepsTable->addRow($row);
}

if (!$this->data['templated']) {
	$stepsTable->addRow((new CCol(new CButton('add_step', _('Add'), null, 'link_menu')))->setColSpan(8));
}

$httpStepFormList->addRow(_('Steps'), new CDiv($stepsTable, 'objectgroup inlineblock border_dotted ui-corner-all'));

// append tabs to form
$httpTab = new CTabView();
if (!$this->data['form_refresh']) {
	$httpTab->setSelected(0);
}
$httpTab->addTab('scenarioTab', _('Scenario'), $httpFormList);
$httpTab->addTab('stepTab', _('Steps'), $httpStepFormList);
$httpTab->addTab('authenticationTab', _('Authentication'), $httpAuthenticationFormList);

// append buttons to form
if (!empty($this->data['httptestid'])) {
	$buttons = [new CSubmit('clone', _('Clone'))];

	if ($this->data['templated'] == 0) {
		$buttons[] = new CButtonDelete(
			_('Delete web scenario?'),
			url_param('form').url_param('httptestid').url_param('hostid')
		);
	}

	if ($this->data['host']['status'] == HOST_STATUS_MONITORED
			|| $this->data['host']['status'] == HOST_STATUS_NOT_MONITORED) {

		$buttons[] = new CButtonQMessage(
			'del_history',
			_('Clear history and trends'),
			_('History clearing can take a long time. Continue?')
		);
	}

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
$httpWidget->addItem($httpForm);

$this->data['agentVisibility'] = [];
zbx_subarray_push($this->data['agentVisibility'], ZBX_AGENT_OTHER, 'agent_other');
zbx_subarray_push($this->data['agentVisibility'], ZBX_AGENT_OTHER, 'row_agent_other');

require_once dirname(__FILE__).'/js/configuration.httpconf.edit.js.php';

return $httpWidget;

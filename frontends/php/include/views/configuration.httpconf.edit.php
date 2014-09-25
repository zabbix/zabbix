<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


require_once dirname(__FILE__).'/js/configuration.httpconf.edit.js.php';

$httpWidget = new CWidget();
$httpWidget->addPageHeader(_('CONFIGURATION OF WEB MONITORING'));

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
	$nameTextBox->attr('autofocus', 'autofocus');
}
$httpFormList->addRow(_('Name'), $nameTextBox);

// Application
if ($this->data['application_list']) {
	$applications = zbx_array_merge(array(''), $this->data['application_list']);
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

// append http agents to form list - http://www.useragentstring.com
$agentComboBox = new CEditableComboBox('agent', $this->data['agent'], ZBX_TEXTBOX_STANDARD_SIZE);
$agentComboBox->addItemsInGroup(_('Internet Explorer'), array(
	'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)' => 'Internet Explorer 10.0',
	'Mozilla/5.0 (Windows; U; MSIE 9.0; Windows NT 9.0; en-US)' => 'Internet Explorer 9.0',
	'Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0; WOW64; Trident/4.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; .NET CLR 1.0.3705; .NET CLR 1.1.4322)' => 'Internet Explorer 8.0',
	'Mozilla/5.0 (Windows; U; MSIE 7.0; Windows NT 6.0; en-US)' => 'Internet Explorer 7.0',
	'Mozilla/5.0 (compatible; MSIE 6.0; Windows NT 5.1)' => 'Internet Explorer 6.0'
));
$agentComboBox->addItemsInGroup(_('Mozilla Firefox'), array(
	'Mozilla/5.0 (X11; Linux i686; rv:8.0) Gecko/20100101 Firefox/8.0' => 'Mozilla Firefox 8.0',
	'Mozilla/5.0 (X11; Linux i686; rv:7.0) Gecko/20100101 Firefox/7.0' => 'Mozilla Firefox 7.0',
	'Mozilla/5.0 (X11; Linux i686; rv:6.0) Gecko/20100101 Firefox/6.0' => 'Mozilla Firefox 6.0',
	'Mozilla/5.0 (X11; U; Linux i586; de; rv:5.0) Gecko/20100101 Firefox/5.0' => 'Mozilla Firefox 5.0',
	'Mozilla/5.0 (X11; U; Linux x86_64; pl-PL; rv:2.0) Gecko/20110307 Firefox/4.0' => 'Mozilla Firefox 4.0',
	'Mozilla/6.0 (Macintosh; U; PPC Mac OS X Mach-O; en-US; rv:2.0.0.0) Gecko/20061028 Firefox/3.0' => 'Mozilla Firefox 3.0',
	'Mozilla/5.0 (X11;U;Linux i686;en-US;rv:1.8.1) Gecko/2006101022 Firefox/2.0' => 'Mozilla Firefox 2.0'
));
$agentComboBox->addItemsInGroup(_('Opera'), array(
	'Opera/9.80 (Windows NT 6.1; U; es-ES) Presto/2.9.181 Version/12.00' => 'Opera 12.00',
	'Opera/9.80 (X11; Linux x86_64; U; pl) Presto/2.7.62 Version/11.00' => 'Opera 11.00',
	'Opera/9.80 (X11; Linux x86_64; U; en) Presto/2.2.15 Version/10.00' => 'Opera 10.00',
	'Opera/9.00 (X11; Linux i686; U; pl)' => 'Opera 9.00'
));
$agentComboBox->addItemsInGroup(_('Safari'), array(
	'Mozilla/5.0 (X11; U; Linux x86_64; en-us) AppleWebKit/531.2+ (KHTML, like Gecko) Version/5.0 Safari/531.2+' => 'Safari 5.0',
	'Mozilla/5.0 (Windows; U; Windows NT 6.0; ru-RU) AppleWebKit/528.16 (KHTML, like Gecko) Version/4.0 Safari/528.16' => 'Safari 4.0',
	'Mozilla/5.0 (Windows; U; Windows NT 6.0; sv-SE) AppleWebKit/523.13 (KHTML, like Gecko) Version/3.0 Safari/523.13' => 'Safari 3.0',
	'Mozilla/5.0 (iPhone; U; CPU iPhone OS 2_1 like Mac OS X; fr-fr) AppleWebKit/525.18.1 (KHTML, like Gecko) Mobile/5F136' => 'Safari on iPhone'
));
$agentComboBox->addItemsInGroup(_('Google Chrome'), array(
	'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.8 (KHTML, like Gecko) Chrome/17.0.940.0 Safari/535.8' => 'Google Chrome 17',
	'Mozilla/5.0 (Windows NT 6.0; WOW64) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.36 Safari/535.7' => 'Google Chrome 16',
	'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.2 (KHTML, like Gecko) Chrome/15.0.872.0 Safari/535.2' => 'Google Chrome 15',
	'Mozilla/5.0 (X11; Linux i686) AppleWebKit/535.1 (KHTML, like Gecko) Ubuntu/11.04 Chromium/14.0.825.0 Chrome/14.0.825.0 Safari/535.1' => 'Google Chrome 14',
	'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_3) AppleWebKit/535.1 (KHTML, like Gecko) Chrome/13.0.782.32 Safari/535.1' => 'Google Chrome 13',
	'Mozilla/5.0 (Windows NT 6.1; en-US) AppleWebKit/534.30 (KHTML, like Gecko) Chrome/12.0.750.0 Safari/534.30' => 'Google Chrome 12'
));
$agentComboBox->addItemsInGroup(_('Others'), array(
	'Mozilla/5.0 (X11; Linux 3.1.0-rc9+; en_US) KHTML/4.7.2 (like Gecko) Konqueror/4.7' => 'Konqueror 4.7',
	'Mozilla/5.0 (compatible; Konqueror/4.6; Linux) KHTML/4.6.0 (like Gecko)' => 'Konqueror 4.6',
	'Lynx/2.8.7rel.1 libwww-FM/2.14 SSL-MM/1.4.1 OpenSSL/0.9.8r' => 'Lynx 2.8.7rel.1',
	'Lynx/2.8.4rel.1 libwww-FM/2.14' => 'Lynx 2.8.4rel.1',
	'Links (2.3pre1; Linux 2.6.35.10 i686; 225x51)' => 'Links 2.3pre1',
	'Links (2.2; Linux 2.6.37.6-0.7-desktop i686; 225x51)' => 'Links 2.2',
	'Googlebot/2.1 (+http://www.google.com/bot.html)' => 'Googlebot',
	-1 => _('other').' ...'
));

$httpFormList->addRow(_('Agent'), $agentComboBox);

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
$authenticationComboBox = new CComboBox('authentication', $this->data['authentication']);
$authenticationComboBox->addItems(httptest_authentications());
$httpAuthenticationFormList->addRow(_('HTTP authentication'), $authenticationComboBox);

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
$stepsTable = new CTable(null, 'formElementTable');
$stepsTable->setAttributes(array(
	'style' => 'min-width: 500px;',
	'id' => 'httpStepTable'
));
$stepsTable->setHeader(array(
	new CCol(SPACE, null, null, '15'),
	new CCol(SPACE, null, null, '15'),
	new CCol(_('Name'), null, null, '150'),
	new CCol(_('Timeout'), null, null, '50'),
	new CCol(_('URL'), null, null, '200'),
	new CCol(_('Required'), null, null, '50'),
	new CCol(_('Status codes'), 'nowrap', null, '90'),
	new CCol('', null, null, '50')
));

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

	$numSpan = new CSpan($i++.':');
	$numSpan->addClass('rowNum');
	$numSpan->setAttribute('id', 'current_step_'.$stepid);

	$name = new CSpan($step['name'], 'link');
	$name->setAttributes(array(
		'id' => 'name_'.$stepid,
		'name_step' => $stepid
	));

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

	$row = new CRow(array(
		$dragHandler,
		$numSpan,
		$name,
		$step['timeout'].SPACE._('sec'),
		$url,
		htmlspecialchars($step['required']),
		$step['status_codes'],
		$removeButton
	), 'sortable', 'steps_'.$stepid);

	$stepsTable->addRow($row);
}

if (!$this->data['templated']) {
	$stepsTable->addRow(new CCol(new CButton('add_step', _('Add'), null, 'link_menu'), null, 8));
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

$httpForm->addItem($httpTab);

// append buttons to form
if (!empty($this->data['httptestid'])) {
	if ($this->data['templated'] == 0) {
		$btnDelete = new CButtonDelete(
			_('Delete scenario?'),
			url_param('form').url_param('httptestid').url_param('hostid')
		);
	}
	else {
		$btnDelete = null;
	}

	$httpForm->addItem(makeFormFooter(
		new CSubmit('update', _('Update')),
		array(
			new CSubmit('clone', _('Clone')),
			new CButtonQMessage(
				'del_history',
				_('Clear history and trends'),
				_('History clearing can take a long time. Continue?')
			),
			$btnDelete,
			new CButtonCancel()
		)
	));
}
else {
	$httpForm->addItem(makeFormFooter(
		new CSubmit('add', _('Add')),
		new CButtonCancel()
	));
}
$httpWidget->addItem($httpForm);

return $httpWidget;

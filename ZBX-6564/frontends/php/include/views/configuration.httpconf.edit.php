<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

// create form
$httpForm = new CForm();
$httpForm->setName('httpForm');
$httpForm->addVar('form', $this->data['form']);
$httpForm->addVar('hostid', $this->data['hostid']);
if ($this->data['groupid'] > 0) {
	$httpForm->addVar('groupid', $this->data['groupid']);
}
if (!empty($this->data['httptestid'])) {
	$httpForm->addVar('httptestid', $this->data['httptestid']);
}
$httpForm->addVar('steps', $this->data['steps']);

/*
 * Scenario tab
 */
$httpFormList = new CFormList('httpFormList');
$httpFormList->addRow(_('Application'), array(
	new CTextBox('application', $this->data['application'], ZBX_TEXTBOX_STANDARD_SIZE),
	SPACE,
	new CButton('select_app', _('Select'),
		'return PopUp("popup.php?srctbl=applications&srcfld1=name'.
			'&dstfrm='.$httpForm->getName().'&dstfld1=application'.
			'&only_hostid='.$this->data['hostid'].'", 500, 600, "application");',
		'formlist'
	)
));
$httpFormList->addRow(_('Name'), new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE, 'no', 64));

// append authentication to form list
$authenticationComboBox = new CComboBox('authentication', $this->data['authentication'], 'submit();');
$authenticationComboBox->addItems(httptest_authentications());
$httpFormList->addRow(_('Authentication'), $authenticationComboBox);
if (in_array($this->data['authentication'], array(HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM))) {
	$httpFormList->addRow(_('User'), new CTextBox('http_user', $this->data['http_user'], ZBX_TEXTBOX_STANDARD_SIZE, 'no', 64));
	$httpFormList->addRow(_('Password'), new CTextBox('http_password', $this->data['http_password'], ZBX_TEXTBOX_STANDARD_SIZE, 'no', 64));
}

$httpFormList->addRow(_('Update interval (in sec)'), new CNumericBox('delay', $this->data['delay'], 5));

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
	'Googlebot/2.1 (+http://www.google.com/bot.html)' => 'Googlebot'
));
$httpFormList->addRow(_('Agent'), $agentComboBox);

// append status to form list
$httpFormList->addRow(_('Variables'), new CTextArea('macros', $this->data['macros']));
$httpFormList->addRow(_('Active'), new CCheckBox('status', $this->data['status'] ? (!isset($_REQUEST['httptestid']) ? 1 : 0) : 1, null, 1)); // invert status 0 - enable, 1 - disable

/*
 * Step tab
 */
$httpStepFormList = new CFormList('httpFormList');
$stepsTable = new CTable(_('No steps defined.'), 'formElementTable');
$stepsTable->setAttribute('style', 'min-width: 500px;');
$stepsTable->setAttribute('id', 'httpStepTable');
$stepsTable->setHeader(array(
	new CCol(SPACE, null, null, '15'),
	new CCol(SPACE, null, null, '15'),
	new CCol(_('Name'), null, null, '150'),
	new CCol(_('Timeout'), null, null, '50'),
	new CCol(_('URL'), null, null, '200'),
	new CCol(_('Required'), null, null, '50'),
	new CCol(_('Status codes'), null, null, '90'),
	new CCol(_('Action'), null, null, '50')
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
	$name->setAttribute('id', 'name_'.$stepid);
	$name->setAttribute('name_step', $stepid);
	$name->onClick('return PopUp("popup_httpstep.php?dstfrm='.$httpForm->getName().'&list_name=steps&stepid="+jQuery(this).attr("name_step")+"'.
		url_param($step['name'], false, 'name').
		url_param($step['timeout'], false, 'timeout').
		url_param($step['url'], false, 'url').
		url_param($step['posts'], false, 'posts').
		url_param($step['required'], false, 'required').
		url_param($step['status_codes'], false, 'status_codes').
		'", 600, 410);'
	);

	if (zbx_strlen($step['url']) > 70) {
		$url = new CSpan(substr($step['url'], 0, 35).SPACE.'...'.SPACE.substr($step['url'], zbx_strlen($step['url']) - 25, 25));
		$url->setHint($step['url']);
	}
	else {
		$url = $step['url'];
	}

	$removeButton = new CButton('remove_'.$stepid, _('Remove'), 'javascript: removeStep(this);', 'link_menu');
	$removeButton->setAttribute('remove_step', $stepid);

	$row = new CRow(array(
		new CSpan(null, 'ui-icon ui-icon-arrowthick-2-n-s move'),
		$numSpan,
		$name,
		$step['timeout'].SPACE._('sec'),
		$url,
		htmlspecialchars($step['required']),
		$step['status_codes'],
		$removeButton
	), 'sortable');
	$row->setAttribute('id', 'steps_'.$stepid);
	$stepsTable->addRow($row);
}

$tmpColumn = new CCol(new CButton('add_step', _('Add'), 'return PopUp("popup_httpstep.php?dstfrm='.$httpForm->getName().'", 600, 410);', 'link_menu'), null, 8);
$tmpColumn->setAttribute('style', 'vertical-align: middle;');
$stepsTable->addRow(new CRow($tmpColumn));

$httpStepFormList->addRow(_('Steps'), new CDiv($stepsTable, 'objectgroup inlineblock border_dotted ui-corner-all'));

// append tabs to form
$httpTab = new CTabView(array('remember' => true));
if (!$this->data['form_refresh']) {
	$httpTab->setSelected(0);
}
$httpTab->addTab('scenarioTab', _('Scenario'), $httpFormList);
$httpTab->addTab('stepTab', _('Steps'), $httpStepFormList);
$httpForm->addItem($httpTab);

// append buttons to form
if (!empty($this->data['httptestid'])) {
	$httpForm->addItem(makeFormFooter(
		array(new CSubmit('save', _('Save'))),
		array(
			new CSubmit('clone', _('Clone')),
			new CButtonDelete(_('Delete scenario?'), url_param('form').url_param('httptestid').url_param('hostid')),
			new CButtonCancel()
		)
	));
}
else {
	$httpForm->addItem(makeFormFooter(
		array(new CSubmit('save', _('Save'))),
		array(new CButtonCancel())
	));
}
$httpWidget->addItem($httpForm);

return $httpWidget;

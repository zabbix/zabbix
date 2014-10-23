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


include('include/views/js/configuration.httpconf.popup.js.php');

$httpPopupWidget = new CWidget(null, 'httptest-popup');

$result = false;
if (hasRequest('add') || hasRequest('update')) {
	$result = true;
	if ((!hasRequest('stepid') || (hasRequest('stepid') && getRequest('name') !== getRequest('old_name')))
			&& hasRequest('steps_names')) {
		foreach (getRequest('steps_names', array()) as $name) {
			if ($name === getRequest('name')) {
				show_error_message(_s('Step with name "%1$s" already exists.', $name));
				$result = false;
			}
		}
	}
}

if (hasRequest('stepid')) {
	$followRedirects = getRequest('follow_redirects', HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF);
	$retrieveMode = getRequest('retrieve_mode', HTTPTEST_STEP_RETRIEVE_MODE_CONTENT);
}
else {
	$followRedirects = HTTPTEST_STEP_FOLLOW_REDIRECTS_ON;
	$retrieveMode = HTTPTEST_STEP_RETRIEVE_MODE_CONTENT;
}

if ((hasRequest('add') || hasRequest('update')) && $result) {

	$httpStepForJs = array(
		'stepid' => getRequest('stepid'),
		'name' => getRequest('name'),
		'timeout' => getRequest('timeout'),
		'url' => getRequest('url'),
		'posts'  => getRequest('posts'),
		'variables' => getRequest('variables'),
		'required' => getRequest('required'),
		'status_codes' => getRequest('status_codes'),
		'headers' => getRequest('headers'),
		'follow_redirects' => getRequest('follow_redirects'),
		'retrieve_mode'  => getRequest('retrieve_mode')
	);

	if (!hasRequest('stepid')) {
		insert_js('add_httpstep('.CJs::encodeJson(getRequest('dstfrm')).','.
					CJs::encodeJson($httpStepForJs).');'."\n");
	}
	else {
		insert_js('update_httpstep('.CJs::encodeJson(getRequest('dstfrm')).','.
					CJs::encodeJson(getRequest('list_name')).','.
					CJs::encodeJson($httpStepForJs).');'."\n"
		);
	}
}
else {
	$httpPopupForm = new CForm();
	$httpPopupForm->addVar('dstfrm', getRequest('dstfrm'));
	$httpPopupForm->addVar('stepid', getRequest('stepid'));
	$httpPopupForm->addVar('list_name', getRequest('list_name'));
	$httpPopupForm->addVar('templated', getRequest('templated'));
	$httpPopupForm->addVar('old_name', getRequest('old_name'));
	$httpPopupForm->addVar('steps_names', getRequest('steps_names'));

	$httpPopupFormList = new CFormList('httpPopupFormList');
	$httpPopupFormList->addRow(_('Name'), new CTextBox('name', getRequest('name', ''), ZBX_TEXTBOX_STANDARD_SIZE, (bool) getRequest('templated'), 64));
	$httpPopupFormList->addRow(_('URL'), new CTextBox('url', getRequest('url', ''), ZBX_TEXTBOX_STANDARD_SIZE, false, null));
	$httpPopupFormList->addRow(_('Post'), new CTextArea('posts', getRequest('posts', '')));
	$httpPopupFormList->addRow(_('Variables'), new CTextArea('variables', getRequest('variables', '')));

	$httpPopupFormList->addRow(_('Headers'), new CTextArea('headers', getRequest('headers', '')));

	$httpPopupFormList->addRow(_('Follow redirects'), new CCheckBox('follow_redirects', $followRedirects, null, true));

	$httpPopupFormList->addRow(_('Retrieve only headers'), new CCheckBox('retrieve_mode', $retrieveMode, null, true));

	$httpPopupFormList->addRow(_('Timeout'), new CNumericBox('timeout', getRequest('timeout', 15), 5));
	$httpPopupFormList->addRow(_('Required string'), new CTextBox('required', getRequest('required', ''), ZBX_TEXTBOX_STANDARD_SIZE));
	$httpPopupFormList->addRow(_('Required status codes'), new CTextBox('status_codes', getRequest('status_codes', ''), ZBX_TEXTBOX_STANDARD_SIZE));

	// append tabs to form
	$httpPopupTab = new CTabView();
	$httpPopupTab->addTab('scenarioStepTab', _('Step of scenario'), $httpPopupFormList);
	$httpPopupForm->addItem($httpPopupTab);

	// append buttons to form
	if (hasRequest('stepid')) {
		$httpPopupForm->addItem(makeFormFooter(
			new CSubmit('update', _('Update')),
			new CButtonCancel(null, 'close_window();')
		));
	}
	else {
		$httpPopupForm->addItem(makeFormFooter(
			new CSubmit('add', _('Add')),
			new CButtonCancel(null, 'close_window();')
		));
	}

	$httpPopupWidget->addItem($httpPopupForm);
}

return $httpPopupWidget;

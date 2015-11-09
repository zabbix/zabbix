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


include('include/views/js/configuration.httpconf.popup.js.php');

$httpPopupWidget = new CWidget();

$result = false;
if (hasRequest('add') || hasRequest('update')) {
	$result = true;
	if ((!hasRequest('stepid') || (hasRequest('stepid') && getRequest('name') !== getRequest('old_name')))
			&& hasRequest('steps_names')) {
		foreach (getRequest('steps_names', []) as $name) {
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

	$httpStepForJs = [
		'stepid' => getRequest('stepid'),
		'name' => getRequest('name'),
		'timeout' => getRequest('timeout'),
		'url' => getRequest('url'),
		'posts' => getRequest('posts'),
		'variables' => getRequest('variables'),
		'required' => getRequest('required'),
		'status_codes' => getRequest('status_codes'),
		'headers' => getRequest('headers'),
		'follow_redirects' => getRequest('follow_redirects', HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF),
		'retrieve_mode' => getRequest('retrieve_mode', HTTPTEST_STEP_RETRIEVE_MODE_CONTENT)
	];

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
	$httpPopupForm = (new CForm())
		->addVar('dstfrm', getRequest('dstfrm'))
		->addVar('stepid', getRequest('stepid'))
		->addVar('list_name', getRequest('list_name'))
		->addVar('templated', getRequest('templated'))
		->addVar('old_name', getRequest('old_name'))
		->addVar('steps_names', getRequest('steps_names'));

	$httpPopupFormList = (new CFormList())
		->addRow(_('Name'),
			(new CTextBox('name', getRequest('name', ''), (bool) getRequest('templated'), 64))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
		->addRow(_('URL'),
			(new CTextBox('url', getRequest('url', ''), false, null))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
		->addRow(_('Post'), (new CTextArea('posts', getRequest('posts', '')))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))
		->addRow(_('Variables'),
			(new CTextArea('variables', getRequest('variables', '')))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
		->addRow(_('Headers'),
			(new CTextArea('headers', getRequest('headers', '')))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
		->addRow(_('Follow redirects'), (new CCheckBox('follow_redirects'))->setChecked($followRedirects == 1))
		->addRow(_('Retrieve only headers'), (new CCheckBox('retrieve_mode'))->setChecked($retrieveMode == 1))
		->addRow(_('Timeout'),
			(new CNumericBox('timeout', getRequest('timeout', 15), 5))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
		)
		->addRow(_('Required string'),
			(new CTextBox('required', getRequest('required', '')))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
		->addRow(_('Required status codes'),
			(new CTextBox('status_codes', getRequest('status_codes', '')))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		);

	// append tabs to form
	$httpPopupTab = new CTabView();
	$httpPopupTab->addTab('scenarioStepTab', _('Step of web scenario'), $httpPopupFormList);

	// append buttons to form
	if (hasRequest('stepid')) {
		$httpPopupTab->setFooter(makeFormFooter(
			new CSubmit('update', _('Update')),
			[new CButtonCancel(null, 'close_window();')]
		));
	}
	else {
		$httpPopupTab->setFooter(makeFormFooter(
			new CSubmit('add', _('Add')),
			[new CButtonCancel(null, 'close_window();')]
		));
	}

	$httpPopupForm->addItem($httpPopupTab);
	$httpPopupWidget->addItem($httpPopupForm);
}

return $httpPopupWidget;

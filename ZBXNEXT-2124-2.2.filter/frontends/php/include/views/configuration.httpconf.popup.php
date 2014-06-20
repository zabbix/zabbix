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

$httpPopupWidget = new CWidget();

if (isset($_REQUEST['save'])) {
	$result = true;
	if ((!isset($_REQUEST['stepid']) || (isset($_REQUEST['stepid']) && $_REQUEST['name'] !== $_REQUEST['old_name']))
			&& isset($_REQUEST['steps_names'])) {
		foreach ($_REQUEST['steps_names'] as $name) {
			if ($name === $_REQUEST['name']) {
				show_error_message(_s('Step with name "%1$s" already exists.', $name));
				$result = false;
			}
		}
	}
}

if (isset($_REQUEST['save']) && $result) {
	if (!isset($_REQUEST['stepid'])) {
		insert_js('add_httpstep('.
					zbx_jsvalue($_REQUEST['dstfrm']).','.
					zbx_jsvalue($_REQUEST['name']).','.
					zbx_jsvalue($_REQUEST['timeout']).','.
					zbx_jsvalue($_REQUEST['url']).','.
					zbx_jsvalue($_REQUEST['posts']).','.
					zbx_jsvalue($_REQUEST['variables']).','.
					zbx_jsvalue($_REQUEST['required']).','.
					zbx_jsvalue($_REQUEST['status_codes']).");\n"
		);
	}
	else {
		insert_js('update_httpstep('.
					zbx_jsvalue($_REQUEST['dstfrm']).','.
					zbx_jsvalue($_REQUEST['list_name']).','.
					zbx_jsvalue($_REQUEST['stepid']).','.
					zbx_jsvalue($_REQUEST['name']).','.
					zbx_jsvalue($_REQUEST['timeout']).','.
					zbx_jsvalue($_REQUEST['url']).','.
					zbx_jsvalue($_REQUEST['posts']).','.
					zbx_jsvalue($_REQUEST['variables']).','.
					zbx_jsvalue($_REQUEST['required']).','.
					zbx_jsvalue($_REQUEST['status_codes']).");\n"
		);
	}
}
else {
	$httpPopupForm = new CForm();
	$httpPopupForm->addVar('dstfrm', get_request('dstfrm', null));
	$httpPopupForm->addVar('stepid', get_request('stepid', null));
	$httpPopupForm->addVar('list_name', get_request('list_name', null));
	$httpPopupForm->addVar('templated', get_request('templated', null));
	$httpPopupForm->addVar('old_name', get_request('old_name', null));
	$httpPopupForm->addVar('steps_names', get_request('steps_names', null));

	$httpPopupFormList = new CFormList('httpPopupFormList');
	$httpPopupFormList->addRow(_('Name'), new CTextBox('name', get_request('name', ''), ZBX_TEXTBOX_STANDARD_SIZE, get_request('templated', null), 64));
	$httpPopupFormList->addRow(_('URL'), new CTextBox('url', get_request('url', ''), ZBX_TEXTBOX_STANDARD_SIZE));
	$httpPopupFormList->addRow(_('Post'), new CTextArea('posts', get_request('posts', '')));
	$httpPopupFormList->addRow(_('Variables'), new CTextArea('variables', get_request('variables', '')));
	$httpPopupFormList->addRow(_('Timeout'), new CNumericBox('timeout', get_request('timeout', 15), 5));
	$httpPopupFormList->addRow(_('Required string'), new CTextBox('required', get_request('required', ''), ZBX_TEXTBOX_STANDARD_SIZE));
	$httpPopupFormList->addRow(_('Required status codes'), new CTextBox('status_codes', get_request('status_codes', ''), ZBX_TEXTBOX_STANDARD_SIZE));

	// append tabs to form
	$httpPopupTab = new CTabView();
	$httpPopupTab->addTab('scenarioStepTab', _('Step of scenario'), $httpPopupFormList);
	$httpPopupForm->addItem($httpPopupTab);

	// append buttons to form
	$stepid = get_request('stepid', null);
	$httpPopupForm->addItem(makeFormFooter(
		new CSubmit('save', isset($stepid) ? _('Update') : _('Add')),
		new CButtonCancel(null, 'close_window();')
	));

	$httpPopupWidget->addItem($httpPopupForm);
}

return $httpPopupWidget;

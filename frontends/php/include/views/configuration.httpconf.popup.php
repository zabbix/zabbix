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
		'post_type' => getRequest('post_type', ZBX_POSTTYPE_FORM),
		'posts' => getRequest('posts'),
		'pairs' => array_values(getRequest('pairs', [])),
		'required' => getRequest('required'),
		'status_codes' => getRequest('status_codes'),
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
			new CDiv([
				(new CTextBox('url', getRequest('url', ''), false, null))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('parse', _('Parse')))
					->onClick('javascript: parseUrl();')
					->addClass(ZBX_STYLE_BTN_GREY)
			])
		);

	$pair_tables = [
		[
			'id' => 'query_fields',
			'label' => _('Query fields'),
			'class' => 'pair-container pair-container-sortable'
		],
		[
			'id' => 'post_fields',
			'label' => _('Post fields'),
			'header' => [
				'label' => _('Post type'),
				'items' => (new CRadioButtonList('post_type', getRequest('post_type', ZBX_POSTTYPE_FORM)))
					->addValue(_('Form data'), ZBX_POSTTYPE_FORM, null, 'return switchToPostType(this.value);')
					->addValue(_('Raw data'), ZBX_POSTTYPE_RAW, null, 'return switchToPostType(this.value);')
					->setModern(true)
			],
			'footer' => [
				'id' => 'post_raw_row',
				'label' => _('Raw post'),
				'items' => (new CTextArea('posts', getRequest('posts', '')))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			],
			'class' => 'pair-container pair-container-sortable'
		],
		[
			'id' => 'variables',
			'label' => _('Variables'),
			'class' => 'pair-container'
		],
		[
			'id' => 'headers',
			'label' => _('Headers'),
			'class' => 'pair-container pair-container-sortable'
		]
	];

	foreach ($pair_tables as $pair_table) {
		if (array_key_exists('header', $pair_table)) {
			$httpPopupFormList->addRow($pair_table['header']['label'], $pair_table['header']['items']);
		}

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

		$httpPopupFormList->addRow($pair_table['label'],
			(new CDiv($pair_tab))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('data-type', $pair_table['id'])
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH . 'px;'),
			$pair_table['id'].'_row'
		);

		if (array_key_exists('footer', $pair_table)) {
			$httpPopupFormList->addRow($pair_table['footer']['label'], $pair_table['footer']['items'],
				$pair_table['footer']['id']
			);
		}
	}

	$httpPopupFormList
		->addRow(_('Follow redirects'), (new CCheckBox('follow_redirects'))->setChecked($followRedirects == 1))
		->addRow(_('Retrieve only headers'), (new CCheckBox('retrieve_mode'))->setChecked($retrieveMode == 1))
		->addRow(_('Timeout'),
			(new CTextBox('timeout', getRequest('timeout', DB::getDefault('httpstep', 'timeout'))))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
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

zbx_add_post_js('pairManager.add(' . CJs::encodeJson(array_values(getRequest('pairs', []))) . ');');
zbx_add_post_js('setPostType(' . CJs::encodeJson(getRequest('post_type', ZBX_POSTTYPE_FORM)) . ');');

return $httpPopupWidget;

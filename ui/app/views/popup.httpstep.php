<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */

$output = [
	'header' => $data['title'],
	'doc_url' => CDocHelper::getUrl(CDocHelper::POPUP_HTTP_STEP_EDIT)
];

$options = $data['options'];

$http_popup_form = (new CForm())
	->cleanItems()
	->setId('http_step')
	->addVar('no', $options['no'])
	->addVar('httpstepid', $options['httpstepid'])
	->addItem((new CVar('templated', $options['templated']))->removeId())
	->addVar('old_name', $options['old_name'])
	->addVar('steps_names', $options['steps_names'])
	->addVar('action', 'popup.httpstep')
	->addItem((new CInput('submit', 'submit'))->addStyle('display: none;'));

$http_popup_form_list = (new CFormList())
	->addRow(
		(new CLabel(_('Name'), 'step_name'))->setAsteriskMark(),
		(new CTextBox('name', $options['name'], (bool) $options['templated'], 64))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setId('step_name')
	)
	->addRow(
		(new CLabel(_('URL'), 'url'))->setAsteriskMark(),
		new CDiv([
			(new CTextBox('url', $options['url'], false, null))
				->setAriaRequired()
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('parse', _('Parse')))
				->onClick('httpconf.steps.edit_form.parseUrl();')
				->addClass(ZBX_STYLE_BTN_GREY)
		])
	);

$http_popup_form_list->addRow(_('Query fields'),
	(new CDiv(
		(new CTable())
			->addClass('httpconf-dynamic-row')
			->addStyle('width: 100%;')
			->setAttribute('data-type', 'query_fields')
			->setHeader(['', _('Name'), '', _('Value'), ''])
			->addRow((new CRow([
				(new CCol(
					(new CButton(null, _('Add')))
						->addClass('element-table-add')
						->addClass(ZBX_STYLE_BTN_LINK)
				))->setColSpan(5)
			])))
	))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH . 'px;'),
		'query-fields-row'
);

$http_popup_form_list->addRow(_('Post type'), (new CRadioButtonList('post_type', (int) $options['post_type']))
	->addValue(_('Form data'), ZBX_POSTTYPE_FORM)
	->addValue(_('Raw data'), ZBX_POSTTYPE_RAW)
	->setModern(true)
);

$http_popup_form_list->addRow(_('Post fields'),
	(new CDiv(
		(new CTable())
			->addClass('httpconf-dynamic-row')
			->addStyle('width: 100%;')
			->setAttribute('data-type', 'post_fields')
			->setHeader(['', _('Name'), '', _('Value'), ''])
			->addRow((new CRow([
				(new CCol(
					(new CButton(null, _('Add')))
						->addClass('element-table-add')
						->addClass(ZBX_STYLE_BTN_LINK)
				))->setColSpan(5)
			])))
	))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH . 'px;'),
		'post-fields-row'
);

$http_popup_form_list->addRow(_('Raw post'), (new CTextArea('posts', $options['posts']))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH), 'post-raw-row'
);

$http_popup_form_list->addRow(_('Variables'),
	(new CDiv(
		(new CTable())
			->addClass('httpconf-dynamic-row')
			->setAttribute('data-type', 'variables')
			->addStyle('width: 100%;')
			->setHeader(['', _('Name'), '', _('Value'), ''])
			->addRow((new CRow([
				(new CCol(
					(new CButton(null, _('Add')))
						->addClass('element-table-add')
						->addClass(ZBX_STYLE_BTN_LINK)
				))->setColSpan(5)
			])))
	))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH . 'px;')
);

$http_popup_form_list->addRow(_('Headers'),
	(new CDiv(
		(new CTable())
			->addClass('httpconf-dynamic-row')
			->setAttribute('data-type', 'headers')
			->addStyle('width: 100%;')
			->setHeader(['', _('Name'), '', _('Value'), ''])
			->addRow((new CRow([
				(new CCol(
					(new CButton(null, _('Add')))
						->addClass('element-table-add')
						->addClass(ZBX_STYLE_BTN_LINK)
				))->setColSpan(5)
			])))
	))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH . 'px;')
);

$http_popup_form_list
	->addRow(_('Follow redirects'),
		(new CCheckBox('follow_redirects'))
			->setChecked($options['follow_redirects'] == HTTPTEST_STEP_FOLLOW_REDIRECTS_ON)
	)
	->addRow(
		new CLabel(_('Retrieve mode'), 'retrieve_mode'),
		(new CRadioButtonList('retrieve_mode', (int) $options['retrieve_mode']))
			->addValue(_('Body'), HTTPTEST_STEP_RETRIEVE_MODE_CONTENT)
			->addValue(_('Headers'), HTTPTEST_STEP_RETRIEVE_MODE_HEADERS)
			->addValue(_('Body and headers'), HTTPTEST_STEP_RETRIEVE_MODE_BOTH)
			->setModern(true)
	)
	->addRow((new CLabel(_('Timeout'), 'timeout'))->setAsteriskMark(),
		(new CTextBox('timeout', $options['timeout']))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	)
	->addRow(_('Required string'),
		(new CTextBox('required', $options['required']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', _('pattern'))
	)
	->addRow(_('Required status codes'),
		(new CTextBox('status_codes', $options['status_codes']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

$output['buttons'] = [
	[
		'title' => $options['old_name'] ? _('Update') : _('Add'),
		'class' => '',
		'keepOpen' => true,
		'isSubmit' => true,
		'action' => 'return httpconf.steps.edit_form.validate(overlay);'
	]
];

$http_popup_form->addItem($http_popup_form_list);

// HTTP test step editing form.
$output['body'] = (new CDiv($http_popup_form))->toString();
$output['script_inline'] = 'httpconf.steps.onStepOverlayReadyCb('.$options['no'].');';

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


$output = [
	'header' => $data['title'],
	'script_inline' => require 'app/views/popup.httpstep.js.php'
];

$options = $data['options'];

$http_popup_form = (new CForm())
	->cleanItems()
	->setId('http_step')
	->addVar('dstfrm', $options['dstfrm'])
	->addVar('stepid', $options['stepid'])
	->addVar('list_name', $options['list_name'])
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
				->onClick('javascript: parseUrl("'.$http_popup_form->getId().'");')
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
			'items' => (new CRadioButtonList('post_type', $options['post_type']))
				->addValue(_('Form data'), ZBX_POSTTYPE_FORM, null,
					'return switchToPostType("'.$http_popup_form->getId().'", this.value);')
				->addValue(_('Raw data'), ZBX_POSTTYPE_RAW, null,
					'return switchToPostType("'.$http_popup_form->getId().'", this.value);')
				->setModern(true)
		],
		'footer' => [
			'id' => 'post_raw_row',
			'label' => _('Raw post'),
			'items' => (new CTextArea('posts', $options['posts']))
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
		$http_popup_form_list->addRow($pair_table['header']['label'], $pair_table['header']['items']);
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

	$http_popup_form_list->addRow($pair_table['label'],
		(new CDiv($pair_tab))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('data-type', $pair_table['id'])
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH . 'px;'),
		$pair_table['id'].'_row'
	);

	if (array_key_exists('footer', $pair_table)) {
		$http_popup_form_list->addRow($pair_table['footer']['label'], $pair_table['footer']['items'],
			$pair_table['footer']['id']
		);
	}
}

$http_popup_form_list
	->addRow(_('Follow redirects'),
		(new CCheckBox('follow_redirects'))
			->setChecked($options['follow_redirects'] == HTTPTEST_STEP_FOLLOW_REDIRECTS_ON)
	)
	->addRow(_('Retrieve only headers'),
		(new CCheckBox('retrieve_mode'))
			->setChecked($options['retrieve_mode'] == HTTPTEST_STEP_RETRIEVE_MODE_HEADERS)
	)
	->addRow((new CLabel(_('Timeout'), 'timeout'))->setAsteriskMark(),
		(new CTextBox('timeout', $options['timeout']))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	)
	->addRow(_('Required string'),
		(new CTextBox('required', $options['required']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Required status codes'),
		(new CTextBox('status_codes', $options['status_codes']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

$output['buttons'] = [
	[
		'title' => ($options['stepid'] == -1) ? _('Add') : _('Update'),
		'class' => '',
		'keepOpen' => true,
		'isSubmit' => true,
		'action' => 'return validateHttpStep("'.$http_popup_form->getId().'", '.
						'jQuery(window.document.forms["'.$http_popup_form->getId().'"])' .
							'.closest("[data-dialogueid]").attr("data-dialogueid"));'
	]
];

$http_popup_form->addItem($http_popup_form_list);

// HTTP test step editing form.
$output['body'] = (new CDiv($http_popup_form))->toString();

$output['script_inline'] .=
	'jQuery(document).ready(function() {'."\n".
		'pairManager.removeAll("'.$http_popup_form->getId().'", "");' .
		'pairManager.add("'.$http_popup_form->getId().'",' .
			CJs::encodeJson(array_values($options['pairs'])) . ');'."\n".
		'pairManager.initControls("'.$http_popup_form->getId().'");'."\n".
		'setPostType("'.$http_popup_form->getId().'",' .
			CJs::encodeJson($options['post_type']) . ');'."\n".
		'cookie.init();'."\n".
		'chkbxRange.init();'."\n".
	'});';

echo (new CJson())->encode($output);

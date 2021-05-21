<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

$form = (new CForm())
	->cleanItems()
	->setId('preprocessing-test-form');

if ($data['show_prev']) {
	$form
		->addVar('upd_last', '')
		->addVar('upd_prev', '');
}

foreach ($data['inputs'] as $name => $value) {
	if ($name === 'interface') {
		// SNMPv3 additional details about interface.
		if (array_key_exists('useip', $value)) {
			$form->addVar('interface[useip]', $value['useip']);
		}
		if (array_key_exists('interfaceid', $value)) {
			$form->addVar('interface[interfaceid]', $value['interfaceid']);
		}
		continue;
	}
	elseif ($name === 'host' && array_key_exists('hostid', $value)) {
		$form->addVar('hostid', $value['hostid']);
		continue;
	}
	elseif ($name === 'proxy_hostid') {
		continue;
	}
	elseif ($name === 'query_fields' || $name === 'headers') {
		foreach (['name', 'value'] as $key) {
			if (array_key_exists($key, $value)) {
				$form->addVar($name.'['.$key.']', $value[$key]);
			}
		}
		continue;
	}

	$form->addItem((new CInput('hidden', $name, $value))->removeId());
}

// Create macros table.
$macros_table = $data['macros'] ? (new CTable())->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER) : null;

$i = 0;
foreach ($data['macros'] as $macro_name => $macro_value) {
	$macros_table->addRow([
		(new CCol(
			(new CTextAreaFlexible('macro_rows['.$i++.']', $macro_name, ['readonly' => true]))
				->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
				->removeAttribute('name')
				->removeId()
		))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol('&rArr;'))->addStyle('vertical-align: top;'),
		(new CCol(
			(new CTextAreaFlexible('macros['.$macro_name.']', $macro_value))
				->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
				->setAttribute('placeholder', _('value'))
				->removeId()
		))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT)
	]);
}

$form_list_left = new CFormList();
$form_list_right = new CFormList();

if ($data['is_item_testable']) {
	$form_list_left
		->addRow(
			new CLabel(_('Get value from host'), 'get_value'),
			(new CCheckBox('get_value', 1))->setChecked($data['get_value'])
		)
		->addRow(
			new CLabel(_('Host address'), 'host_address'),
			$data['interface_address_enabled']
				? (new CTextBox('interface[address]', $data['inputs']['interface']['address']))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				: (new CTextBox('interface[address]'))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setEnabled(false),
			'host_address_row'
		)
		->addRow(
			new CLabel(_('Proxy'), 'label-proxy-hostid'),
			(new CSelect('proxy_hostid'))
				->setReadonly(!$data['proxies_enabled'])
				->addOptions(CSelect::createOptionsFromArray([0 => _('(no proxy)')] + $data['proxies']))
				->setFocusableElementId('label-proxy-hostid')
				->setValue(array_key_exists('proxy_hostid', $data['inputs']) ? $data['inputs']['proxy_hostid'] : 0)
				->setId('proxy_hostid')
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
			'proxy_hostid_row'
		)
		->addRow(null, null, 'empty_row_1');

	$form_list_right
		->addRow((new CDiv())->addStyle('height: 24px'))
		->addRow(
			new CLabel(_('Port'), 'port'),
			$data['interface_port_enabled']
				? (new CTextBox('interface[port]', $data['inputs']['interface']['port'], '', 64))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				: (new CTextBox('interface[port]'))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
					->setEnabled(false),
			'host_port_row'
		)
		->addRow(null, null, 'empty_row_2')
		->addRow(
			null,
			(new CSimpleButton(_('Get value')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->setId('get_value_btn')
				->addStyle('float: right'),
			'get_value_row'
		);
}

$form_list_left
	->addRow(
		new CLabel(_('Value'), 'value'),
		(new CMultilineInput('value', '', [
			'disabled' => false,
			'readonly' => false
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		'preproc-test-popup-value-row'
	)
	->addRow(
		new CLabel(_('Previous value'), 'prev_item_value'),
			(new CMultilineInput('prev_value', '', [
				'disabled' => !$data['show_prev']
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		'preproc-test-popup-prev-value-row'
	)
	->addRow(
		new CLabel(_('End of line sequence'), 'eol'),
		(new CRadioButtonList('eol', $data['eol']))
			->addValue(_('LF'), ZBX_EOL_LF)
			->addValue(_('CRLF'), ZBX_EOL_CRLF)
			->setModern(true)
	);

$form_list_right
	->addRow(
		new CLabel(_('Time'), 'time'),
		(new CTextBox(null, 'now', true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setId('time')
	)
	->addRow(
		new CLabel(_('Prev. time'), 'prev_time'),
		(new CTextBox('prev_time', $data['prev_time']))
			->setEnabled($data['show_prev'])
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);

$form_list = new CFormList();

if ($macros_table) {
	$form_list->addRow(
		_('Macros'),
		(new CDiv($macros_table))
			->addStyle('width: 675px;')
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	);
}

if (count($data['steps']) > 0) {
	// Create results table.
	$result_table = (new CTable())
		->setId('preprocessing-steps')
		->addClass('preprocessing-test-results')
		->addStyle('width: 100%;')
		->setHeader([
			'',
			(new CColHeader(_('Name')))->addStyle('width: 100%;'),
			(new CColHeader(_('Result')))->addClass(ZBX_STYLE_RIGHT)
		]);

	foreach ($data['steps'] as $i => $step) {
		$form
			->addVar('steps['.$i.'][type]', $step['type'])
			->addVar('steps['.$i.'][error_handler]', $step['error_handler'])
			->addVar('steps['.$i.'][error_handler_params]', $step['error_handler_params']);

		// Temporary solution to fix "\n\n1" conversion to "\n1" in the hidden textarea field after jQuery.append().
		if ($step['type'] == ZBX_PREPROC_CSV_TO_JSON || $step['type'] == ZBX_PREPROC_VALIDATE_RANGE) {
			$form->addItem(new CInput('hidden', 'steps['.$i.'][params]', $step['params']));
		}
		else {
			$form->addVar('steps['.$i.'][params]', $step['params']);
		}

		$result_table->addRow([
			$step['num'].':',
			(new CCol($step['name']))
				->setId('preproc-test-step-'.$i.'-name')
				->addClass(ZBX_STYLE_WORDBREAK),
			(new CCol())
				->addClass(ZBX_STYLE_RIGHT)
				->setId('preproc-test-step-'.$i.'-result')
		]);
	}

	$form_list->addRow(
		_('Preprocessing steps'),
		(new CDiv($result_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('width: 675px;')
	);
}

if ($data['show_final_result']) {
	$form_list->addRow(_('Result'), false, 'final-result');
}

$container = (new CDiv())
	->addClass(ZBX_STYLE_ROW)
	->addItem([
		(new CDiv($form_list_left))->addClass(ZBX_STYLE_CELL),
		(new CDiv($form_list_right))->addClass(ZBX_STYLE_CELL)
	]);

$form
	->addItem($container)
	->addItem($form_list)
	->addItem((new CInput('submit', 'submit'))->addStyle('display: none;'));

$templates = [
	(new CTag('script', true))
		->setAttribute('type', 'text/x-jquery-tmpl')
		->setId('preprocessing-step-error-icon')
		->addItem(makeErrorIcon('#{error}')),
	(new CTag('script', true))
		->setAttribute('type', 'text/x-jquery-tmpl')
		->setId('preprocessing-gray-label')
		->addItem(
			(new CDiv('#{label}'))
				->addStyle('margin-top: 5px;')
				->addClass(ZBX_STYLE_GREY)
		),
	(new CTag('script', true))
		->setAttribute('type', 'text/x-jquery-tmpl')
		->setId('preprocessing-step-result')
		->addItem(
			(new CDiv(
				(new CSpan('#{result}'))
					->addClass(ZBX_STYLE_LINK_ACTION)
					->setHint('#{result}', 'hintbox-wrap')
			))
				->addStyle('max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
		),
	(new CTag('script', true))
		->setAttribute('type', 'text/x-jquery-tmpl')
		->setId('preprocessing-step-action-done')
		->addItem(
			(new CDiv([
				'#{action_name} ',
				(new CDiv(
					(new CSpan('#{failed}'))
						->addClass(ZBX_STYLE_LINK_ACTION)
						->setHint('#{failed}', 'hintbox-wrap')
				))
					->addStyle('max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
					->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
					->addClass(ZBX_STYLE_REL_CONTAINER)
			]))
				->addStyle('margin-top: 1px;')
				->addClass(ZBX_STYLE_GREY)
		)
];

$warning_box = $data['show_warning']
	? makeMessageBox(ZBX_STYLE_MSG_WARNING, [[
		'message' => _('Item contains user defined macros with type "Secret text". Values of these macros should be entered manually.')
	]])
	: null;

$output = [
	'header' => $data['title'],
	'script_inline' => $this->readJsFile('popup.itemtestedit.view.js.php'),
	'body' => (new CDiv([$warning_box, $form, $templates]))->toString(),
	'cancel_action' => 'return saveItemTestInputs();',
	'buttons' => [
		[
			'title' => ($data['is_item_testable'] && $data['get_value']) ? _('Get value and test') : _('Test'),
			'keepOpen' => true,
			'enabled' => true,
			'isSubmit' => true,
			'action' => 'return itemCompleteTest(overlay);'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

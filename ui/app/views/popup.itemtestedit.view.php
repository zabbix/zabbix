<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 * @var array $data
 */

$form = (new CForm())
	->setId('preprocessing-test-form')
	->setName('preprocessing_test_form')
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('itemtest')))->removeId())
	->addVar('hostid', $data['hostid'])
	->addVar('interfaceid', $data['interfaceid'])
	->addVar('valuemapid', $data['valuemapid'])
	->addVar('test_type', $data['test_type'])
	->addVar('show_final_result', $data['show_final_result'])
	->addStyle('display: none;');

if ($data['show_prev']) {
	$form
		->addVar('upd_last', '')
		->addVar('upd_prev', '');
}

foreach ($data['inputs']['item'] as $name => $value) {
	if (in_array($name, ['query_fields', 'headers', 'parameters'])) {
		foreach ($value as $num => $row) {
			$form->addVar($name.'['.$num.'][name]', $row['name']);
			$form->addVar($name.'['.$num.'][value]', $row['value']);
		}
	}
	elseif ($name === 'type') {
		$form->addItem(
			(new CInput('hidden', 'item_type', $value))
				->setAttribute('data-field-type', 'hidden')
				->removeId()
		);
	}
	else {
		$form->addItem(
			(new CInput('hidden', $name, $value))
				->setAttribute('data-field-type', 'hidden')
				->removeId()
		);
	}
}

foreach ($data['inputs']['host'] as $name => $value) {
	if ($name === 'proxyid') {
		continue;
	}

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

	$form->addItem(
		(new CInput('hidden', $name, $value))
			->setAttribute('data-field-type', 'hidden')
			->removeId()
	);
}

// Create macros table.
$macros_table = $data['macros']
	? (new CTable())
		->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
		->setAttribute('data-field-type', 'set')
		->setAttribute('data-field-name', 'macros')
	: null;

$i = 0;
foreach ($data['macros'] as $macro_name => $macro_value) {
	$macros_table->addRow([
		(new CCol(
			(new CTextAreaFlexible('macros['.$i.'][name]', $macro_name))
				->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
				->removeId()
				->setReadonly()
		))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol(RARR()))->addStyle('vertical-align: top;'),
		(new CCol([
			(new CTextAreaFlexible('macros['.$i.'][value]', $macro_value))
				->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
				->setMaxlength(DB::getFieldLength('globalmacro', 'value'))
				->setAttribute('placeholder', _('value'))
				->disableSpellcheck()
				->removeId()
		]))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT)
	]);

	$i++;
}

$form_grid = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_3_1);

if ($data['is_item_testable']) {
	$form_grid->addItem([
		new CLabel(_('Get value from host'), 'get_value'),
		(new CFormField(
			(new CCheckBox('get_value', 1))
				->setChecked($data['get_value'])
				->setUncheckedValue(0)
				->setAttribute('autofocus', 'autofocus')
		))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID),

		(new CLabel(_('Host address'), 'interface_address'))
			->setAsteriskMark($data['interface_address_enabled'])
			->addClass('js-host-address-row'),
		(new CFormField(
			$data['interface_address_enabled']
				? (new CTextBox('interface[address]', $data['inputs']['host']['interface']['address'], false,
						DB::getFieldLength('interface', 'dns')
					))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				: (new CTextBox('interface[address]', '', false,
						DB::getFieldLength('interface', 'dns')
					))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setEnabled(false)
		))->addClass('js-host-address-row'),

		(new CLabel(_('Port'), 'interface_port'))->addClass('js-host-address-row'),
		(new CFormField(
			$data['interface_port_enabled']
				? (new CTextBox('interface[port]', $data['inputs']['host']['interface']['port'], '',
						DB::getFieldLength('interface', 'port')
					))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				: (new CTextBox('interface[port]'))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
					->setEnabled(false)
		))->addClass('js-host-address-row')
	]);

	if ($data['show_snmp_form']) {
		$form_grid->addItem([
			(new CLabel(_('SNMP version'), 'label-interface-details-version'))
				->addClass('js-popup-row-snmp-version'),
			(new CFormField(
				(new CSelect('interface[details][version]'))
					->setId('interface_details_version')
					->setFocusableElementId('label-interface-details-version')
					->setValue($data['inputs']['host']['interface']['details']['version'])
					->addOptions(CSelect::createOptionsFromArray([
						SNMP_V1 => _('SNMPv1'),
						SNMP_V2C => _('SNMPv2'),
						SNMP_V3 => _('SNMPv3')
					]))
					->setAttribute('data-prevent-validation-on-change', 1)
			))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-popup-row-snmp-version'),

			(new CLabel(_('SNMP community'), 'interface[details][community]'))
				->setAsteriskMark()
				->addClass('js-popup-row-snmp-community'),
			(new CFormField(
				(new CTextBox('interface[details][community]',
					$data['inputs']['host']['interface']['details']['community'], false,
					DB::getFieldLength('interface_snmp', 'community')
				))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
			))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-popup-row-snmp-community'),

			(new CLabel(_('Max repetition count'), 'interface[details][max_repetitions]'))
				->setAsteriskMark()
				->addClass('js-popup-row-snmp-max-repetition'),
			(new CFormField(
					(new CTextBox('interface[details][max_repetitions]',
						$data['inputs']['host']['interface']['details']['max_repetitions'], false,
						DB::getFieldLength('interface_snmp', 'max_repetitions')
					))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAriaRequired()
				))
					->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
					->addClass('js-popup-row-snmp-max-repetition'),

			(new CLabel(_('Context name'), 'interface[details][contextname]'))
				->addClass('js-popup-row-snmpv3-contextname'),
			(new CFormField(
				(new CTextBox('interface[details][contextname]',
					$data['inputs']['host']['interface']['details']['contextname'], false,
					DB::getFieldLength('interface_snmp', 'contextname')
				))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-popup-row-snmpv3-contextname'),

			(new CLabel(_('Security name'), 'interface[details][securityname]'))
				->addClass('js-popup-row-snmpv3-securityname'),
			(new CFormField(
				(new CTextBox('interface[details][securityname]',
					$data['inputs']['host']['interface']['details']['securityname'], false,
					DB::getFieldLength('interface_snmp', 'securityname')
				))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-popup-row-snmpv3-securityname'),

			(new CLabel(_('Security level'), 'label-interface-details-securitylevel'))
				->addClass('js-popup-row-snmpv3-securitylevel'),
			(new CFormField(
				(new CSelect('interface[details][securitylevel]'))
					->setId('interface_details_securitylevel')
					->setValue($data['inputs']['host']['interface']['details']['securitylevel'])
					->setFocusableElementId('label-interface-details-securitylevel')
					->addOptions(CSelect::createOptionsFromArray([
						ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => 'noAuthNoPriv',
						ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => 'authNoPriv',
						ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => 'authPriv'
					]))
			))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-popup-row-snmpv3-securitylevel'),

			(new CLabel(_('Authentication protocol'), 'label-authprotocol'))
				->addClass('js-popup-row-snmpv3-authprotocol'),
			(new CFormField(
				(new CSelect('interface[details][authprotocol]'))
					->setValue((int) $data['inputs']['host']['interface']['details']['authprotocol'])
					->setFocusableElementId('label-authprotocol')
					->addOptions(CSelect::createOptionsFromArray(getSnmpV3AuthProtocols()))
			))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-popup-row-snmpv3-authprotocol'),

			(new CLabel(_('Authentication passphrase'), 'interface[details][authpassphrase]'))
				->addClass('js-popup-row-snmpv3-authpassphrase'),
			(new CFormField(
				(new CTextBox('interface[details][authpassphrase]',
					$data['inputs']['host']['interface']['details']['authpassphrase'], false,
					DB::getFieldLength('interface_snmp', 'authpassphrase')
				))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-popup-row-snmpv3-authpassphrase'),

			(new CLabel(_('Privacy protocol'), 'label-privprotocol'))->addClass('js-popup-row-snmpv3-privprotocol'),
			(new CFormField(
				(new CSelect('interface[details][privprotocol]'))
					->setValue((int) $data['inputs']['host']['interface']['details']['privprotocol'])
					->setFocusableElementId('label-privprotocol')
					->addOptions(CSelect::createOptionsFromArray(getSnmpV3PrivProtocols()))
			))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-popup-row-snmpv3-privprotocol'),

			(new CLabel(_('Privacy passphrase'), 'interface[details][privpassphrase]'))
				->addClass('js-popup-row-snmpv3-privpassphrase'),
			(new CFormField(
				(new CTextBox('interface[details][privpassphrase]',
					$data['inputs']['host']['interface']['details']['privpassphrase'], false,
					DB::getFieldLength('interface_snmp', 'privpassphrase')
				))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-popup-row-snmpv3-privpassphrase')
		]);
	}

	$form_grid->addItem([
		(new CLabel(_('Test with'), 'test_with'))->addClass('js-test-with-row'),
		(new CFormField([
			(new CRadioButtonList('test_with', (int) $data['test_with']))
				->addValue(_('Server'), CControllerPopupItemTest::TEST_WITH_SERVER)
				->addValue(_('Proxy'), CControllerPopupItemTest::TEST_WITH_PROXY)
				->setReadonly(!$data['proxies_enabled'])
				->setModern(),
			(new CDiv(
				(new CMultiSelect([
					'name' => 'proxyid',
					'object_name' => 'proxies',
					'multiple' => false,
					'data' => $data['ms_proxy'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'proxies',
							'srcfld1' => 'proxyid',
							'srcfld2' => 'name',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'proxyid'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			))
				->addClass('js-test-with-proxy')
				->addStyle($data['test_with'] == CControllerPopupItemTest::TEST_WITH_SERVER ? 'display: none;' : '')
				->addStyle('margin-top: 5px;')
		]))
			->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
			->addClass('js-test-with-row'),

		(new CFormField(
			(new CSimpleButton(_('Get value')))
				->addClass('js-get-value-submit')
				->addClass(ZBX_STYLE_BTN_ALT)
		))
			->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_3)
			->addClass('js-get-value-row')
			->addStyle('text-align: right;')
	]);
}

$form_grid->addItem([
	new CLabel([
		_('Value'),
		makeWarningIcon('#{warning}')
			->setId('value_warning')
			->addStyle('display: none;')
	], 'value'),
	new CFormField(
		(new CMultilineInput('value', $data['value'], [
			'placeholder' => _('value'),
			'rows' => 0,
			'grow' => 'auto',
			'monospace_font' => false,
			'readonly' => $data['not_supported'],
			'use_tab' => false,
			'autofocus' => true
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	),

	new CLabel(_('Time'), 'time'),
	new CFormField(
		(new CTextBox('', 'now', true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->removeAttribute('data-field-type')
			->setId('time')
	),

	($data['test_type'] == CControllerPopupItemTestEdit::ZBX_TEST_TYPE_LLD)
		? null
		: (new CFormField([
			(new CCheckBox('not_supported'))
				->setLabel(_('Not supported'))
				->setChecked((bool) $data['not_supported'])
				->setUncheckedValue(0),
			(new CDiv([
				(new CLabel(_('Error'), 'runtime_error_match'))->setFor('runtime_error'),
				new CMultilineInput('runtime_error', $data['runtime_error'], [
					'placeholder' => _('error text'),
					'rows' => 0,
					'grow' => 'auto',
					'monospace_font' => false,
					'readonly' => $data['not_supported'],
					'use_tab' => false
				])
			]))
		]))
			->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
			->addClass('runtime-error-fields')
			->addStyle('width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),

	new CLabel(_('Previous value'), 'prev_item_value'),
	new CFormField(
		(new CMultilineInput('prev_value', $data['prev_value'], [
			'placeholder' => $data['show_prev'] ? _('value'): '',
			'rows' => 0,
			'grow' => 'auto',
			'monospace_font' => false,
			'disabled' => !$data['show_prev'],
			'use_tab' => false
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	),

	new CLabel(_('Prev. time'), 'prev_time'),
	new CFormField(
		(new CTextBox('prev_time', $data['prev_time']))
			->setEnabled($data['show_prev'])
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	),

	new CLabel(_('End of line sequence'), 'eol'),
	(new CFormField(
		(new CRadioButtonList('eol', $data['eol']))
			->addValue(_('LF'), ZBX_EOL_LF)
			->addValue(_('CRLF'), ZBX_EOL_CRLF)
			->setModern(true)
	))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
]);

if ($macros_table) {
	$form_grid->addItem([
		new CLabel(_('Macros')),
		(new CFormField(
			(new CDiv($macros_table))
				->addStyle('width: 675px;')
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
	]);
}

if (count($data['steps']) > 0) {
	// Create results table.
	$result_table = (new CTable())
		->setId('preprocessing-steps')
		->addClass('table-forms preprocessing-test-results')
		->addStyle('width: 100%;')
		->setHeader([
			'',
			(new CColHeader(_('Name')))->addStyle('width: 100%;'),
			(new CColHeader(_('Result')))->addClass(ZBX_STYLE_RIGHT),
			''
		]);

	foreach ($data['steps'] as $i => $step) {
		$form
			->addVar('steps['.$i.'][type]', $step['type'])
			->addVar('steps['.$i.'][error_handler]', $step['error_handler'])
			->addVar('steps['.$i.'][error_handler_params]', $step['error_handler_params']);

		if ($step['error_handler'] == ZBX_PREPROC_FAIL_SET_VALUE
				|| $step['error_handler'] == ZBX_PREPROC_FAIL_SET_ERROR) {
			$form->addVar('steps['.$i.'][on_fail]', 1);
		}

		$step_params = $step['type'] == ZBX_PREPROC_SCRIPT
			? [$step['params'], ''] : explode("\n", $step['params']);

		if ($step['type'] == ZBX_PREPROC_VALIDATE_NOT_SUPPORTED) {
			foreach ($step_params as $j => $param_value) {
				$form->addItem(
						(new CInput('hidden', 'steps['.$i.'][params_'.$j.'_not_supported]', $param_value))
							->setAttribute('data-notrim', '')
							->setAttribute('data-field-type', 'hidden')
					);
			}
		}
		elseif ($step['type'] == ZBX_PREPROC_SNMP_WALK_TO_JSON) {
			$j = 0;

			for ($k = 0; $k < count($step_params); $k += 3) {
				$form->addItem(
						(new CInput('hidden', 'steps['.$i.'][params_set_snmp]['.$j.'][name]', $step_params[$k]))
							->setAttribute('data-field-type', 'hidden')
					)
					->addItem(
						(new CInput('hidden', 'steps['.$i.'][params_set_snmp]['.$j.'][oid_prefix]',
							$step_params[$k+1]
						))
							->setAttribute('data-field-type', 'hidden')
					)
					->addItem(
						(new CInput('hidden', 'steps['.$i.'][params_set_snmp]['.$j.'][format]', $step_params[$k+2]))
							->setAttribute('data-field-type', 'hidden')
					);

				$j++;
			}
		}
		else {
			foreach ($step_params as $j => $param_value) {
				$form->addItem(
					(new CInput('hidden', 'steps['.$i.'][params_'.$j.']', $param_value))
						->setAttribute('data-notrim', '')
						->setAttribute('data-field-type', 'hidden')
				);
			}
		}

		$result_table->addRow(
			(new CRow([
				(new CCol($step['num'].':')),
				(new CCol($step['name']))
					->setId('preproc-test-step-'.$i.'-name')
					->addClass('js-preproc-step-name')
					->addClass(ZBX_STYLE_WORDBREAK),
				(new CCol())
					->addClass(ZBX_STYLE_RIGHT)
					->addClass('js-preproc-step-result')
					->setId('preproc-test-step-'.$i.'-result'),
				(new CCol(
					(new CButton('copy_button-'.$i))
						->setTitle(_('Copy to clipboard'))
						->addClass(ZBX_ICON_COPY)
						->addClass(ZBX_STYLE_BTN_GREY_ICON)
						->addClass('js-copy-button')
						->setAttribute('data-index', $i)
						->addStyle('display: none')
				))->addClass('result-copy')
			]))
				->addClass('js-preprocessing-test-step')
				->setAttribute('data-index', $i)
		);
	}

	$form_grid->addItem([
		new CLabel(_('Preprocessing steps')),
		(new CFormField(
			(new CDiv($result_table))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('width: 675px;')
		))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
	]);
}

if ($data['show_final_result']) {
	$form_grid->addItem([
		(new CLabel(_('Result')))->addClass('js-final-result')->addStyle('display: none'),
		(new CFormField(
			(new CDiv())->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		))
			->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
			->addClass('item-final-result')
			->addStyle('display: none')
	]);
}

$form->addItem($form_grid);

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form->addItem([
	(new CTemplateTag('preprocessing-step-error-icon'))->addItem(
		makeErrorIcon('#{error}')
	),
	(new CTemplateTag('preprocessing-gray-label'))->addItem(
		(new CDiv('#{label}'))
			->addStyle('margin-top: 5px;')
			->addClass(ZBX_STYLE_GREY)
	),
	(new CTemplateTag('preprocessing-step-result'))->addItem(
		(new CDiv(
			(new CSpan('#{result}'))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->setHint('#{result_hint}', 'hintbox-wrap')
		))
			->addStyle('max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
			->addClass('item-test-result')
			->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
	),
	(new CTemplateTag('preprocessing-step-result-empty'))->addItem(
		(new CSpan('#{result}'))->addClass(ZBX_STYLE_GREY)
	),
	(new CTemplateTag('preprocessing-step-result-default'))->addItem(
		(new CSpan('#{result}'))
	),
	(new CTemplateTag('preprocessing-step-result-warning'))->addItem(
		(new CDiv([
			(new CDiv('#{result}'))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
				->setHint('#{result_hint}', 'hintbox-wrap'),
			makeWarningIcon('#{warning}')
		]))
			->addStyle('max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
			->addClass('item-test-result')
	),
	(new CTemplateTag('preprocessing-step-action-done'))->addItem(
		(new CDiv([
			'#{action_name} ',
			(new CDiv(
				(new CSpan('#{failed}'))
					->addClass(ZBX_STYLE_LINK_ACTION)
					->setHint('#{failed_hint}', 'hintbox-wrap')
				))
				->addStyle('max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
				->addClass(ZBX_STYLE_REL_CONTAINER)
		]))
			->addStyle('margin-top: 1px;')
			->addClass(ZBX_STYLE_GREY)
	),
	(new CTemplateTag('final-result-row'))->addItem(
		(new CDiv([
			(new CSpan('#{action}'))->addClass('final-result-action'),
			(new CSpan())
				->addClass('final-result-result')
				->addStyle('max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
				->addClass('item-test-result')
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
			(new CButton('copy_button_final_#{mode}'))
				->setTitle(_('Copy to clipboard'))
				->addClass(ZBX_ICON_COPY)
				->addClass(ZBX_STYLE_BTN_GREY_ICON)
				->addClass('js-copy-button')
				->addStyle('display: none')
		]))
			->addClass('final-result-row')
			->addClass('display-icon')
	)
]);

$warning_box = $data['show_warning']
	? makeMessageBox(ZBX_STYLE_MSG_WARNING, [[
		'message' => _('Item contains user-defined macros with secret values. Values of these macros should be entered manually.')
	]])
	: null;

$output = [
	'header' => $data['title'],
	'doc_url' => CDocHelper::getUrl(CDocHelper::POPUP_TEST_EDIT),
	'body' => (new CDiv([$warning_box, $form]))->toString(),
	'buttons' => [
		[
			'title' => ($data['is_item_testable'] && $data['get_value']) ? _('Get value and test') : _('Test'),
			'class' => 'js-submit',
			'keepOpen' => true,
			'enabled' => true,
			'isSubmit' => true
		]
	],
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.itemtestedit.view.js.php').
		'itemtestedit_view_popup.init('.json_encode([
			'rules' => $data['js_validation_rules'],
			'rules_get_value' => $data['js_validation_rules_get_value'],
			'is_item_testable' => $data['is_item_testable'],
			'show_prev' => $data['show_prev'],
			'show_snmp_form' => $data['show_snmp_form'],
			'interface_address_enabled' => $data['interface_address_enabled'],
			'interface_port_enabled' => $data['interface_port_enabled']
		]).');'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

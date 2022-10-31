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
	elseif ($name === 'query_fields' || $name === 'headers' || $name === 'parameters') {
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
				->setMaxlength(CControllerPopupItemTest::INPUT_MAX_LENGTH)
				->setAttribute('placeholder', _('value'))
				->removeId()
		))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT)
	]);
}

$form_grid = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_3_1);

if ($data['is_item_testable']) {
	$form_grid->addItem([
		new CLabel(_('Get value from host'), 'get_value'),
		(new CFormField(
			(new CCheckBox('get_value', 1))->setChecked($data['get_value'])
		))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID),

		(new CLabel(_('Host address'), 'interface_address'))
			->setAsteriskMark($data['interface_address_enabled'])
			->addClass('js-host-address-row'),
		(new CFormField(
			$data['interface_address_enabled']
				? (new CTextBox('interface[address]', $data['inputs']['interface']['address'], false,
						CControllerPopupItemTest::INPUT_MAX_LENGTH
					))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				: (new CTextBox('interface[address]', '', false, CControllerPopupItemTest::INPUT_MAX_LENGTH))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setEnabled(false)
		))->addClass('js-host-address-row'),

		(new CLabel(_('Port'), 'interface_port'))->addClass('js-host-address-row'),
		(new CFormField(
			$data['interface_port_enabled']
				? (new CTextBox('interface[port]', $data['inputs']['interface']['port'], '', 64))
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
					->setValue($data['inputs']['interface']['details']['version'])
					->addOptions(CSelect::createOptionsFromArray([
						SNMP_V1 => _('SNMPv1'),
						SNMP_V2C => _('SNMPv2'),
						SNMP_V3 => _('SNMPv3')
					]))
			))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-popup-row-snmp-version'),

			(new CLabel(_('SNMP community'), 'interface[details][community]'))
				->setAsteriskMark()
				->addClass('js-popup-row-snmp-community'),
			(new CFormField(
				(new CTextBox('interface[details][community]', $data['inputs']['interface']['details']['community'],
					false, CControllerPopupItemTest::INPUT_MAX_LENGTH
				))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
			))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-popup-row-snmp-community'),

			(new CLabel(_('Context name'), 'interface[details][contextname]'))
				->addClass('js-popup-row-snmpv3-contextname'),
			(new CFormField(
				(new CTextBox('interface[details][contextname]', $data['inputs']['interface']['details']['contextname'],
					false, CControllerPopupItemTest::INPUT_MAX_LENGTH
				))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-popup-row-snmpv3-contextname'),

			(new CLabel(_('Security name'), 'interface[details][securityname]'))
				->addClass('js-popup-row-snmpv3-securityname'),
			(new CFormField(
				(new CTextBox('interface[details][securityname]',
					$data['inputs']['interface']['details']['securityname'], false,
					CControllerPopupItemTest::INPUT_MAX_LENGTH
				))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-popup-row-snmpv3-securityname'),

			(new CLabel(_('Security level'), 'label-interface-details-securitylevel'))
				->addClass('js-popup-row-snmpv3-securitylevel'),
			(new CFormField(
				(new CSelect('interface[details][securitylevel]'))
					->setId('interface_details_securitylevel')
					->setValue($data['inputs']['interface']['details']['securitylevel'])
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
				(new CSelect('interfaces[details][authprotocol]'))
					->setValue((int) $data['inputs']['interface']['details']['authprotocol'])
					->setFocusableElementId('label-authprotocol')
					->addOptions(CSelect::createOptionsFromArray(getSnmpV3AuthProtocols()))
			))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-popup-row-snmpv3-authprotocol'),

			(new CLabel(_('Authentication passphrase'), 'interface[details][authpassphrase]'))
				->addClass('js-popup-row-snmpv3-authpassphrase'),
			(new CFormField(
				(new CTextBox('interface[details][authpassphrase]',
					$data['inputs']['interface']['details']['authpassphrase'], false,
					CControllerPopupItemTest::INPUT_MAX_LENGTH
				))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-popup-row-snmpv3-authpassphrase'),

			(new CLabel(_('Privacy protocol'), 'label-privprotocol'))->addClass('js-popup-row-snmpv3-privprotocol'),
			(new CFormField(
				(new CSelect('interfaces[details][privprotocol]'))
					->setValue((int) $data['inputs']['interface']['details']['privprotocol'])
					->setFocusableElementId('label-privprotocol')
					->addOptions(CSelect::createOptionsFromArray(getSnmpV3PrivProtocols()))
			))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-popup-row-snmpv3-privprotocol'),

			(new CLabel(_('Privacy passphrase'), 'interface[details][privpassphrase]'))
				->addClass('js-popup-row-snmpv3-privpassphrase'),
			(new CFormField(
				(new CTextBox('interface[details][privpassphrase]',
					$data['inputs']['interface']['details']['privpassphrase'], false,
					CControllerPopupItemTest::INPUT_MAX_LENGTH
				))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-popup-row-snmpv3-privpassphrase')
		]);
	}

	$form_grid->addItem([
		(new CLabel(_('Proxy'), 'label-proxy-hostid'))->addClass('js-proxy-hostid-row'),
		(new CFormField(
			(new CSelect('proxy_hostid'))
				->setReadonly(!$data['proxies_enabled'])
				->addOptions(CSelect::createOptionsFromArray([0 => _('(no proxy)')] + $data['proxies']))
				->setFocusableElementId('label-proxy-hostid')
				->setValue(array_key_exists('proxy_hostid', $data['inputs']) ? $data['inputs']['proxy_hostid'] : 0)
				->setId('proxy_hostid')
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		))
			->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
			->addClass('js-proxy-hostid-row'),

		(new CFormField(
			(new CSimpleButton(_('Get value')))
				->setId('get_value_btn')
				->addClass(ZBX_STYLE_BTN_ALT)
		))
			->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_3)
			->addClass('js-get-value-row')
			->addStyle('text-align: right;')
	]);
}

$form_grid->addItem([
	new CLabel(_('Value'), 'value'),
	new CFormField(
		(new CMultilineInput('value', '', [
			'disabled' => false,
			'readonly' => false
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	),

	new CLabel(_('Time'), 'time'),
	new CFormField(
		(new CTextBox(null, 'now', true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setId('time')
	),

	($data['preproc_item'] instanceof CDiscoveryRule)
		? null
		: (new CFormField((new CCheckBox('not_supported'))->setLabel(_('Not supported'))))
			->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID),

	new CLabel(_('Previous value'), 'prev_item_value'),
	new CFormField(
		(new CMultilineInput('prev_value', '', [
			'disabled' => !$data['show_prev']
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
			(new CCol($step['num'].':')),
			(new CCol($step['name']))
				->setId('preproc-test-step-'.$i.'-name')
				->addClass(ZBX_STYLE_WORDBREAK),
			(new CCol())
				->addClass(ZBX_STYLE_RIGHT)
				->setId('preproc-test-step-'.$i.'-result')
		]);
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
		(new CLabel(_('Result')))->addClass('js-final-result'),
		(new CFormField())->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
	]);
}

$form
	->addItem($form_grid)
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
		'message' => _('Item contains user-defined macros with secret values. Values of these macros should be entered manually.')
	]])
	: null;

$output = [
	'header' => $data['title'],
	'doc_url' => CDocHelper::getUrl(CDocHelper::POPUP_TEST_EDIT),
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

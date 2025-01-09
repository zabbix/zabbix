<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
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
	->setId('script-userinput-form')
	->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

if ($data['manualinput_validator_type'] == ZBX_SCRIPT_MANUALINPUT_TYPE_LIST) {
	$form
		->addItem(
			(new CSpan($data['manualinput_prompt']))
				->addClass(ZBX_STYLE_WORDBREAK)
				->addClass(ZBX_STYLE_FLOAT_LEFT)
		)
		->addItem(
			(new CSelect('manualinput'))
				->addStyle('margin-top: 8px;')
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->addOptions(CSelect::createOptionsFromArray($data['dropdown_options']))
				->setValue(array_shift($data['dropdown_options']))
				->addClass(ZBX_STYLE_FLOAT_LEFT)
		);
}
else {
	$form
		->addItem((new CSpan($data['manualinput_prompt']))->addClass(ZBX_STYLE_WORDBREAK))
		->addItem(
			new CFormField(
				(new CTextBox('manualinput', $data['manualinput_default_value'], false,
					DB::getFieldLength('scripts', 'manualinput_default_value')
				))
					->addStyle('margin-top: 8px;')
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		);
}

$buttons = [
	[
		'title' => _('Cancel'),
		'class' => ZBX_STYLE_BTN_ALT,
		'cancel' => true,
		'action' => ''
	]
];

if ($data['test']) {
	$buttons[] = [
		'title' => _('Test'),
		'keepOpen' => true,
		'isSubmit' => true,
		'action' => 'script_userinput_popup.submitTestForm();',
		'class' => 'userinput-submit',
		'enabled' => $data['manualinput_validator_type'] == ZBX_SCRIPT_MANUALINPUT_TYPE_STRING
	];
}
else {
	$buttons[] = [
		'title' => $data['has_confirmation'] ? _('Continue') : _('Execute'),
		'keepOpen' => true,
		'isSubmit' => true,
		'action' => 'script_userinput_popup.submit();'
	];
}

$form
	->addItem(
		(new CScriptTag('script_userinput_popup.init('.json_encode([
			'test' => $data['test'],
			'input_type' => $data['manualinput_validator_type'],
			'default_input' => $data['manualinput_default_value'],
			'input_validator' => $data['manualinput_validator']
		]).');'))->setOnDocumentReady()
	);

$output = [
	'header' => _('Manual input'),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().$this->readJsFile('script.userinput.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * @var array $data
 */

$form = (new CForm())
	->setId('script-userinput-form')
	->addItem((new CInput('submit', null))->addStyle('display: none;'));

if ($data['input_type'] == SCRIPT_MANUALINPUT_TYPE_LIST) {
	$form
		->addItem((new CSpan($data['input_prompt']))->addClass(ZBX_STYLE_WORDBREAK))
		->addItem(
			(new CSelect('manual_input'))
				->addStyle('margin-top: 8px;')
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->addOptions(CSelect::createOptionsFromArray($data['dropdown_options']))
				->setValue(array_shift($data['dropdown_options']))
		);
}
else {
	$form
		->addItem((new CSpan($data['input_prompt']))->addClass(ZBX_STYLE_WORDBREAK))
		->addItem(
			new CFormField(
				(new CTextBox('manual_input', $data['default_input'], false,
					DB::getFieldLength('scripts', 'manualinput_default_value'))
				)
					->addStyle('margin-top: 8px;')
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		);
}

if($data['test']) {
	$buttons = [
		[
			'title' => _('Test'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'script_userinput_popup.test();',
			'class' => 'userinput-submit'
		]
	];
}
else {
	$buttons = [
		[
			'title' => $data['confirmation'] ? _('Continue') : _('Execute'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'script_userinput_popup.submit();'
		]
	];
}

$form
	->addItem(
		(new CScriptTag('script_userinput_popup.init('.json_encode([
			'test' => $data['test'],
			'input_type' => $data['input_type'],
			'default_input' => $data['default_input'],
			'input_validation' => $data['input_validation']
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

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
 */

$data_table = (new CTable())
	->addStyle('width: 100%;')
	->setHeader([
		_('Expression variable elements'),
		_('Result type'),
		_('Value')
	]);

foreach ($data['test_data_rows'] as $i => $macro) {
	$row = (new CRow())->addItem(
		(new CCol($macro['macro']))
			->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
			->addStyle('max-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);

	if ($macro['error'] !== null) {
		$row->addItem((new CCol($macro['error']))->addClass(ZBX_STYLE_RED)->setColspan(2));
	}
	else {
		$name = 'test_data['.$i.'][value]';

		$control = $macro['values'] !== null
			? (new CSelect($name))
				->addOptions(CSelect::createOptionsFromArray($macro['values']))
				->setValue($macro['value'])
			: (new CTextBox($name, $macro['value']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);

		$row->addItem([
			$macro['value_type'],
			[(new CVar('test_data['.$i.'][key]', $macro['key']))->removeId(), $control]
		]);
	}

	$data_table->addRow($row);
}

$form_list = (new CFormList())
	->addRow(_('Test data'),
		(new CDiv($data_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);

$result_table = (new CTable())
	->addStyle('width: 100%;')
	->setHeader([
		_('Expression'),
		_('Result'),
		_('Error')
	]);

foreach ($data['eHTMLTree'] as $e) {
	$expression = $e['expression']['value'];
	$result = '';
	$style = null;
	$error = null;

	if (array_key_exists($expression, $data['results'])) {
		if (array_key_exists('value', $data['results'][$expression])) {
			$result = $data['results'][$expression]['value'] ? 'TRUE' : 'FALSE';
			$style = $data['results'][$expression]['value'] ? ZBX_STYLE_GREEN : ZBX_STYLE_RED;
		}
		if (array_key_exists('error', $data['results'][$expression])) {
			$error = makeErrorIcon($data['results'][$expression]['error']);
		}
	}

	$result_table->addRow([
		(new CCol($e['list']))
			->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
			->addStyle('max-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
		(new CCol($result))->addClass($style),
		new CCol($error)
	]);
}

$expression = $data['expression'];
$result = '';
$style = null;
$error = null;

if (array_key_exists($expression, $data['results'])) {
	if (array_key_exists('value', $data['results'][$expression])) {
		$result = $data['results'][$expression]['value'] ? 'TRUE' : 'FALSE';
		$style = $data['results'][$expression]['value'] ? ZBX_STYLE_GREEN : ZBX_STYLE_RED;
	}
	if (array_key_exists('error', $data['results'][$expression])) {
		$error = makeErrorIcon($data['results'][$expression]['error']);
	}
}

$result_table->setFooter([
	(new CCol($data['outline']))
		->setAttribute('title', $data['outline'])
		->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
		->addStyle('max-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
	(new CCol($result))->addClass($style),
	new CCol($error)
]);

$form_list->addRow(_('Result'),
	(new CDiv($result_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

$output = [
	'header' => $data['title'],
	'body' => (new CDiv([
		$data['messages'],
		(new CForm())
			->setId('expression_testing_from')
			->addItem((new CVar('expression', $data['expression']))->removeId())
			->addItem((new CVar('test_expression', 1))->removeId())
			->addItem([
				$form_list,
				(new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN)
			])
		]))->toString(),
	'buttons' => [
		[
			'title' => _('Test'),
			'enabled' => $data['allowed_testing'],
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return reloadPopup(document.forms["expression_testing_from"], "popup.testtriggerexpr");'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

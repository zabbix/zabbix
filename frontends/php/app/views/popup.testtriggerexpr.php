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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


$allowed_testing = $data['allowed_testing'];
$test = $data['test'];

$data_table = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setHeader([
		_('Expression Variable Elements'),
		_('Result type'),
		_('Value')
	]);

foreach ($data['data_table_rows'] as $row) {
	$data_table->addRow($row);
}

$form_list = (new CFormList())
	->addRow(_('Test data'),
		(new CDiv($data_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);

$result_table = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setHeader([
		_('Expression'),
		_('Result')
	]);

foreach ($data['eHTMLTree'] as $e) {
	$result = '';
	$style = null;

	if ($allowed_testing && $test && array_key_exists('expression', $e)) {
		if (evalExpressionData($e['expression']['value'], $data['macros_data'])) {
			$result = 'TRUE';
			$style = ZBX_STYLE_GREEN;
		}
		else {
			$result = 'FALSE';
			$style = ZBX_STYLE_RED;
		}
	}

	$result_table->addRow([$e['list'], (new CCol($result))->addClass($style)]);
}

$result = '';
if ($allowed_testing && $test) {
	if (evalExpressionData($data['expression'], $data['macros_data'])) {
		$result = 'TRUE';
		$style = ZBX_STYLE_GREEN;
	}
	else {
		$result = 'FALSE';
		$style = ZBX_STYLE_RED;
	}
}

$result_table->setFooter([$data['outline'], (new CCol($result))->addClass($style)]);

$form_list->addRow(_('Result'),
	(new CDiv($result_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

$output = [
	'header' => $data['title'],
	'body' => (new CDiv([
		$data['message'],
		(new CForm())
			->setId('expression_testing_from')
			->addVar('expression', $data['expression'])
			->addVar('test_expression', 1)
			->addItem([
				(new CTabView())->addTab('test_tab', null, $form_list),
				(new CInput('submit', 'submit'))->addStyle('display: none;')
			])
		]))->toString(),
	'buttons' => [
		[
			'title' => _('Test'),
			'enabled' => $allowed_testing,
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return reloadPopup(document.forms["expression_testing_from"], "popup.testtriggerexpr");'
		]
	]
];

echo (new CJson())->encode($output);

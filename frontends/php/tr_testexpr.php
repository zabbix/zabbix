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
** along with this program; ifnot, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';

$page['title'] = _('Test');
$page['file'] = 'tr_testexpr.php';

define('ZBX_PAGE_NO_MENU', 1);
define('COMBO_PATTERN', 'str_in_array({},array(');
define('COMBO_PATTERN_LENGTH', strlen(COMBO_PATTERN));

$definedErrorPhrases = [
	EXPRESSION_HOST_UNKNOWN => _('Unknown host, no such host present in system'),
	EXPRESSION_HOST_ITEM_UNKNOWN => _('Unknown host item, no such item in selected host'),
	EXPRESSION_NOT_A_MACRO_ERROR => _('Given expression is not a macro'),
	EXPRESSION_FUNCTION_UNKNOWN => _('Incorrect function is used')
];

require_once dirname(__FILE__).'/include/page_header.php';

// expression analyze
$expression = getRequest('expression', '');

define('NO_LINK_IN_TESTING', true);
list($outline, $eHTMLTree) = analyzeExpression($expression);

// test data (create table, create check fields)
$dataTable = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Expression Variable Elements'), _('Result type'), _('Value')]);

$datas = [];
$fields = [];
$rplcts = [];
$allowedTesting = true;

$expressionData = new CTriggerExpression();
$result = $expressionData->parse($expression);
if ($result) {
	$macrosData = [];

	$supportedTokenTypes = [
		CTriggerExpressionParserResult::TOKEN_TYPE_FUNCTION_MACRO => 1,
		CTriggerExpressionParserResult::TOKEN_TYPE_MACRO => 1,
		CTriggerExpressionParserResult::TOKEN_TYPE_USER_MACRO => 1,
		CTriggerExpressionParserResult::TOKEN_TYPE_LLD_MACRO => 1
	];
	foreach ($result->getTokens() as $token) {
		if (!isset($supportedTokenTypes[$token['type']]) || isset($macrosData[$token['value']])) {
			continue;
		}

		$row = (new CRow())->addItem($token['value']);

		$fname = 'test_data_'.md5($token['value']);
		$macrosData[$token['value']] = getRequest($fname, '');

		$info = get_item_function_info($token['value']);

		if (!is_array($info) && isset($definedErrorPhrases[$info])) {
			$allowedTesting = false;
			$row->addItem(
				(new CCol($definedErrorPhrases[$info]))
					->addClass(ZBX_STYLE_RED)
					->setColspan(2)
			);
		}
		else {
			$validation = $info['validation'];

			if (substr($validation, 0, COMBO_PATTERN_LENGTH) == COMBO_PATTERN) {
				$end = strlen($validation) - COMBO_PATTERN_LENGTH - 4;
				$vals = explode(',', substr($validation, COMBO_PATTERN_LENGTH, $end));
				$control = new CComboBox($fname, $macrosData[$token['value']]);

				foreach ($vals as $v) {
					$control->addItem($v, $v);
				}
			}
			else {
				$control = (new CTextBox($fname, $macrosData[$token['value']]))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
			}

			$fields[$fname] = [$info['type'], O_OPT, null, $validation, 'isset({test_expression})',
				$token['value']
			];

			$row->addItem($info['value_type']);
			$row->addItem($control);
		}

		$dataTable->addRow($row);
	}
}

// checks
$fields['test_expression'] = [T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null];
if (!check_fields($fields)) {
	$test = false;
}

// actions
if (isset($_REQUEST['test_expression'])) {
	show_messages();
	$test = true;
}
else {
	$test = false;
}

// form
$widget = (new CWidget())->setTitle(_('Test'));

$form = (new CForm())
	->addVar('expression', $expression);

$form_list = (new CFormList())
	->addRow(_('Test data'),
		(new CDiv($dataTable))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);

$resultTable = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Expression'), _('Result')]);

ksort($rplcts, SORT_NUMERIC);

foreach ($eHTMLTree as $e) {
	$result = '';
	$style = null;

	if ($allowedTesting && $test && isset($e['expression'])) {
		if (evalExpressionData($e['expression']['value'], $macrosData)) {
			$result = 'TRUE';
			$style = ZBX_STYLE_GREEN;
		}
		else {
			$result = 'FALSE';
			$style = ZBX_STYLE_RED;
		}
	}

	$resultTable->addRow([$e['list'], (new CCol($result))->addClass($style)]);
}

$result = '';

if ($allowedTesting && $test) {
	if (evalExpressionData($expression, $macrosData)) {
		$result = 'TRUE';
		$style = ZBX_STYLE_GREEN;
	}
	else {
		$result = 'FALSE';
		$style = ZBX_STYLE_RED;
	}
}

$resultTable->setFooter([$outline, (new CCol($result))->addClass($style)]);

$form_list->addRow(_('Result'),
	(new CDiv($resultTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

$tab = (new CTabView())->addTab('test_tab', null, $form_list);

$tab->setFooter(makeFormFooter(
	(new CSubmit('test_expression', _('Test')))->setEnabled($allowedTesting),
	[(new CButton('close', _('Close')))->onClick('javascript: self.close();')]
));

$form->addItem($tab);

$widget
	->addItem($form)
	->show();

require_once dirname(__FILE__).'/include/page_footer.php';

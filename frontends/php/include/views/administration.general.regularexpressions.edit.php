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


require_once dirname(__FILE__).'/js/adm.regexprs.edit.js.php';

$widget = (new CWidget())
	->setTitle(_('Regular expressions'))
	->setControls((new CTag('nav', true,
		(new CForm())
			->cleanItems()
			->addItem((new CList())
				->addItem(makeAdministrationGeneralMenu('adm.regexps.php'))
			)
		))
			->setAttribute('aria-label', _('Content controls'))
	);

$form = (new CForm())
	->setId('zabbixRegExpForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', 1)
	->addVar('regexpid', $data['regexpid']);

/*
 * Expressions tab
 */
$exprTable = (new CTable())
	->setId('tbl_expr')
	->setAttribute('style', 'width: 100%;')
	->setHeader([
		_('Expression type'),
		_('Expression'),
		_('Delimiter'),
		_('Case sensitive'),
		_('Action')
	]);

foreach ($data['expressions'] as $i => $expression) {
	$exp_delimiter = new CComboBox('expressions['.$i.'][exp_delimiter]', $expression['exp_delimiter'], null,
		expressionDelimiters()
	);

	if ($expression['expression_type'] != EXPRESSION_TYPE_ANY_INCLUDED) {
		$exp_delimiter->addStyle('display: none;');
	}

	$row = [
		(new CComboBox('expressions['.$i.'][expression_type]', $expression['expression_type'], null,
			expression_type2str()
		))->onChange('onChangeExpressionType(this, '.$i.')'),
		(new CTextBox('expressions['.$i.'][expression]', $expression['expression'], false, 255))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
		$exp_delimiter,
		(new CCheckBox('expressions['.$i.'][case_sensitive]', '1'))->setChecked($expression['case_sensitive'] == 1)
	];

	$button_cell = [
		(new CButton('expressions['.$i.'][remove]', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
	];
	if (array_key_exists('expressionid', $expression)) {
		$button_cell[] = new CVar('expressions['.$i.'][expressionid]', $expression['expressionid']);
	}

	$row[] = (new CCol($button_cell))->addClass(ZBX_STYLE_NOWRAP);

	$exprTable->addRow(
		(new CRow($row))
			->addClass('form_row')
			->setAttribute('data-index', $i)
	);
}

$exprTable->setFooter(
	(new CButton('expression_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
);

$exprTab = (new CFormList('exprTab'))
	->addRow(_('Name'),
		(new CTextBox('name', $data['name'], false, 128))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(_('Expressions'), (new CDiv($exprTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);

/*
 * Test tab
 */
$testTab = (new CFormList())
	->addRow(_('Test string'),
		(new CTextArea('test_string', $data['test_string']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow('', (new CButton('testExpression', _('Test expressions')))->addClass(ZBX_STYLE_BTN_ALT))
	->addRow(_('Result'),
		(new CDiv(
			(new CTable())
				->setId('testResultTable')
				->setAttribute('style', 'width: 100%;')
				->setHeader([_('Expression type'), _('Expression'), _('Result')])
		))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);

$regExpView = new CTabView();
if (!$data['form_refresh']) {
	$regExpView->setSelected(0);
}
$regExpView->addTab('expr', _('Expressions'), $exprTab);
$regExpView->addTab('test', _('Test'), $testTab);

// footer
if (isset($data['regexpid'])) {
	$regExpView->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CButton('clone', _('Clone')),
			new CButtonDelete(
				_('Delete regular expression?'),
				url_param('regexpid').url_param('regexp.massdelete', false, 'action')
			),
			new CButtonCancel()
		]
	));
}
else {
	$regExpView->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$form->addItem($regExpView);

$widget->addItem($form);

return $widget;

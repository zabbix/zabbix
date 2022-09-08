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

$this->includeJsFile('administration.regex.edit.js.php');

$widget = (new CWidget())
	->setTitle(_('Regular expressions'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_REGEX_EDIT));

$action = (new CUrl('zabbix.php'))->setArgument('action', ($data['regexid'] == 0) ? 'regex.create' : 'regex.update');

if ($data['regexid'] != 0) {
	$action->setArgument('regexid', $data['regexid']);
}

$form = (new CForm())
	->setId('regex')
	->setAction($action->getUrl())
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE);

$table = (new CTable())
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
	$exp_delimiter = (new CSelect('expressions['.$i.'][exp_delimiter]'))
		->setValue($expression['exp_delimiter'])
		->setId('expressions_'.$i.'_exp_delimiter')
		->addClass('js-expression-delimiter-select')
		->addOptions(CSelect::createOptionsFromArray(CRegexHelper::expressionDelimiters()))
		->setDisabled($expression['expression_type'] != EXPRESSION_TYPE_ANY_INCLUDED);

	if ($expression['expression_type'] != EXPRESSION_TYPE_ANY_INCLUDED) {
		$exp_delimiter->addStyle('display: none;');
	}

	$row = [
		(new CSelect('expressions['.$i.'][expression_type]'))
			->setId('expressions_'.$i.'_expression_type')
			->addClass('js-expression-type-select')
			->addOptions(CSelect::createOptionsFromArray(CRegexHelper::expression_type2str()))
			->setValue($expression['expression_type']),
		(new CTextBox('expressions['.$i.'][expression]', $expression['expression'], false, 255))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAriaRequired(),
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

	$table->addRow(
		(new CRow($row))
			->addClass('form_row')
			->setAttribute('data-index', $i)
	);
}

$table->setFooter(
	(new CButton('expression_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
);

$expr_tab = (new CFormList('exprTab'))
	->addRow(
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['name'], false, 128))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Expressions'), 'tbl_expr'))->setAsteriskMark(),
		(new CDiv($table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);

$test_tab = (new CFormList())
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

$reg_exp_view = new CTabView();
if (!$data['form_refresh']) {
	$reg_exp_view->setSelected(0);
}

$reg_exp_view->addTab('expr', _('Expressions'), $expr_tab);
$reg_exp_view->addTab('test', _('Test'), $test_tab);

// footer
if ($data['regexid'] != 0) {
	$reg_exp_view->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			(new CSimpleButton(_('Clone')))->setId('clone'),
			(new CRedirectButton(_('Delete'),
					(new CUrl('zabbix.php'))
						->setArgument('action', 'regex.delete')
						->setArgument('regexids', (array) $data['regexid'])
						->setArgumentSID(),
				_('Delete regular expression?')
			))->setId('delete'),
			(new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
				->setArgument('action', 'regex.list')
			))->setId('cancel')
		]
	));
}
else {
	$reg_exp_view->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[
			(new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
				->setArgument('action', 'regex.list')
			))->setId('cancel')
		]
	));
}

$form->addItem($reg_exp_view);

$widget
	->addItem($form)
	->show();

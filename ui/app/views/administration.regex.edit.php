<?php
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

$this->includeJsFile('administration.regex.edit.js.php');

$csrf_token = CCsrfTokenHelper::get('regex');

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, $csrf_token))->removeId())
	->setAction((new CUrl('zabbix.php'))->getUrl())
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->setId('regexp')
	->addItem($data['regexp']['regexpid'] != 0 ? new CVar('regexpid', $data['regexp']['regexpid']) : null);

$table = (new CTable())
	->setId('regular-expressions-table')
	->setHeader([
		_('Expression type'),
		_('Expression'),
		_('Delimiter'),
		_('Case sensitive'),
		''
	]);

$options_delimiter = CSelect::createOptionsFromArray(CRegexHelper::expressionDelimiters());
$options_expression_type = CSelect::createOptionsFromArray(CRegexHelper::expression_type2str());

foreach ($data['regexp']['expressions'] as $index => $expression) {
	$table
		->addRow(getExpressionEntryView($index, $expression['case_sensitive'],
			$expression['expression_type'], $expression['expression'], $expression['exp_delimiter'], $options_delimiter,
			$options_expression_type
		))
		->addRow(getExpressionEntryErrorView($index));
}

$table->addRow((new CRow((new CCol(
	(new CButton('add', _('Add')))->addClass(ZBX_STYLE_BTN_LINK)->removeId()
))->setColSpan(5)))->setId('expression-list-footer'));

$tabs = (new CTabView())
	->addTab('expr', _('Expressions'), (new CFormGrid())
		->addItem((new CLabel(_('Name'), 'name'))->setAsteriskMark())
		->addItem((new CFormField())
			->addItem((new CTextBox('name', $data['regexp']['name'], false, DB::getFieldLength('regexps', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('autofocus', 'autofocus')
				->setAriaRequired()
			)
		)
		->addItem((new CLabel(_('Expressions'), 'regular-expressions-table'))->setAsteriskMark())
		->addItem((new CFormField())
			->addItem((new CDiv($table))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
				->setAttribute('data-field-type', 'set')
				->setAttribute('data-field-name', 'expressions')
			)
		)
	)
	->addTab('test', _('Test'), (new CFormGrid())
		->addItem(new CLabel(_('Test string')))
		->addItem((new CFormField())
			->addItem((new CTextArea('test_string', $data['regexp']['test_string']))
				->setMaxlength(DB::getFieldLength('regexps', 'test_string'))
				->setId('test-string')
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->disableSpellcheck()
			)
		)
		->addItem([
			null,
			new CFormField((new CButton('test-expression', _('Test expressions')))->addClass(ZBX_STYLE_BTN_ALT))
		])
		->addItem(new CLabel(_('Result')))
		->addItem((new CFormField())
			->addItem((new CDiv())
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
				->addItem((new CTable())
					->setHeader([_('Expression type'), _('Expression'), _('Result')])
					->setId('test-result-table')
					->setAttribute('style', 'width: 100%;')
				)
			)
		)
	)
	->setFooter($data['regexp']['regexpid'] != 0
		? makeFormFooter(new CSubmit('update', _('Update')), [
			(new CSimpleButton(_('Clone')))->setId('clone'),
			(new CRedirectButton(_('Delete'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'regex.delete')
					->setArgument('regexpids', (array) $data['regexp']['regexpid'])
					->setArgument(CSRF_TOKEN_NAME, $csrf_token),
				_('Delete regular expression?')
			))->setId('delete'),
			(new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
				->setArgument('action', 'regex.list')
			))->setId('cancel')
		])
		: makeFormFooter(new CSubmit('add', _('Add')), [
			(new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
				->setArgument('action', 'regex.list')
			))->setId('cancel')
		])
	)
	->setSelected(0);

$form
	->addItem($tabs)
	->addItem((new CTemplateTag('row-expression-template'))
		->addItem(getExpressionEntryView('#{index}', '0', '#{type}', '#{expression}', '#{delimiter}',
			$options_delimiter, $options_expression_type
		))
		->addItem(getExpressionEntryErrorView('#{index}'))
	)
	->addItem((new CTemplateTag('combined-result-template'))
		->addItem((new CRow())
			->addClass('js-expression-result-row')
			->addItem((new CCol(_('Combined result')))->setColspan(2))
			->addItem((new CSpan('#{result}'))->addClass('#{result_class}'))
	))
	->addItem((new CTemplateTag('result-row-template'))
		->addItem((new CRow())
			->addClass('js-expression-result-row')
			->addItem(new CCol('#{type}'))
			->addItem(new CCol('#{expression}'))
			->addItem(new CCol((new CSpan('#{result}'))->addClass('#{result_class}')))
	))
	->addItem(
		(new CScriptTag('regular_expression_edit.init('.json_encode([
			'rules' => $data['js_validation_rules'],
		]).');'))->setOnDocumentReady()
	);

(new CHtmlPage())
	->setTitle(_('Regular expressions'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_REGEX_EDIT))
	->addItem($form)
	->show();

function getExpressionEntryView(string $index, string $case_sensitive, string $type, string $expression,
		string $delimiter, array $options_delimiter, array $options_expression_type): CRow {

	return (new CRow())
		->addItem((new CSelect('expressions['.$index.'][expression_type]'))
			->setId('expressions_'.$index.'_expression_type')
			->addClass('js-expression-type-select')
			->addOptions($options_expression_type)
			->setValue($type)
			->setErrorContainer('expressions-'.$index.'-error-container')
			->setErrorLabel(_('Expression type'))
		)
		->addItem((new CTextBox('expressions['.$index.'][expression]', $expression, false,
			DB::getFieldLength('expressions', 'expression'))
		)
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAriaRequired()
			->setErrorContainer('expressions-'.$index.'-error-container')
			->setErrorLabel(_('Expression'))
		)
		->addItem((new CSelect('expressions['.$index.'][exp_delimiter]'))
			->setValue($delimiter)
			->setId('expressions_'.$index.'_exp_delimiter')
			->addClass('js-expression-delimiter-select')
			->addOptions($options_delimiter)
			->addClass($type != EXPRESSION_TYPE_ANY_INCLUDED ? ZBX_STYLE_DISPLAY_NONE : null)
			->setDisabled($type != EXPRESSION_TYPE_ANY_INCLUDED)
			->setErrorContainer('expressions-'.$index.'-error-container')
			->setErrorLabel(_('Delimiter'))
		)
		->addItem((new CCheckBox('expressions['.$index.'][case_sensitive]'))
			->setChecked($case_sensitive == 1)
			->setUncheckedValue('0')
			->setErrorContainer('expressions-'.$index.'-error-container')
			->setErrorLabel(_('Case sensitive'))
		)
		->addItem((new CCol())
			->addClass(ZBX_STYLE_NOWRAP)
			->addItem((new CButton('remove', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
				->removeId()
			)
		);
}

function getExpressionEntryErrorView(string $index): CRow {
	return (new CRow())->addItem((new CCol())
		->setId('expressions-'.$index.'-error-container')
		->addClass(ZBX_STYLE_ERROR_CONTAINER)
		->setAttribute('colspan', 5));
}

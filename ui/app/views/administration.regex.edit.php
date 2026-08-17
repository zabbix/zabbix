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

$csrf_token = CCsrfTokenHelper::get('regex');

$form = (new CForm('post'))
	->addItem((new CVar(CSRF_TOKEN_NAME, $csrf_token))->removeId())
	->setId('regexp-form')
	->setName('regexp_form')
	->addItem(getMessages());

if ($data['regexp']['regexpid'] != 0) {
	$form->addVar('regexpid', $data['regexp']['regexpid']);
}

$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$table = (new CTable())
	->setId('regular-expressions-table')
	->setHeader([
		_('Expression type'),
		_('Expression'),
		_('Delimiter'),
		_('Case sensitive'),
		_('Test result'),
		''
	]);

$options_delimiter = CSelect::createOptionsFromArray(CRegexHelper::expressionDelimiters());
$options_expression_type = CSelect::createOptionsFromArray(CRegexHelper::expression_type2str());

foreach ($data['regexp']['expressions'] as $index => $expression) {
	$table
		->addItem(new CPartial('administration.regex.entry', [
			'index' => $index,
			'case_sensitive' => $expression['case_sensitive'],
			'type' => $expression['expression_type'],
			'expression' => $expression['expression'],
			'delimiter' => $expression['exp_delimiter'],
			'options_delimiter' => $options_delimiter,
			'options_expression_type' => $options_expression_type
		]));
}

$table->addRow((new CRow((new CCol(
	(new CButton('add', _('Add')))->addClass(ZBX_STYLE_BTN_LINK)->addClass('js-add')->removeId()
))->setColSpan(6)))->setId('expression-list-footer'));

$form_grid = (new CFormGrid())
	->addItem((new CLabel(_('Name'), 'name'))->setAsteriskMark())
	->addItem((new CFormField())
		->addItem((new CTextAreaFlexible('name', $data['regexp']['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxlength(DB::getFieldLength('regexps', 'name'))
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
	->addItem(new CLabel(_('Description')))
	->addItem((new CFormField())
		->addItem((new CTextArea('description', $data['regexp']['description']))
			->setMaxlength(DB::getFieldLength('regexps', 'description'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->disableSpellcheck()
		)
	)
	->addItem(new CLabel(_('Test expression')))
	->addItem((new CFormField())
		->addItem((new CTextArea('test_string', $data['regexp']['test_string']))
			->setMaxlength(DB::getFieldLength('regexps', 'test_string'))
			->setId('test-string')
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->disableSpellcheck()
			->setAttribute('data-notrim', '')
		)
	)
	->addItem([
		new CLabel(_('Combined result')),
		new CFormField((new CSpan())->setId('test-result-combined'))
	])
	->addItem((new CTemplateTag('row-expression-template'))
		->addItem(new CPartial('administration.regex.entry', [
			'index' => '#{index}',
			'case_sensitive' => '0',
			'type' => '#{type}',
			'expression' => '#{expression}',
			'delimiter' => '#{delimiter}',
			'options_delimiter' => $options_delimiter,
			'options_expression_type' => $options_expression_type
		]))
	);

$form->addItem($form_grid);

if ($data['regexp']['regexpid'] != 0) {
	$title = _('Regular expression');
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-submit',
			'keepOpen' => true,
			'isSubmit' => true
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'isSubmit' => false
		],
		[
			'title' => _('Test'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-test']),
			'keepOpen' => true,
			'isSubmit' => false
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete selected regular expression?'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
			'keepOpen' => true,
			'isSubmit' => false
		]
	];
}
else {
	$title = _('New regular expression');
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-submit',
			'keepOpen' => true,
			'isSubmit' => true
		],
		[
			'title' => _('Test'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-test']),
			'keepOpen' => true,
			'isSubmit' => false
		]
	];
}

$output = [
	'header' => $title,
	'doc_url' => CDocHelper::getUrl(CDocHelper::ADMINISTRATION_REGEX_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('administration.regex.edit.js.php').
		'regex_edit_popup.init('.json_encode([
			'rules' => $data['js_validation_rules'],
			'clone_rules' => $data['js_clone_validation_rules']
		]).');',
	'dialogue_class' => 'modal-popup-large'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

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
	->setId('webscenario-step-form')
	->setName('webscenario_step_form')
	->addVar('edit', $data['is_edit'] ? '1' : null)
	->addVar('templated', (int) $data['templated'])
	->addVar('httpstepid', $data['httpstepid'])
	->addVar('old_name', $data['form']['name'])
	->addVar('names', $data['names'])
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['form']['name'], $data['templated'], DB::getFieldLength('httpstep', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
		)
	])
	->addItem([
		(new CLabel(_('URL'), 'url'))->setAsteriskMark(),
		new CFormField([
			(new CTextBox('url', $data['form']['url'], false, DB::getFieldLength('httpstep', 'url')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CSimpleButton(_('Parse')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->addClass('js-parse-url')
		])
	])
	->addItem([
		new CLabel(_('Query fields')),
		new CFormField(
			(new CDiv([
				(new CTable())
					->setId('step-query-fields')
					->setHeader([(new CColHeader())->setWidth(12), _('Name'), '', _('Value'), ''])
					->setFooter(
						(new CCol(
							(new CButtonLink(_('Add')))->addClass('element-table-add')
						))->setColSpan(5)
					),
				(new CTemplateTag('step-query-field-row-tmpl'))->addItem(
					(new CRow([
						(new CCol(
							(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
						))->addClass(ZBX_STYLE_TD_DRAG_ICON),
						(new CTextAreaFlexible('query_fields[#{rowNum}][name]', '#{name}', ['add_post_js' => false]))
							->removeId()
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH)
							->setAttribute('placeholder', _('name'))
							->disableSpellcheck(),
						RARR(),
						(new CTextAreaFlexible('query_fields[#{rowNum}][value]', '#{value}', ['add_post_js' => false]))
							->removeId()
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH)
							->setAttribute('placeholder', _('value'))
							->disableSpellcheck(),
						(new CCol(
							(new CButtonLink(_('Remove')))->addClass('element-table-remove')
						))->addClass(ZBX_STYLE_NOWRAP)
					]))->addClass('form_row')
				)
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
		)
	])
	->addItem([
		new CLabel(_('Post type'), 'post_type'),
		new CFormField(
			(new CRadioButtonList('post_type', $data['form']['post_type']))
				->addValue(_('Form data'), ZBX_POSTTYPE_FORM)
				->addValue(_('Raw data'), ZBX_POSTTYPE_RAW)
				->setModern()
		)
	])
	->addItem([
		(new CLabel(_('Post fields')))->addClass('js-field-post-fields'),
		(new CFormField(
			(new CDiv([
				(new CTable())
					->setId('step-post-fields')
					->setHeader([(new CColHeader())->setWidth(12), _('Name'), '', _('Value'), ''])
					->setFooter(
						(new CCol(
							(new CButtonLink(_('Add')))->addClass('element-table-add')
						))->setColSpan(5)
					),
				(new CTemplateTag('step-post-field-row-tmpl'))->addItem(
					(new CRow([
						(new CCol(
							(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
						))->addClass(ZBX_STYLE_TD_DRAG_ICON),
						(new CTextAreaFlexible('post_fields[#{rowNum}][name]', '#{name}', ['add_post_js' => false]))
							->removeId()
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH)
							->setAttribute('placeholder', _('name'))
							->disableSpellcheck(),
						RARR(),
						(new CTextAreaFlexible('post_fields[#{rowNum}][value]', '#{value}', ['add_post_js' => false]))
							->removeId()
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH)
							->setMaxlength(DB::getFieldLength('httpstep_field', 'value'))
							->setAttribute('placeholder', _('value'))
							->disableSpellcheck(),
						(new CCol(
							(new CButtonLink(_('Remove')))->addClass('element-table-remove')
						))->addClass(ZBX_STYLE_NOWRAP)
					]))->addClass('form_row')
				)
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
		))->addClass('js-field-post-fields')
	])
	->addItem([
		(new CLabel(_('Raw post'), 'posts'))->addClass('js-field-posts'),
		(new CFormField(
			(new CTextArea('posts', $data['form']['posts']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setMaxlength(DB::getFieldLength('httpstep', 'posts'))
				->disableSpellcheck()
		))->addClass('js-field-posts')
	])
	->addItem([
		new CLabel(_('Variables')),
		new CFormField(
			(new CDiv([
				(new CTable())
					->setId('step-variables')
					->setHeader([(new CColHeader())->setWidth(12), _('Name'), '', _('Value'), ''])
					->setFooter(
						(new CCol(
							(new CButtonLink(_('Add')))->addClass('element-table-add')
						))->setColSpan(5)
					),
				(new CTemplateTag('step-variable-row-tmpl'))->addItem(
					(new CRow([
						'',
						(new CTextAreaFlexible('variables[#{rowNum}][name]', '#{name}', ['add_post_js' => false]))
							->removeId()
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH)
							->setAttribute('placeholder', _('name'))
							->disableSpellcheck(),
						RARR(),
						(new CTextAreaFlexible('variables[#{rowNum}][value]', '#{value}', ['add_post_js' => false]))
							->removeId()
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH)
							->setMaxlength(DB::getFieldLength('httpstep_field', 'value'))
							->setAttribute('placeholder', _('value'))
							->disableSpellcheck(),
						(new CCol(
							(new CButtonLink(_('Remove')))->addClass('element-table-remove')
						))->addClass(ZBX_STYLE_NOWRAP)
					]))->addClass('form_row')
				)
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
		)
	])
	->addItem([
		new CLabel(_('Headers')),
		new CFormField(
			(new CDiv([
				(new CTable())
					->setId('step-headers')
					->setHeader([(new CColHeader())->setWidth(12), _('Name'), '', _('Value'), ''])
					->setFooter(
						(new CCol(
							(new CButtonLink(_('Add')))->addClass('element-table-add')
						))->setColSpan(5)
					),
				(new CTemplateTag('step-header-row-tmpl'))->addItem(
					(new CRow([
						(new CCol(
							(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
						))->addClass(ZBX_STYLE_TD_DRAG_ICON),
						(new CTextAreaFlexible('headers[#{rowNum}][name]', '#{name}', ['add_post_js' => false]))
							->removeId()
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH)
							->setAttribute('placeholder', _('name'))
							->disableSpellcheck(),
						RARR(),
						(new CTextAreaFlexible('headers[#{rowNum}][value]', '#{value}', ['add_post_js' => false]))
							->removeId()
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH)
							->setMaxlength(DB::getFieldLength('httpstep_field', 'value'))
							->setAttribute('placeholder', _('value'))
							->disableSpellcheck(),
						(new CCol(
							(new CButtonLink(_('Remove')))->addClass('element-table-remove')
						))->addClass(ZBX_STYLE_NOWRAP)
					]))->addClass('form_row')
				)
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
		)
	])
	->addItem([
		new CLabel(_('Follow redirects'), 'follow_redirects'),
		new CFormField(
			(new CCheckBox('follow_redirects'))
				->setChecked($data['form']['follow_redirects'] == HTTPTEST_STEP_FOLLOW_REDIRECTS_ON)
		)
	])
	->addItem([
		new CLabel(_('Retrieve mode')),
		new CFormField(
			(new CRadioButtonList('retrieve_mode', $data['form']['retrieve_mode']))
				->addValue(_('Body'), HTTPTEST_STEP_RETRIEVE_MODE_CONTENT)
				->addValue(_('Headers'), HTTPTEST_STEP_RETRIEVE_MODE_HEADERS)
				->addValue(_('Body and headers'), HTTPTEST_STEP_RETRIEVE_MODE_BOTH)
				->setModern()
		)
	])
	->addItem([
		(new CLabel(_('Timeout'), 'timeout'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('timeout', $data['form']['timeout'], false, DB::getFieldLength('httpstep', 'timeout')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		new CLabel(_('Required string'), 'required'),
		new CFormField(
			(new CTextBox('required', $data['form']['required'], false, DB::getFieldLength('httpstep', 'required')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('placeholder', _('pattern'))
		)
	])
	->addItem([
		new CLabel(_('Required status codes'), 'status_codes'),
		new CFormField(
			(new CTextBox('status_codes', $data['form']['status_codes'], false,
				DB::getFieldLength('httpstep', 'status_codes')
			))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
	]);

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('
			webscenario_step_edit_popup.init('.json_encode([
				'query_fields' => $data['form']['query_fields'],
				'post_fields' => $data['form']['post_fields'],
				'variables' => $data['form']['variables'],
				'headers' => $data['form']['headers']
			]).');
		'))->setOnDocumentReady()
	);

$output = [
	'header' => $data['is_edit'] ? _('Step of web scenario') : _('New step of web scenario'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::POPUP_HTTP_STEP_EDIT),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => $data['is_edit'] ? _('Update') : _('Add'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'webscenario_step_edit_popup.submit();'
		]
	],
	'script_inline' => getPagePostJs().
		$this->readJsFile('webscenario.step.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

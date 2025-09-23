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

$form = (new CForm('post'))
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('service')))->removeId())
	->setId('service-form')
	->setName('service_form')
	->addItem(getMessages())
	->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN))
	->addItem((new CInput('hidden', 'serviceid', $data['serviceid']))->setAttribute('data-field-type', 'hidden'));

// Service tab.
$service_tab = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['form']['name'], false, DB::getFieldLength('services', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
		)
	])
	->addItem([
		new CLabel(_('Parent services'), 'parent_serviceids__ms'),
		new CFormField(
			(new CMultiSelect([
				'name' => 'parent_serviceids[]',
				'object_name' => 'services',
				'data' => CArrayHelper::renameObjectsKeys($data['form']['parents'], ['serviceid' => 'id']),
				'custom_select' => true
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
	])
	->addItem([
		new CLabel(_('Problem tags')),
		(new CFormField(
			(new CDiv([
				(new CTable())
					->setId('problem_tags')
					->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
					->setHeader(
						(new CRowHeader([_('Name'), _('Operation'), _('Value'), '']))
							->addClass(ZBX_STYLE_GREY)
					)
					->setFooter(
						(new CCol(
							(new CButtonLink(_('Add')))->addClass('element-table-add')
						))
					),
				(new CTemplateTag('problem-tag-row-tmpl'))
					->addItem([
						(new CRow([
							(new CTextBox('problem_tags[#{rowNum}][tag]', '#{tag}', false,
								DB::getFieldLength('service_problem_tag', 'tag')
							))
								->addClass('js-problem-tag-input')
								->addClass('js-problem-tag-tag')
								->setAttribute('placeholder', _('tag'))
								->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
								->setErrorContainer('problem_tags_#{rowNum}_error_container'),
							(new CSelect('problem_tags[#{rowNum}][operator]'))
								->addClass('js-problem-tag-input')
								->addOptions(CSelect::createOptionsFromArray([
									ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL => _('Equals'),
									ZBX_SERVICE_PROBLEM_TAG_OPERATOR_LIKE => _('Contains')
								]))
								->setValue(ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL),
							(new CTextBox('problem_tags[#{rowNum}][value]', '#{value}', false,
								DB::getFieldLength('service_problem_tag', 'value')
							))
								->addClass('js-problem-tag-input')
								->setAttribute('placeholder', _('value'))
								->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
							(new CButtonLink(_('Remove')))->addClass('element-table-remove')
						]))->addClass('form_row'),
						(new CRow([
							(new CCol())
								->setId('problem_tags_#{rowNum}_error_container')
								->addClass(ZBX_STYLE_ERROR_CONTAINER)
								->setColSpan(3)
						]))->addClass('error-container-row')
					])
			]))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		))
			->setAttribute('data-field-type', 'set')
			->setAttribute('data-field-name', 'problem_tags')
	])
	->addItem([
		(new CLabel(_('Sort order (0->999)'), 'sortorder'))->setAsteriskMark(),
		new CFormField(
			(new CNumericBox('sortorder', $data['form']['sortorder'], 3, allow_negative: false))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		new CLabel([
			_('Status calculation rule'),
			(new CSpan(
				makeWarningIcon(
					_('Status calculation rule and additional rules are only applicable if child services exist.')
				)
			))
				->setId('algorithm-not-applicable-warning')
				->addStyle($data['form']['children'] ? 'display: none' : '')
		], 'algorithm_focusable'),
		new CFormField(
			(new CSelect('algorithm'))
				->setId('algorithm')
				->setFocusableElementId('algorithm_focusable')
				->setValue($data['form']['algorithm'])
				->addOptions(CSelect::createOptionsFromArray(CServiceHelper::getAlgorithmNames()))
		)
	])
	->addItem([
		new CLabel(_('Description'), 'description'),
		new CFormField(
			(new CTextArea('description', $data['form']['description']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setMaxlength(DB::getFieldLength('services', 'description'))
		)
	])
	->addItem(
		$data['serviceid'] !== null
			? [
				new CLabel(_('Created at'), 'created_at'),
				new CFormField(
					(new CTextBox('created_at', zbx_date2str(DATE_FORMAT, $data['form']['created_at'])))
						->setEnabled(false)
						->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				)
			]
			: null
	);

$additional_rules = (new CTable())
	->setId('status_rules')
	->setHeader(
		(new CRowHeader([_('Name'), _('Actions')]))->addClass(ZBX_STYLE_GREY)
	)
	->addItem(
		(new CTag('tfoot', true))
			->addItem(
				(new CCol(
					(new CButtonLink(_('Add')))->addClass('js-add')
				))->setColSpan(2)
		)
	);

$additional_rules_row = (new CTemplateTag('status-rule-tmpl'))->addItem([
	(new CRow([
		new CCol([
			new CSpan('#{*name}'),
			(new CInput('hidden'))
				->setId('status_rules_#{row_index}_new_status')
				->setAttribute('name', 'status_rules[#{row_index}][new_status]')
				->setAttribute('value', '#{new_status}')
				->setAttribute('data-field-type', 'hidden'),
			(new CInput('hidden'))
				->setId('status_rules_#{row_index}_type')
				->setAttribute('name', 'status_rules[#{row_index}][type]')
				->setAttribute('value', '#{type}')
				->setAttribute('data-field-type', 'hidden')
				->setErrorContainer('additional_rules_error_container_#{row_index}'),
			(new CInput('hidden'))
				->setId('status_rules_#{row_index}_limit_value')
				->setAttribute('name', 'status_rules[#{row_index}][limit_value]')
				->setAttribute('value', '#{limit_value}')
				->setAttribute('data-field-type', 'hidden')
				->setErrorContainer('additional_rules_error_container_#{row_index}'),
			(new CInput('hidden'))
				->setId('status_rules_#{row_index}_limit_status')
				->setAttribute('name', 'status_rules[#{row_index}][limit_status]')
				->setAttribute('value', '#{limit_status}')
				->setAttribute('data-field-type', 'hidden')
				->setErrorContainer('additional_rules_error_container_#{row_index}')
		]),
		new CCol([
			(new CList([
				(new CButton('edit', _('Edit')))
					->addClass('js-edit')
					->addClass(ZBX_STYLE_BTN_LINK)
					->removeId(),
				(new CButton('remove', _('Remove')))
					->addClass('js-remove')
					->addClass(ZBX_STYLE_BTN_LINK)
					->removeId()
			]))->addClass(ZBX_STYLE_HOR_LIST)
		])
	]))->setAttribute('data-row_index', '#{row_index}'),
	(new CRow([
		(new CCol())
			->setId('additional_rules_error_container_#{row_index}')
			->addClass(ZBX_STYLE_ERROR_CONTAINER)
			->setColSpan(4)
	]))->addClass('error-container-row')
]);

$propagation_value_number = (new CRadioButtonList('propagation_value_number',
	$data['form']['propagation_value_number'] !== null ? (int) $data['form']['propagation_value_number'] : null
))
	->setId('propagation_value_number')
	->setModern();

foreach (range(1, TRIGGER_SEVERITY_COUNT - 1) as $value) {
	$propagation_value_number->addValue($value, $value, 'propagation_value_number_'.$value);
}

$propagation_value_status = (new CSeverity('propagation_value_status',
	$data['form']['propagation_value_status'] !== null ? (int) $data['form']['propagation_value_status'] : null
))->addValue(_('OK'), ZBX_SEVERITY_OK, ZBX_STYLE_NORMAL_BG);

$service_tab->addItem(
	(new CFormFieldsetCollapsible(_('Advanced configuration')))
		->setId('advanced-configuration')
		->addItem([
			new CLabel(_('Additional rules')),
			(new CFormField([
				(new CDiv($additional_rules))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->addStyle('min-width: '.(ZBX_TEXTAREA_BIG_WIDTH - 27).'px;')
					->setAttribute('data-field-type', 'set')
					->setAttribute('data-field-name', 'status_rules'),
				(new CDiv())
					->setId('status_rules_error_container')
					->addClass(ZBX_STYLE_ERROR_CONTAINER),
				$additional_rules_row
			]))
		])
		->addItem([
			new CLabel(_('Status propagation rule'), 'propagation_rule_focusable'),
			new CFormField(
				(new CSelect('propagation_rule'))
					->setId('propagation_rule')
					->setFocusableElementId('propagation_rule_focusable')
					->setValue($data['form']['propagation_rule'])
					->addOptions(CSelect::createOptionsFromArray(CServiceHelper::getStatusPropagationNames()))
			)
		])
		->addItem([
			(new CFormField([
				$propagation_value_number,
				$propagation_value_status
			]))->setId('status_propagation_value_field')
		])
		->addItem([
			(new CLabel(_('Weight'), 'weight'))->setAsteriskMark(),
			new CFormField(
				(new CNumericBox('weight', $data['form']['weight'], 7, allow_negative: false))
					->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
					->setAriaRequired()
			)
		])
);

// Tags tab.
$tags_tab = (new CFormGrid())
	->addItem([
		new CLabel(_('Tags')),
		(new CFormField(
			(new CDiv())
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
				->addItem([
					renderTagTable($data['form']['tags'], options: ['has_inline_validation' => true])
						->addClass('tags-table')
						->setHeader((new CRowHeader([_('Name'), _('Value'), '']))->addClass(ZBX_STYLE_GREY)),
					(new CTemplateTag('tag-row-tmpl'))->addItem(
						renderTagTableRow('#{rowNum}', ['tag' => '', 'value' => ''], ['add_post_js' => false,
							'has_inline_validation' => true
						])
					)
				])
		))
			->setAttribute('data-field-type', 'set')
			->setAttribute('data-field-name', 'tags')
	]);

// Child services tab.
$child_services = (new CTable())
	->setId('children')
	->setAttribute('data-tab-indicator', count($data['form']['children']))
	->setHeader(
		(new CRowHeader([
			_('Service'),
			_('Problem tags'),
			_('Action')
		]))->addClass(ZBX_STYLE_GREY)
	)
	->addItem(
		(new CTag('tfoot', true))
			->addItem(
				(new CCol(
					(new CList())
						->addClass(ZBX_STYLE_INLINE_FILTER_FOOTER)
						->addItem(
							(new CButtonLink(_('Add')))->addClass('js-add')
						)
						->addItem(
							(new CListItem(null))->addClass(ZBX_STYLE_INLINE_FILTER_STATS)
						)
				))->setColSpan(3)
			)
	);

$child_services_filter = (new CList())
	->setId('children-filter')
	->addClass(ZBX_STYLE_INLINE_FILTER)
	->addItem(new CLabel(_('Name'), 'children-filter-name'), ZBX_STYLE_INLINE_FILTER_LABEL)
	->addItem(
		(new CTextBox('children-filter-name'))
			->setId('children-filter-name')
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH))
	->addItem(
		(new CSimpleButton(_('Filter')))
			->addClass('js-filter')
	)
	->addItem(
		(new CSimpleButton(_('Reset')))
			->addClass('js-reset')
			->addClass(ZBX_STYLE_BTN_ALT)
	);

$child_service_row = (new CTemplateTag('child-service-tmpl'))->addItem([
	(new CRow([
		(new CCol('#{*name}'))
			->addClass(ZBX_STYLE_WORDWRAP)
			->addStyle('max-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
		(new CCol('#{*problem_tags_html}'))->addClass(ZBX_STYLE_WORDWRAP)->addClass(ZBX_STYLE_TAGS_WRAPPER),
		(new CCol(
			(new CButton('edit', _('Remove')))
				->addClass('js-remove')
				->addClass(ZBX_STYLE_BTN_LINK)
		))
	]))->setAttribute('data-serviceid', '#{serviceid}')
]);

$child_services_tab = [
	(new CFormGrid())
		->addItem(new CFormField($child_services_filter))
		->addItem([
			new CLabel(_('Child services')),
			(new CFormField([
				(new CDiv($child_services))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
				$child_service_row
			]))
				->setAttribute('data-field-type', 'set')
				->setAttribute('data-field-name', 'child-services')
		])
];

$tabs = (new CTabView())
	->setSelected(0)
	->addTab('service-tab', _('Service'), $service_tab)
	->addTab('tags-tab', _('Tags'), $tags_tab, TAB_INDICATOR_TAGS)
	->addTab('child-services-tab', _('Child services'), $child_services_tab, TAB_INDICATOR_CHILD_SERVICES);

$form
	->addItem($tabs)
	->addItem(
		(new CScriptTag('
			service_edit_popup.init('.json_encode([
				'rules' => $data['js_validation_rules'],
				'tabs_id' => $tabs->getId(),
				'serviceid' => $data['serviceid'],
				'children' => $data['form']['children'],
				'children_problem_tags_html' => $data['form']['children_problem_tags_html'],
				'problem_tags' => $data['form']['problem_tags'],
				'status_rules' => $data['form']['status_rules'],
				'search_limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT)
			]).');
		'))->setOnDocumentReady()
	);

if ($data['serviceid'] !== null) {
	$title = _('Service');
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-update',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'service_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'service_edit_popup.clone('.json_encode([
				'title' => _('New service'),
				'buttons' => [
					[
						'title' => _('Add'),
						'class' => 'js-add',
						'keepOpen' => true,
						'isSubmit' => true,
						'action' => 'service_edit_popup.submit();'
					],
					[
						'title' => _('Cancel'),
						'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-cancel']),
						'cancel' => true,
						'action' => ''
					]
				],
				'rules' => (new CFormValidator(CControllerServiceCreate::getValidationRules()))->getRules()
			]).');'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete selected service?'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'service_edit_popup.delete();'
		]
	];
}
else {
	$title = _('New service');
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'service_edit_popup.submit();'
		]
	];
}

$output = [
	'header' => $title,
	'doc_url' => CDocHelper::getUrl(CDocHelper::POPUP_SERVICE_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('service.edit.js.php'),
	'dialogue_class' => 'modal-popup-medium'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

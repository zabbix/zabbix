<?php declare(strict_types = 1);
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
 * @var array $data
 */

$form = (new CForm('post'))
	->setId('service-form')
	->setName('service_form')
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CInput('submit'))->addStyle('display: none;'));

// Service tab.

$parent_services = (new CMultiSelect([
	'name' => 'parent_serviceids[]',
	'object_name' => 'services',
	'data' => CArrayHelper::renameObjectsKeys($data['form']['parents'], ['serviceid' => 'id']),
	'custom_select' => true
]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

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
		new CFormField($parent_services)
	])
	->addItem([
		new CLabel(_('Problem tags')),
		new CFormField(
			(new CDiv([
				(new CTable())
					->setId('problem_tags')
					->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
					->setHeader(
						(new CRowHeader([_('Name'), _('Operation'), _('Value'), _('Action')]))
							->addClass(ZBX_STYLE_GREY)
					)
					->setFooter(
						(new CCol(
							(new CSimpleButton(_('Add')))
								->addClass(ZBX_STYLE_BTN_LINK)
								->addClass('element-table-add')
						))
					),
				(new CScriptTemplate('problem-tag-row-tmpl'))
					->addItem(
						(new CRow([
							(new CTextBox('problem_tags[#{rowNum}][tag]', '#{tag}', false,
								DB::getFieldLength('service_problem_tag', 'tag')
							))
								->addClass('js-problem-tag-input')
								->addClass('js-problem-tag-tag')
								->setAttribute('placeholder', _('tag'))
								->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
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
							(new CSimpleButton(_('Remove')))
								->addClass(ZBX_STYLE_BTN_LINK)
								->addClass('element-table-remove')
						]))->addClass('form_row')
					)
			]))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		)
	])
	->addItem([
		(new CLabel(_('Sort order (0->999)'), 'sortorder'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('sortorder', $data['form']['sortorder'], false, 3))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		new CLabel([
			_('Status calculation rule'),
			(new CSpan([
				' ',
				makeWarningIcon(
					_('Status calculation rule and additional rules are only applicable if child services exist.')
				)
			]))
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
	)
	->addItem(
		(new CFormField(
			(new CCheckBox('advanced_configuration'))
				->setLabel(_('Advanced configuration'))
				->setChecked($data['form']['advanced_configuration'])
		))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1)
	);

$additional_rules = (new CTable())
	->setId('status_rules')
	->setHeader(
		(new CRowHeader([_('Name'), _('Action')]))->addClass(ZBX_STYLE_GREY)
	);

$additional_rules->addItem(
	(new CTag('tfoot', true))
		->addItem(
			(new CCol(
				(new CSimpleButton(_('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('js-add')
			))->setColSpan(2)
		)
);

$service_tab
	->addItem([
		(new CLabel(_('Additional rules')))
			->setId('additional_rules_label')
			->addStyle('display: none;'),
		(new CFormField(
			(new CDiv($additional_rules))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		))
			->setId('additional_rules_field')
			->addStyle('display: none;')
	])
	->addItem([
		(new CLabel(_('Status propagation rule')))
			->setId('status_propagation_rules_label')
			->addStyle('display: none;'),
		(new CFormField(
			(new CSelect('propagation_rule'))
				->setId('propagation_rule')
				->setFocusableElementId('propagation_rule_focusable')
				->setValue($data['form']['propagation_rule'])
				->addOptions(CSelect::createOptionsFromArray(CServiceHelper::getStatusPropagationNames()))
		))
			->setId('status_propagation_rules_field')
			->addStyle('display: none;')
	]);

$propagation_value_number = (new CRadioButtonList('propagation_value_number',
	$data['form']['propagation_value_number'] !== null ? (int) $data['form']['propagation_value_number'] : null
))
	->setId('propagation_value_number')
	->setModern(true);

foreach (range(1, TRIGGER_SEVERITY_COUNT - 1) as $value) {
	$propagation_value_number->addValue($value, $value, 'propagation_value_number_'.$value);
}

$propagation_value_status = (new CSeverity('propagation_value_status',
	$data['form']['propagation_value_status'] !== null ? (int) $data['form']['propagation_value_status'] : null
))->addValue(_('OK'), ZBX_SEVERITY_OK, ZBX_STYLE_NORMAL_BG);

$service_tab
	->addItem([
		(new CFormField([
			$propagation_value_number,
			$propagation_value_status
		]))
			->setId('status_propagation_value_field')
			->addStyle('display: none;')
	])
	->addItem([
		(new CLabel(_('Weight')))
			->setId('weight_label')
			->addStyle('display: none;'),
		(new CFormField(
			(new CTextBox('weight', $data['form']['weight'], false, 7))->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
		))
			->setId('weight_field')
			->addStyle('display: none;')
	]);

// Tags tab.

$tags_tab = (new CFormGrid())
	->addItem([
		new CLabel(_('Tags')),
		new CFormField(
			(new CDiv())
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
				->addItem([
					renderTagTable($data['form']['tags'])
						->setId('tags-table')
						->setHeader((new CRowHeader([_('Name'), _('Value'), _('Action')]))->addClass(ZBX_STYLE_GREY)),
					(new CScriptTemplate('tag-row-tmpl'))
						->addItem(renderTagTableRow('#{rowNum}', '', '', ['add_post_js' => false]))
				])
		)
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
							(new CSimpleButton(_('Add')))
								->addClass(ZBX_STYLE_BTN_LINK)
								->addClass('js-add')
						)
						->addItem(
							(new CListItem(null))
								->addClass(ZBX_STYLE_INLINE_FILTER_STATS)
						)
				))->setColSpan(3)
			)
	);

$child_services_filter = (new CList())
	->setId('children-filter')
	->addClass(ZBX_STYLE_INLINE_FILTER)
	->addItem(new CLabel(_('Name'), 'children-filter-name'), ZBX_STYLE_INLINE_FILTER_LABEL)
	->addItem(
		(new CTextBox(null))
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

$child_services_tab = [
	(new CFormGrid())
		->addItem(new CFormField($child_services_filter))
		->addItem([
			new CLabel(_('Child services')),
			new CFormField(
				(new CDiv($child_services))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
			)
		])
];

$tabs = (new CTabView())
	->setSelected(0)
	->addTab('service-tab', _('Service'), $service_tab)
	->addTab('tags-tab', _('Tags'), $tags_tab, TAB_INDICATOR_TAGS)
	->addTab('child-services-tab', _('Child services'), $child_services_tab, TAB_INDICATOR_CHILD_SERVICES);

// Output.

$form
	->addItem($tabs)
	->addItem(
		(new CScriptTag('
			service_edit_popup.init('.json_encode([
				'tabs_id' => $tabs->getId(),
				'serviceid' => $data['serviceid'],
				'children' => $data['form']['children'],
				'children_problem_tags_html' => $data['form']['children_problem_tags_html'],
				'problem_tags' => $data['form']['problem_tags'],
				'status_rules' => $data['form']['status_rules'],
				'create_url' => (new CUrl('zabbix.php'))
					->setArgument('action', 'service.create')
					->getUrl(),
				'update_url' => (new CUrl('zabbix.php'))
					->setArgument('action', 'service.update')
					->getUrl(),
				'delete_url' => (new CUrl('zabbix.php'))
					->setArgument('action', 'service.delete')
					->getUrl(),
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
				]
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
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.service.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

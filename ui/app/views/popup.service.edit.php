<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

$form = (new CForm())
	->setId('service-form')
	->setName('service-form')
	->addVar('action', $data['form_action'])
	->addVar('serviceid', $data['serviceid'])
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CInput('submit'))->addStyle('display: none;'));

// Service tab.

$parent_services = (new CMultiSelect([
	'name' => 'parent_serviceids[]',
	'object_name' => 'services',
	'data' => CArrayHelper::renameObjectsKeys($data['form']['parents'], ['serviceid' => 'id']),
]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$service_tab = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_FIXED)
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
		new CLabel(_('Status calculation algorithm'), 'algorithm_focusable'),
		new CFormField(
			(new CSelect('algorithm'))
				->setId('algorithm')
				->setFocusableElementId('algorithm_focusable')
				->setValue($data['form']['algorithm'])
				->addOptions(CSelect::createOptionsFromArray(CServiceHelper::getAlgorithmNames()))
		)
	])
	->addItem([
		(new CLabel(_('Problem tags')))->setId('problem_tags_label'),
		(new CFormField())
			->setId('problem_tags_field')
			->addItem([
				(new CDiv())
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->addItem([
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
										->setAttribute('placeholder', _('tag'))
										->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
									(new CSelect('problem_tags[#{rowNum}][operator]'))
										->addOptions(CSelect::createOptionsFromArray([
											SERVICE_TAG_OPERATOR_EQUAL => _('Equals'),
											SERVICE_TAG_OPERATOR_LIKE => _('Contains')
										]))
										->setValue(SERVICE_TAG_OPERATOR_EQUAL),
									(new CTextBox('problem_tags[#{rowNum}][value]', '#{value}', false,
										DB::getFieldLength('service_problem_tag', 'value')
									))
										->setAttribute('placeholder', _('value'))
										->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
									(new CSimpleButton(_('Remove')))
										->addClass(ZBX_STYLE_BTN_LINK)
										->addClass('element-table-remove')
								]))->addClass('form_row')
							)
					])
			])
	])
	->addItem([
		(new CLabel(_('Sort order (0->999)'), 'sortorder'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('sortorder', $data['form']['sortorder'], false, 3))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		)
	]);

// SLA tab.

$times = (new CTable())
	->setId('times')
	->setHeader(
		(new CRowHeader([_('Type'), _('Interval'), _('Note'), _('Action')]))->addClass(ZBX_STYLE_GREY)
	);

foreach ($data['form']['times'] as $row_index => $time) {
	$times->addItem(new CPartial('service.time.row', ['row_index' => $row_index] + $time));
}

$times->addItem(
	(new CTag('tfoot', true))
		->addItem(
			(new CCol(
				(new CSimpleButton(_('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('js-add')
			))->setColSpan(4)
		)
);

$sla_tab = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_FIXED)
	->addItem([
		new CLabel(_('SLA'), 'showsla'),
		new CFormField([
			(new CCheckBox('showsla'))->setChecked($data['form']['showsla'] == SERVICE_SHOW_SLA_ON),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('goodsla', $data['form']['goodsla'], false, 8))->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
		])
	])
	->addItem([
		new CLabel(_('Service times')),
		new CFormField([
			(new CDiv($times))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
		])
	]);

// Tags tab.

$tags_tab = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_FIXED)
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
	->setHeader(
		(new CRowHeader([
			_('Service'),
			_('Status calculation'),
			_('Problem tags'),
			_('Action')
		]))->addClass(ZBX_STYLE_GREY)
	)
	->addItem(
		(new CTag('tfoot', true))
			->addItem(
				(new CCol(
					(new CSimpleButton(_('Add')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('js-add')
				))->setColSpan(4)
			)
	);

$child_services_tab = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_FIXED)
	->addItem([
		new CLabel(_('Child services')),
		new CFormField([
			(new CDiv($child_services))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
		])
	]);

$tabs = (new CTabView())
	->setSelected(0)
	->addTab('service-tab', _('Service'), $service_tab)
	->addTab('sla-tab', _('SLA'), $sla_tab, TAB_INDICATOR_SLA)
	->addTab('tags-tab', _('Tags'), $tags_tab, TAB_INDICATOR_TAGS)
	->addTab('child-services-tab', _('Child services'), $child_services_tab, TAB_INDICATOR_CHILD_SERVICES);

// Output.

$form
	->addItem($tabs)
	->addItem(
		(new CScriptTag('
			service_edit_popup.init('.json_encode([
				'serviceid' => $data['serviceid'],
				'children' => $data['form']['children'],
				'children_problem_tags_html' => $data['form']['children_problem_tags_html'],
				'problem_tags' => $data['form']['problem_tags']
			]).');
		'))->setOnDocumentReady()
	);

$output = [
	'header' => $data['title'],
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => $data['serviceid'] !== null ? _('Update') : _('Add'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'service_edit_popup.submit();'
		]
	],
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.service.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

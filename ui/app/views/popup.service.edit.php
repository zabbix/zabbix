<?php
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
	->addVar('action', ($data['serviceid'] == 0) ? 'popup.service.create' : 'popup.service.update')
	->addVar('triggerid', $data['triggerid'])
	->addItem(
		(new CInput('submit', 'submit'))
			->addStyle('display: none;')
			->removeId()
	);

if ($data['serviceid'] != 0) {
	$form->addVar('serviceid', $data['serviceid']);
}

// Service tab.
$service_form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['name'], false, DB::getFieldLength('services', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
		)
	]);

$parent_services_multiselect = (new CMultiSelect([
	'name' => 'parent_serviceids[]',
	'object_name' => 'services',
	'data' => $data['ms_parent_services'],
	'popup' => [
		'parameters' => [
			'srctbl' => 'services',
			'srcfld1' => 'serviceid',
			'srcfld2' => 'name',
			'dstfrm' => $form->getName(),
			'dstfld1' => 'parent_serviceids_'
		]
	],
	'add_post_js' => false
]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$service_form_grid
	->addItem([
		new CLabel(_('Parent services'), 'parent_serviceids'),
		new CFormField($parent_services_multiselect)
	])
	->addItem([
		new CLabel(_('Status calculation algorithm'), 'label-algorithm'),
		new CFormField(
			(new CSelect('algorithm'))
				->setId('algorithm')
				->setValue($data['algorithm'])
				->addOptions(CSelect::createOptionsFromArray(serviceAlgorithm()))
				->setFocusableElementId('label-algorithm')
		)
	]);

$service_form_grid
	->addItem([
		new CLabel(_('Trigger'), 'trigger'),
		new CFormField([
			(new CTextBox('trigger', $data['trigger'], true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('trigger-btn', _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('return PopUp("popup.generic",'.
					json_encode([
						'srctbl' => 'triggers',
						'srcfld1' => 'triggerid',
						'srcfld2' => 'description',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'triggerid',
						'dstfld2' => 'trigger',
						'real_hosts' => '1',
						'with_triggers' => '1'
					]).', null, this);'
				)
		])
	])
	->addItem([
		(new CLabel(_('Sort order (0->999)'), 'sortorder'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('sortorder', $data['sortorder'], false, 3))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		)
	]);

// SLA tab.
$sla_form_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('SLA'), 'showsla'),
		new CFormField([
			(new CCheckBox('showsla'))->setChecked($data['showsla'] == SERVICE_SHOW_SLA_ON),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('goodsla', $data['goodsla'], false, 8))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setEnabled($data['showsla'] == SERVICE_SHOW_SLA_ON)
		])
	]);

$times_table = (new CTable())
	->setId('times-table')
	->addClass('table-forms')
	->setHeader([_('Type'), _('Interval'), _('Note'), _('Action')])
	->addItem(
		(new CTag('tfoot', true))->addItem(
			(new CCol(
				(new CSimpleButton(_('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('js-add-service-time')
			))->setColSpan(4)
		)
	);

$sla_form_grid->addItem([
	new CLabel(_('Service times')),
	new CFormField([
		(new CDiv($times_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	])
]);

// Child services tab.
$child_services_table = (new CTable())
	->setId('child-services-table')
	->addClass('table-forms')
	->setHeader([_('Services'), _('Trigger'), _('Action')])
	->addItem(
		(new CTag('tfoot', true))->addItem(
			(new CCol(
				(new CSimpleButton(_('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('js-add-service-child')
			))->setColSpan(3)
		)
	);

$child_services_form_grid = (new CFormGrid())->addItem([
	(new CLabel(''))->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_FIXED),
	new CFormField([$child_services_table])
]);

// Append tabs to form.
$tabs = (new CTabView())
	->addTab('service-tab', _('Service'), $service_form_grid)
	->addTab('sla-tab', _('SLA'), $sla_form_grid)
	->addTab('tags-tab', _('Tags'), new CPartial('configuration.tags.tab', [
		'source' => 'service',
		'tags' => $data['tags'],
		'readonly' => false
	]), TAB_INDICATOR_TAGS)
	->addTab('child-services-tab', _('Child services'), $child_services_form_grid)
	->setSelected(0);

$form
	->addItem($tabs)
	->addItem(
		(new CScriptTemplate('service-time-row-tmpl'))->addItem([
			(new CCol([
				new CSpan('#{type_name}'),
				new CInput('hidden', 'times[#{counter}][type]', '#{type}'),
				new CInput('hidden', 'times[#{counter}][ts_from]', '#{ts_from}'),
				new CInput('hidden', 'times[#{counter}][ts_to]', '#{ts_to}'),
				new CInput('hidden', 'times[#{counter}][note]', '#{note}')
			]))
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
				->addStyle('max-width: '.ZBX_TEXTAREA_MEDIUM_WIDTH.'px;'),
			'#{time_from}'.' - '.'#{time_till}',
			(new CCol('#{note}'))
				->addClass(ZBX_STYLE_WORDWRAP)
				->addStyle('max-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
			(new CHorList([
				(new CButton(null, _('Edit')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('js-edit-service-time'),
				(new CButton(null, _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('js-remove-service-time')
			]))->addClass(ZBX_STYLE_NOWRAP)
		])
	);

$output = [
	'header' => $data['title'],
	'body' => (new CDiv([$data['errors'], $form]))->toString(),
	'buttons' => [
		[
			'title' => ($data['serviceid'] == 0) ? _('Add') : _('Update'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return submitService(overlay);'
		]
	]
];

$output['script_inline'] = $parent_services_multiselect->getPostJS();
$output['script_inline'] .= $this->readJsFile('popup.service.edit.js.php');

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

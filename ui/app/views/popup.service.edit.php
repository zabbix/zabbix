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
	->addVar('action', $data['serviceid'] === null ? 'popup.service.create' : 'popup.service.update')
	->addVar('serviceid', $data['serviceid'])
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CInput('submit'))->addStyle('display: none;'));

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
				->addOptions(CSelect::createOptionsFromArray(serviceAlgorithm()))
		)
	])
	->addItem([
		new CLabel(_('Trigger'), 'trigger'),
		new CFormField([
			[
				(new CTextBox('trigger',
					$data['form']['triggerid'] != 0
						? $data['form']['trigger_descriptions'][$data['form']['triggerid']]
						: '',
					true
				))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
				new CVar('triggerid', $data['form']['triggerid'])
			],
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('trigger-button', _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('PopUp("popup.generic",'.json_encode([
					'srctbl' => 'triggers',
					'srcfld1' => 'triggerid',
					'srcfld2' => 'description',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'triggerid',
					'dstfld2' => 'trigger',
					'real_hosts' => '1',
					'with_triggers' => '1'
				]).', null, this);')
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

$child_services = (new CTable())
	->setId('children')
	->setHeader(
		(new CRowHeader([_('Service'), _('Trigger'), _('Action')]))->addClass(ZBX_STYLE_GREY)
	)
	->addItem(
		(new CTag('tfoot', true))
			->addItem(
				(new CCol(
					(new CSimpleButton(_('Add')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('js-add')
				))->setColSpan(3)
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

$tags_tab = new CPartial('configuration.tags.tab', [
	'source' => 'service',
	'tags' => $data['form']['tags'],
	'readonly' => false
]);

$tabs = (new CTabView())
	->setSelected(0)
	->addTab('service-tab', _('Service'), $service_tab)
	->addTab('sla-tab', _('SLA'), $sla_tab)
	->addTab('child-services-tab', _('Child services'), $child_services_tab, TAB_INDICATOR_CHILD_SERVICES)
	->addTab('tags-tab', _('Tags'), $tags_tab, TAB_INDICATOR_TAGS);

$form->addItem($tabs);

$script_inline = getPagePostJs();
$script_inline .= $this->readJsFile('popup.service.edit.js.php');

$output = [
	'header' => $data['title'],
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => $data['serviceid'] === null ? _('Add') : _('Update'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'service_edit_popup.submit();'
		]
	],
	'script_inline' => $script_inline
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

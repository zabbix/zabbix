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
 * @var CPartial $this
 * @var array $data
 */

$data += ['is_discovered_prototype' => false];

$graph_tab = new CFormGrid();

if ($data['is_templated']) {
	$graph_tab->addItem([
		new CLabel(_('Parent graphs')),
		new CFormField($data['templates'])
	]);
}

if ($data['discovered']) {
	$parent_lld = $data['discoveryRule'] ?: $data['discoveryRulePrototype'];

	$graph_tab->addItem([
		new CLabel(_('Discovered by')),
		new CFormField(
			new CLink($parent_lld['name'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'popup')
					->setArgument('popup', 'graph.prototype.edit')
					->setArgument('parent_discoveryid', $data['discoveryData']['lldruleid'])
					->setArgument('graphid', $data['discoveryData']['parent_graphid'])
					->setArgument('context', $data['context'])
			)
		)
	]);
}

$graph_tab
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['name'], $data['readonly'], DB::getFieldLength('graphs', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
		)
	])
	->addItem([
		(new CLabel(_('Width'), 'width'))->setAsteriskMark(),
		new CFormField(
			(new CNumericBox('width', $data['width'], 5, $data['readonly']))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('Height'), 'height'))->setAsteriskMark(),
		new CFormField(
			(new CNumericBox('height', $data['height'], 5, $data['readonly']))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('Graph type'), 'label-graphtype')),
		new CFormField(
			(new CSelect('graphtype'))
				->setId('graphtype')
				->setFocusableElementId('label-graphtype')
				->setValue($data['graphtype'])
				->addOptions(CSelect::createOptionsFromArray(graphType()))
				->setReadonly($data['readonly'])
		)
	])
	->addItem([
		new CLabel(_('Show legend')),
		new CFormField(
			(new CCheckBox('show_legend'))
				->setChecked($data['show_legend'] == 1)
				->setReadonly($data['readonly'])
		)
	])
	->addItem([
		new CLabel(_('Show working time')),
		(new CFormField(
			(new CCheckBox('show_work_period'))
				->setChecked($data['show_work_period'] == 1)
				->setReadonly($data['readonly'])
		))->setId('show_work_period_field')
	])
	->addItem([
		new CLabel(_('Show triggers')),
		(new CFormField(
			(new CCheckbox('show_triggers'))
				->setchecked($data['show_triggers'] == 1)
				->setReadonly($data['readonly'])
		))->setId('show_triggers_field')
	]);

// Percent left.
$percent_left_checkbox = (new CCheckBox('visible[percent_left]'))
	->setChecked(true)
	->addClass('js-toggle-percent')
	->setReadonly($data['readonly']);

if (array_key_exists('visible', $data) && array_key_exists('percent_left', $data['visible'])) {
	$percent_left_checkbox->setChecked(true);
}
elseif($data['percent_left'] == 0) {
	$percent_left_checkbox->setChecked(false);
}

$graph_tab->addItem([
	new CLabel(_('Percentile line (left)')),
	(new CFormField([
		$percent_left_checkbox,
		NBSP(),
		(new CTextBox('percent_left', $data['percent_left'], $data['readonly'], 7))
			->setId('percent_left')
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	]))->setId('percent_left_field')
]);

// Percent right.
$percent_right_checkbox = (new CCheckBox('visible[percent_right]'))
	->setChecked(true)
	->addClass('js-toggle-percent')
	->setReadonly($data['readonly']);

if (array_key_exists('visible', $data) && array_key_exists('percent_right', $data['visible'])) {
	$percent_right_checkbox->setChecked(true);
}
elseif ($data['percent_right'] == 0) {
	$percent_right_checkbox->setChecked(false);
}

$graph_tab->addItem([
	new CLabel(_('Percentile line (right)')),
	(new CFormField([
		$percent_right_checkbox,
		NBSP(),
		(new CTextBox('percent_right', $data['percent_right'], $data['readonly'], 7))
			->setId('percent_right')
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	]))->setId('percent_right_field')
]);

$yaxis_min_type = (new CSelect('ymin_type'))
	->setId('ymin_type')
	->setValue($data['ymin_type'])
	->addOptions(CSelect::createOptionsFromArray([
		GRAPH_YAXIS_TYPE_CALCULATED => _('Calculated'),
		GRAPH_YAXIS_TYPE_FIXED => _('Fixed'),
		GRAPH_YAXIS_TYPE_ITEM_VALUE => _('Item')
	]))
	->setReadonly($data['readonly'])
	->setFocusableElementId('ymin_type_label')
	->addClass('yaxis-select');

$yaxis_min_value = (new CDiv(
	(new CTextBox('yaxismin', $data['yaxismin'], $data['readonly']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
))
	->setId('yaxis_min_value')
	->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);

$ymin_axis_ms_data = [];

if ($data['ymin_itemid'] != 0) {
	if (array_key_exists($data['ymin_itemid'], $data['yaxis_items'])) {
		$ymin_axis_ms_data = [[
			'id' => $data['ymin_itemid'],
			'name' => $data['yaxis_items'][$data['ymin_itemid']]['name'],
			'prefix' => $data['yaxis_items'][$data['ymin_itemid']]['hosts'][0]['name'].NAME_DELIMITER
		]];
	}
	else {
		$ymin_axis_ms_data = [[
			'id' => $data['ymin_itemid'],
			'name' => _('Inaccessible item'),
			'prefix' => ''
		]];
	}
}

$yaxis_min_itemid = (new CDiv(
	(new CMultiSelect([
		'name' => 'ymin_itemid',
		'object_name' => 'items',
		'data' => $ymin_axis_ms_data,
		'multiple' => false,
		'readonly' => $data['readonly'],
		'styles' => [
			'display' => 'inline-flex'
		],
		'popup' => [
			'parameters' => [
				'srctbl' => 'items',
				'srcfld1' => 'itemid',
				'srcfld2' => 'name',
				'dstfrm' => $data['form_name'],
				'dstfld1' => 'ymin_itemid',
				'hostid' => $data['is_template'] ? $data['hostid'] : 0,
				'numeric' => '1',
				'real_hosts' => !$data['is_template']
			]
		]
	]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
))
	->setId('yaxis_min_ms')
	->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);

$yaxis_min_item_prototpye = (new CDiv(
	(new CButton('yaxis_min_prototype', _('Select prototype')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->addClass('js-item-prototype-select')
		->setAttribute('data-dstfld1', 'ymin_itemid')
		->setEnabled(!$data['readonly'])
))
	->setId('yaxis_min_prototype_ms')
	->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);

$graph_tab->addItem([
	(new CLabel(_('Y axis MIN value'), 'ymin_type_label')),
	(new CFormField([
		$yaxis_min_type, $yaxis_min_value, $yaxis_min_itemid, $yaxis_min_item_prototpye
	]))->setId('yaxis_min_field')
]);

$yaxis_max_type = (new CSelect('ymax_type'))
	->setId('ymax_type')
	->setValue($data['ymax_type'])
	->addOptions(CSelect::createOptionsFromArray([
		GRAPH_YAXIS_TYPE_CALCULATED => _('Calculated'),
		GRAPH_YAXIS_TYPE_FIXED => _('Fixed'),
		GRAPH_YAXIS_TYPE_ITEM_VALUE => _('Item')
	]))
	->setReadonly($data['readonly'])
	->setFocusableElementId('ymax_type_label')
	->addClass('yaxis-select');

$yaxis_max_value = (new CDiv(
	(new CTextBox('yaxismax', $data['yaxismax'], $data['readonly']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
))
	->setId('yaxis_max_value')
	->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);

$ymax_axis_ms_data = [];

if ($data['ymax_itemid'] != 0) {
	if (array_key_exists($data['ymax_itemid'], $data['yaxis_items'])) {
		$ymax_axis_ms_data = [[
			'id' => $data['ymax_itemid'],
			'name' => $data['yaxis_items'][$data['ymax_itemid']]['name'],
			'prefix' => $data['yaxis_items'][$data['ymax_itemid']]['hosts'][0]['name'].NAME_DELIMITER
		]];
	}
	else {
		$ymax_axis_ms_data = [[
			'id' => $data['ymax_itemid'],
			'name' => _('Inaccessible item'),
			'prefix' => ''
		]];
	}
}

$yaxis_max_itemid = (new CDiv(
	(new CMultiSelect([
		'name' => 'ymax_itemid',
		'object_name' => 'items',
		'data' => $ymax_axis_ms_data,
		'multiple' => false,
		'readonly' => $data['readonly'],
		'styles' => [
			'display' => 'inline-flex'
		],
		'popup' => [
			'parameters' => [
				'srctbl' => 'items',
				'srcfld1' => 'itemid',
				'srcfld2' => 'name',
				'dstfrm' => $data['form_name'],
				'dstfld1' => 'ymax_itemid',
				'hostid' => $data['is_template'] ? $data['hostid'] : 0,
				'numeric' => '1',
				'real_hosts' => !$data['is_template']
			]
		]
	]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
))
	->setId('yaxis_max_ms')
	->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);

$yaxis_max_item_prototpye = (new CDiv(
	(new CButton('yaxis_max_prototype', _('Select prototype')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->addClass('js-item-prototype-select')
		->setAttribute('data-dstfld1', 'ymax_itemid')
		->setEnabled(!$data['readonly'])
))
	->setId('yaxis_max_prototype_ms')
	->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);

$graph_tab
	->addItem([
		(new CLabel(_('Y axis MAX value'), 'ymax_type_label')),
		(new CFormField([
			$yaxis_max_type, $yaxis_max_value, $yaxis_max_itemid, $yaxis_max_item_prototpye
		]))->setId('yaxis_max_field')
	])
	->addItem([
		new CLabel(_('3D view')),
		(new CFormField(
			(new CCheckBox('show_3d'))
				->setChecked($data['show_3d'] == 1)
				->setReadonly($data['readonly'])
		))->setId('show_3d_field')
	]);

$items_table = (new CTable())
	->setId('items-table')
	->addClass(ZBX_STYLE_LIST_NUMBERED)
	->setColumns([
		(new CTableColumn())->addClass('table-col-handle'),
		(new CTableColumn())->addClass('table-col-no'),
		(new CTableColumn(_('Name')))
			->setId('name-column')
			->addClass('table-col-name'),
		(new CTableColumn(_('Type')))->addClass('table-col-type'),
		(new CTableColumn(_('Function')))->addClass('table-col-function'),
		(new CTableColumn((new CColHeader(_('Draw style')))->addClass(ZBX_STYLE_NOWRAP)))->addClass('table-col-draw-style'),
		(new CTableColumn((new CColHeader(_('Y axis side')))->addClass(ZBX_STYLE_NOWRAP)))->addClass('table-col-y-axis-side'),
		(new CTableColumn(_('Color')))->addClass('table-col-color'),
		$data['readonly'] ? null : (new CTableColumn(''))->addClass('table-col-action')
	]);

$items_table->addRow(
	(new CRow(
		$data['readonly']
			? null
			: (new CCol(
			new CHorList([
				(new CButton('add_item', _('Add')))
					->addClass('js-add-item')
					->addClass(ZBX_STYLE_BTN_LINK),
				array_key_exists('parent_discoveryid', $data) && $data['parent_discoveryid']
					? (new CButton('add_item_prototype', _('Add prototype')))
						->addClass('js-add-item-prototype')
						->addClass(ZBX_STYLE_BTN_LINK)
					: null
			])
		))->setColSpan(8)
	))->setId('item-buttons-row')
);

$graph_item_drawtypes = [];

foreach (graph_item_drawtypes() as $drawtype) {
	$graph_item_drawtypes[$drawtype] = graph_item_drawtype2str($drawtype);
}

$graph_tab->addItem([
	(new CLabel(_('Items'), $items_table->getId()))->setAsteriskMark(),
	(new CDiv($items_table))
		->addClass('graph-items')
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR),
	getItemTemplateNormal($data['readonly'], $graph_item_drawtypes),
	getItemTemplateStacked($data['readonly']),
	getItemTemplatePieAndExploded($data['readonly'])
]);

if (array_key_exists('parent_discoveryid', $data)) {
	$graph_tab->addItem([
		new CLabel(_('Discover')),
		new CFormField(
			(new CCheckBox('discover', ZBX_PROTOTYPE_DISCOVER))
				->setChecked($data['discover'] == ZBX_PROTOTYPE_DISCOVER)
				->setReadonly($data['is_discovered_prototype'])
		)
	]);
}

$graph_tab->show();

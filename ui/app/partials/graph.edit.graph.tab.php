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


$is_templated = (bool) $data['templates'];
$discovered_graph = array_key_exists('flags', $data) && $data['flags'] == ZBX_FLAG_DISCOVERY_CREATED;
$readonly = $is_templated || $discovered_graph;

$graph_tab = new CFormGrid();

if ($is_templated) {
	$graph_tab->addItem([
		new CLabel(_('Parent graphs')),
		new CFormField($data['templates'])
	]);
}

if ($discovered_graph) {
	$graph_tab->addItem([
		new CLabel(_('Discovered by')),
		new CFormField(
			new CLink($data['discoveryRule']['name'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'popup')
					->setArgument('popup', 'graph.prototype.edit')
					->setArgument('parent_discoveryid', $data['discoveryRule']['itemid'])
					->setArgument('graphid', $data['graphDiscovery']['parent_graphid'])
					->setArgument('context', $data['context'])
			)
		)
	]);
}

$graph_tab
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['name'], $readonly, DB::getFieldLength('graphs', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
		)
	])
	->addItem([
		(new CLabel(_('Width'), 'width'))->setAsteriskMark(),
		new CFormField(
			(new CNumericBox('width', $data['width'], 5, $readonly))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('Height'), 'height'))->setAsteriskMark(),
		new CFormField(
			(new CNumericBox('height', $data['height'], 5, $readonly))
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
				->setReadonly($readonly)
		)
	])
	->addItem([
		new CLabel(_('Show legend')),
		new CFormField(
			(new CCheckBox('show_legend'))
				->setChecked($data['show_legend'] == 1)
				->setReadonly($readonly)
		)
	])
	->addItem([
		new CLabel(_('Show working time')),
		(new CFormField(
			(new CCheckBox('show_work_period'))
				->setChecked($data['show_work_period'] == 1)
				->setReadonly($readonly)
		))->setId('show_work_period_field')
	])
	->addItem([
		new CLabel(_('Show triggers')),
		(new CFormField(
			(new CCheckbox('show_triggers'))
				->setchecked($data['show_triggers'] == 1)
				->setReadonly($readonly)
		))->setId('show_triggers_field')
	]);

// Percent left.
$percent_left_checkbox = (new CCheckBox('visible[percent_left]'))
	->setChecked(true)
	->addClass('js-toggle-percent')
	->setReadonly($readonly);

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
		(new CTextBox('percent_left', $data['percent_left'], $readonly, 7))
			->setId('percent_left')
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	]))->setId('percent_left_field')
]);

// Percent right.
$percent_right_checkbox = (new CCheckBox('visible[percent_right]'))
	->setChecked(true)
	->addClass('js-toggle-percent')
	->setReadonly($readonly);

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
		(new CTextBox('percent_right', $data['percent_right'], $readonly, 7))
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
	->setReadonly($readonly)
	->setFocusableElementId('ymin_type_label');

$yaxis_min_value = (new CDiv(
	(new CTextBox('yaxismin', $data['yaxismin'], $readonly))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH))
)
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
		'readonly' => $readonly,
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
	(new CButton('yaxis_main_prototype', _('Select prototype')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->addClass('js-item-prototype-select')
		->setAttribute('data-dstfld1', 'ymin_itemid')
		->setEnabled(!$readonly)
))
	->setId('yaxis_min_prototype_ms')
	->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);

$graph_tab->addItem([
	(new CLabel(_('Y axis MIN value'),'ymin_type_label')),
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
	->setReadonly($readonly)
	->setFocusableElementId('ymax_type_label');

$yaxis_max_value = (new CDiv(
	(new CTextBox('yaxismax', $data['yaxismax'], $readonly))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
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
		'readonly' => $readonly,
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
		->setEnabled(!$readonly)
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
				->setReadonly($readonly)
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
		$readonly ? null : (new CTableColumn(''))->addClass('table-col-action')
	]);

$items_table->addRow(
	(new CRow(
		$readonly
			? null
			: (new CCol(
			new CHorList([
				(new CButton('add_item', _('Add')))
					->addClass('js-add-item')
					->addClass(ZBX_STYLE_BTN_LINK),
				$data['parent_discoveryid']
					? (new CButton('add_item_prototype', _('Add prototype')))
						->addClass('js-add-item-prototype')
						->addClass(ZBX_STYLE_BTN_LINK)
					: null
			])
		))->setColSpan(8)
	))->setId('item-buttons-row')
);

$name_column = new CCol(
	$readonly
		? (new CSpan('#{name}'))->setId('items_#{number}_name')
		: (new CLink('#{name}', 'javascript:void(0);'))->setId('items_#{number}_name')
);

$graph_item_drawtypes = [];

foreach (graph_item_drawtypes() as $drawtype) {
	$graph_item_drawtypes[$drawtype] = graph_item_drawtype2str($drawtype);
}

$item_row_template_normal = (new CTemplateTag('tmpl-item-row-'.GRAPH_TYPE_NORMAL))
	->addItem([
		(new CRow([
			(new CCol([
				$readonly ? null : (new CDiv)->addClass(ZBX_STYLE_DRAG_ICON),
				(new CInput('hidden', 'items[#{number}][gitemid]', '#{gitemid}'))->setId('items_#{number}_gitemid'),
				(new CInput('hidden', 'items[#{number}][itemid]', '#{itemid}'))->setId('items_#{number}_itemid'),
				(new CInput('hidden', 'items[#{number}][sortorder]', '#{sortorder}'))->setId('items_#{number}_sortorder'),
				(new CInput('hidden', 'items[#{number}][flags]', '#{flags}'))->setId('items_#{number}_flags'),
				(new CInput('hidden', 'items[#{number}][type]', GRAPH_ITEM_SIMPLE))->setId('items_#{number}_type'),
				(new CInput('hidden', 'items[#{number}][calc_fnc]', '#{calc_fnc}'))->setId('items_#{number}_calc_fnc'),
				(new CInput('hidden', 'items[#{number}][drawtype]', '#{drawtype}'))->setId('items_#{number}_drawtype'),
				(new CInput('hidden', 'items[#{number}][yaxisside]', '#{yaxisside}'))->setId('items_#{number}_yaxisside')
			]))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			new CCol((new CSpan(':'))->addClass(ZBX_STYLE_LIST_NUMBERED_ITEM)),
			new CCol($readonly
				? (new CSpan('#{name}'))->setId('items_#{number}_name')
				: (new CLink('#{name}', 'javascript:void(0);'))
					->addClass('js-item-name')
					->setId('items_#{number}_name')
			),
			new CCol(
				(new CSelect('items[#{number}][calc_fnc]'))
					->setValue('#{calc_fnc}')
					->addOptions(CSelect::createOptionsFromArray([
						CALC_FNC_ALL => _('all'),
						CALC_FNC_MIN => _('min'),
						CALC_FNC_AVG => _('avg'),
						CALC_FNC_MAX => _('max')
					]))
					->setReadonly($readonly)
			),
			new CCol(
				(new CSelect('items[#{number}][drawtype]'))
					->setValue('#{drawtype}')
					->addOptions(CSelect::createOptionsFromArray($graph_item_drawtypes))
					->setReadonly($readonly)
			),
			new CCol(
				(new CSelect('items[#{number}][yaxisside]'))
					->setValue('#{yaxisside}')
					->addOptions(CSelect::createOptionsFromArray([
						GRAPH_YAXIS_SIDE_LEFT => _('Left'),
						GRAPH_YAXIS_SIDE_RIGHT => _('Right')
					]))
					->setReadonly($readonly)
			),
			new CCol([
				(new CColor('items[#{number}][color]', '#{color}', 'items_#{number}_color'))
					->addClass('js-event-color-picker')
			]),
			$readonly
				? null
				: (new CCol(
				(new CButton('remove', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('js-remove')
					->setAttribute('data-remove', '#{number}')
					->setId('items_#{number}_remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('graph-item')
			->setId('items_#{number}')
	]);

$item_row_template_stacked = (new CTemplateTag('tmpl-item-row-'.GRAPH_TYPE_STACKED))
	->addItem([
		(new CRow([
			(new CCol([
				$readonly ? null : (new CDiv)->addClass(ZBX_STYLE_DRAG_ICON),
				(new CInput('hidden', 'items[#{number}][gitemid]', '#{gitemid}'))->setId('items_#{number}_gitemid'),
				(new CInput('hidden', 'items[#{number}][itemid]', '#{itemid}'))->setId('items_#{number}_itemid'),
				(new CInput('hidden', 'items[#{number}][sortorder]', '#{sortorder}'))->setId('items_#{number}_sortorder'),
				(new CInput('hidden', 'items[#{number}][flags]', '#{flags}'))->setId('items_#{number}_flags'),
				// todo - check if stacked shouldnt be added here:
				(new CInput('hidden', 'items[#{number}][type]', GRAPH_ITEM_SIMPLE))->setId('items_#{number}_type'),
				(new CInput('hidden', 'items[#{number}][calc_fnc]', '#{calc_fnc}'))->setId('items_#{number}_calc_fnc'),
				(new CInput('hidden', 'items[#{number}][drawtype]', '#{drawtype}'))->setId('items_#{number}_drawtype'),
				(new CInput('hidden', 'items[#{number}][yaxisside]', '#{yaxisside}'))->setId('items_#{number}_yaxisside')
			]))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			new CCol((new CSpan(':'))->addClass(ZBX_STYLE_LIST_NUMBERED_ITEM)),
			new CCol($readonly
				? (new CSpan('#{name}'))->setId('items_#{number}_name')
				: (new CLink('#{name}', 'javascript:void(0);'))->setId('items_#{number}_name')
			),
			new CCol(
				(new CSelect('items[#{number}][calc_fnc]'))
					->setValue('#{calc_fnc}')
					->addOptions(CSelect::createOptionsFromArray([
						CALC_FNC_MIN => _('min'),
						CALC_FNC_AVG => _('avg'),
						CALC_FNC_MAX => _('max')
					]))
					->setReadonly($readonly)
			),
			new CCol(
				(new CSelect('items[#{number}][yaxisside]'))
					->setValue('#{yaxisside}')
					->addOptions(CSelect::createOptionsFromArray([
						GRAPH_YAXIS_SIDE_LEFT => _('Left'),
						GRAPH_YAXIS_SIDE_RIGHT => _('Right')
					]))
					->setReadonly($readonly)
			),
			new CCol(
				(new CColor('items[#{number}][color]', '#{color}', 'items_#{number}_color'))
					->appendColorPickerJs(false)
			),
			$readonly
				? null
				: (new CCol(
				(new CButton('remove', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('js-remove')
					->setAttribute('data-remove', '#{number}')
					->setId('items_#{number}_remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('graph-item')
			->setId('items_#{number}')
	]);

$item_row_template_pie = (new CTemplateTag('tmpl-item-row-'.GRAPH_TYPE_PIE))
	->addItem([
		(new CRow([
			(new CCol([
				$readonly ? null : (new CDiv)->addClass(ZBX_STYLE_DRAG_ICON),
				(new CInput('hidden', 'items[#{number}][gitemid]', '#{gitemid}'))->setId('items_#{number}_gitemid'),
				(new CInput('hidden', 'items[#{number}][itemid]', '#{itemid}'))->setId('items_#{number}_itemid'),
				(new CInput('hidden', 'items[#{number}][sortorder]', '#{sortorder}'))->setId('items_#{number}_sortorder'),
				(new CInput('hidden', 'items[#{number}][flags]', '#{flags}'))->setId('items_#{number}_flags'),
				(new CInput('hidden', 'items[#{number}][type]', '#{type}'))->setId('items_#{number}_type'),
				(new CInput('hidden', 'items[#{number}][calc_fnc]', '#{calc_fnc}'))->setId('items_#{number}_calc_fnc'),
				(new CInput('hidden', 'items[#{number}][drawtype]', GRAPH_ITEM_DRAWTYPE_LINE))->setId('items_#{number}_drawtype'),
				(new CInput('hidden', 'items[#{number}][yaxisside]', GRAPH_YAXIS_SIDE_LEFT))->setId('items_#{number}_yaxisside')
			]))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			new CCol((new CSpan(':'))->addClass(ZBX_STYLE_LIST_NUMBERED_ITEM)),
			new CCol($readonly
				? (new CSpan('#{name}'))->setId('items_#{number}_name')
				: (new CLink('#{name}', 'javascript:void(0);'))->setId('items_#{number}_name')
			),
			new CCol(
				(new CSelect('items[#{number}][type]'))
					->setValue('#{type}')
					->addOptions(CSelect::createOptionsFromArray([
						GRAPH_ITEM_SIMPLE =>_('Simple'),
						GRAPH_ITEM_SUM =>_('Graph sum')
					]))
					->setReadonly($readonly)
			),
			new CCol(
				(new CSelect('items[#{number}][calc_fnc]'))
					->setValue('#{calc_fnc}')
					->addOptions(CSelect::createOptionsFromArray([
						CALC_FNC_MIN => _('min'),
						CALC_FNC_AVG => _('avg'),
						CALC_FNC_MAX => _('max'),
						CALC_FNC_LST => _('last')
					]))
					->setReadonly($readonly)
			),
			new CCol(
				(new CColor('items[#{number}][color]', '#{color}', 'items_#{number}_color'))
					->appendColorPickerJs(false)
			),
			$readonly
				? null
				: (new CCol(
				(new CButton('remove', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('js-remove')
					->setAttribute('data-remove', '#{number}')
					->setId('items_#{number}_remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('graph-item')
			->setId('items_#{number}')
	]);

$item_row_template_exploded = (new CTemplateTag('tmpl-item-row-'.GRAPH_TYPE_EXPLODED))
	->addItem([
		(new CRow([
			(new CCol([
				$readonly ? null : (new CDiv)->addClass(ZBX_STYLE_DRAG_ICON),
				(new CInput('hidden', 'items[#{number}][gitemid]', '#{gitemid}'))->setId('items_#{number}_gitemid'),
				(new CInput('hidden', 'items[#{number}][itemid]', '#{itemid}'))->setId('items_#{number}_itemid'),
				(new CInput('hidden', 'items[#{number}][sortorder]', '#{sortorder}'))->setId('items_#{number}_sortorder'),
				(new CInput('hidden', 'items[#{number}][flags]', '#{flags}'))->setId('items_#{number}_flags'),
				(new CInput('hidden', 'items[#{number}][type]', '#{type}'))->setId('items_#{number}_type'),
				(new CInput('hidden', 'items[#{number}][calc_fnc]', '#{calc_fnc}'))->setId('items_#{number}_calc_fnc'),
				(new CInput('hidden', 'items[#{number}][drawtype]', GRAPH_ITEM_DRAWTYPE_LINE))->setId('items_#{number}_drawtype'),
				(new CInput('hidden', 'items[#{number}][yaxisside]', GRAPH_YAXIS_SIDE_LEFT))->setId('items_#{number}_yaxisside')
			]))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			new CCol((new CSpan(':'))->addClass(ZBX_STYLE_LIST_NUMBERED_ITEM)),
			new CCol($readonly
				? (new CSpan('#{name}'))->setId('items_#{number}_name')
				: (new CLink('#{name}', 'javascript:void(0);'))->setId('items_#{number}_name')
			),
			new CCol(
				(new CSelect('items[#{number}][type]'))
					->setValue('#{type}')
					->addOptions(CSelect::createOptionsFromArray([
						GRAPH_ITEM_SIMPLE =>_('Simple'),
						GRAPH_ITEM_SUM =>_('Graph sum')
					]))
					->setReadonly($readonly)
			),
			new CCol(
				(new CSelect('items[#{number}][calc_fnc]'))
					->setValue('#{calc_fnc}')
					->addOptions(CSelect::createOptionsFromArray([
						CALC_FNC_MIN => _('min'),
						CALC_FNC_AVG => _('avg'),
						CALC_FNC_MAX => _('max'),
						CALC_FNC_LST => _('last')
					]))
					->setReadonly($readonly)
			),
			new CCol(
				(new CColor('items[#{number}][color]', '#{color}', 'items_#{number}_color'))
					->appendColorPickerJs(false)
			),
			$readonly
				? null
				: (new CCol(
				(new CButton('remove', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('js-remove')
					->setAttribute('data-remove', '#{number}')
					->setId('items_#{number}_remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('graph-item')
			->setId('items_#{number}')
	]);

$graph_tab->addItem([
	(new CLabel(_('Items'), $items_table->getId()))->setAsteriskMark(),
	(new CDiv($items_table))
		->addStyle('graph-items')
		->addStyle('max-width: 750px')
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR),
	$item_row_template_normal,
	$item_row_template_stacked,
	$item_row_template_pie,
	$item_row_template_exploded
]);

if (array_key_exists('parent_discoveryid', $data)) {
	$graph_tab->addItem([
		new CLabel(_('Discover')),
		new CFormField(
			(new CCheckBox('discover', ZBX_PROTOTYPE_DISCOVER))->setChecked($data['discover'] == ZBX_PROTOTYPE_DISCOVER)
		)
	]);
}

$graph_tab->show();

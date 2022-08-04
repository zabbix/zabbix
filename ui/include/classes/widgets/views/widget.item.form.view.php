<?php declare(strict_types = 0);
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Item value widget.
 *
 * @var CView $this
 * @var array $data
 */

$fields = $data['dialogue']['fields'];

$form = CWidgetHelper::createForm();

$scripts = [$this->readJsFile('../../../include/classes/widgets/views/js/widget.item.form.view.js.php')];

$form_grid = CWidgetHelper::createFormGrid($data['dialogue']['name'], $data['dialogue']['type'],
	$data['dialogue']['view_mode'], $data['known_widget_types'],
	$data['templateid'] === null ? $fields['rf_rate'] : null
);

// Item.
$field_itemid = CWidgetHelper::getItem($fields['itemid'], $data['captions']['ms']['items']['itemid'],
	$form->getName()
);
$form_grid->addItem([
	CWidgetHelper::getMultiselectLabel($fields['itemid']),
	new CFormField($field_itemid)
]);
$scripts[] = $field_itemid->getPostJS();

// Show.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['show']),
	new CFormField(
		CWidgetHelper::getCheckBoxList($fields['show'], [
			WIDGET_ITEM_SHOW_DESCRIPTION => _('Description'),
			WIDGET_ITEM_SHOW_VALUE => _('Value'),
			WIDGET_ITEM_SHOW_TIME => _('Time'),
			WIDGET_ITEM_SHOW_CHANGE_INDICATOR => _('Change indicator')
		], [ZBX_STYLE_COLUMNS, ZBX_STYLE_COLUMNS_2])
	)
]);

// Advanced configuration.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['adv_conf']),
	new CFormField(CWidgetHelper::getCheckBox($fields['adv_conf']))
]);

// Description.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['description'], CFormGrid::ZBX_STYLE_FIELDS_GROUP_LABEL, [
		_('Supported macros:'),
		(new CList([
			'{HOST.*}',
			'{ITEM.*}',
			'{INVENTORY.*}',
			_('User macros')
		]))->addClass(ZBX_STYLE_LIST_DASHED)
	])->addClass('js-row-description'),
	(new CDiv([
		new CFormField(
			CWidgetHelper::getTextArea($fields['description'])
				->setAttribute('maxlength', DB::getFieldLength('widget_field', 'value_str'))
		),

		CWidgetHelper::getLabel($fields['desc_h_pos']),
		new CFormField(CWidgetHelper::getRadioButtonList($fields['desc_h_pos'])),

		CWidgetHelper::getLabel($fields['desc_size']),
		(new CFormField([CWidgetHelper::getIntegerBox($fields['desc_size']), '%']))->addClass('field-size'),

		CWidgetHelper::getLabel($fields['desc_v_pos']),
		new CFormField(CWidgetHelper::getRadioButtonList($fields['desc_v_pos'])),

		CWidgetHelper::getLabel($fields['desc_bold']),
		new CFormField(CWidgetHelper::getCheckBox($fields['desc_bold'])),

		CWidgetHelper::getLabel($fields['desc_color'])->addClass('offset-3'),
		new CFormField(CWidgetHelper::getColor($fields['desc_color'], true))
	]))
		->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP)
		->addClass('fields-group-description')
		->addClass('js-row-description')
]);

// Value.
$form_grid->addItem([
	(new CLabel(_('Value')))
		->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP_LABEL)
		->addClass('js-row-value'),
	(new CDiv([
		CWidgetHelper::getLabel($fields['decimal_places']),
		new CFormField(CWidgetHelper::getIntegerBox($fields['decimal_places'])),

		CWidgetHelper::getLabel($fields['decimal_size']),
		(new CFormField([CWidgetHelper::getIntegerBox($fields['decimal_size']), '%']))->addClass('field-size'),

		new CTag('hr'),

		CWidgetHelper::getLabel($fields['value_h_pos']),
		new CFormField(CWidgetHelper::getRadioButtonList($fields['value_h_pos'])),

		CWidgetHelper::getLabel($fields['value_size']),
		(new CFormField([CWidgetHelper::getIntegerBox($fields['value_size']), '%']))->addClass('field-size'),

		CWidgetHelper::getLabel($fields['value_v_pos']),
		new CFormField(CWidgetHelper::getRadioButtonList($fields['value_v_pos'])),

		CWidgetHelper::getLabel($fields['value_bold']),
		new CFormField(CWidgetHelper::getCheckBox($fields['value_bold'])),

		CWidgetHelper::getLabel($fields['value_color'])->addClass('offset-3'),
		new CFormField(CWidgetHelper::getColor($fields['value_color'], true)),

		new CTag('hr'),

		(new CDiv([
			CWidgetHelper::getCheckBox($fields['units_show']),
			CWidgetHelper::getLabel($fields['units'])
		]))->addClass('units-show'),

		(new CFormField(
			CWidgetHelper::getTextBox($fields['units'])
				->setAttribute('style', '')
				->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH)
		))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID),

		CWidgetHelper::getLabel($fields['units_pos'], null,
			_('Position is ignored for s, uptime and unixtime units.')
		),
		new CFormField(CWidgetHelper::getSelect($fields['units_pos'])),

		CWidgetHelper::getLabel($fields['units_size']),
		(new CFormField([CWidgetHelper::getIntegerBox($fields['units_size']), '%']))->addClass('field-size'),

		CWidgetHelper::getLabel($fields['units_bold'])->addClass('offset-3'),
		new CFormField(CWidgetHelper::getCheckBox($fields['units_bold'])),

		CWidgetHelper::getLabel($fields['units_color'])->addClass('offset-3'),
		new CFormField(CWidgetHelper::getColor($fields['units_color'], true))
	]))
		->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP)
		->addClass('fields-group-value')
		->addClass('js-row-value')
]);

// Time.
$form_grid->addItem([
	(new CLabel(_('Time')))
		->addCLass(CFormGrid::ZBX_STYLE_FIELDS_GROUP_LABEL)
		->addClass('js-row-time'),
	(new CDiv([
		CWidgetHelper::getLabel($fields['time_h_pos']),
		new CFormField(CWidgetHelper::getRadioButtonList($fields['time_h_pos'])),

		CWidgetHelper::getLabel($fields['time_size']),
		(new CFormField([CWidgetHelper::getIntegerBox($fields['time_size']), '%']))->addClass('field-size'),

		CWidgetHelper::getLabel($fields['time_v_pos']),
		new CFormField(CWidgetHelper::getRadioButtonList($fields['time_v_pos'])),

		CWidgetHelper::getLabel($fields['time_bold']),
		new CFormField(CWidgetHelper::getCheckBox($fields['time_bold'])),

		CWidgetHelper::getLabel($fields['time_color'])->addClass('offset-3'),
		new CFormField(CWidgetHelper::getColor($fields['time_color'], true))
	]))
		->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP)
		->addClass('fields-group-time')
		->addClass('js-row-time')
]);

// Change indicator.
$form_grid->addItem([
	(new CLabel(_('Change indicator')))
		->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP_LABEL)
		->addClass('js-row-change-indicator'),
	(new CDiv([
		(new CSvgArrow(['up' => true, 'fill_color' => $fields['up_color']->getValue()]))
			->setId('change-indicator-up')
			->setSize(14, 20),
		new CFormField(CWidgetHelper::getColor($fields['up_color'], true)),

		(new CSvgArrow(['down' => true, 'fill_color' => $fields['down_color']->getValue()]))
			->setId('change-indicator-down')
			->setSize(14, 20),
		new CFormField(CWidgetHelper::getColor($fields['down_color'], true)),

		(new CSvgArrow(['up' => true, 'down' => true, 'fill_color' => $fields['updown_color']->getValue()]))
			->setId('change-indicator-updown')
			->setSize(14, 20),
		new CFormField(CWidgetHelper::getColor($fields['updown_color'], true))
	]))
		->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP)
		->addClass('fields-group-change-indicator')
		->addClass('js-row-change-indicator')
]);

// Background color.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['bg_color'])->addClass('js-row-bg-color'),
	(new CFormField(CWidgetHelper::getColor($fields['bg_color'], true)))->addClass('js-row-bg-color')
]);

// Dynamic item.
if ($data['templateid'] === null) {
	$form_grid->addItem([
		CWidgetHelper::getLabel($fields['dynamic']),
		new CFormField(CWidgetHelper::getCheckBox($fields['dynamic']))
	]);
}

$form->addItem($form_grid);

$scripts[] = '
	widget_item_form.init();
';

return [
	'form' => $form,
	'scripts' => $scripts
];

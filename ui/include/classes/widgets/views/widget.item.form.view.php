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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Single item widget.
 *
 * @var CView $this
 */

$fields = $data['dialogue']['fields'];

$form = CWidgetHelper::createForm();

$rf_rate_field = ($data['templateid'] === null) ? $fields['rf_rate'] : null;

$form_list = CWidgetHelper::createFormList($data['dialogue']['name'], $data['dialogue']['type'],
	$data['dialogue']['view_mode'], $data['known_widget_types'], $rf_rate_field
);

$scripts = [];

$field_itemid = CWidgetHelper::getItem($fields['itemid'], $data['captions']['ms']['items']['itemid'],
	$form->getName()
);
$form_list->addRow(CWidgetHelper::getMultiselectLabel($fields['itemid']), $field_itemid);
$scripts[] = $field_itemid->getPostJS();

$form_list
	->addRow(
		CWidgetHelper::getLabel($fields['show']),
		CWidgetHelper::getCheckBoxList($fields['show'], [
			WIDGET_ITEM_SHOW_DESCRIPTION => _('Description'),
			WIDGET_ITEM_SHOW_VALUE => _('Value'),
			WIDGET_ITEM_SHOW_TIME => _('Time'),
			WIDGET_ITEM_SHOW_CHANGE_INDICATOR => _('Change indicator')
		], [ZBX_STYLE_COLUMNS, ZBX_STYLE_COLUMNS_2])
	)
	->addRow(CWidgetHelper::getLabel($fields['adv_conf']), CWidgetHelper::getCheckBox($fields['adv_conf']))
	->addRow(
		CWidgetHelper::getLabel($fields['description'], ZBX_STYLE_WIDGET_ITEM_LABEL, [
			_('Supported macros:'),
			(new CList([
				'{HOST.*}',
				'{ITEM.*}',
				'{INVENTORY.*}',
				_('User macros')
			]))->addClass(ZBX_STYLE_LIST_DASHED)
		]),
		(new CDiv([
			(new CDiv(
				CWidgetHelper::getTextArea($fields['description'])
					->setAttribute('maxlength', DB::getFieldLength('widget_field', 'value_str'))
			))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['desc_h_pos']),
			(new CDiv(CWidgetHelper::getRadioButtonList($fields['desc_h_pos'])))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['desc_size']),
			(new CDiv([CWidgetHelper::getIntegerBox($fields['desc_size']), '%']))
				->addClass('form-field')
				->addClass('field-size'),

			CWidgetHelper::getLabel($fields['desc_v_pos']),
			(new CDiv(CWidgetHelper::getRadioButtonList($fields['desc_v_pos'])))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['desc_bold']),
			(new CDiv(CWidgetHelper::getCheckBox($fields['desc_bold'])))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['desc_color'])->addClass('offset-3'),
			(new CDiv(CWidgetHelper::getColor($fields['desc_color'], true)))->addClass('form-field')
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addClass('field-group-description'),
		'description-row'
	)
	->addRow(
		(new CLabel(_('Value')))->addClass(ZBX_STYLE_WIDGET_ITEM_LABEL),
		(new CDiv([
			CWidgetHelper::getLabel($fields['decimal_places']),
			(new CDiv(CWidgetHelper::getIntegerBox($fields['decimal_places'])))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['decimal_size']),
			(new CDiv([CWidgetHelper::getIntegerBox($fields['decimal_size']), '%']))
				->addClass('form-field')
				->addClass('field-size'),

			// Divide decimals and value.
			new CTag('hr'),

			CWidgetHelper::getLabel($fields['value_h_pos']),
			(new CDiv(CWidgetHelper::getRadioButtonList($fields['value_h_pos'])))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['value_size']),
			(new CDiv([CWidgetHelper::getIntegerBox($fields['value_size']), '%']))
				->addClass('form-field')
				->addClass('field-size'),

			CWidgetHelper::getLabel($fields['value_v_pos']),
			(new CDiv(CWidgetHelper::getRadioButtonList($fields['value_v_pos'])))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['value_bold']),
			(new CDiv(CWidgetHelper::getCheckBox($fields['value_bold'])))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['value_color'])->addClass('offset-3'),
			(new CDiv(CWidgetHelper::getColor($fields['value_color'], true)))->addClass('form-field'),

			// Divide value and units.
			new CTag('hr'),

			(new CDiv([
				CWidgetHelper::getCheckBox($fields['units_show']),
				CWidgetHelper::getLabel($fields['units'])
			]))->addClass('units-show'),

			(new CDiv(
				CWidgetHelper::getTextBox($fields['units'])
					->setAttribute('style', '')
					->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH)
			))
				->addClass('form-field')
				->addClass('field-fluid'),

			CWidgetHelper::getLabel($fields['units_pos']),
			(new CDiv(CWidgetHelper::getSelect($fields['units_pos'])))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['units_size']),
			(new CDiv([CWidgetHelper::getIntegerBox($fields['units_size']), '%']))
				->addClass('form-field')
				->addClass('field-size'),

			CWidgetHelper::getLabel($fields['units_bold'])->addClass('offset-3'),
			(new CDiv(CWidgetHelper::getCheckBox($fields['units_bold'])))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['units_color'])->addClass('offset-3'),
			(new CDiv(CWidgetHelper::getColor($fields['units_color'], true)))->addClass('form-field')
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addClass('field-group-value'),
		'value-row'
	)
	->addRow(
		(new CLabel(_('Time')))->addCLass(ZBX_STYLE_WIDGET_ITEM_LABEL),
		(new CDiv([
			CWidgetHelper::getLabel($fields['time_h_pos']),
			(new CDiv(CWidgetHelper::getRadioButtonList($fields['time_h_pos'])))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['time_size']),
			(new CDiv([CWidgetHelper::getIntegerBox($fields['time_size']), '%']))
				->addClass('form-field')
				->addClass('field-size'),

			CWidgetHelper::getLabel($fields['time_v_pos']),
			(new CDiv(CWidgetHelper::getRadioButtonList($fields['time_v_pos'])))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['time_bold']),
			(new CDiv(CWidgetHelper::getCheckBox($fields['time_bold'])))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['time_color'])->addClass('offset-3'),
			(new CDiv(CWidgetHelper::getColor($fields['time_color'], true)))->addClass('form-field')
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addClass('field-group-time'),
		'time-row'
	)
	->addRow(
		(new CLabel(_('Change indicator')))->addClass(ZBX_STYLE_WIDGET_ITEM_LABEL),
		(new CDiv([
			(new CSvgArrow(['up' => true, 'fill_color' => $fields['up_color']->getValue()]))
				->setId('change-indicator-up')
				->setSize(14, 20),
			(new CDiv(CWidgetHelper::getColor($fields['up_color'], true)))->addClass('form-field'),

			(new CSvgArrow(['down' => true, 'fill_color' => $fields['down_color']->getValue()]))
				->setId('change-indicator-down')
				->setSize(14, 20),
			(new CDiv(CWidgetHelper::getColor($fields['down_color'], true)))->addClass('form-field'),

			(new CSvgArrow(['up' => true, 'down' => true, 'fill_color' => $fields['updown_color']->getValue()]))
				->setId('change-indicator-updown')
				->setSize(14, 20),
			(new CDiv(CWidgetHelper::getColor($fields['updown_color'], true)))->addClass('form-field')
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addClass('field-group-change-indicator'),
		'change-indicator-row'
	)
	->addRow(
		CWidgetHelper::getLabel($fields['bg_color']),
		(new CDiv(CWidgetHelper::getColor($fields['bg_color'], true)))->addClass('form-field'),
		'bg-color-row'
	);

// Dynamic item.
if ($data['templateid'] === null) {
	$form_list->addRow(CWidgetHelper::getLabel($fields['dynamic']), CWidgetHelper::getCheckBox($fields['dynamic']));
}

$form->addItem($form_list);

// Append color picker to widget body instead of whole HTML body. Process checkboxes and form block visibility.
$scripts[] =
	'window.setIndicatorColor = function (color) {'.
		'const indicator_ids = '.json_encode([
			'up_color' => 'change-indicator-up',
			'down_color' => 'change-indicator-down',
			'updown_color' => 'change-indicator-updown'
		]).';'.

		'if (this.name in indicator_ids) {'.
			'const indicator = document.getElementById(indicator_ids[this.name]);'.
			'indicator.querySelector("polygon").style.fill = (color !== "") ? "#" + color : "";'.
		'}'.
	'};'.

	'$(".'.ZBX_STYLE_COLOR_PICKER.' input", $(".overlay-dialogue-body"))'.
		'.colorpicker({appendTo: ".overlay-dialogue-body", use_default: true, onUpdate: window.setIndicatorColor});'.

	'var $show = $(\'input[id^="show_"]\', "#widget-dialogue-form").not("#show_header");'.

	'$("#adv_conf").change(function() {'.
		'$show.trigger("change");'.

		'$("#bg-color-row")'.
			'.toggle(this.checked)'.
			'.find("input")'.
			'.prop("disabled", !this.checked);'.
	'});'.

	// Prevent unchecking last "Show" checkbox.
	'$show.on("click", () => Boolean($show.filter(":checked").length));'.

	'$show.change(function() {'.
		'let adv_conf_checked = $("#adv_conf").prop("checked");'.

		'switch($(this).val()) {'.
			'case "'.WIDGET_ITEM_SHOW_DESCRIPTION.'":'.
				'$("#description-row")'.
					'.toggle(adv_conf_checked && this.checked)'.
					'.find("input, textarea")'.
					'.prop("disabled", !adv_conf_checked || !this.checked);'.
				'break;'.

			'case "'.WIDGET_ITEM_SHOW_VALUE.'":'.
				'$("#value-row")'.
					'.toggle(adv_conf_checked && this.checked)'.
					'.find("input")'.
					'.prop("disabled", !adv_conf_checked || !this.checked);'.
				'break;'.

			'case "'.WIDGET_ITEM_SHOW_TIME.'":'.
				'$("#time-row")'.
					'.toggle(adv_conf_checked && this.checked)'.
					'.find("input")'.
					'.prop("disabled", !adv_conf_checked || !this.checked);'.
				'break;'.

			'case "'.WIDGET_ITEM_SHOW_CHANGE_INDICATOR.'":'.
				'$("#change-indicator-row")'.
					'.toggle(adv_conf_checked && this.checked)'.
					'.find("input")'.
					'.prop("disabled", !adv_conf_checked || !this.checked);'.
				'break;'.
		'}'.
	'});'.

	'$("#adv_conf").trigger("change");'.

	'$("#units_show").change(function() {'.
		'$("#units, #units_pos, #units_size, #units_bold, #units_color").prop("disabled", !this.checked);'.
	'});'.

	'$("#units_show").trigger("change");';

return [
	'form' => $form,
	'scripts' => $scripts
];

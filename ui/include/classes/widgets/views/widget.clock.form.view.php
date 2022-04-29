<?php
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
 * Clock widget form view.
 */
$fields = $data['dialogue']['fields'];

$form = CWidgetHelper::createForm();

$rf_rate_field = ($data['templateid'] === null) ? $fields['rf_rate'] : null;

$form_list = CWidgetHelper::createFormList($data['dialogue']['name'], $data['dialogue']['type'],
	$data['dialogue']['view_mode'], $data['known_widget_types'], $rf_rate_field
);

$scripts = [];

// Time type.
$form_list->addRow(CWidgetHelper::getLabel($fields['time_type']), CWidgetHelper::getSelect($fields['time_type']));

// Item.
if (array_key_exists('itemid', $fields)) {
	$field_itemid = CWidgetHelper::getItem($fields['itemid'], $data['captions']['ms']['items']['itemid'],
		$form->getName()
	);
	$form_list->addRow(CWidgetHelper::getMultiselectLabel($fields['itemid']), $field_itemid);
	$scripts[] = $field_itemid->getPostJS();
}

$scripts[] = $fields['tzone_timezone']->getJavascript();

// Clock type
$form_list
	->addRow(CWidgetHelper::getLabel($fields['clock_type']),
		(new CDiv(CWidgetHelper::getRadioButtonList($fields['clock_type'])))->addClass('form-field')
	)
	->addRow(
		CWidgetHelper::getLabel($fields['show']),
		CWidgetHelper::getCheckBoxList($fields['show'], [
			WIDGET_CLOCK_SHOW_DATE => _('Date'),
			WIDGET_CLOCK_SHOW_TIME => _('Time'),
			WIDGET_CLOCK_SHOW_TIMEZONE => _('Time zone'),
		]),
		'show-row'
	)
	->addRow(
		CWidgetHelper::getLabel($fields['adv_conf']),
		CWidgetHelper::getCheckBox($fields['adv_conf']),
		'adv-conf-row'
	)
	->addRow(
		CWidgetHelper::getLabel($fields['bg_color']),
		(new CDiv(CWidgetHelper::getColor($fields['bg_color'], true)))->addClass('form-field'),
		'bg-color-row'
	)
	->addRow(
		(new CLabel(_('Date'))),
		(new CDiv([
			CWidgetHelper::getLabel($fields['date_size']),
			(new CDiv([CWidgetHelper::getIntegerBox($fields['date_size']), '%']))
				->addClass('form-field')
				->addClass('field-size'),

			CWidgetHelper::getLabel($fields['date_bold']),
			(new CDiv(CWidgetHelper::getCheckBox($fields['date_bold'])))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['date_color'])->addClass('offset-3'),
			(new CDiv(CWidgetHelper::getColor($fields['date_color'], true)))->addClass('form-field')
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addClass('field-group-date'),
		'date-row'
	)
	->addRow(
		(new CLabel(_('Time'))),
		(new CDiv([
			CWidgetHelper::getLabel($fields['time_size']),
			(new CDiv([CWidgetHelper::getIntegerBox($fields['time_size']), '%']))
				->addClass('form-field')
				->addClass('field-size'),

			CWidgetHelper::getLabel($fields['time_bold']),
			(new CDiv(CWidgetHelper::getCheckBox($fields['time_bold'])))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['time_color'])->addClass('offset-3'),
			(new CDiv(CWidgetHelper::getColor($fields['time_color'], true)))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['time_sec']),
			(new CDiv(CWidgetHelper::getCheckBox($fields['time_sec'])))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['time_format']),
			(new CDiv(CWidgetHelper::getRadioButtonList($fields['time_format'])))
				->addClass('form-field')
				->addClass('field-format')
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addClass('field-group-time'),
		'time-row'
	)
	->addRow(
		(new CLabel(_('Time zone'))),
		(new CDiv([
			CWidgetHelper::getLabel($fields['tzone_size']),
			(new CDiv([CWidgetHelper::getIntegerBox($fields['tzone_size']), '%']))
				->addClass('form-field')
				->addClass('field-size'),

			CWidgetHelper::getLabel($fields['tzone_bold']),
			(new CDiv(CWidgetHelper::getCheckBox($fields['tzone_bold'])))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['tzone_color'])->addClass('offset-3'),
			(new CDiv(CWidgetHelper::getColor($fields['tzone_color'], true)))->addClass('form-field'),

			CWidgetHelper::getLabel($fields['tzone_timezone']),
			(new CDiv(CWidgetHelper::getSelect($fields['tzone_timezone'])))
				->addClass('form-field')
				->addClass('field-timezone'),

			CWidgetHelper::getLabel($fields['tzone_format']),
			(new CDiv(CWidgetHelper::getRadioButtonList($fields['tzone_format'])))
				->addClass('form-field')
				->addClass('field-format')
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addClass('field-group-tzone'),
		'tzone-row'
	);


$scripts[] =
	'$("#time_type").on("change", () => {'.
		'ZABBIX.Dashboard.reloadWidgetProperties();'.
		'toggleTimeZoneFields();'.
	'});'.

	'$(".'.ZBX_STYLE_COLOR_PICKER.' input", $(".overlay-dialogue-body"))'.
		'.colorpicker({appendTo: ".overlay-dialogue-body", use_default: true, onUpdate: window.setIndicatorColor});'.

	'var $show = $(\'input[id^="show_"]\', "#widget-dialogue-form").not("#show_header");'.

	'$("#adv_conf").change(function() {'.
		'$show.trigger("change");'.

		'$("#bg-color-row")'.
			'.toggle(this.checked && isDigital())'.
			'.find("input")'.
			'.prop("disabled", !(this.checked && isDigital()));'.
	'});'.

	'$("#clock_type").on("change", () => {'.
		'toggleShowFields();'.
		'toggleAdvConfField();'.
		'$("#adv_conf").trigger("change");'.
	'});'.

	'function isDigital() {'.
		'return $(\'input[name="clock_type"]:checked\').val() == '.WIDGET_CLOCK_TYPE_DIGITAL.';'.
	'}'.

	'$show.on("click", () => {'.
		'return Boolean($show'.
			'.filter(\''.
				'input[id="show_'.WIDGET_CLOCK_SHOW_DATE.'"]:checked,'.
				'input[id="show_'.WIDGET_CLOCK_SHOW_TIME.'"]:checked\')'.
			'.length);'.
	'});'.

	'$show.change(function() {'.
		'let adv_conf_checked = $("#adv_conf").prop("checked");'.
		'let is_digital = $(\'input[name="clock_type"]:checked\').val() == '.WIDGET_CLOCK_TYPE_DIGITAL.';'.
		'let show_field = (adv_conf_checked && this.checked && is_digital);'.

		'switch($(this).val()) {'.
			'case "'.WIDGET_CLOCK_SHOW_DATE.'":'.
				'$("#date-row")'.
					'.toggle(show_field)'.
					'.find("input, textarea")'.
					'.prop("disabled", !show_field);'.
				'break;'.

			'case "'.WIDGET_CLOCK_SHOW_TIME.'":'.
				'$("#time-row")'.
					'.toggle(show_field)'.
					'.find("input")'.
					'.prop("disabled", !show_field);'.
				'break;'.

			'case "'.WIDGET_CLOCK_SHOW_TIMEZONE.'":'.
				'toggleTimeZoneFields();'.
				'$("#tzone-row")'.
					'.toggle(show_field)'.
					'.find("input")'.
					'.prop("disabled", !show_field);'.
				'break;'.
		'}'.
	'});'.

	'function toggleTimeZoneFields() {'.
		'let adv_conf_checked = $("#adv_conf").prop("checked");'.
		'let is_host_time = $("#time_type").val() == '.TIME_TYPE_HOST.';'.
		'let enabled = $(\'input[id^="show_'.WIDGET_CLOCK_SHOW_TIMEZONE.'"]\').is(":checked");'.

		'$(\'label[for="tzone_timezone"], #tzone_timezone, label[for="tzone_format"], #tzone_format\')'.
			'.toggle(!is_host_time && enabled && adv_conf_checked)'.
			'.find("input")'.
			'.prop("disabled", !(!is_host_time && enabled && adv_conf_checked));'.
	'};'.

	'function toggleShowFields() {'.
		'let enable = isDigital();'.
		'$("#show-row")'.
			'.toggle(enable)'.
			'.find("input")'.
			'.prop("disabled", !enable);'.
	'}'.

	'function toggleAdvConfField() {'.
		'let enable = isDigital();'.
		'$("#adv-conf-row")'.
			'.toggle(enable)'.
			'.find("input")'.
			'.prop("disabled", !enable);'.
	'}'.

	'$("#clock_type").trigger("change");'.
	'$("#adv_conf").trigger("change");';

$form->addItem($form_list);

return [
	'form' => $form,
	'scripts' => $scripts
];

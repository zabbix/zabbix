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

$form->addItem($form_list);

$form->addItem(
	(new CScriptTag('
		widget_clock_form.init('.json_encode([
			'form_id' => $form->getId()
		]).');
	'))->setOnDocumentReady()
);

$scripts = [$this->readJsFile('../../../include/classes/widgets/views/js/widget.clock.form.view.js.php')];

return [
	'form' => $form,
	'scripts' => $scripts
];

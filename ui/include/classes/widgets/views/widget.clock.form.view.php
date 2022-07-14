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
 * Clock widget form view.
 *
 * @var CView $this
 * @var array $data
 */

$fields = $data['dialogue']['fields'];

$form = CWidgetHelper::createForm();

$scripts = [$this->readJsFile('../../../include/classes/widgets/views/js/widget.clock.form.view.js.php')];

$form_grid = CWidgetHelper::createFormGrid($data['dialogue']['name'], $data['dialogue']['type'],
	$data['dialogue']['view_mode'], $data['known_widget_types'],
	$data['templateid'] === null ? $fields['rf_rate'] : null
);

// Time type.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['time_type']),
	new CFormField(CWidgetHelper::getSelect($fields['time_type']))
]);

// Item.
if (array_key_exists('itemid', $fields)) {
	$field_itemid = CWidgetHelper::getItem($fields['itemid'], $data['captions']['ms']['items']['itemid'],
		$form->getName()
	);
	$form_grid->addItem([
		CWidgetHelper::getMultiselectLabel($fields['itemid']),
		new CFormField($field_itemid)
	]);
	$scripts[] = $field_itemid->getPostJS();
}

// Clock type.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['clock_type']),
	new CFormField(CWidgetHelper::getRadioButtonList($fields['clock_type']))
]);

// Show.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['show'])->addClass('js-row-show'),
	(new CFormField(
		CWidgetHelper::getCheckBoxList($fields['show'], [
			WIDGET_CLOCK_SHOW_DATE => _('Date'),
			WIDGET_CLOCK_SHOW_TIME => _('Time'),
			WIDGET_CLOCK_SHOW_TIMEZONE => _('Time zone')
		])
	))->addClass('js-row-show')
]);

// Advanced configuration.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['adv_conf'])->addClass('js-row-adv-conf'),
	(new CFormField(
		CWidgetHelper::getCheckBox($fields['adv_conf'])
	))->addClass('js-row-adv-conf')
]);

// Background color.
$form_grid->addItem([
	CWidgetHelper::getLabel($fields['bg_color'])->addClass('js-row-bg-color'),
	(new CFormField(
		CWidgetHelper::getColor($fields['bg_color'], true)
	))->addClass('js-row-bg-color')
]);

// Date.
$form_grid->addItem([
	(new CLabel(_('Date')))
		->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP_LABEL)
		->addClass('js-row-date'),
	(new CDiv([
		CWidgetHelper::getLabel($fields['date_size']),
		(new CFormField([CWidgetHelper::getIntegerBox($fields['date_size']), '%']))->addClass('field-size'),

		CWidgetHelper::getLabel($fields['date_bold']),
		new CFormField(CWidgetHelper::getCheckBox($fields['date_bold'])),

		CWidgetHelper::getLabel($fields['date_color'])->addClass('offset-3'),
		new CFormField(CWidgetHelper::getColor($fields['date_color'], true))
	]))
		->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP)
		->addClass('fields-group-date')
		->addClass('js-row-date')
]);

// Time.
$form_grid->addItem([
	(new CLabel(_('Time')))
		->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP_LABEL)
		->addClass('js-row-time'),
	(new CDiv([
		CWidgetHelper::getLabel($fields['time_size']),
		(new CFormField([CWidgetHelper::getIntegerBox($fields['time_size']), '%']))->addClass('field-size'),

		CWidgetHelper::getLabel($fields['time_bold']),
		new CFormField(CWidgetHelper::getCheckBox($fields['time_bold'])),

		CWidgetHelper::getLabel($fields['time_color'])->addClass('offset-3'),
		new CFormField(CWidgetHelper::getColor($fields['time_color'], true)),

		CWidgetHelper::getLabel($fields['time_sec']),
		new CFormField(CWidgetHelper::getCheckBox($fields['time_sec'])),

		CWidgetHelper::getLabel($fields['time_format']),
		(new CFormField(CWidgetHelper::getRadioButtonList($fields['time_format'])))->addClass('field-format')
	]))
		->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP)
		->addClass('fields-group-time')
		->addClass('js-row-time')
]);

// Time zone.
$form_grid->addItem([
	(new CLabel(_('Time zone')))
		->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP_LABEL)
		->addClass('js-row-tzone'),
	(new CDiv([
		CWidgetHelper::getLabel($fields['tzone_size']),
		(new CFormField([CWidgetHelper::getIntegerBox($fields['tzone_size']), '%']))->addClass('field-size'),

		CWidgetHelper::getLabel($fields['tzone_bold']),
		new CFormField(CWidgetHelper::getCheckBox($fields['tzone_bold'])),

		CWidgetHelper::getLabel($fields['tzone_color'])->addClass('offset-3'),
		new CFormField(CWidgetHelper::getColor($fields['tzone_color'], true)),

		(CWidgetHelper::getLabel($fields['tzone_timezone']))->addClass('js-row-tzone-timezone'),
		(new CFormField(CWidgetHelper::getSelect($fields['tzone_timezone'])))
			->addClass('field-timezone')
			->addClass('js-row-tzone-timezone'),

		(CWidgetHelper::getLabel($fields['tzone_format']))->addClass('js-row-tzone-format'),
		(new CFormField(CWidgetHelper::getRadioButtonList($fields['tzone_format'])))
			->addClass('field-format')
			->addClass('js-row-tzone-format')
	]))
		->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP)
		->addClass('fields-group-tzone')
		->addClass('js-row-tzone')
]);

$scripts[] = $fields['tzone_timezone']->getJavascript();

$form->addItem($form_grid);

$scripts[] = '
	widget_clock_form.init();
';

return [
	'form' => $form,
	'scripts' => $scripts
];

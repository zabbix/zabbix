<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

$form = (new CWidgetFormView($data));

$form
	->addField(
		new CWidgetFieldSelectView($data['fields']['time_type'])
	)
	->addField(array_key_exists('itemid', $data['fields'])
		? new CWidgetFieldMultiSelectItemView($data['fields']['itemid'], $data['captions']['ms']['items']['itemid'])
		: null
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['clock_type'])
	)
	->addField(
		new CWidgetFieldCheckBoxListView($data['fields']['show']),
		'js-row-show'
	)
	->addField(
		new CWidgetFieldCheckBoxView($data['fields']['adv_conf']),
		'js-row-adv-conf'
	)
	->addField(
		new CWidgetFieldColorView($data['fields']['bg_color']),
		'js-row-bg-color'
	)
	->addFieldsGroup(_('Date'), getDateFieldsGroupViews($form, $data['fields']), 'fields-group-date')
	->addFieldsGroup(_('Time'), getTimeFieldsGroupViews($form, $data['fields']), 'fields-group-time')
	->addFieldsGroup(_('Time zone'), getTimeZoneFieldsGroupViews($form, $data['fields']), 'fields-group-tzone')
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_clock_form.init();')
	->show();

function getDateFieldsGroupViews(CWidgetFormView $form, array $fields): array {
	$date_size = new CWidgetFieldIntegerBoxView($fields['date_size']);
	$date_color = new CWidgetFieldColorView($fields['date_color']);

	return [
		$form->makeCustomField($date_size, [
			$date_size->getLabel(),
			(new CFormField([$date_size->getView(), '%']))->addClass('field-size')
		]),

		new CWidgetFieldCheckBoxView($fields['date_bold']),

		$form->makeCustomField($date_color, [
			$date_color->getLabel()->addClass('offset-3'),
			new CFormField($date_color->getView())
		])
	];
}

function getTimeFieldsGroupViews(CWidgetFormView $form, array $fields): array {
	$time_size = new CWidgetFieldIntegerBoxView($fields['time_size']);
	$time_color = new CWidgetFieldColorView($fields['time_color']);
	$time_format = new CWidgetFieldRadioButtonListView($fields['time_format']);

	return [
		$form->makeCustomField($time_size, [
			$time_size->getLabel(),
			(new CFormField([$time_size->getView(), '%']))->addClass('field-size')
		]),

		new CWidgetFieldCheckBoxView($fields['time_bold']),

		$form->makeCustomField($time_color, [
			$time_color->getLabel()->addClass('offset-3'),
			new CFormField($time_color->getView())
		]),

		new CWidgetFieldCheckBoxView($fields['time_sec']),

		$form->makeCustomField($time_format, [
			$time_format->getLabel(),
			(new CFormField($time_format->getView()))->addClass('field-format')
		])
	];
}

function getTimeZoneFieldsGroupViews(CWidgetFormView $form, array $fields): array {
	$tzone_size = new CWidgetFieldIntegerBoxView($fields['tzone_size']);
	$tzone_color = new CWidgetFieldColorView($fields['tzone_color']);
	$tzone_timezone = new CWidgetFieldTimeZoneView($fields['tzone_timezone']);
	$tzone_format = new CWidgetFieldRadioButtonListView($fields['tzone_format']);

	return [
		$form->makeCustomField($tzone_size, [
			$tzone_size->getLabel(),
			(new CFormField([$tzone_size->getView(), '%']))->addClass('field-size')
		]),

		new CWidgetFieldCheckBoxView($fields['tzone_bold']),

		$form->makeCustomField($tzone_color, [
			$tzone_color->getLabel()->addClass('offset-3'),
			new CFormField($tzone_color->getView())
		]),

		$form->makeCustomField($tzone_timezone, [], 'field-tzone-timezone'),

		$form->makeCustomField($tzone_format, [], 'field-tzone-format')
	];
}

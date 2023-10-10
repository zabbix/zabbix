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
		? (new CWidgetFieldMultiSelectItemView($data['fields']['itemid']))
			->setPopupParameter('value_types', [
				ITEM_VALUE_TYPE_FLOAT,
				ITEM_VALUE_TYPE_STR,
				ITEM_VALUE_TYPE_LOG,
				ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT
			])
		: null
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['clock_type'])
	)
	->addField(
		(new CWidgetFieldCheckBoxListView($data['fields']['show']))->addRowClass('js-row-show'),
	)
	->addFieldset(
		(new CWidgetFormFieldsetCollapsibleView(_('Advanced configuration')))
			->addField(
				new CWidgetFieldColorView($data['fields']['bg_color'])
			)
			->addFieldsGroup(
				getDateFieldsGroupView($form, $data['fields'])->addRowClass('fields-group-date')
			)
			->addFieldsGroup(
				getTimeFieldsGroupView($form, $data['fields'])->addRowClass('fields-group-time')
			)
			->addFieldsGroup(
				getTimeZoneFieldsGroupView($form, $data['fields'])->addRowClass('fields-group-tzone')
			)
			->addClass('js-fieldset-adv-conf')
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_clock_form.init();')
	->show();

function getDateFieldsGroupView(CWidgetFormView $form, array $fields): CWidgetFieldsGroupView {
	$date_size_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['date_size']));

	return (new CWidgetFieldsGroupView(_('Date')))
		->addItem([
			$date_size_field->getLabel(),
			(new CFormField([$date_size_field->getView(), '%']))->addClass('field-size')
		])
		->addField(
			new CWidgetFieldCheckBoxView($fields['date_bold'])
		)
		->addField(
			(new CWidgetFieldColorView($fields['date_color']))->addLabelClass('offset-3')
		);
}

function getTimeFieldsGroupView(CWidgetFormView $form, array $fields): CWidgetFieldsGroupView {
	$time_size_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['time_size']));

	return (new CWidgetFieldsGroupView(_('Time')))
		->addItem([
			$time_size_field->getLabel(),
			(new CFormField([$time_size_field->getView(), '%']))->addClass('field-size')
		])
		->addField(
			new CWidgetFieldCheckBoxView($fields['time_bold'])
		)
		->addField(
			(new CWidgetFieldColorView($fields['time_color']))->addLabelClass('offset-3')
		)
		->addField(
			new CWidgetFieldCheckBoxView($fields['time_sec'])
		)
		->addField(
			(new CWidgetFieldRadioButtonListView($fields['time_format']))->addClass('field-format')
		);
}

function getTimeZoneFieldsGroupView(CWidgetFormView $form, array $fields): CWidgetFieldsGroupView {
	$tzone_size_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['tzone_size']));

	return (new CWidgetFieldsGroupView(_('Time zone')))
		->addItem([
			$tzone_size_field->getLabel(),
			(new CFormField([$tzone_size_field->getView(), '%']))->addClass('field-size')
		])
		->addField(
			new CWidgetFieldCheckBoxView($fields['tzone_bold'])
		)
		->addField(
			(new CWidgetFieldColorView($fields['tzone_color']))->addLabelClass('offset-3')
		)
		->addField(
			(new CWidgetFieldTimeZoneView($fields['tzone_timezone']))->addRowClass('field-tzone-timezone')
		)
		->addField(
			(new CWidgetFieldRadioButtonListView($fields['tzone_format']))->addRowClass('field-tzone-format')
		);
}

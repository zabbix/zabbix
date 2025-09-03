<?php declare(strict_types = 0);
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
	->addField(
		(new CWidgetFieldMultiSelectItemView($data['fields']['itemid']))
			->setPopupParameter('value_types', [
				ITEM_VALUE_TYPE_FLOAT,
				ITEM_VALUE_TYPE_STR,
				ITEM_VALUE_TYPE_LOG,
				ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT
			])
			->addRowClass('js-row-itemid')
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
				getDateFieldsGroupView($data['fields'])->addRowClass('fields-group-date')
			)
			->addFieldsGroup(
				getTimeFieldsGroupView($data['fields'])->addRowClass('fields-group-time')
			)
			->addFieldsGroup(
				getTimeZoneFieldsGroupView($data['fields'])->addRowClass('fields-group-tzone')
			)
			->addClass('js-fieldset-adv-conf')
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_clock_form.init();')
	->show();

function getDateFieldsGroupView(array $fields): CWidgetFieldsGroupView {
	return (new CWidgetFieldsGroupView(_('Date')))
		->addField(
			new CWidgetFieldCheckBoxView($fields['date_bold'])
		)
		->addField(
			(new CWidgetFieldColorView($fields['date_color']))->addLabelClass('offset-3')
		);
}

function getTimeFieldsGroupView(array $fields): CWidgetFieldsGroupView {
	return (new CWidgetFieldsGroupView(_('Time')))
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

function getTimeZoneFieldsGroupView(array $fields): CWidgetFieldsGroupView {
	return (new CWidgetFieldsGroupView(_('Time zone')))
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

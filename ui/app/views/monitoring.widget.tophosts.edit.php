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
 * System information widget form view.
 *
 * @var CView $this
 * @var array $data
 */

(new CWidgetFormView($data))
	->addField(
		new CWidgetFieldMultiSelectGroupView($data['fields']['groupids'], $data['captions']['ms']['groups']['groupids'])
	)
	->addField(
		new CWidgetFieldMultiSelectHostView($data['fields']['hostids'], $data['captions']['ms']['hosts']['hostids'])
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['evaltype'])
	)
	->addField(
		new CWidgetFieldTagsView($data['fields']['tags'])
	)
	->addItem(
		getColumnsField($data['fields']['columns'])
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['order'])
	)
	->addItem(
		getColumnField($data['fields']['column'])
	)
	->addField(
		new CWidgetFieldIntegerBoxView($data['fields']['count'])
	)
	->includeJsFile('js/monitoring.widget.tophosts.edit.js.php')
	->addJavaScript('widget_tophosts_form.init();')
	->show();

function getColumnsField(CWidgetFieldColumnsList $field): array {
	$columns = new CWidgetFieldColumnsListView($field);

	return [
		$columns->getLabel(),
		(new CFormField($columns->getView()))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	];
}

function getColumnField(CWidgetFieldSelect $field): array {
	$column = new CWidgetFieldSelectView($field);

	return [
		$column->getLabel(),
		(new CFormField($field->getValues() ? $column->getView() : _('Add item column')))
			->addClass($column->isDisabled() ? ZBX_STYLE_DISABLED : null)
	];
}

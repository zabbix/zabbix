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
 * Top hosts widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Zabbix\Widgets\Fields\{
	CWidgetFieldColumnsList,
	CWidgetFieldSelect
};

$form = (new CWidgetFormView($data));

$groupids = new CWidgetFieldMultiSelectGroupView($data['fields']['groupids'], $data['captions']['groups']['groupids']);

$form
	->addField($groupids)
	->addField(
		(new CWidgetFieldMultiSelectHostView($data['fields']['hostids'], $data['captions']['hosts']['hostids']))
			->setFilterPreselect(['id' => $groupids->getId(), 'submit_as' => 'groupid'])
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['evaltype'])
	)
	->addField(
		new CWidgetFieldTagsView($data['fields']['tags'])
	)
	->addItem(
		getColumnsField($form, $data['fields']['columns'])
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['order'])
	)
	->addItem(
		getColumnField($form, $data['fields']['column'])
	)
	->addField(
		new CWidgetFieldIntegerBoxView($data['fields']['count'])
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_tophosts_form.init();')
	->show();

function getColumnsField(CWidgetFormView $form, CWidgetFieldColumnsList $field): array {
	$columns = new CWidgetFieldColumnsListView($field);

	return $form->makeCustomField($columns, [
		$columns->getLabel(),
		(new CFormField($columns->getView()))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	]);
}

function getColumnField(CWidgetFormView $form, CWidgetFieldSelect $field): array {
	$column = new CWidgetFieldSelectView($field);

	return $form->makeCustomField($column, [
		$column->getLabel(),
		(new CFormField($field->getValues() ? $column->getView() : _('Add item column')))
			->addClass($column->isDisabled() ? ZBX_STYLE_DISABLED : null)
	]);
}

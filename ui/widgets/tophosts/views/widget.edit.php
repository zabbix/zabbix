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
 * Top hosts widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\TopHosts\Includes\CWidgetFieldColumnsListView;

$form = new CWidgetFormView($data);

$groupids = array_key_exists('groupids', $data['fields'])
	? new CWidgetFieldMultiSelectGroupView($data['fields']['groupids'])
	: null;

$column = $form->registerField(new CWidgetFieldSelectView($data['fields']['column']));

$form
	->addField($groupids)
	->addField(array_key_exists('hostids', $data['fields'])
		? (new CWidgetFieldMultiSelectHostView($data['fields']['hostids']))
			->setFilterPreselect([
				'id' => $groupids->getId(),
				'accept' => CMultiSelect::FILTER_PRESELECT_ACCEPT_ID,
				'submit_as' => 'groupid'
			])
		: null
	)
	->addField(array_key_exists('evaltype', $data['fields'])
		? new CWidgetFieldRadioButtonListView($data['fields']['evaltype'])
		: null
	)
	->addField(array_key_exists('tags', $data['fields'])
		? new CWidgetFieldTagsView($data['fields']['tags'])
		: null
	)
	->addField(
		new CWidgetFieldCheckBoxView($data['fields']['maintenance'])
	)
	->addField(
		(new CWidgetFieldColumnsListView($data['fields']['columns']))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	)
	->addItem([
		$column->getLabel(),
		(new CFormField($data['fields']['column']->getValues() ? $column->getView() : _('Add a column')))
			->addClass($column->isDisabled() ? ZBX_STYLE_DISABLED : null)
	])
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['order'])
	)
	->addField(array_key_exists('show_lines', $data['fields'])
		? new CWidgetFieldIntegerBoxView($data['fields']['show_lines'])
		: null
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_tophosts_form.init('.json_encode([
		'templateid' => $data['templateid']
	], JSON_THROW_ON_ERROR).');')
	->show();

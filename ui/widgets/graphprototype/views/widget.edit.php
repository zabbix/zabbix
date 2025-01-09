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
 * Graph prototype widget form view.
 *
 * @var CView $this
 * @var array $data
 */

$field_itemid = (new CWidgetFieldMultiSelectItemPrototypeView($data['fields']['itemid']))
	->setPopupParameter('numeric', true);

if (!$data['fields']['itemid']->isTemplateDashboard()) {
	$field_itemid->setPopupParameter('with_simple_graph_item_prototypes', true);
}

(new CWidgetFormView($data))
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['source_type'])
	)
	->addField($field_itemid->addRowClass('js-row-itemid'))
	->addField(
		(new CWidgetFieldMultiSelectGraphPrototypeView($data['fields']['graphid']))
			->addRowClass('js-row-graphid')
	)
	->addField(
		(new CWidgetFieldTimePeriodView($data['fields']['time_period']))
			->setDateFormat(ZBX_FULL_DATE_TIME)
			->setFromPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
			->setToPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
	)
	->addField(
		new CWidgetFieldCheckBoxView($data['fields']['show_legend'])
	)
	->addField(
		new CWidgetFieldIntegerBoxView($data['fields']['columns'])
	)
	->addField(
		new CWidgetFieldIntegerBoxView($data['fields']['rows'])
	)
	->addField($data['templateid'] === null
		? new CWidgetFieldMultiSelectOverrideHostView($data['fields']['override_hostid'])
		: null
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_graph_prototype_form.init();')
	->show();

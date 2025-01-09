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
 * Item navigator widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\ItemNavigator\Includes\CWidgetFieldItemGroupingView;

$form = new CWidgetFormView($data);

$groupids_field = array_key_exists('groupids', $data['fields'])
	? new CWidgetFieldMultiSelectGroupView($data['fields']['groupids'])
	: null;

$hostids_field = $data['templateid'] === null
	? (new CWidgetFieldMultiSelectHostView($data['fields']['hostids']))
		->setFilterPreselect([
			'id' => $groupids_field->getId(),
			'accept' => CMultiSelect::FILTER_PRESELECT_ACCEPT_ID,
			'submit_as' => 'groupid'
		])
	: null;

$form
	->addField($groupids_field)
	->addField($hostids_field)
	->addField(array_key_exists('host_tags_evaltype', $data['fields'])
		? new CWidgetFieldRadioButtonListView($data['fields']['host_tags_evaltype'])
		: null
	)
	->addField(array_key_exists('host_tags', $data['fields'])
		? new CWidgetFieldTagsView($data['fields']['host_tags'])
		: null
	)
	->addField(
		(new CWidgetFieldPatternSelectItemView($data['fields']['items']))
			->setFilterPreselect($hostids_field !== null
				? [
					'id' => $hostids_field->getId(),
					'accept' => CMultiSelect::FILTER_PRESELECT_ACCEPT_ID,
					'submit_as' => 'hostid'
				]
				: []
			)
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['item_tags_evaltype'])
	)
	->addField(
		new CWidgetFieldTagsView($data['fields']['item_tags'])
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['state'])
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['problems'])
	)
	->addField(
		new CWidgetFieldItemGroupingView($data['fields']['group_by'])
	)
	->addField(
		new CWidgetFieldIntegerBoxView($data['fields']['show_lines'])
	)
	->show();

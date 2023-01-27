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
 * Problem hosts widget form view.
 *
 * @var CView $this
 * @var array $data
 */

$groupids = new CWidgetFieldMultiSelectGroupView($data['fields']['groupids'],
	$data['captions']['ms']['groups']['groupids']
);

(new CWidgetFormView($data))
	->addField($groupids)
	->addField(
		new CWidgetFieldMultiSelectGroupView($data['fields']['exclude_groupids'],
			$data['captions']['ms']['groups']['exclude_groupids']
		)
	)
	->addField(
		(new CWidgetFieldMultiSelectHostView($data['fields']['hostids'], $data['captions']['ms']['hosts']['hostids']))
			->setFilterPreselect(['id' => $groupids->getId(), 'submit_as' => 'groupid'])
	)
	->addField(
		new CWidgetFieldTextBoxView($data['fields']['problem'])
	)
	->addField(
		new CWidgetFieldSeveritiesView($data['fields']['severities'])
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['evaltype'])
	)
	->addField(
		new CWidgetFieldTagsView($data['fields']['tags'])
	)
	->addField(
		new CWidgetFieldCheckBoxView($data['fields']['show_suppressed'])
	)
	->addField(
		new CWidgetFieldCheckBoxView($data['fields']['hide_empty_groups'])
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['ext_ack'])
	)
	->show();

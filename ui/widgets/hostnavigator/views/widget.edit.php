<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Host navigator widget form view.
 *
 * @var CView $this
 * @var array $data
 */

$form = new CWidgetFormView($data);

$form
	->addField(array_key_exists('groupids', $data['fields'])
		? new CWidgetFieldMultiSelectGroupView($data['fields']['groupids'])
		: null
	)
	->addField(array_key_exists('hosts', $data['fields'])
		? (new CWidgetFieldHostPatternSelectView($data['fields']['hosts']))->setPlaceholder(_('host pattern'))
		: null
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['status'])
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
		new CWidgetFieldSeveritiesView($data['fields']['severities'])
	)
	->addField(
		new CWidgetFieldCheckBoxView($data['fields']['maintenance'])
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['problems'])
	)
	->addField(
		new CWidgetFieldIntegerBoxView($data['fields']['limit'])
	)
	->includeJsFile('widget.edit.js.php')
	->show();

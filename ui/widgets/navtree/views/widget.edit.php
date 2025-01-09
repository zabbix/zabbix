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
 * Map navigation tree widget form view.
 *
 * @var CView $this
 * @var array $data
 */

$form = new CWidgetFormView($data);

foreach ($data['fields']['navtree']->getValue() as $i => $navtree_item) {
	$form->addVar($data['fields']['navtree']->getName().'['.$i.'][name]', $navtree_item['name']);

	if ($navtree_item['order'] != 1) {
		$form->addVar($data['fields']['navtree']->getName().'['.$i.'][order]', $navtree_item['order']);
	}

	if ($navtree_item['parent'] != 0) {
		$form->addVar($data['fields']['navtree']->getName().'['.$i.'][parent]', $navtree_item['parent']);
	}

	if (array_key_exists('sysmapid', $navtree_item)) {
		$form->addVar($data['fields']['navtree']->getName().'['.$i.'][sysmapid]', $navtree_item['sysmapid']);
	}
}

$form
	->addField(new CWidgetFieldCheckBoxView($data['fields']['show_unavailable']))
	->show();

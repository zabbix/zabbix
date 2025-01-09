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
 * Map navigation tree widget view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\NavTree\Includes\NavigationTree;

$item = new NavigationTree([
	'problems' => $data['problems'],
	'severity_config' => $data['severity_config'],
	'initial_load' => $data['initial_load'],
	'maps_accessible' => $data['maps_accessible'],
	'navtree' => $data['navtree'],
	'navtree_item_selected' => $data['navtree_item_selected'],
	'navtree_items_opened' => $data['navtree_items_opened'],
	'show_unavailable' => $data['show_unavailable']
]);

(new CWidgetView($data))
	->addItem($item)
	->setVar('navtree_data', $item->getScriptData())
	->show();

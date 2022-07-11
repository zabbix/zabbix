<?php
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CNavigationTree extends CDiv {

	private $error;
	private $data;

	public function __construct(array $data = []) {
		parent::__construct();

		$this->data = $data;

		$this->setId(uniqid());
		$this->addClass(ZBX_STYLE_NAVIGATIONTREE);
	}

	public function setError($value) {
		$this->error = $value;
		return $this;
	}

	public function getScriptData() {
		return [
			'problems' => $this->data['problems'],
			'severity_levels' => $this->data['severity_config'],
			'navtree' => $this->data['navtree'],
			'navtree_items_opened' => $this->data['navtree_items_opened'],
			'navtree_item_selected' => $this->data['navtree_item_selected'],
			'maps_accessible' => array_map('strval', $this->data['maps_accessible']),
			'show_unavailable' => $this->data['show_unavailable'],
			'initial_load' => $this->data['initial_load'],
			'max_depth' => WIDGET_NAVIGATION_TREE_MAX_DEPTH
		];
	}

	private function build() {
		if ($this->error !== null) {
			$this->addClass(ZBX_STYLE_DISABLED);
		}

		$this->addItem((new CDiv())->addClass('tree'));
	}

	public function toString($destroy = true) {
		$this->build();

		return parent::toString($destroy);
	}
}

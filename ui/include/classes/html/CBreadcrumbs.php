<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

class CBreadcrumbs extends CList {
	private const ZBX_STYLE_BREADCRUMBS = 'filter-breadcrumb';
	private const DELIMITER = '/';
	private $breadcrumbs;

	/**
	 * Creates a UL list.
	 *
	 * @param array $values an array of items to add to the list
	 */
	public function __construct() {
		parent::__construct();
		$this->setAttribute('role', 'navigation');
		$this->setAttribute('aria-label', _x('Hierarchy', 'screen reader'));
		$this->addClass(ZBX_STYLE_OBJECT_GROUP);
		$this->addClass(ZBX_STYLE_FILTER_BREADCRUMB);
	}

	public function addBreadcrumbElements($breadcrumb_elements) {
		$i = 0;
		$last_value_number = count($breadcrumb_elements);
		foreach ($breadcrumb_elements as $key => $breadcrumb) {
			$i++;
			$this->addItem($breadcrumb);
			if ($last_value_number != $i) {
				$this->addItem(self::DELIMITER);
			}
		}
		return $this;
	}
}

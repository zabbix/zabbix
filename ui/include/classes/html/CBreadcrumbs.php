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

	public function __construct(array $values = []) {
		parent::__construct($values);

		$this->setAttribute('role', 'navigation')
		      ->setAttribute('aria-label', _x('Hierarchy', 'screen reader'))
			  ->addClass(ZBX_STYLE_OBJECT_GROUP)
			  ->addClass(self::ZBX_STYLE_BREADCRUMBS);
	}

	/**
	 * Return element as delimiter for breadcrumbs
	 * @return string
	 */
	protected function getDelimiter() {

		return '/';
	}

	public function toString($destroy = true) {
		$this->items = [implode($this->getDelimiter(), $this->items)];

		return parent::toString($destroy);
	}
}

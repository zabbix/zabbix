<?php
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
 * Class for breadcrumbs-style navigation.
 */
class CBreadcrumbs extends CList {

	private const ZBX_STYLE_BREADCRUMBS = 'breadcrumbs';

	/**
	 * Create a CBreadcrumbs instance for the specified item list.
	 *
	 * @param array $list  Breadcrumb list.
	 */
	public function __construct(array $list = []) {
		parent::__construct($list);

		$this
			->addClass(self::ZBX_STYLE_BREADCRUMBS)
			->setAttribute('role', 'navigation')
			->setAttribute('aria-label', _x('Hierarchy', 'screen reader'));
	}
}

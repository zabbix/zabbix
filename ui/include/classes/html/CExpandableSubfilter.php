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
 * Div element for subfilter. It shows "..." button exceeding limit of specified lines.
 */
class CExpandableSubfilter extends CDiv {

	private const ZBX_STYLE_EXPANDABLE = 'expandable-subfilter';
	private const ZBX_STYLE_EXPANDED = 'expanded';
	public const ZBX_STYLE_EXPANDABLE_TEN_LINES = 'ten-lines';

	/**
	 * CExpandableSubfilter constructor.
	 *
	 * @param string            $name      Subfilter name.
	 * @param string|array|CTag $items     (optional) Subfilter content.
	 * @param bool              $expanded  (optional) Whether subfilter must be expanded.
	 */
	public function __construct(string $name, $items = null, bool $expanded = false) {
		parent::__construct([new CDiv($items)]);

		$this
			->addClass(self::ZBX_STYLE_EXPANDABLE)
			->addClass($expanded ? self::ZBX_STYLE_EXPANDED : null)
			->setAttribute('data-name', $name);
	}
}

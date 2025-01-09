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


class CSvgTag extends CTag {

	const ZBX_STYLE_GRAPH_HIGHLIGHTED_VALUE = 'svg-point-highlight';
	const ZBX_STYLE_GRAPH_HELPER = 'svg-helper';

	/**
	 * SVG styles array.
	 */
	protected $styles = [];

	protected $width = 0;
	protected $height = 0;
	protected $x = 0;
	protected $y = 0;

	public function __construct($tag) {
		parent::__construct($tag, true);
	}

	public function makeStyles(): array {
		return $this->styles;
	}

	/**
	 * Add child item with styles.
	 *
	 * @param string|array|CSvgTag  Child item.
	 *
	 * @return CSvgTag
	 */
	public function addItem($value): self {
		if ($value instanceof self) {
			$this->styles = array_merge($this->styles, $value->makeStyles());
		}

		return parent::addItem($value);
	}

	/**
	 * Set axis container position.
	 *
	 * @param int $x
	 * @param int $y
	 *
	 * @return CSvgTag
	 */
	public function setPosition(int $x, int $y): self {
		$this->x = $x;
		$this->y = $y;

		return $this;
	}

	/**
	 * Set axis container size.
	 *
	 * @param int $width
	 * @param int $height
	 *
	 * @return CSvgTag
	 */
	public function setSize(int $width, int $height) {
		$this->width = $width;
		$this->height = $height;

		return $this;
	}
}

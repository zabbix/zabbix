<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CSvgGraphLegend extends CSvgForeignObject {

	/**
	 * Height of child item element container.
	 *
	 * @var int
	 */
	private $child_height = 16;

	/**
	 * Width of child item element container.
	 *
	 * @var int
	 */
	private $child_width = 160;

	/**
	 * Array of CTag child items.
	 *
	 * @var array
	 */
	private $labels = [];

	/**
	 * Array of colors for every label.
	 *
	 * @var array
	 */
	private $colors = [];

	public function getStyles() {
		$style = [
			'.'.CSvgTag::ZBX_STYLE_GRAPH_LEGEND => [
				'overflow-y' => 'hidden',
				'width' => $this->width.'px',
				'height' => $this->height.'px'
			],
			'.'.CSvgTag::ZBX_STYLE_GRAPH_LEGEND.' div' => [
				'position' => 'relative',
				'display' => 'inline-block',
				'white-space' => 'nowrap',
				'overflow' => 'hidden',
				'text-overflow' => 'ellipsis',
				'width' => $this->child_width.'px',
				'line-height' => $this->child_height.'px',
				'padding' => '0 4px 0 14px'
			],
			'.'.CSvgTag::ZBX_STYLE_GRAPH_LEGEND.' div::before' => [
				'content' => '" "',
				'display' => 'block',
				'width' => '10px',
				'height' => '1px',
				'position' => 'absolute',
				'left' => 0,
				'top' => ($this->child_height/2-4).'px',
				'border-bottom' => '4px solid black'
			]
		];

		foreach ($this->colors as $index => $color) {
			$selector = '.'.CSvgTag::ZBX_STYLE_GRAPH_LEGEND.' div:nth-child('.($index+1).')::before';
			$style[$selector] = [
				'border-color' => $color
			];
		}

		return $style;
	}

	/**
	 * Set container width and height of every child label element.
	 *
	 * @param int $width
	 * @param int $height
	 * @return CSvgGraphLegend
	 */
	public function setChildSize($width, $height) {
		$this->child_width = $width;
		$this->child_height = $height;

		return $this;
	}

	/**
	 * Add label element.
	 *
	 * @param string $label    Label text.
	 * @param string $color    Color of label prefix line.
	 * @return CSvgGraphLegend
	 */
	public function addLabel($label, $color) {
		$this->labels[] = new CDiv($label);
		$this->colors[] = $color;

		return $this;
	}

	public function toString($destroy = true) {
		$this
			->setAttribute('x', $this->x)
			->setAttribute('y', $this->y)
			->setAttribute('width', $this->width)
			->setAttribute('height', $this->height);

		parent::addItem((new CTag('body', true))
			->setAttribute('xmlns', 'http://www.w3.org/1999/xhtml')
			->addItem((new CDiv($this->labels))->addClass(CSvgTag::ZBX_STYLE_GRAPH_LEGEND))
		);

		return parent::toString($destroy);
	}
}

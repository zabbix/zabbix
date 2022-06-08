<?php declare(strict_types = 0);
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


class CSvg extends CSvgTag {

	public function __construct() {
		parent::__construct('svg');

		$this
			->setAttribute('id', str_replace('.', '', uniqid('svg_', true)))
			->setAttribute('version', '1.1')
			->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
	}

	/**
	 * Set SVG element width and height.
	 *
	 * @param int $width
	 * @param int $height
	 *
	 * @return CSvg
	 */
	public function setSize(int $width, int $height): self {
		$this->setAttribute('width', $width.'px');
		$this->setAttribute('height', $height.'px');

		return parent::setSize($width, $height);
	}

	protected function startToString(): string {
		if (!$this->styles) {
			return parent::startToString();
		}

		$styles = "\n";
		$scope = '#'.$this->getAttribute('id').' ';

		foreach ($this->styles as $selector => $properties) {
			if ($properties) {
				$styles .= $scope.$selector.'{';
				foreach ($properties as $property => $value) {
					$styles .= $property.':'.$value.';';
				}
				$styles .= '}'."\n";
			}
		}

		$styles = (new CTag('style', true, $styles))->toString();

		return parent::startToString().$styles;
	}
}

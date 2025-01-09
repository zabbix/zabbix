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
	 * @return $this
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

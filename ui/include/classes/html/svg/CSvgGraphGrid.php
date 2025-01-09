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


class CSvgGraphGrid extends CSvgGroup {

	private const ZBX_STYLE_CLASS = 'svg-graph-grid';

	private $points_value;
	private $points_time;

	private $color;

	public function __construct(array $points_value, array $points_time) {
		parent::__construct();

		$this->points_value = $points_value;
		$this->points_time = $points_time;
	}

	public function setColor(string $color): self {
		$this->color = $color;

		return $this;
	}

	public function makeStyles(): array {
		return [
			'.'.self::ZBX_STYLE_CLASS.' path' => [
				'stroke-dasharray' => '2,2',
				'stroke' => $this->color
			]
		];
	}

	private function draw(): void {
		$path = new CSvgPath();

		foreach ($this->points_time as $point => $time) {
			if (($this->x + $point) <= ($this->x + $this->width)) {
				$path
					->moveTo($this->x + $point, $this->y)
					->lineTo($this->x + $point, $this->y + $this->height);
			}
		}

		foreach ($this->points_value as $point => $value) {
			if (($this->y + $this->height - $point) <= ($this->y + $this->height)) {
				$path
					->moveTo($this->x, $this->y + $this->height - $point)
					->lineTo($this->x + $this->width, $this->y + $this->height - $point);
			}
		}

		$this->addItem($path);
	}

	public function toString($destroy = true): string {
		$this
			->addClass(self::ZBX_STYLE_CLASS)
			->setAttribute('shape-rendering', 'crispEdges')
			->draw();

		return parent::toString($destroy);
	}
}

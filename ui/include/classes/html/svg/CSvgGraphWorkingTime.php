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


class CSvgGraphWorkingTime extends CSvgGroup {

	private const ZBX_STYLE_CLASS = 'svg-graph-working-time';

	private $points;

	private $color;

	public function __construct(array $points) {
		parent::__construct();

		$this->points = $points;
	}

	public function setColor(string $color): self {
		$this->color = $color;

		return $this;
	}

	public function makeStyles(): array {
		return [
			'.'.self::ZBX_STYLE_CLASS => [
				'fill' => $this->color
			]
		];
	}

	private function draw(): void {
		for ($i = 0, $count = count($this->points); $i < $count; $i += 2) {
			if ($this->points[$i] != $this->points[$i + 1]) {
				$this->addItem([
					(new CSvgRect($this->points[$i] + $this->x, $this->y, $this->points[$i + 1] - $this->points[$i],
						$this->height
					))
				]);
			}
		}
	}

	public function toString($destroy = true): string {
		$this
			->addClass(self::ZBX_STYLE_CLASS)
			->draw();

		return parent::toString($destroy);
	}
}

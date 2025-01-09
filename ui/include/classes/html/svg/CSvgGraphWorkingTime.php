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

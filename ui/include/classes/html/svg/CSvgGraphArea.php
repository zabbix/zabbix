<?php declare(strict_types = 1);
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


class CSvgGraphArea extends CSvgGraphLine {

	public const ZBX_STYLE_CLASS = 'svg-graph-area';

	private $y_zero;

	public function __construct(array $path, array $metric, ?int $y_zero) {
		parent::__construct($path, $metric, true);

		$this->y_zero = $y_zero;
		$this->options = $metric['options'] + [
			'fill' => 5
		];
	}

	protected function draw(): void {
		parent::draw();

		$this->addClass(self::ZBX_STYLE_CLASS);

		if ($this->path) {
			if ($this->y_zero !== null) {
				$first_point = reset($this->path);
				$last_point = end($this->path);

				$this
					->lineTo($last_point[0], $this->y_zero)
					->lineTo($first_point[0], $this->y_zero);
			}

			$this->closePath();
		}
	}
}

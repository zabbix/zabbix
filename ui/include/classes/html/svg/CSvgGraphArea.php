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


class CSvgGraphArea extends CSvgGraphLine {

	public const ZBX_STYLE_CLASS = 'svg-graph-area';

	public function __construct(array $path, array $metric) {
		$this->path = $path;

		parent::__construct($path, $metric, false);

		$this->options = $metric['options'] + [
			'fill' => CSvgGraph::SVG_GRAPH_DEFAULT_TRANSPARENCY
		];
	}

	protected function draw(): void {
		$this->addClass(self::ZBX_STYLE_CLASS);

		parent::draw();

		if (count($this->path) > 1) {
			$this->closePath();
		}
	}
}

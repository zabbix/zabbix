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

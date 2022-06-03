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


class CSvgGraphClipArea extends CSvgTag {

	private $area_id;

	public function __construct(string $area_id) {
		parent::__construct('clipPath');

		$this->area_id = $area_id;
	}

	public function makeStyles(): array {
		return [
			'.'.CSvgGraphArea::ZBX_STYLE_CLASS => [
				'clip-path' => 'url(#'.$this->area_id.')'
			],
			'[data-metric]' => [
				'clip-path' => 'url(#'.$this->area_id.')'
			]
		];
	}

	protected function draw(): void {
		$this->addItem(
			(new CSvgPath(implode(' ', [
				'M'.$this->x.','.($this->y - 3),
				'H'.($this->width + $this->x),
				'V'.($this->height + $this->y),
				'H'.($this->x)
			])))
		);
	}

	public function toString($destroy = true) {
		$this
			->setAttribute('id', $this->area_id)
			->draw();

		return parent::toString($destroy);
	}
}

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

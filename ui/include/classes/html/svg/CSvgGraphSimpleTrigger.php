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


class CSvgGraphSimpleTrigger extends CSvgGroup {

	private const LABEL_FONT_SIZE = 10;
	private const LABEL_MARGIN = 4;

	private const ZBX_STYLE_CLASS = 'svg-graph-simple-trigger';

	private $color;

	private $index;
	private $side;

	private $constant;
	private $description;
	private $value;
	private $min;
	private $max;

	public function __construct($constant, $description, $value, $min, $max) {
		parent::__construct();

		$this->constant = $constant;
		$this->description = $description;
		$this->value = $value;
		$this->min = $min;
		$this->max = $max;
	}

	public function setColor(string $color): CSvgGraphSimpleTrigger {
		$this->color = $color;

		return $this;
	}

	public function setIndex(int $index): CSvgGraphSimpleTrigger {
		$this->index = $index;

		return $this;
	}

	public function setSide(int $side): CSvgGraphSimpleTrigger {
		$this->side = $side;

		return $this;
	}

	public function makeStyles(): array {
		return [
			'.'.self::ZBX_STYLE_CLASS.'-'.$this->index.'-'.$this->side.' line' => [
				'stroke' => $this->color,
				'stroke-width' => '2px',
				'stroke-dasharray' => 5
			],
			'.'.self::ZBX_STYLE_CLASS.'-'.$this->index.'-'.$this->side.' text' => [
				'font-size' => self::LABEL_FONT_SIZE.'px',
				'fill' => $this->color
			]
		];
	}

	private function draw(): void {
		$total = $this->max - $this->min;

		if ($total == INF) {
			$total = $this->max / 10 - $this->min / 10;
			$fraction = $this->value / 10 - $this->min / 10;
		}
		else {
			$fraction = $this->value - $this->min;
		}

		$y = $this->height + $this->y - CMathHelper::safeMul([
			$this->height, $fraction, 1 / $total
		]);
		$label_x = ($this->side == GRAPH_YAXIS_SIDE_RIGHT)
			? $this->width + $this->x - self::LABEL_MARGIN
			: $this->x + self::LABEL_MARGIN;

		$this->addItem([
			new CSvgLine($this->x, $y, $this->x + $this->width, $y),
			(new CSvgText($this->constant, $label_x, $y - self::LABEL_MARGIN / 2))
				->setAttribute('text-anchor', $this->side == GRAPH_YAXIS_SIDE_RIGHT ? 'end' : null)
		]);
	}

	public function toString($destroy = true): string {
		$this
			->addClass(self::ZBX_STYLE_CLASS)
			->addClass(self::ZBX_STYLE_CLASS.'-'.$this->index.'-'.$this->side)
			->setAttribute('severity-color', $this->color)
			->setAttribute('constant', $this->constant)
			->setAttribute('description', $this->description)
			->draw();

		return parent::toString($destroy);
	}
}

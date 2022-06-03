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


class CSvgGraphProblems extends CSvgGroup {

	public const ZBX_STYLE_CLASS = 'svg-graph-problems';

	public const ZBX_STYLE_GRAPH_PROBLEM_BOX = 'svg-graph-problem-box';
	public const ZBX_STYLE_GRAPH_PROBLEM_HANDLE = 'svg-graph-problem-handle';
	public const ZBX_STYLE_GRAPH_PROBLEM_ARROW = 'svg-graph-problem-arrow';

	public const ANNOTATION_TYPE_SIMPLE = 0x1;
	public const ANNOTATION_TYPE_RANGE = 0x2;

	public const DASH_LINE_START = 0x4;
	public const DASH_LINE_END = 0x8;

	private $annotations;

	private $color = '#AA4455';

	public function __construct(array $annotations) {
		parent::__construct();

		$this->annotations = $annotations;
	}


	public function makeStyles(): array {
		$this
			->addClass(self::ZBX_STYLE_CLASS);

		return [
			'.'.CSvgLine::ZBX_STYLE_DASHED => [
				'stroke-dasharray' => '2,2'
			],
			'.'.self::ZBX_STYLE_GRAPH_PROBLEM_HANDLE => [
				'fill' => $this->color,
				'stroke' => $this->color
			],
			'.'.self::ZBX_STYLE_GRAPH_PROBLEM_BOX => [
				'fill' => $this->color,
				'opacity' => '0.1'
			],
			'.'.self::ZBX_STYLE_CLASS.' line' => [
				'stroke' => $this->color
			],
			'.'.self::ZBX_STYLE_GRAPH_PROBLEM_ARROW => [
				'stroke' => $this->color,
				'fill' => $this->color,
				'stroke-width' => 3
			]
		];
	}

	private function draw(): void {
		foreach ($this->annotations as $annotation) {
			if ($annotation['draw_type'] & self::ANNOTATION_TYPE_SIMPLE) {
				$this->addItem($this->drawTypeSimple($annotation));
			} else {
					$this->addItem($this->drawTypeRange($annotation));
			}
		}
	}

	/**
	 * Return markup for problem of type simple as array.
	 *
	 * @param array $annotation
	 *
	 * @return array
	 */
	private function drawTypeSimple(array $annotation): array {
		[
			'x' => $x,
			'y' => $y,
			'height' => $height,
			'data_info' => $data_info
		] = $annotation;

		$arrow_width = 6;
		$offset = $arrow_width / 2;

		return [
			(new CSvgLine($x, $y, $x, $y + $height))
				->addClass(CSvgLine::ZBX_STYLE_DASHED),
			(new CSvgPolygon([
				[$x, $y + $height + 1],
				[$x - $offset, $y + $height + 5],
				[$x + $offset, $y + $height + 5]
			]))
				->addClass(self::ZBX_STYLE_GRAPH_PROBLEM_ARROW)
				->setAttribute('x', $x - $offset)
				->setAttribute('width', $arrow_width)
				->setAttribute('data-info', $data_info)
		];
	}

	/**
	 * Return markup for problem of type range as array.
	 *
	 * @param array $annotation
	 *
	 * @return array
	 */
	private function drawTypeRange(array $annotation): array {
		[
			'x' => $x,
			'y' => $y,
			'width' => $width,
			'height' => $height,
			'draw_type' => $draw_type,
			'data_info' => $data_info
		] = $annotation;

		$start_line = new CSvgLine($x, $y, $x, $y + $height);
		$end_line = new CSvgLine($x + $width, $y, $x + $width, $y + $height);

		if ($draw_type & self::DASH_LINE_START) {
			$start_line->addClass(CSvgLine::ZBX_STYLE_DASHED);
		}

		if ($draw_type & self::DASH_LINE_END) {
			$end_line->addClass(CSvgLine::ZBX_STYLE_DASHED);
		}

		return [
			$start_line,
			(new CSvgRect($x, $y, $width, $height))
				->addClass(self::ZBX_STYLE_GRAPH_PROBLEM_BOX),
			$end_line,
			(new CSvgRect($x, $y + $height, $width, 4))
				->addClass(self::ZBX_STYLE_GRAPH_PROBLEM_HANDLE)
				->setAttribute('data-info', $data_info)
		];
	}

	public function toString($destroy = true): string {
		$this->draw();

		return parent::toString($destroy);
	}
}

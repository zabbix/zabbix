<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CMapPainter {

	protected $canvas;
	protected $mapData;
	protected $options;

	public function __construct(array $mapData, array $options = []) {
		$this->options = [
			'map' => [
				'drawAreas' => true
			],
			'grid' => [
				'size' => 50
			],
			'graphtheme' => []
		];

		foreach ($options as $key => $option) {
			$this->options[$key] = array_merge($this->options[$key], $option);
		}

		$this->canvas = new CCanvas($mapData['width'], $mapData['height']);

		$this->mapData = $mapData;
	}

	public function paint() {
		$this->paintBackground();
		$this->paintTitle();
		$this->paintGrid();

		if ($this->options['map']['drawAreas']) {
			$this->paintAreas();
		}

		return $this->canvas->getCanvas();
	}

	protected function paintBackground() {
		$this->canvas->fill($this->options['graphtheme']['backgroundcolor']);

		if ($this->mapData['backgroundid']) {
			$image = get_image_by_imageid($this->mapData['backgroundid']);

			if ($image['image']) {
				$this->canvas->setBgImage($image['image']);
			}
		}
	}

	protected function paintTitle() {
		$this->canvas->drawTitle($this->mapData['name'], $this->options['graphtheme']['textcolor']);
	}

	protected function paintGrid() {
		$size = $this->options['grid']['size'];
		if (empty($size)) {
			return;
		}

		$width = $this->canvas->getWidth();
		$height = $this->canvas->getHeight();
		$maxSize = max($width, $height);

		$dims = imageTextSize(8, 0, '00');
		for ($xy = $size; $xy < $maxSize; $xy += $size) {
			if ($xy < $width) {
				$this->canvas->drawLine($xy, 0, $xy, $height, $this->options['graphtheme']['gridcolor'], MAP_LINK_DRAWTYPE_DASHED_LINE);
				$this->canvas->drawText(8, 0, $xy + 3, $dims['height'] + 3, $this->options['graphtheme']['maingridcolor'], $xy);
			}
			if ($xy < $height) {
				$this->canvas->drawLine(0, $xy, $width, $xy, $this->options['graphtheme']['gridcolor'], MAP_LINK_DRAWTYPE_DASHED_LINE);
				$this->canvas->drawText(8, 0, 3, $xy + $dims['height'] + 3, $this->options['graphtheme']['maingridcolor'], $xy);
			}
		}

		$this->canvas->drawText(8, 0, 2, $dims['height'] + 3, $this->options['graphtheme']['maingridcolor'], 'Y X:');
	}

	protected function paintAreas() {
		foreach ($this->mapData['selements'] as $selement) {
			if ($selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS
					&& $selement['areatype'] == SYSMAP_ELEMENT_AREA_TYPE_CUSTOM) {
				$this->canvas->drawRectangle(
					$selement['x'] + 1,
					$selement['y'] + 1,
					$selement['x'] + $selement['width'] - 1,
					$selement['y'] + $selement['height'] - 1,
					$this->options['graphtheme']['maingridcolor']
				);
				$this->canvas->drawRectangle(
					$selement['x'],
					$selement['y'],
					$selement['x'] + $selement['width'],
					$selement['y'] + $selement['height'],
					$this->options['graphtheme']['maingridcolor']
				);
				$this->canvas->drawRectangle(
					$selement['x'] - 1,
					$selement['y'] - 1,
					$selement['x'] + $selement['width'] + 1,
					$selement['y'] + $selement['height'] + 1,
					$this->options['graphtheme']['maingridcolor']
				);
			}
		}
	}
}

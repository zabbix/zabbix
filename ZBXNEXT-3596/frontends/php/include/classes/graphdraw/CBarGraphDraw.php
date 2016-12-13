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

class CBarGraphDraw extends CSvg {

	private $header = null;
	private $data;
	private $width;
	private $height;
	private $gridRows = 10;
	private $gridColumns;
	private $gridX;
	private $gridY;
	private $gridWidth;
	private $gridHeight;

	public function __construct($width, $height, $header = null, $data) {
		parent::__construct();

		$this->header = $header;
		$this->data = $data;

		$this->width = $width;
		$this->setAttribute('width', $this->width.'px');
		$this->height = $height;
		$this->setAttribute('height', $this->height.'px');

		// grid coordinates and size
		$this->gridX = 24;
		$this->gridY = 8 + ($this->header == null) ? 24 : 0;
		$this->gridWidth = $this->width - 50;
		$this->gridHeight = $this->height - 50;

		// calculate number of bars
		$this->gridColumns = count($this->data);
	}

	private function drawBackground() {
		$this->setAttribute('style', 'background:#white');
	}

	private function drawHeader() {
		if ($this->header == null) {
			return;
		}

		$this->addItem(
			(new CText(
				$this->width/2,
				24,
				$this->header,
				'#black'))
			->setAttribute('text-anchor', 'middle')
		);
	}

	private function drawGrid() {
		// vertical lines
		for ($x = $this->gridX; $x <= $this->gridX + $this->gridWidth; $x += $this->gridWidth/$this->gridColumns) {
			$this->addItem(
				(new CLine($x, $this->gridY, $x, $this->gridY + $this->gridHeight, '#ACCBC2'))
					->setDashed()
			);
		}

		// horizontal lines
		for ($y = $this->gridY; $y <= $this->gridY + $this->gridHeight; $y += $this->gridHeight/$this->gridRows) {
			$this->addItem(
				(new CLine($this->gridX, $y, $this->gridX + $this->gridWidth, $y, '#ACCBC2'))
					->setDashed()
			);
		}
	}

	private function drawGridLegend() {
		// vertical legend
		for ($y = $this->gridY; $y <= $this->gridY + $this->gridHeight; $y += $this->gridHeight/$this->gridRows) {
			$this->addItem(
				new CText($this->gridX + $this->gridWidth + 4, $y + 4, $y, '#DD0000')
			);
		}
		// horizontal legend
		$i = 0;
		for ($x = $this->gridX; $x <= $this->gridX + $this->gridWidth - 1; $x += $this->gridWidth/$this->gridColumns) {
			$this->addItem(
				(new CText($x + 4, $this->gridY + $this->gridHeight + 4, $this->data[$i], '#DD0000'))
					->setAttribute('text-anchor', 'end')
					->setAngle(-90)
			);
			$i++;
		}
	}

	private function drawBars() {
		// vertical lines
		$height = 150;
		$width = $this->gridWidth/$this->gridColumns/2;
		$i = 0;
		for ($x = $this->gridX; $x <= $this->gridX + $this->gridWidth - 1; $x += $this->gridWidth/$this->gridColumns) {
			$this->addItem(
				(new CRect($x - $width/2, $this->gridY + $this->gridHeight - $height, $width, $this->data[$i]))
					->setFillColor('#00AA00')
					->setStrokeColor('#00AA00')
			);
			$i++;
		}
	}

	public function draw() {
		$this->drawBackground();
		$this->drawHeader();
		$this->drawGrid();
		$this->drawGridLegend();
		$this->drawBars();

		$this->show();
	}

}

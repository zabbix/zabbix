<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
?>
<?php

/**
 * Draws a table containing text or images.
 */
class CImageTextTable {

	public $image;
	public $fontsize;
	public $color;
	public $align;
	public $x;
	public $y;

	/**
	 * Minimal row height. If the height of some row is bigger then given, the $rowheight will be set to this height.
	 *
	 * @var int
	 */
	public $rowheight;

	private $table;
	private $numrows;

	public function __construct($image, $x, $y) {
		$this->image = $image;
		$this->fontsize = 8;
		$this->rowheight = 0;
		$this->color = 0;
		$this->align = 0;
		$this->x = $x;
		$this->y = $y;
		$this->table = array();
		$this->numrows = 0;
	}

	public function getNumRows() {
		return $this->numrows;
	}

	/**
	 * Adds a new table cell.
	 *
	 * Supported $cell options:
	 * - marginRight    - right margin, defaults to 20
	 * - image          - resource of the image to display in the cell
	 * - text           - text to display in the cell
	 * - color          - text color resource
	 * - align          - text alignment: 0 - left, 1 - center, 2 - right
	 * - fontsize       - text font size
	 *
	 * @param int   $numrow
	 * @param array $cell
	 *
	 * @return void
	 */
	public function addCell($numrow, array $cell) {
		if ($numrow >= $this->numrows) {
			$numrow = $this->numrows;
			$this->numrows++;
			$this->table[$numrow] = array();
		}
		$this->table[$numrow][] = $cell;
	}

	public function addRow($row) {
		$this->table[$this->numrows] = $row;
		$this->numrows++;
	}

	public function draw() {
		$this->calcRows();

		$coly = $this->y;
		foreach ($this->table as $row) {
			$rowx = $this->x;

			foreach ($row as $col) {
				$col['marginRight'] = (isset($col['marginRight'])) ? $col['marginRight'] : 20;

				// draw image
				if (isset($col['image'])) {
					$imageWidth = imagesx($col['image']);
					$imageHeight = imagesy($col['image']);

					imagecopy(
						$this->image,
						$col['image'],
						$rowx,
						$coly - $imageHeight + 1,
						0,
						0,
						$imageWidth,
						$imageHeight
					);
				}
				// draw text
				else {
					$text_color = isset($col['color']) ? $col['color'] : $this->color;
					$align = $this->align;
					if (isset($col['align'])) {
						if ($col['align'] == 1) {
							$align = floor(($col['width'] - $col['size']['width']) / 2); // center
						}
						elseif ($col['align'] == 2) {
							$align = $col['width'] - $col['size']['width']; // right
						}
					}
					imageText($this->image, $col['fontsize'], 0, $rowx+$align, $coly, $text_color, $col['text']);
				}

				$rowx += $col['width'] + $col['marginRight'];
				$height = $col['height'];
			}
			$coly += $height;
		}
	}

	/**
	 * Calculates the size of each row and column.
	 *
	 * @return void
	 */
	private function calcRows() {
		$rowHeight = 0;
		$colWidth = array();

		foreach ($this->table as $y => $row) {
			foreach ($row as $x => $col) {
				// calculate size from image
				if (isset($col['image'])) {
					$dims = array(
						'width' => imagesx($col['image']),
						'height' => imagesy($col['image'])
					);
				}
				// calculate size from text
				else {
					if (!isset($col['fontsize'])) {
						$col['fontsize'] = $this->fontsize;
					}
					$this->table[$y][$x]['fontsize'] = $col['fontsize'];

					$dims = imageTextSize($col['fontsize'], 0, $col['text']);
				}

				$this->table[$y][$x]['size'] = $dims;

				$rowHeight = ($dims['height'] > $rowHeight) ? $dims['height'] : $rowHeight;

				if (!isset($colWidth[$x])) {
					$colWidth[$x] = $dims['width'];
				}
				elseif ($dims['width'] > $colWidth[$x]) {
					$colWidth[$x] = $dims['width'];
				}
			}
		}

		if ($rowHeight < $this->rowheight) {
			$rowHeight = $this->rowheight;
		}
		else {
			$this->rowheight = $rowHeight;
		}

		foreach ($this->table as $y => $row) {
			foreach ($row as $x => $col) {
				$this->table[$y][$x]['height'] = $rowHeight;
				$this->table[$y][$x]['width'] = $colWidth[$x];
			}
		}
	}
}
?>

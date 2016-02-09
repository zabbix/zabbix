<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
/**
 * @author Aly
 */
class CImageTextTable{
public $image;
public $fontsize;
public $color;
public $align;
public $x;
public $y;
public $rowheight;

private $table;
private $numrows;

public function __construct($image, $x, $y){
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

public function getNumRows(){
	return $this->numrows;
}


public function addCell($numrow, $cell){
	if($numrow >= $this->numrows){
		$numrow = $this->numrows;
		$this->numrows++;

		$this->table[$numrow] = array();
	}

	$this->table[$numrow][] = $cell;
}

public function addRow($row){
	$this->table[$this->numrows] = $row;
	$this->numrows++;
}

public function draw(){
	$this->calcRows();

	$coly = $this->y;
	foreach($this->table as $numRow => $row){
		$rowx = $this->x;

		foreach($row as $numCol => $col){
			$text_color = (isset($col['color']))?$col['color']:$this->color;
//SDI($text_color);
			$align = $this->align;
			if(isset($col['align'])){
				if($col['align'] == 1)
					$align = floor(($col['width'] - $col['size']['width'])/2); // center
				else if($col['align'] == 2)
					$align = $col['width'] - $col['size']['width']; // right
			}


//SDI(array($col['fontsize'], 0, $rowx+$align, $coly, $text_color, $col['text']));
			imageText($this->image, $col['fontsize'], 0, $rowx+$align, $coly, $text_color, $col['text']);
			$rowx += $col['width'] + 20;
			$height = $col['height'];
		}

		$coly += $height;
	}
}

private function calcRows(){

	$rowHeight = 0;
	$colWidth = array();

	foreach($this->table as $y => $row){
		foreach($row as $x => $col){
			if(!isset($col['fontsize'])) $col['fontsize'] = $this->fontsize;
			$this->table[$y][$x]['fontsize'] = $col['fontsize'];

			$dims = imageTextSize($col['fontsize'], 0, $col['text']);
			$this->table[$y][$x]['size'] = $dims;

			$rowHeight = ($dims['height'] > $rowHeight)?$dims['height']:$rowHeight;

			if(!isset($colWidth[$x])) $colWidth[$x] = $dims['width'];
			else if($dims['width'] > $colWidth[$x]) $colWidth[$x] = $dims['width'];
		}
	}

	if($rowHeight < $this->rowheight) $rowHeight = $this->rowheight;
	else $this->rowheight = $rowHeight;

	foreach($this->table as $y => $row){
		foreach($row as $x => $col){
			$this->table[$y][$x]['height'] = $rowHeight;
			$this->table[$y][$x]['width'] = $colWidth[$x];
		}
	}
}
}

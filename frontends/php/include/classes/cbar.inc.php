<?php 
/* 
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
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
require_once "include/classes/graph.inc.php";

//define('GRAPH_TYPE_BAR',				6);
//define('GRAPH_TYPE_COLUMN',			7);
//define('GRAPH_TYPE_BAR_STACKED',		8);
//define('GRAPH_TYPE_COLUMN_STACKED',	9);

class CBar extends Graph{

function cbar($type = GRAPH_TYPE_COLUMN){
	parent::Graph($type);
	
	$this->background = false;
	$this->sum = false;

	$this->shiftlegendright = 0;	// count of static chars * px/char + for color rectangle + space
	$this->shiftCaption = 0;	
	$this->maxCaption = 0;	
	$this->drawlegendallow = 0;
	
	$this->series = array();
	$this->periodCaption = array();
	$this->seriesLegend = array();
	$this->seriesColor = array();
	$this->seriesCount = 0;
	$this->columnWidth = 10;					// bar/column width per serie
	$this->seriesWidth = 10;					// overal per serie bar/column width
	$this->seriesDistance = 10;
	
	$this->xLabel = null;
	$this->yLabel = null;
		
	$this->yaxismin = 0;
	$this->yaxismax = 100;
	
	$this->minValue = 0;
	$this->maxValue = null;
	
	$this->gridLinesCount = NULL;				// How many grids to draw
	$this->gridPixels = 40;						// optimal grid size
	$this->gridStep = null;						// setted value
	
	$this->side_values = ITEM_VALUE_TYPE_UINT64;	// 3 - int, 0 - float
	
	$this->column = null;
	
	$this->units = '';							// Units for values
}

function drawHeader(){
	$str=$this->header;
	$fontnum = ($this->sizeX < 500)?2:4;
	
	$x=$this->fullSizeX/2-imagefontwidth($fontnum)*strlen($str)/2;
	imagestring($this->im, $fontnum,$x,1, $str , $this->GetColor('Dark Red No Alpha'));
}

function setGridStep($step){
	$this->gridStep = $step;
}

function setUnits($units=''){
	$this->units = $units;
}

function setSideValueType($type=ITEM_VALUE_TYPE_UINT64){
	$this->side_values = $type;
}

function showLegend($type=null){
	if(!is_null($type)){
		$this->drawlegendallow = $type;
		return $this->drawlegendallow;
	} 
	else if($this->drawlegendallow == 0){
		$this->drawlegendallow = 1;
	} 
	else {
		$this->drawlegendallow = 0;
	}
	
return $this->drawlegendallow;
}

function setXLabel($label){
	$this->xLabel = $label;
}

function setYLabel($label){
	$this->yLabel = $label;
}

// SERIES SETTINGS
function addSeries($serie){
	foreach($serie as $key => $value){
		if(!isset($this->periodCaption[$key])) $this->periodCaption[$key] = $key;
		$this->series[$key][$this->seriesCount] = $value;
	}
	$this->seriesCount++;
return $this->seriesCount;
}

function setPeriodCaption($periodCaption){
	foreach($periodCaption as $key => $value){
		$this->periodCaption[$key] = $value;

		$tmp = zbx_strlen($value);
		if($tmp > $this->maxCaption) $this->maxCaption = $tmp;
	}
	$this->shiftCaption = round($this->maxCaption * 5.7);
}

function setSeriesLegend($seriesLegend){
	foreach($seriesLegend as $key => $value){
		$this->seriesLegend[$key] = $value;
		
		$tmp = zbx_strlen($value) * 7 + 8;	// count of chars * font size + color box
		if($tmp > $this->shiftlegendright) $this->shiftlegendright = $tmp;
	}
}

function setSeriesColor($seriesColor){
	foreach($seriesColor as $key => $value){
		$this->seriesColor[$key] = $value;
	}
}

function calcShifts(){
	$this->shiftXleft = 10 + (is_null($this->xLabel)?0:16);
	$this->shiftXright = 10;

	if($this->drawlegendallow == 0){
		$this->shiftlegendright = 0;
	}
	
	if($this->column){
		$this->shiftXCaption = 74;
		$this->shiftYCaption = $this->shiftCaption;
	}
	else{
		$this->shiftYCaption = 74;
		$this->shiftXCaption = $this->shiftCaption;
	}
	
	$this->shiftYLegend =  0 + (is_null($this->yLabel)?0:16);
}

function calcSeriesWidth(){
	$serieLength = count($this->periodCaption);
	
	if($this->column){
		$seriesSizeX = $this->sizeX - ($this->seriesDistance * $serieLength);
		
		$this->columnWidth = floor($seriesSizeX / ($serieLength * $this->seriesCount));
		$this->seriesWidth = floor($seriesSizeX / $serieLength);
	}
	else{
		$seriesSizeY = $this->sizeY - ($this->seriesDistance * $serieLength);
		
		$this->columnWidth = floor($seriesSizeY / ($serieLength * $this->seriesCount));
		$this->seriesWidth = floor($seriesSizeY / $serieLength);
	}
//SDI($this->columnWidth);
}

// Calculation of minimum Y axis
function calcMiniMax(){
	if($this->stacked){
		foreach($this->series as $kay => $series){
			$stackedMinValue = 0;
			$stackedMaxValue = 0;
			foreach($series as $c => $value){
				if($value > 0)
					$stackedMaxValue+=$value;
				else
					$stackedMinValue+=$value;
			}
	
			if($this->minValue > $stackedMinValue){
				$this->minValue = $stackedMinValue;
			}
			
			if(($this->maxValue < $stackedMaxValue) || is_null($this->maxValue)){
				$this->maxValue = $stackedMaxValue;
			}
		}
	}
	else{
		foreach($this->series as $kay => $series){
			$localmin = min($series);
			$localmax = max($series);
			
			if($this->minValue > $localmin){
				$this->minValue = $localmin;
			}
				
			if(($this->maxValue < $localmax) || is_null($this->maxValue)){
				$this->maxValue = $localmax;
			}
		}
	}
}	

function calcZero(){
	$left = GRAPH_YAXIS_SIDE_LEFT;
	$right = GRAPH_YAXIS_SIDE_RIGHT;

	$this->unit2px[$right] = ($this->m_maxY[$right] - $this->m_minY[$right])/$this->sizeY;
	$this->unit2px[$left] = ($this->m_maxY[$left] - $this->m_minY[$left])/$this->sizeY;

	if($this->m_minY[$right]>0){
		$this->zero[$right] = $this->sizeY+$this->shiftY;
		$this->oxy[$right] = min($this->m_minY[$right],$this->m_maxY[$right]);
	} 
	else if($this->m_maxY[$right]<0) {
		$this->zero[$right] = $this->shiftY;
		$this->oxy[$right] = max($this->m_minY[$right],$this->m_maxY[$right]);
	}
	else{
		$this->zero[$right] = $this->sizeY+$this->shiftY - (int)abs($this->m_minY[$right]/$this->unit2px[$right]);
		$this->oxy[$right] = 0;
	}			

	if($this->m_minY[$left]>0){
		$this->zero[$left] = $this->sizeY+$this->shiftY;
		$this->oxy[$left] = min($this->m_minY[$left],$this->m_maxY[$left]);
	}
	else if($this->m_maxY[$left]<0){
		$this->zero[$left] = $this->shiftY;
		$this->oxy[$left] = max($this->m_minY[$left],$this->m_maxY[$left]);
	}
	else{
		$this->zero[$left] = $this->sizeY+$this->shiftY - (int)abs($this->m_minY[$left]/$this->unit2px[$left]);
		$this->oxy[$left] = 0;
	}
}

function correctMiniMax(){
	if(is_null($this->gridStep)){
		if($this->column)
			$this->gridLinesCount = round($this->sizeY/$this->gridPixels) + 1;
		else
			$this->gridLinesCount = round($this->sizeX/$this->gridPixels) + 1;
		
		$tmp_maxY = $this->maxValue;
		$tmp_minY = $this->minValue;

//SDI($this->minValue.' : '.$this->maxValue);
		if($this->side_values == ITEM_VALUE_TYPE_UINT64){
			if($this->maxValue < $this->gridLinesCount) return true;
			
			$this->maxValue = round($this->maxValue);
			$this->minValue = floor($this->minValue);
	
			$value_delta = round($this->maxValue - $this->minValue);
			
			$step = floor((($value_delta/$this->gridLinesCount) + 1));	// round to top
			$value_delta2 = $step * $this->gridLinesCount;
//SDI($value_delta.' <> '.$value_delta2);
			$first_delta = round(($value_delta2-$value_delta)/2);
			$second_delta = ($value_delta2-$value_delta) - $first_delta;
	
//SDI($this->maxValue.' : '.$first_delta.' --- '.$this->minValue.' : '.$second_delta);
			if($this->minValue >= 0){
				if($this->minValue < $second_delta){
					$first_delta += $second_delta - $this->minValue;
					$second_delta = $this->minValue;
				}
			}
			else if(($this->maxValue <= 0)){
				if($this->maxValue > $first_delta){
					$second_delta += $first_delta - $this->maxValue;
					$first_delta = $this->maxValue;
				}
			}				
	
			$this->maxValue += $first_delta;
			$this->minValue -= ($value_delta2-$value_delta) - $first_delta;
//SDI($this->minValue.' : '.$this->maxValue);
		}
		else if($this->side_values == ITEM_VALUE_TYPE_FLOAT){
//*
			if($this->maxValue>0){
		
				$this->maxValue = round($this->maxValue,1) + round($this->maxValue,1)*0.1 + 0.05;
			} 
			else if($this->maxValue<0){
				$this->maxValue = round($this->maxValue,1) - round($this->maxValue,1)*0.1 + 0.05;
			} 				
			
			if($this->minValue>0){
				$this->minValue = $this->minValue - ($this->minValue * 0.2) - 0.05;
			} 
			else if($this->minValue<0){
				$this->minValue = $this->minValue + ($this->minValue * 0.2) - 0.05;
			} 
			
			$this->minValue = round($this->minValue,1);
//*/
		}	

	}
	else{
		$this->gridLinesCount = floor($this->maxValue / $this->gridStep) + 1;
		
		$this->maxValue = $this->gridStep * $this->gridLinesCount;
	}
}

//***************************************************************************
//									DRAW									*
//***************************************************************************

function drawGrid(){
	$this->drawSmallRectangle();
	
	if($this->column){
		$hline_count = $this->gridLinesCount;
		
		for($i=1;$i<$hline_count;$i++){
			dashedline($this->im,
					$this->shiftXleft+$this->shiftXCaption,
					$i*($this->sizeY/$hline_count)+$this->shiftY,
					$this->sizeX+$this->shiftXleft+$this->shiftXCaption,
					$i*($this->sizeY/$hline_count)+$this->shiftY,
					$this->getColor('Gray')
				);
		}

		$i=0;

		foreach($this->series as $key => $serie){
			$caption = $this->periodCaption[$key];
			$caption = str_pad($caption,$this->maxCaption,' ', STR_PAD_LEFT);

			imagestringup($this->im, 
						1,
						$i*($this->seriesWidth+$this->seriesDistance)+$this->shiftXleft+$this->shiftXCaption+round($this->seriesWidth/2), 
						$this->sizeY+$this->shiftY+$this->shiftYCaption,
						$caption,
						$this->getColor('Black No Alpha')
				);
			$i++;
		}

	}
	else{
		$vline_count = $this->gridLinesCount;
		
		for($i=1;$i<$vline_count;$i++){
			dashedline($this->im,
						$i*($this->sizeX/$vline_count)+$this->shiftXleft+$this->shiftXCaption,
						$this->shiftY,
						$i*($this->sizeX/$vline_count)+$this->shiftXleft+$this->shiftXCaption,
						$this->sizeY+$this->shiftY,
						$this->getColor('Gray')
				);
		}
		
		$i=0;

		foreach($this->series as $key => $serie){
			$caption = $this->periodCaption[$key];
			$caption = str_pad($caption,$this->maxCaption,' ', STR_PAD_LEFT);

			imagestring($this->im, 
						1,
						$this->shiftXleft,
						($this->sizeY + $this->shiftY) - ($i*($this->seriesWidth+$this->seriesDistance)+$this->seriesDistance+round($this->seriesWidth/2)),
						$caption,
						$this->getColor('Black No Alpha')
				);
			$i++;
		}
	}
	
}

function drawSideValues(){
	$min = $this->minValue;
	$max = $this->maxValue;
	
	$hstr_count = $this->gridLinesCount;

	if($this->column){
		for($i=0;$i<=$hstr_count;$i++){
			$str = str_pad(convert_units(($this->sizeY*$i/$hstr_count*($max-$min)/$this->sizeY+$min),$this->units),14,' ', STR_PAD_LEFT);
			imagestring($this->im, 
						1, 
						$this->shiftXleft, 
						$this->sizeY-$this->sizeY*$i/$hstr_count-4+$this->shiftY, 
						$str, 
						$this->GetColor('Dark Red No Alpha')
					);
		}
	}
	else if(in_array($this->type, array(GRAPH_TYPE_BAR, GRAPH_TYPE_BAR_STACKED))){
		for($i=0;$i<=$hstr_count;$i++){
			$str = str_pad(convert_units(($this->sizeX*$i/$hstr_count*($max-$min)/$this->sizeX+$min),$this->units),14,' ', STR_PAD_LEFT);

			imagestringup($this->im, 
						1, 
						$this->shiftXleft + ($this->sizeX*$i/$hstr_count-4)+$this->shiftXCaption,
						$this->shiftY + $this->sizeY + $this->shiftYCaption, 
						$str, 
						$this->GetColor('Dark Red No Alpha')
					);
		}
	}
	
	if(!is_null($this->xLabel)){
		imagestring($this->im, 2,
			$this->shiftXleft + ($this->sizeX/2) - 20,
			$this->fullSizeY-14,
			$this->xLabel,
			$this->getColor('Black No Alpha'));
	}
	
	if(!is_null($this->yLabel)){
		imagestringup($this->im, 2,
			0,
			$this->shiftY + ($this->sizeY/2) + 20,
			$this->yLabel,
			$this->getColor('Black No Alpha'));
	}
}

function drawLegend(){
	if(!$this->drawlegendallow) return;
	
	$shiftY = $this->shiftY;
	$shiftX = $this->fullSizeX - $this->shiftlegendright;
	
	$count = 0;
	
	foreach($this->series as $key => $serie){
		foreach($serie as $num => $value){
			$caption = $this->seriesLegend[$num];
			$color = $this->getColor($this->seriesColor[$num]);
			
			imagefilledrectangle($this->im, $shiftX, $shiftY+12*$count, $shiftX+5, $shiftY+5+12*$count, $color);
			imagerectangle($this->im,$shiftX, $shiftY+12*$count, $shiftX+5, $shiftY+5+12*$count, $this->getColor('Black No Alpha'));
	
			imagestring($this->im, 2,
				$shiftX+9,
				$shiftY-5+12*$count,
				$caption,
				$this->getColor('Black No Alpha'));
	
			$count++;
		}
		break;  //!!!!
	}
}

function draw(){
	$start_time=getmicrotime();
	set_image_header();
	check_authorisation();
		
	$this->column = in_array($this->type, array(GRAPH_TYPE_COLUMN, GRAPH_TYPE_COLUMN_STACKED));
	
	$this->fullSizeX = $this->sizeX;
	$this->fullSizeY = $this->sizeY;
	
	if(($this->sizeX < 300) || ($this->sizeY < 200)) $this->switchlegend(0);
	
	$this->calcShifts();

	$this->sizeX -= ($this->shiftXleft+$this->shiftXright+$this->shiftlegendright+$this->shiftXCaption);
	$this->sizeY -= ($this->shiftY + $this->shiftYLegend + $this->shiftYCaption);
	
	$this->calcSeriesWidth();
	
	$this->calcMiniMax();
	$this->correctMiniMax();

	$this->calcZero();
		
	if(function_exists('imagecolorexactalpha') && function_exists('imagecreatetruecolor') && @imagecreatetruecolor(1,1))
		$this->im = imagecreatetruecolor($this->fullSizeX,$this->fullSizeY);
	else
		$this->im = imagecreate($this->fullSizeX,$this->fullSizeY);


	$this->initColors();
	$this->drawRectangle();
	$this->drawHeader();

	$this->drawGrid();
	$this->drawSideValues();

	$this->drawLogo();
	$this->drawLegend();

	$count = 0;
	$start = ($this->column)?($this->shiftXleft+$this->shiftXCaption+floor($this->seriesDistance/2)):($this->sizeY+$this->shiftY-floor($this->seriesDistance/2));
//	$start = ($this->column)?($this->shiftXleft + 1):($this->sizeY+$this->shiftY - 1);
	foreach($this->series as $key => $values){
		foreach($values as $num => $value){
			$color = $this->getColor($this->seriesColor[$num],20);
			if($this->column){
				imagefilledrectangle($this->im,
									$start,
									$this->sizeY+$this->shiftY - round(($this->sizeY/$this->maxValue) * $value),
									$start+$this->columnWidth,
									$this->sizeY+$this->shiftY,
									$color);
									
				imagerectangle($this->im,
									$start,
									$this->sizeY+$this->shiftY - round(($this->sizeY/$this->maxValue) * $value),
									$start+$this->columnWidth,
									$this->sizeY+$this->shiftY,
									$this->getColor('Black No Alpha'));
			}
			else{
				imagefilledrectangle($this->im,
									$this->shiftXleft+$this->shiftXCaption,
									$start-$this->columnWidth,
									$this->shiftXleft+$this->shiftXCaption + round(($this->sizeX/$this->maxValue) * $value),
									$start,
									$color);
									

				imagerectangle($this->im,
									$this->shiftXleft+$this->shiftXCaption,
									$start-$this->columnWidth,
									$this->shiftXleft+$this->shiftXCaption + round(($this->sizeX/$this->maxValue) * $value),
									$start,
									$this->getColor('Black No Alpha'));
			}
			$start=($this->column)?($start+$this->columnWidth):($start-$this->columnWidth);	
		}
		
		$count++;
		if($this->column){
			$start=$count*($this->seriesWidth+$this->seriesDistance)+$this->shiftXleft+$this->shiftXCaption + floor($this->seriesDistance/2);
		}
		else{
			$start=($this->sizeY + $this->shiftY) - ($count*($this->seriesWidth+$this->seriesDistance)) - floor($this->seriesDistance/2);
		}
	}
	
	$end_time=getmicrotime();
	$str=sprintf('%0.2f',(getmicrotime()-$start_time));
	imagestring($this->im, 0,$this->fullSizeX-120,$this->fullSizeY-12,"Generated in $str sec", $this->GetColor('Gray'));

	unset($this->items, $this->data);

	imageOut($this->im); 
}

}
?>
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

	$this->shiftlegendright = 17*7 + 7 + 10;	// count of static chars * px/char + for color rectangle + space
	$this->drawlegendallow = 0;
	
	$this->series = array();
	$this->seriesCaption = array();
	$this->seriesColor = array();
	$this->seriesCount = 0;
	$this->columnWidth = 10;					// bar/column width per serie
	$this->seriesWidth = 10;					// overal per serie bar/column width
	$this->seriesDistance = 10;
		
	$this->yaxismin = 0;
	$this->yaxismax = 100;
	
	$this->minValue = 0;
	$this->maxValue = null;
	
	$this->minValueStacked = 0;
	$this->maxValueStacked = null;
	
	$this->gridLinesCount = NULL;				// How many grids to draw
	$this->gridPixels = 40;						// optimal grid size
	
	$this->side_values = ITEM_VALUE_TYPE_UINT64;	// 3 - int, 0 - float
	
	$this->column = null;
}

function drawHeader(){
	$str=$this->header;
	$fontnum = ($this->sizeX < 500)?2:4;
	
	$x=$this->fullSizeX/2-imagefontwidth($fontnum)*strlen($str)/2;
	imagestring($this->im, $fontnum,$x,1, $str , $this->GetColor('Dark Red No Alpha'));
}

function setSideValueType($type=ITEM_VALUE_TYPE_UINT64){
	$this->side_values = $type;
}

function switchlegend($type=false){
	if($type && is_numeric($type)){
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

function addSeries($serie){
	foreach($serie as $key => $value){
		if(!isset($this->seriesCaption[$key])) $this->seriesCaption[$key] = $key;
		$this->series[$key][$this->seriesCount] = $value;
	}
	$this->seriesCount++;
}

function setSeriesCaption($seriesCaption){
	foreach($seriesCaption as $key => $value){
		$this->seriesCaption[$key] = $value;
	}
}

function setSeriesColor($seriesColor){
	foreach($seriesColor as $key => $value){
		$this->seriesColor[$key] = $value;
	}
}

function drawLegend(){
	$shiftY = $this->shiftY + $this->shiftYLegend;
	
	$max_host_len=0;
	$max_desc_len=0;
	
	for($i=0;$i<$this->num;$i++){
		if(strlen($this->items[$i]['host'])>$max_host_len)		$max_host_len=strlen($this->items[$i]['host']);
		if(strlen($this->items[$i]['description'])>$max_desc_len)	$max_desc_len=strlen($this->items[$i]['description']);
	}

	for($i=0;$i<$this->num;$i++){

		$color = $this->getColor($this->items[$i]['color']);
		$data = &$this->data[$this->items[$i]['itemid']][$this->items[$i]['calc_type']];
		
		switch($this->items[$i]['calc_fnc']){
			case CALC_FNC_MIN:	
				$fnc_name = 'min';
				$datavalue = $data->min;
				break;
			case CALC_FNC_MAX:	
				$fnc_name = 'max';	
				$datavalue = $data->max;
				break;
			case CALC_FNC_LST:	
				$fnc_name = 'last';	
				$datavalue = $data->lst;
				break;
			case CALC_FNC_AVG:
			default:		
				$fnc_name = 'avg';
				$datavalue = $data->avg;
		}
		
		$proc = ($datavalue * 100)/ $this->sum;
//		convert_units($datavalue,$this->items[$i]["units"]),
		if(isset($data) && isset($datavalue)){
			$strvalue = sprintf(S_VALUE.': %s ('.((round($proc)!=$proc)?'%0.2f':'%s')."%s)",convert_units($datavalue,$this->items[$i]["units"]),$proc,'%');

			$str = sprintf('%s: %s [%s] ',
					str_pad($this->items[$i]['host'],$max_host_len,' '),
					str_pad($this->items[$i]['description'],$max_desc_len,' '),
					$fnc_name);
		}
		else{
			$strvalue = sprintf(S_VALUE.': '.S_NO_DATA_SMALL);
			$str=sprintf('%s: %s [ '.S_NO_DATA_SMALL.' ]',
				str_pad($this->items[$i]['host'],$max_host_len,' '),
				str_pad($this->items[$i]['description'],$max_desc_len,' '));
		}

		imagefilledrectangle($this->im,$this->shiftXleft,$this->sizeY+$shiftY+12*$i,$this->shiftXleft+5,$this->sizeY+$shiftY+5+12*$i,$color);
		imagerectangle($this->im,$this->shiftXleft,$this->sizeY+$shiftY+12*$i,$this->shiftXleft+5,$this->sizeY+$shiftY+5+12*$i,$this->GetColor('Black No Alpha'));

		imagestring($this->im, 2,
			$this->shiftXleft+9,
			$this->sizeY+$shiftY-5+12*$i,
			$str,
			$this->getColor('Black No Alpha'));

		$shiftX = $this->fullSizeX - $this->shiftlegendright - $this->shiftXright + 10;
//		SDI($shiftX.','.$this->sizeX);
		
		imagefilledrectangle($this->im,$shiftX,$this->shiftY+10+5+12*$i,$shiftX+5,$this->shiftY+10+10+12*$i,$color);
		imagerectangle($this->im,$shiftX,$this->shiftY+10+5+12*$i,$shiftX+5,$this->shiftY+10+10+12*$i,$this->GetColor('Black No Alpha'));
		
		imagestring($this->im, 2,
			$shiftX+9,
			$this->shiftY+10+12*$i,
			$strvalue,
			$this->GetColor('Black No Alpha'));
	}

	if($this->sizeY < 120) return;
}


function calcShifts(){
	if($this->drawlegendallow){
		$this->shiftXleft = 60;
		$this->shiftXright = 80;
	}
	else{
		$this->shiftXleft = 60;
		$this->shiftXright = 10;
	}
	
	$this->shiftYLegend = 57;
}

function calcSeriesWidth(){
	$serieLength = count($this->seriesCaption);
	
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

function drawGrid(){
	$this->drawSmallRectangle();
	
	if($this->column){
		$hline_count = round($this->sizeY / $this->gridPixels);
		for($i=1;$i<=$hline_count;$i++){
			dashedline($this->im,
					$this->shiftXleft,
					$i*($this->sizeY/($hline_count+1))+$this->shiftY,
					$this->sizeX+$this->shiftXleft,
					$i*($this->sizeY/($hline_count+1))+$this->shiftY,
					$this->getColor('Gray')
				);
		}

		$i=0;

		foreach($this->seriesCaption as $key => $caption){
			$caption = str_pad($caption,10,' ', STR_PAD_LEFT);
			imagestringup($this->im, 
						1,
						$i*($this->seriesWidth+$this->seriesDistance)+$this->shiftXleft+round($this->seriesWidth/2), 
						$this->sizeY+$this->shiftY+$this->shiftYLegend, 
						$caption,
						$this->getColor('Black No Alpha')
				);
			$i++;
		}

	}
	else{
		$vline_count = round($this->sizeX / $this->gridPixels);
		for($i=1;$i<=$vline_count;$i++){
			dashedline($this->im,
						$i*($this->sizeX/($vline_count+1))+$this->shiftXleft,
						$this->shiftY,
						$i*($this->sizeX/($vline_count+1))+$this->shiftXleft,
						$this->sizeY+$this->shiftY,
						$this->getColor('Gray')
				);
		}
		
		$i=0;

		foreach($this->seriesCaption as $key => $caption){
			$caption = str_pad($caption,10,' ', STR_PAD_LEFT);

			imagestring($this->im, 
						1,
						$this->shiftXleft - 57,
						($this->sizeY + $this->shiftY) - ($i*($this->seriesWidth+$this->seriesDistance)+$this->seriesDistance+round($this->seriesWidth/2)),
						$caption,
						$this->getColor('Black No Alpha')
				);
			$i++;
		}
	}
	
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
	if($this->column)
		$this->gridLinesCount = round($this->sizeY/$this->gridPixels) + 1;
	else
		$this->gridLinesCount = round($this->sizeX/$this->gridPixels) + 1;
		
	$tmp_maxY = $this->maxValue;
	$tmp_minY = $this->minValue;
//SDI($this->minValue.' : '.$this->maxValue);
	if($this->side_values == ITEM_VALUE_TYPE_UINT64){

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
	
			$this->maxValue = round($this->maxValue,1) + round($this->maxValue,1)*0.2 + 0.05;
		} 
		else if($this->maxValue<0){
			$this->maxValue = round($this->maxValue,1) - round($this->maxValue,1)*0.2 + 0.05;
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


function drawSideValues(){
	$min = $this->minValue;
	$max = $this->maxValue;
	
	$hstr_count = $this->gridLinesCount;

	if($this->column){
		for($i=0;$i<=$hstr_count;$i++){
			$str = str_pad(($this->sizeY*$i/$hstr_count*($max-$min)/$this->sizeY+$min),10,' ', STR_PAD_LEFT);
			imagestring($this->im, 
						1, 
						5, 
						$this->sizeY-$this->sizeY*$i/$hstr_count-4+$this->shiftY, 
						$str, 
						$this->GetColor('Dark Red No Alpha')
					);
		}
	}
	else if(in_array($this->type, array(GRAPH_TYPE_BAR, GRAPH_TYPE_BAR_STACKED))){
		for($i=0;$i<=$hstr_count;$i++){
			$str = str_pad(($this->sizeX*$i/$hstr_count*($max-$min)/$this->sizeX+$min),10,' ', STR_PAD_LEFT);
			imagestringup($this->im, 
						1, 
						$this->shiftXleft + ($this->sizeX*$i/$hstr_count-4),
						$this->sizeY + $this->shiftY + 57, 
						$str, 
						$this->GetColor('Dark Red No Alpha')
					);
		}
	}
/*		
		if(($this->zero[GRAPH_YAXIS_SIDE_LEFT] != $this->sizeY+$this->shiftY) && 
			($this->zero[GRAPH_YAXIS_SIDE_LEFT] != $this->shiftY))
		{
			imageline($this->im,
						$this->shiftXleft,
						$this->zero[GRAPH_YAXIS_SIDE_LEFT],
						$this->shiftXleft+$this->sizeX,
						$this->zero[GRAPH_YAXIS_SIDE_LEFT],
						$this->GetColor(GRAPH_ZERO_LINE_COLOR_LEFT)
					); 
		}
//*/
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

	$this->sizeX -= ($this->shiftXleft+$this->shiftXright);
	$this->sizeY -= $this->shiftY + $this->shiftYLegend;
	
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
//	$this->drawLegend();

	$count = 0;
	$start = ($this->column)?($this->shiftXleft+floor($this->seriesDistance/2)):($this->sizeY+$this->shiftY-floor($this->seriesDistance/2));
//	$start = ($this->column)?($this->shiftXleft + 1):($this->sizeY+$this->shiftY - 1);
	foreach($this->series as $key => $values){
		foreach($values as $num => $value){
			$color = $this->seriesColor[$num];
			if($this->column){
				imagefilledrectangle($this->im,
									$start,
									$this->sizeY+$this->shiftY,
									$start+$this->columnWidth,
									$this->sizeY+$this->shiftY - round(($this->sizeY/$this->maxValue) * $value),
									$this->getColor($this->seriesColor[$num],20));
									
				imagerectangle($this->im,
									$start,
									$this->sizeY+$this->shiftY,
									$start+$this->columnWidth,
									$this->sizeY+$this->shiftY - round(($this->sizeY/$this->maxValue) * $value),
									$this->getColor('Black No Alpha'));
			}
			else{
				imagefilledrectangle($this->im,
									$this->shiftXleft,
									$start,
									$this->shiftXleft + round(($this->sizeX/$this->maxValue) * $value),
									$start-$this->columnWidth,
									$this->getColor($this->seriesColor[$num],20));
									

				imagerectangle($this->im,
									$this->shiftXleft,
									$start,
									$this->shiftXleft + round(($this->sizeX/$this->maxValue) * $value),
									$start-$this->columnWidth,
									$this->getColor('Black No Alpha'));
			}
			$start=($this->column)?($start+$this->columnWidth):($start-$this->columnWidth);	
		}
		
		$count++;
		if($this->column){
			$start=$count*($this->seriesWidth+$this->seriesDistance)+$this->shiftXleft + floor($this->seriesDistance/2);
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
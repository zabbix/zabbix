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

//define('GRAPH_TYPE_BAR',		6);
//define('GRAPH_TYPE_COLUMN',		7);
//define('GRAPH_TYPE_3D_BAR',		8);
//define('GRAPH_TYPE_3D_COLUMN',	9);

class CBar extends Graph{

function cbar($type = GRAPH_TYPE_COLUMN){
	parent::Graph($type);
	
	$this->background = false;
	$this->sum = false;

	$this->shiftlegendright = 17*7 + 7 + 10; // count of static chars * px/char + for color rectangle + space
	$this->drawlegendallow = 1;
	
	$this->series = array();
	$this->series_caption = array();
	$this->seriesCount = 0;
	
	$this->yaxismin = 0;
	$this->yaxismax = 100;
	
	$this->minValue = 0;
	$this->maxValue = null;
	
	$this->minValueStacked = 0;
	$this->maxValueStacked = null;
	
	$this->gridLinesCount = NULL;		// How many grids to draw
	$this->gridPixels = 40;				// optimal grid size
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
		$this->series[$key][$this->seriesCount] = $value
	}
	$this->seriesCount++;
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

		$color = $this->GetColor($this->items[$i]['color']);
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

		ImageFilledRectangle($this->im,$this->shiftXleft,$this->sizeY+$shiftY+12*$i,$this->shiftXleft+5,$this->sizeY+$shiftY+5+12*$i,$color);
		ImageRectangle($this->im,$this->shiftXleft,$this->sizeY+$shiftY+12*$i,$this->shiftXleft+5,$this->sizeY+$shiftY+5+12*$i,$this->GetColor('Black No Alpha'));

		ImageString($this->im, 2,
			$this->shiftXleft+9,
			$this->sizeY+$shiftY-5+12*$i,
			$str,
			$this->GetColor('Black No Alpha'));

		$shiftX = $this->fullSizeX - $this->shiftlegendright - $this->shiftXright + 10;
//		SDI($shiftX.','.$this->sizeX);
		
		ImageFilledRectangle($this->im,$shiftX,$this->shiftY+10+5+12*$i,$shiftX+5,$this->shiftY+10+10+12*$i,$color);
		ImageRectangle($this->im,$shiftX,$this->shiftY+10+5+12*$i,$shiftX+5,$this->shiftY+10+10+12*$i,$this->GetColor('Black No Alpha'));
		
		ImageString($this->im, 2,
			$shiftX+9,
			$this->shiftY+10+12*$i,
			$strvalue,
			$this->GetColor('Black No Alpha'));
	}

	if($this->sizeY < 120) return;
}


function updateShifts(){
	if( ($this->yaxisleft == 1) && ($this->yaxisright == 1)){
		$this->shiftXleft = 60;
		$this->shiftXright = 60;
	}
	else if($this->yaxisleft == 1){
		$this->shiftXleft = 60;
		$this->shiftXright = 20;
	}
	else if($this->yaxisright == 1){
		$this->shiftXleft = 10;
		$this->shiftXright = 60;
	}
//			$this->sizeX = $this->sizeX - $this->shiftXleft-$this->shiftXright;
}

function CheckGraphOrientation($value){
	
	if(!empty($this->graphorientation)){
		if(($this->graphorientation == '+') && ($value<0)){
//					Error();
		} 
		else if(($this->graphorientation == '-') && ($value>0)){
//					Error();
		}
	} 
	else {
		if($value < 0){
			$this->graphorientation = '-';
		}
		else if($value > 0){
			$this->graphorientation = '+';
		}
	}
}


function drawGrid(){
	$this->drawSmallRectangle();
	$hline_count = round($this->sizeY / $this->gridPixels);
	for($i=1;$i<=$hline_count;$i++){
		dashedline($this->im,
				$this->shiftXleft,
				$i*($this->sizeY/($hline_count+1))+$this->shiftY,
				$this->sizeX+$this->shiftXleft,
				$i*($this->sizeY/($hline_count+1))+$this->shiftY,
				$this->GetColor('Gray')
			);
	}

	$vline_count = round($this->sizeX / $this->gridPixels);
	for($i=1;$i<=$vline_count;$i++){
		dashedline($this->im,
					$i*($this->sizeX/($vline_count+1))+$this->shiftXleft,
					$this->shiftY,
					$i*($this->sizeX/($vline_count+1))+$this->shiftXleft,
					$this->sizeY+$this->shiftY,
					$this->GetColor('Gray')
			);
	}

	$old_day=-1;
	for($i=0;$i<=($vline_count+1);$i++){
		imagestringup($this->im, 
					1,
					$i*($this->sizeX/($vline_count+1))+$this->shiftXleft-3, 
					$this->sizeY+$this->shiftY+57, 
					date('      H:i',$this->from_time+$i*($this->period/($vline_count+1))), 
					$this->GetColor('Black No Alpha')
			);

		$new_day=date('d',$this->from_time+$i*($this->period/($vline_count+1)));
		if(($old_day != $new_day) || ($i==($vline_count+1))){
			$old_day=$new_day;
			imagestringup($this->im, 
								1,
								$i*($this->sizeX/($vline_count+1))+$this->shiftXleft-3, 
								$this->sizeY+$this->shiftY+57, 
								date('m.d H:i',$this->from_time+$i*($this->period/($vline_count+1))), 
								$this->GetColor('Dark Red No Alpha')
						);

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
	else{
		foreach($this->series as $kay => $series){
			$localmin = min($series);
			$localmax = max($series);
			
			if($this->minValue > $localmin){
				$this->minValue = $localmin;
			}
				
			if($this->maxValue < $localmax) || is_null($this->maxValue)){
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

function correctMinMax(){
	if(in_array($this->type, array(GRAPH_TYPE_COLUMN, GRAPH_TYPE_3D_COLUMN))
		$this->gridLinesCount = round($this->sizeY/$this->gridPixels) + 1;
	else
		$this->gridLinesCount = round($this->sizeX/$this->gridPixels) + 1;
			
	$tmp_maxY = $this->m_maxY[$side];
	$tmp_minY = $this->m_minY[$side];

	if($this->axis_valuetype[$side] == ITEM_VALUE_TYPE_UINT64){

		$this->m_maxY[$side] = round($this->m_maxY[$side]);
		$this->m_minY[$side] = floor($this->m_minY[$side]);

		$value_delta = round($this->m_maxY[$side] - $this->m_minY[$side]);
		
		$step = floor((($value_delta/$this->gridLinesCount) + 1));	// round to top
		$value_delta2 = $step * $this->gridLinesCount;
//SDI($value_delta.' <> '.$value_delta2);
		$first_delta = round(($value_delta2-$value_delta)/2);
		$second_delta = ($value_delta2-$value_delta) - $first_delta;

//SDI($this->m_maxY[$side].' : '.$first_delta.' --- '.$this->m_minY[$side].' : '.$second_delta);
		if($this->m_minY[$side] >= 0){
			if($this->m_minY[$side] < $second_delta){
				$first_delta += $second_delta - $this->m_minY[$side];
				$second_delta = $this->m_minY[$side];
			}
		}
		else if(($this->m_maxY[$side] <= 0)){
			if($this->m_maxY[$side] > $first_delta){
				$second_delta += $first_delta - $this->m_maxY[$side];
				$first_delta = $this->m_maxY[$side];
			}
		}				

		$this->m_maxY[$side] += $first_delta;
		$this->m_minY[$side] -= ($value_delta2-$value_delta) - $first_delta;

	}
	else if($this->axis_valuetype[$side] == ITEM_VALUE_TYPE_FLOAT){
//*
		if($this->m_maxY[$side]>0){
	
			$this->m_maxY[$side] = round($this->m_maxY[$side],1) + round($this->m_maxY[$side],1)*0.2 + 0.05;
		} 
		else if($this->m_maxY[$side]<0){
			$this->m_maxY[$side] = round($this->m_maxY[$side],1) - round($this->m_maxY[$side],1)*0.2 + 0.05;
		} 				
		
		if($this->m_minY[$side]>0){
			$this->m_minY[$side] = $this->m_minY[$side] - ($this->m_minY[$side] * 0.2) - 0.05;
		} 
		else if($this->m_minY[$side]<0){
			$this->m_minY[$side] = $this->m_minY[$side] + ($this->m_minY[$side] * 0.2) - 0.05;
		} 
		
		$this->m_minY[$side] = round($this->m_minY[$side],1);
//*/
	}
	
	if($this->ymax_type == GRAPH_YAXIS_TYPE_FIXED){
		$this->m_maxY[$side] = $this->yaxismax;
	}
	else if($this->ymax_type == GRAPH_YAXIS_TYPE_ITEM_VALUE){
		$this->m_maxY[$side] = $tmp_maxY;
	}
	
	if($this->ymin_type == GRAPH_YAXIS_TYPE_FIXED){
		$this->m_minY[$side] = $this->yaxismin;
	}
	else if($this->ymin_type == GRAPH_YAXIS_TYPE_ITEM_VALUE){
		$this->m_minY[$side] = $tmp_minY;
	}
}


function drawLeftSide(){
	if($this->yaxisleft == 1){
		$minY = $this->m_minY[GRAPH_YAXIS_SIDE_LEFT];
		$maxY = $this->m_maxY[GRAPH_YAXIS_SIDE_LEFT];

		for($item=0;$item<$this->num;$item++){
			if($this->items[$item]['axisside'] == GRAPH_YAXIS_SIDE_LEFT){
				$units=$this->items[$item]['units'];
				break;
			}
		}
		
		$hstr_count = $this->gridLinesCount;
		for($i=0;$i<=$hstr_count;$i++){
			$str = str_pad(convert_units($this->sizeY*$i/$hstr_count*($maxY-$minY)/$this->sizeY+$minY,$units),10,' ', STR_PAD_LEFT);
			imagestring($this->im, 
						1, 
						5, 
						$this->sizeY-$this->sizeY*$i/$hstr_count-4+$this->shiftY, 
						$str, 
						$this->GetColor('Dark Red No Alpha')
					);
		}
		
		if(($this->zero[GRAPH_YAXIS_SIDE_LEFT] != $this->sizeY+$this->shiftY) && 
			($this->zero[GRAPH_YAXIS_SIDE_LEFT] != $this->shiftY))
		{
			imageline($this->im,
						$this->shiftXleft,
						$this->zero[GRAPH_YAXIS_SIDE_LEFT],
						$this->shiftXleft+$this->sizeX,
						$this->zero[GRAPH_YAXIS_SIDE_LEFT],
						$this->GetColor(GRAPH_ZERO_LINE_COLOR_LEFT)
					); //*/
		}
	}
}

function drawRightSide(){
	if($this->yaxisright == 1){
		$minY = $this->m_minY[GRAPH_YAXIS_SIDE_RIGHT];
		$maxY = $this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT];

		for($item=0;$item<$this->num;$item++){
			if($this->items[$item]['axisside'] == GRAPH_YAXIS_SIDE_RIGHT){
				$units=$this->items[$item]['units'];
				break;
			}
		}
		$hstr_count = $this->gridLinesCount;
		for($i=0;$i<=$hstr_count;$i++){
			$str = str_pad(convert_units($this->sizeY*$i/$hstr_count*($maxY-$minY)/$this->sizeY+$minY,$units),10,' ');
			imagestring($this->im, 
						1, 
						$this->sizeX+$this->shiftXleft+2, 
						$this->sizeY-$this->sizeY*$i/$hstr_count-4+$this->shiftY, 
						$str, 
						$this->GetColor('Dark Red No Alpha'));
		}
		
		if(($this->zero[GRAPH_YAXIS_SIDE_RIGHT] != $this->sizeY+$this->shiftY) && 
			($this->zero[GRAPH_YAXIS_SIDE_RIGHT] != $this->shiftY))
		{
			imageline($this->im,
						$this->shiftXleft,
						$this->zero[GRAPH_YAXIS_SIDE_RIGHT],
						$this->shiftXleft+$this->sizeX,
						$this->zero[GRAPH_YAXIS_SIDE_RIGHT],
						$this->GetColor(GRAPH_ZERO_LINE_COLOR_RIGHT)
					); //*/
		}
	}
}


function draw(){
	$start_time=getmicrotime();
	set_image_header();
	check_authorisation();

	$this->minValue	= $this->calculateMin();
	$this->maxValue	= $this->calculateMax();

	$this->correctMinMax();
	
	$this->updateShifts();
	$this->calcZero();
	
	$this->fullSizeX = $this->sizeX+$this->shiftXleft+$this->shiftXright+1;
	$this->fullSizeY = $this->sizeY+$this->shiftY+62;
	$this->fullSizeY += 12*($this->num+(($this->sizeY < 120)?0:count($this->triggers)))+8;

	foreach($this->percentile as $side => $percentile){
		if(($percentile['percent']>0) && $percentile['value']){
			$this->fullSizeY += 12;
		}
	}
	
	if(function_exists('imagecolorexactalpha') && function_exists('imagecreatetruecolor') && @imagecreatetruecolor(1,1))
		$this->im = imagecreatetruecolor($this->fullSizeX,$this->fullSizeY);
	else
		$this->im = imagecreate($this->fullSizeX,$this->fullSizeY);


	$this->initColors();
	$this->drawRectangle();
	$this->drawHeader();

	if($this->num==0){
//				$this->noDataFound();
	}

	$this->drawWorkPeriod();
	$this->drawGrid();
	
	$maxX = $this->sizeX;
	
// For each metric
	for($item = 0; $item < $this->num; $item++){
		$minY = $this->m_minY[$this->items[$item]['axisside']];
		$maxY = $this->m_maxY[$this->items[$item]['axisside']];

		$data = &$this->data[$this->items[$item]['itemid']][$this->items[$item]['calc_type']];
		
		if(!isset($data))	continue;

		if($this->items[$item]['calc_type'] == GRAPH_ITEM_AGGREGATED){
			$drawtype	= GRAPH_ITEM_DRAWTYPE_LINE;

			$max_color	= $this->GetColor('HistoryMax');
			$avg_color	= $this->GetColor('HistoryAvg');
			$min_color	= $this->GetColor('HistoryMin');
			$minmax_color	= $this->GetColor('HistoryMinMax');

			$calc_fnc	= CALC_FNC_ALL;
		}
		else if($this->type == GRAPH_TYPE_STACKED){
			$drawtype	= $this->items[$item]['drawtype'];

			$max_color	= $this->GetColor('ValueMax',GRAPH_STACKED_ALFA);
			$avg_color	= $this->GetColor($this->items[$item]['color'],GRAPH_STACKED_ALFA);
			$min_color	= $this->GetColor('ValueMin',GRAPH_STACKED_ALFA);
			$minmax_color	= $this->GetColor('ValueMinMax',GRAPH_STACKED_ALFA);

			$calc_fnc = $this->items[$item]['calc_fnc'];					
		}
		else{
			$drawtype	= $this->items[$item]['drawtype'];

			$max_color	= $this->GetColor('ValueMax');
			$avg_color	= $this->GetColor($this->items[$item]['color']);
			$min_color	= $this->GetColor('ValueMin');
			$minmax_color	= $this->GetColor('ValueMinMax');

			$calc_fnc = $this->items[$item]['calc_fnc'];
		}
// For each X
		for($i = 1, $j = 0; $i < $maxX; $i++){  // new point
		
			if(($data->count[$i] == 0) && ($i != ($maxX-1))) continue;

			$diff	= abs($data->clock[$i] - $data->clock[$j]);
			$cell	= ($this->to_time - $this->from_time)/$this->sizeX;
			$delay	= $this->items[$item]['delay'];
								
			if($cell > $delay)
				$draw = (boolean) ($diff < ZBX_GRAPH_MAX_SKIP_CELL * $cell);
			else		
				$draw = (boolean) ($diff < ZBX_GRAPH_MAX_SKIP_DELAY * $delay);

			if(($draw == false) && ($this->items[$item]['calc_type'] == GRAPH_ITEM_AGGREGATED))
				$draw = $i - $j < 5;

			if($this->items[$item]['type'] == ITEM_TYPE_TRAPPER)
				$draw = true;
//SDI($draw);

			if($draw){
				$this->drawElement(
					$data,
					$i, $j,
					0, $this->sizeX, 
					$minY, $maxY,
					$drawtype,
					$max_color,
					$avg_color,
					$min_color,
					$minmax_color,
					$calc_fnc,
					$this->items[$item]['axisside']
					);
			}
//echo '\nDraw II \n'; printf('%0.4f',(getmicrotime()-$start_time));

			$j = $i;
		}
	}
	
	$this->drawLeftSide();
	$this->drawRightSide();
	$this->drawTriggers();
	$this->drawPercentile();

	$this->drawLogo();

	$this->drawLegend();
	
	$end_time=getmicrotime();
	$str=sprintf('%0.2f',(getmicrotime()-$start_time));
	imagestring($this->im, 0,$this->fullSizeX-120,$this->fullSizeY-12,"Generated in $str sec", $this->GetColor('Gray'));

	unset($this->items, $this->data);

	ImageOut($this->im); 
}

}
?>
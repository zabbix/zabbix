<?php 
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
require_once('include/items.inc.php');
require_once('include/hosts.inc.php');

define("GRAPH_YAXIS_TYPE_CALCULATED",	0);
define("GRAPH_YAXIS_TYPE_FIXED",		1);
define("GRAPH_YAXIS_TYPE_ITEM_VALUE",	2);

define('GRAPH_YAXIS_SIDE_LEFT',0);
define('GRAPH_YAXIS_SIDE_RIGHT',1);

define('GRAPH_ITEM_SIMPLE' , 	0);
define('GRAPH_ITEM_AGGREGATED',	1);
define('GRAPH_ITEM_SUM',		2);


define('GRAPH_TYPE_NORMAL',		0);
define('GRAPH_TYPE_STACKED',	1);

define('GRAPH_TYPE_PIE',		2);
define('GRAPH_TYPE_EXPLODED',	3);
define('GRAPH_TYPE_3D',			4);
define('GRAPH_TYPE_3D_EXPLODED',5);

define('GRAPH_TYPE_BAR',			6);
define('GRAPH_TYPE_COLUMN',			7);
define('GRAPH_TYPE_BAR_STACKED',	8);
define('GRAPH_TYPE_COLUMN_STACKED',	9);

define('GRAPH_3D_ANGLE', 70);

define('GRAPH_STACKED_ALFA',	15);	//0..100 transperancy

define('GRAPH_ZERO_LINE_COLOR_LEFT', 'AAAAAA');
define('GRAPH_ZERO_LINE_COLOR_RIGHT', '888888');

define('ZBX_MAX_TREND_DIFF', 3600);

define('ZBX_GRAPH_MAX_SKIP_CELL', 16);
define('ZBX_GRAPH_MAX_SKIP_DELAY', 4);

class Graph{

	function Graph($type = GRAPH_TYPE_NORMAL){
	
		$this->stime = null;
		$this->fullSizeX = null;
		$this->fullSizeY = null;

		$this->m_minY = null;
		$this->m_maxY = null;

		$this->data = array();

		$this->items = null;

		$this->min = null;
		$this->max = null;
		$this->avg = null;
		$this->clock = null;
		$this->count = null;

		$this->header = null;

		$this->from_time = null;
		$this->to_time = null;

		$this->colors = null;
		$this->colorsrgb = null;
		$this->im = null;
		
		$this->period=3600;
		$this->from=0;
		$this->sizeX=900;				// default graph size X
		$this->sizeY=200;				// default graph size Y
		$this->shiftXleft=10;
		$this->shiftXright=60;
		$this->shiftXCaption=0;
		$this->shiftY=17;
		$this->border=1;
		$this->num=0;
		$this->type = $type;			// graph type
		
		$this->axis_valuetype = array();		// overal items type (int/float)
	}


	function initColors(){

		$colors = array(		/*  Red, Green, Blue, Alpha */
			'Red'			=> array(255,0,0,50),
			'Dark Red'		=> array(150,0,0,50),
			'Green'			=> array(0,255,0,50),
			'Dark Green'		=> array(0,150,0,50),
			'Blue'			=> array(0,0,255,50),
			'Dark Blue'		=> array(0,0,150,50),
			'Yellow'		=> array(255,255,0,50),
			'Dark Yellow'		=> array(150,150,0,50),
			'Cyan'			=> array(0,255,255,50),
			'Dark Cyan'		=> array(0,150,150,50),
			'Black'			=> array(0,0,0,50),
			'Gray'			=> array(150,150,150,50),
			'White'			=> array(255,255,255),
			'Dark Red No Alpha'	=> array(150,0,0),
			'Black No Alpha'	=> array(0,0,0),

			'HistoryMinMax'		=> array(90,150,185,50),
			'HistoryMax'		=> array(255,100,100,50),
			'HistoryMin'		=> array(50,255,50,50),
			'HistoryAvg'		=> array(50,50,50,50),

			'ValueMinMax'		=> array(255,255,150,50),
			'ValueMax'		=> array(255,180,180,50),
			'ValueMin'		=> array(100,255,100,50),

			'Priority Disaster'	=> array(255,0,0),
			'Priority Hight'	=> array(255,100,100),
			'Priority Average'	=> array(221,120,120),
			'Priority'		=> array(100,100,100),
			'Not Work Period'	=> array(230,230,230),

			'UnknownData'		=> array(130,130,130, 50)
		);
		
		$this->colorsrgb = $colors;
		
// I should rename No Alpha to Alpha at some point to get rid of some confusion
		foreach($colors as $name => $RGBA){
			if(isset($RGBA[3]) &&  function_exists('imagecolorexactalpha') && function_exists('imagecreatetruecolor') && @imagecreatetruecolor(1,1)){
				$this->colors[$name]	= imagecolorexactalpha($this->im,$RGBA[0],$RGBA[1],$RGBA[2],$RGBA[3]);
			}
			else{
				$this->colors[$name]	= imagecolorallocate($this->im,$RGBA[0],$RGBA[1],$RGBA[2]);
			}
		}
	}



	function setPeriod($period){
		$this->period=$period;
	}


	function setSTime($stime){
		if($stime>200000000000 && $stime<220000000000){
			$this->stime=mktime(substr($stime,8,2),substr($stime,10,2),0,substr($stime,4,2),substr($stime,6,2),substr($stime,0,4));
		}
	}

	function setFrom($from){
		$this->from=$from;
	}

	function setWidth($value = NULL){
// Avoid sizeX==0, to prevent division by zero later
		if($value == 0) $value = NULL;
//		if($value > 1300) $value = 1300;
		if(is_null($value)) $value = 900;

		$this->sizeX = $value;
	}

	function setHeight($value = NULL){
		if($value == 0) $value = NULL;
		if(is_null($value)) $value = 900;

		$this->sizeY = $value;
	}

	function setBorder($border){
		$this->border=$border;
	}

	function getLastValue($num){
		$data = &$this->data[$this->items[$num]['itemid']][$this->items[$num]['calc_type']];
		if(isset($data)) for($i=$this->sizeX-1;$i>=0;$i--){
			if(isset($data->count[$i]) && ($data->count[$i] > 0)){
				switch($this->items[$num]['calc_fnc']){
					case CALC_FNC_MIN:	return	$data->min[$i];
					case CALC_FNC_MAX:	return	$data->max[$i];
					case CALC_FNC_ALL:	/* use avg */
					case CALC_FNC_AVG:
					default:		return	$data->avg[$i];
				}
			}
		}
	return 0;
	}

	function drawSmallRectangle(){
		dashedrectangle($this->im,
			$this->shiftXleft+$this->shiftXCaption-1,
			$this->shiftY-1,
			$this->sizeX+$this->shiftXleft+$this->shiftXCaption-1,
			$this->sizeY+$this->shiftY+1,
			$this->GetColor('Black No Alpha')
			);
	}

	function drawRectangle(){
		imagefilledrectangle($this->im,0,0,
			$this->fullSizeX,$this->fullSizeY,
			$this->GetColor('White'));


		if($this->border==1){
			imagerectangle($this->im,0,0,$this->fullSizeX-1,$this->fullSizeY-1,$this->GetColor('Black No Alpha'));
		}
	}

	function period2str($period){
		$second = 1; $minute=$second * 60; $hour=$minute*60; $day=$hour*24;
		$str = ' ( ';

		$days=floor($this->period/$day);
		$hours=floor(($this->period%$day)/$hour);
		$minutes=floor((($this->period%$day)%$hour)/$minute);
		$seconds=floor(((($this->period%$day)%$hour)%$minute)/$second);
		
		$str.=($days>0 ? $days.'d' : '').($hours>0 ?  $hours.'h' : '').($minutes>0 ? $minutes.'m' : '').($seconds>0 ? $seconds.'s' : '');
		$str.=' history ';

		$hour=1; $day=$hour*24;
		$days=floor($this->from/$day);
		$hours=floor(($this->from%$day)/$hour);
		$minutes=floor((($this->from%$day)%$hour)/$minute);
		$seconds=floor(((($this->from%$day)%$hour)%$minute)/$second);
		
		$str.=($days>0 ? $days.'d' : '').($hours>0 ?  $hours.'h' : '').($minutes>0 ? $minutes.'m' : '').($seconds>0 ? $seconds.'s' : '');
		$str.=($days+$hours+$minutes+$seconds>0 ? ' in past ' : '');

		$str.=')';

	return $str;
	}

	function drawHeader(){
		if(!isset($this->header)){
			$str=$this->items[0]['host'].':'.$this->items[0]['description'];
		}
		else{
			$str=$this->header;
		}

		$str=$str.$this->period2str($this->period);

		if($this->sizeX < 500){
			$fontnum = 2;
		}
		else{
			$fontnum = 4;
		}
		$x=$this->fullSizeX/2-imagefontwidth($fontnum)*strlen($str)/2;
		imagestring($this->im, $fontnum,$x,1, $str , $this->GetColor('Dark Red No Alpha'));
	}

	function setHeader($header){
		$this->header=$header;
	}


	function drawLogo(){
		imagestringup($this->im,0,$this->fullSizeX-10,$this->fullSizeY-50, 'http://www.zabbix.com', $this->GetColor('Gray'));
	}

	function getColor($color,$alfa=50){
		if(isset($this->colors[$color]))
			return $this->colors[$color];
			
		$RGB = array(
			hexdec('0x'.substr($color, 0,2)),
			hexdec('0x'.substr($color, 2,2)),
			hexdec('0x'.substr($color, 4,2))
			);
		
		if(isset($alfa) && 
			function_exists('imagecolorexactalpha') && 
			function_exists('imagecreatetruecolor') && 
			@imagecreatetruecolor(1,1)
		)
		{
			return imagecolorexactalpha($this->im,$RGB[0],$RGB[1],$RGB[2],$alfa);
		}
		
		return imagecolorallocate($this->im,$RGB[0],$RGB[1],$RGB[2]);
	}
	
	function getShadow($color,$alfa=0){
		
		if(isset($this->colorsrgb[$color])){
			$red = $this->colorsrgb[$color][0];
			$green = $this->colorsrgb[$color][1];
			$blue = $this->colorsrgb[$color][2];
		} 
		else{
			$red = hexdec(substr($color, 0,2));
			$green = hexdec(substr($color, 2,2));
			$blue = hexdec(substr($color, 4,2));
		}
		
		if($this->sum > 0){
			$red = (int)($red * 0.6);
			$green = (int)($green * 0.6);
			$blue = (int)($blue * 0.6);
		}
		
		$RGB = array($red,$green,$blue);
		
		if(isset($alfa) && 
			function_exists('imagecolorexactalpha') && 
			function_exists('imagecreatetruecolor') && 
			@imagecreatetruecolor(1,1)
		)
		{
				return imagecolorexactalpha($this->im,$RGB[0],$RGB[1],$RGB[2],$alfa);
		}
		
		return imagecolorallocate($this->im,$RGB[0],$RGB[1],$RGB[2]);
	}

}
?>

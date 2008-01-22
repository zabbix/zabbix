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

class Chart extends Graph{

	function Chart($type = GRAPH_TYPE_NORMAL){
		parent::Graph($type);
		
		$this->yaxismin = null;
		$this->yaxismax = null;

		$this->triggers = array();
		
		$this->yaxistype=GRAPH_YAXIS_TYPE_CALCULATED;
		$this->yaxisright=0;
		$this->yaxisleft=0;
		
		$this->m_showWorkPeriod = 1;
		$this->m_showTriggers = 1;
		
		$this->zero = array();
		$this->graphorientation = '';
	}

	function updateShifts()
	{
		if( ($this->yaxisleft == 1) && ($this->yaxisright == 1))
		{
			$this->shiftXleft = 60;
			$this->shiftXright = 60;
		}
		else if($this->yaxisleft == 1)
		{
			$this->shiftXleft = 60;
			$this->shiftXright = 20;
		}
		else if($this->yaxisright == 1)
		{
			$this->shiftXleft = 10;
			$this->shiftXright = 60;
		}
//			$this->sizeX = $this->sizeX - $this->shiftXleft-$this->shiftXright;
	}

	function ShowWorkPeriod($value)
	{
		$this->m_showWorkPeriod = $value == 1 ? 1 : 0;
	}

	function ShowTriggers($value)
	{
		$this->m_showTriggers = $value == 1 ? 1 : 0;
	}

	function AddItem($itemid, $axis=GRAPH_YAXIS_SIDE_RIGHT, $calc_fnc=CALC_FNC_AVG,
				$color=null, $drawtype=null, $type=null, $periods_cnt=null)
	{
		if($this->type == GRAPH_TYPE_STACKED /* stacked graph */)
			$drawtype = GRAPH_ITEM_DRAWTYPE_FILLED_REGION;

		$this->items[$this->num] = get_item_by_itemid($itemid);
		$this->items[$this->num]["description"]=item_description($this->items[$this->num]["description"],$this->items[$this->num]["key_"]);
		$host=get_host_by_hostid($this->items[$this->num]["hostid"]);

		$this->items[$this->num]["host"] = $host["host"];
		$this->items[$this->num]["color"] = is_null($color) ? "Dark Green" : $color;
		$this->items[$this->num]["drawtype"] = is_null($drawtype) ? GRAPH_ITEM_DRAWTYPE_LINE : $drawtype;
		$this->items[$this->num]["axisside"] = is_null($axis) ? GRAPH_YAXIS_SIDE_RIGHT : $axis;
		$this->items[$this->num]["calc_fnc"] = is_null($calc_fnc) ? CALC_FNC_AVG : $calc_fnc;
		$this->items[$this->num]["calc_type"] = is_null($type) ? GRAPH_ITEM_SIMPLE : $type;
		$this->items[$this->num]["periods_cnt"] = is_null($periods_cnt) ? 0 : $periods_cnt;

		if($this->items[$this->num]["axisside"] == GRAPH_YAXIS_SIDE_LEFT)
			$this->yaxisleft=1;

		if($this->items[$this->num]["axisside"] == GRAPH_YAXIS_SIDE_RIGHT)
			$this->yaxisright=1;
//		SDI($this->items);
		
		$this->num++;
	}
	
	function CheckGraphOrientation($value){
		
		if(!empty($this->graphorientation)){
			if(($this->graphorientation == '+') && ($value<0)){
//					Error();
			} 
			elseif(($this->graphorientation == '-') && ($value>0)){
//					Error();
			}
		} 
		else {
			if($value < 0){
				$this->graphorientation = '-';
			}
			elseif($value > 0){
				$this->graphorientation = '+';
			}
		}
	}

	function setYAxisMin($yaxismin)
	{
		$this->yaxismin=$yaxismin;
	}

	function setYAxisMax($yaxismax)
	{
		$this->yaxismax=$yaxismax;
	}

	function setYAxisType($yaxistype)
	{
		$this->yaxistype=$yaxistype;
	}

	function drawGrid()
	{
		$this->drawSmallRectangle();
		for($i=1;$i<=5;$i++)
		{
			DashedLine($this->im,$this->shiftXleft,$i*($this->sizeY/6)+$this->shiftY,$this->sizeX+$this->shiftXleft,$i*($this->sizeY/6)+$this->shiftY,$this->GetColor("Gray"));
		}
	
		for($i=1;$i<=23;$i++)
		{
			DashedLine($this->im,$i*($this->sizeX/24)+$this->shiftXleft,$this->shiftY,$i*($this->sizeX/24)+$this->shiftXleft,$this->sizeY+$this->shiftY,$this->GetColor("Gray"));
		}

		$old_day=-1;
		for($i=0;$i<=24;$i++)
		{
			ImageStringUp($this->im, 1,$i*($this->sizeX/24)+$this->shiftXleft-3, $this->sizeY+$this->shiftY+57, date("      H:i",$this->from_time+$i*($this->period/24)) , $this->GetColor("Black No Alpha"));

			$new_day=date("d",$this->from_time+$i*($this->period/24));
			if( ($old_day != $new_day) ||($i==24))
			{
				$old_day=$new_day;
				ImageStringUp($this->im, 1,$i*($this->sizeX/24)+$this->shiftXleft-3, $this->sizeY+$this->shiftY+57, date("m.d H:i",$this->from_time+$i*($this->period/24)) , $this->GetColor("Dark Red No Alpha"));

			}
		}
	}

	function drawWorkPeriod()
	{
		if($this->m_showWorkPeriod != 1) return;
		if($this->period > 2678400) return; // > 31*24*3600 (month)

		$db_work_period = DBselect("select work_period from config");
		$work_period = DBfetch($db_work_period);
		if(!$work_period)
			return;

		$periods = parse_period($work_period['work_period']);
		if(!$periods)
			return;

		ImageFilledRectangle($this->im,
			$this->shiftXleft+1,
			$this->shiftY,
			$this->sizeX+$this->shiftXleft,
			$this->sizeY+$this->shiftY,
			$this->GetColor("Not Work Period"));

		$now = time();
		if(isset($this->stime))
		{
			$this->from_time=$this->stime;
			$this->to_time=$this->stime+$this->period;
		}
		else
		{
			$this->to_time=$now-3600*$this->from;
			$this->from_time=$this->to_time-$this->period;
		}
		$from = $this->from_time;
		$max_time = $this->to_time;

		$start = find_period_start($periods,$from);
		$end = -1;
		while($start < $max_time && $start > 0)
		{
			$end = find_period_end($periods,$start,$max_time);

			$x1 = round((($start-$from)*$this->sizeX)/$this->period) + $this->shiftXleft;
			$x2 = round((($end-$from)*$this->sizeX)/$this->period) + $this->shiftXleft;
			
			//draw rectangle
			ImageFilledRectangle(
				$this->im,
				$x1,
				$this->shiftY,
				$x2,
				$this->sizeY+$this->shiftY,
				$this->GetColor("White"));

			$start = find_period_start($periods,$end);
		}
	}
	
	function calcTriggers()
	{
		$this->triggers = array();
		if($this->m_showTriggers != 1) return;
		if($this->num != 1) return; // skip multiple graphs

		$max = 3;
		$cnt = 0;

		$db_triggers = DBselect("select distinct tr.triggerid,tr.expression,tr.priority from triggers tr,functions f,items i".
			" where tr.triggerid=f.triggerid and f.function in ('last','min','max') and".
			" tr.status=".TRIGGER_STATUS_ENABLED." and i.itemid=f.itemid and f.itemid=".$this->items[0]["itemid"]." order by tr.priority");

		while(($trigger = DBfetch($db_triggers)) && ($cnt < $max))
		{
			$db_fnc_cnt = DBselect('select count(*) as cnt from functions f where f.triggerid='.$trigger['triggerid']);
			$fnc_cnt = DBfetch($db_fnc_cnt);
			if($fnc_cnt['cnt'] != 1) continue;

			if(!eregi('\{([0-9]{1,})\}([\<\>\=]{1})([0-9\.]{1,})([K|M|G]{0,1})',$trigger['expression'],$arr))
				continue;

			$val = $arr[3];
			if(strcasecmp($arr[4],'K') == 0)	$val *= 1024;
			else if(strcasecmp($arr[4],'M') == 0)	$val *= 1048576; //1024*1024;
			else if(strcasecmp($arr[4],'G') == 0)	$val *= 1073741824; //1024*1024*1024;

			$minY = $this->m_minY[$this->items[0]["axisside"]];
			$maxY = $this->m_maxY[$this->items[0]["axisside"]];

			if($val <= $minY || $val >= $maxY)	continue;

			if($trigger['priority'] == 5)		$color = "Priority Disaster";
			elseif($trigger['priority'] == 4)	$color = "Priority Hight";
			elseif($trigger['priority'] == 3)	$color = "Priority Average";
			else 					$color = "Priority";

			array_push($this->triggers,array(
				'y' => $this->sizeY - (($val-$minY) / ($maxY-$minY)) * $this->sizeY + $this->shiftY,
				'color' => $color,
				'description' => 'trigger: '.expand_trigger_description($trigger['triggerid']).' ['.$arr[2].' '.$arr[3].$arr[4].']'
				));
			++$cnt;
		}
		
	}
	function drawTriggers()
	{
		if($this->m_showTriggers != 1) return;
		if($this->num != 1) return; // skip multiple graphs

		foreach($this->triggers as $trigger)
		{
			DashedLine(
				$this->im,
				$this->shiftXleft,
				$trigger['y'],
				$this->sizeX+$this->shiftXleft,
				$trigger['y'],
				$this->GetColor($trigger['color']));
		}
		
	}

	
	function drawLegend()
	{
		$max_host_len=0;
		$max_desc_len=0;
		for($i=0;$i<$this->num;$i++)
		{
			if(strlen($this->items[$i]["host"])>$max_host_len)		$max_host_len=strlen($this->items[$i]["host"]);
			if(strlen($this->items[$i]["description"])>$max_desc_len)	$max_desc_len=strlen($this->items[$i]["description"]);
		}

		for($i=0;$i<$this->num;$i++)
		{
			if($this->items[$i]["calc_type"] == GRAPH_ITEM_AGGREGATED)
			{
				$fnc_name = "agr(".$this->items[$i]["periods_cnt"].")";
				$color = $this->GetColor("HistoryMinMax");
			}
			else
			{
				$color = $this->GetColor($this->items[$i]["color"]);
				switch($this->items[$i]["calc_fnc"])
				{
					case CALC_FNC_MIN:	$fnc_name = "min";	break;
					case CALC_FNC_MAX:	$fnc_name = "max";	break;
					case CALC_FNC_ALL:	$fnc_name = "all";	break;
					case CALC_FNC_AVG:
					default:		$fnc_name = "avg";
				}
			}

			$data = &$this->data[$this->items[$i]["itemid"]][$this->items[$i]["calc_type"]];
			if(isset($data)&&isset($data->min))
			{
				$str=sprintf("%s: %s [%s] [min:%s max:%s last:%s]",
					str_pad($this->items[$i]["host"],$max_host_len," "),
					str_pad($this->items[$i]["description"],$max_desc_len," "),
					$fnc_name,
					convert_units(min($data->min),$this->items[$i]["units"]),
					convert_units(max($data->max),$this->items[$i]["units"]),
					convert_units($this->getLastValue($i),$this->items[$i]["units"]));
			}
			else
			{
				$str=sprintf("%s: %s [ no data ]",
					str_pad($this->items[$i]["host"],$max_host_len," "),
					str_pad($this->items[$i]["description"],$max_desc_len," "));
			}

			ImageFilledRectangle($this->im,$this->shiftXleft,$this->sizeY+$this->shiftY+62+12*$i,$this->shiftXleft+5,$this->sizeY+$this->shiftY+5+62+12*$i,$color);
			ImageRectangle($this->im,$this->shiftXleft,$this->sizeY+$this->shiftY+62+12*$i,$this->shiftXleft+5,$this->sizeY+$this->shiftY+5+62+12*$i,$this->GetColor("Black No Alpha"));

			ImageString($this->im, 2,
				$this->shiftXleft+9,
				$this->sizeY+$this->shiftY+(62-5)+12*$i,
				$str,
				$this->GetColor("Black No Alpha"));
		}

		if($this->sizeY < 120) return;

		foreach($this->triggers as $trigger)
		{
			ImageFilledEllipse($this->im,
				$this->shiftXleft + 2,
				$this->sizeY+$this->shiftY+2+62+12*$i,
				6,
				6,
				$this->GetColor($trigger["color"]));

			ImageEllipse($this->im,
				$this->shiftXleft + 2,
				$this->sizeY+$this->shiftY+2+62+12*$i,
				6,
				6,
				$this->GetColor("Black No Alpha"));

			ImageString(
				$this->im, 
				2,
				$this->shiftXleft+9,
				$this->sizeY+$this->shiftY+(62-5)+12*$i,
				$trigger['description'],
				$this->GetColor("Black No Alpha"));
			++$i;
		}
	}

	function drawElement(
		&$data, $from, $to, 
		$minX, $maxX, $minY, $maxY, 
		$drawtype, $max_color, $avg_color, $min_color, $minmax_color,
		$calc_fnc, 
		$axisside
		)
	{	
		if(!isset($data->max[$from]) || !isset($data->max[$to])) return;

		$oxy = $this->oxy[$axisside];
		$zero = $this->zero[$axisside];
		$unit2px = $this->unit2px[$axisside];

		$shift_min_from = $shift_min_to = 0;
		$shift_max_from = $shift_max_to = 0;
		$shift_avg_from = $shift_avg_to = 0;

		if(isset($data->shift_min[$from]))	$shift_min_from = $data->shift_min[$from];
		if(isset($data->shift_min[$to]))	$shift_min_to = $data->shift_min[$to];

		if(isset($data->shift_max[$from]))	$shift_max_from = $data->shift_max[$from];
		if(isset($data->shift_max[$to]))	$shift_max_to = $data->shift_max[$to];

		if(isset($data->shift_avg[$from]))	$shift_avg_from = $data->shift_avg[$from];
		if(isset($data->shift_avg[$to]))	$shift_avg_to = $data->shift_avg[$to];
/**/
		$min_from	= $data->min[$from]	+ $shift_min_from;
		$min_to		= $data->min[$to]	+ $shift_min_to;

		$max_from	= $data->max[$from]	+ $shift_max_from;
		$max_to		= $data->max[$to]	+ $shift_max_to;

		$avg_from	= $data->avg[$from]	+ $shift_avg_from;
		$avg_to		= $data->avg[$to]	+ $shift_avg_to;

		$x1 = $from + $this->shiftXleft - 1;
		$x2 = $to + $this->shiftXleft;

		
		$y1min = $zero - ($min_from-$oxy)/$unit2px;
		$y2min = $zero - ($min_to-$oxy)/$unit2px;
		
		$y1max = $zero - ($max_from-$oxy)/$unit2px;
		$y2max = $zero - ($max_to-$oxy)/$unit2px;

		$y1avg = $zero - ($avg_from-$oxy)/$unit2px;
		$y2avg = $zero - ($avg_to-$oxy)/$unit2px;		//*/
		
		switch($calc_fnc){
			case CALC_FNC_MAX:
				$y1 = $y1max;
				$y2 = $y2max;
				$shift_from	= $shift_max_from;
				$shift_to	= $shift_max_to;
				break;
			case CALC_FNC_MIN:
				$y1 = $y1min;
				$y2 = $y2min;
				$shift_from	= $shift_min_from;
				$shift_to	= $shift_min_to;
				break;
			case CALC_FNC_ALL:
				$a[0] = $x1;		$a[1] = $y1max;
				$a[2] = $x1;		$a[3] = $y1min;
				$a[4] = $x2;		$a[5] = $y2min;
				$a[6] = $x2;		$a[7] = $y2max;
				
				ImageFilledPolygon($this->im,$a,4,$minmax_color);
				ImageLine($this->im,$x1,$y1max,$x2,$y2max,$max_color);
				ImageLine($this->im,$x1,$y1min,$x2,$y2min,$min_color);
				
				/* don't use break, avg must be drawed in this statement */
				// break;
			case CALC_FNC_AVG:
				/* don't use break, avg must be drawed in this statement */
				// break;
			default:
				$y1 = $y1avg;
				$y2 = $y2avg;
				$shift_from	= $shift_avg_from ;
				$shift_to	= $shift_avg_to;
		}

		$shift_from -= ($shift_from != 0)?($oxy):(0);
		$shift_to -= ($shift_to != 0)?($oxy):(0);
		
		$y1_shift	= $zero - $shift_from/$unit2px;
		$y2_shift	= $zero - $shift_to/$unit2px;//*/

		/* draw main line */
		switch($drawtype)
		{
			case GRAPH_ITEM_DRAWTYPE_BOLD_LINE:
				ImageLine($this->im,$x1,$y1+1,$x2,$y2+1,$avg_color);
				// break; /* don't use break, must be drawed line also */
			case GRAPH_ITEM_DRAWTYPE_LINE:
				ImageLine($this->im,$x1,$y1,$x2,$y2,$avg_color);
				break;
			case GRAPH_ITEM_DRAWTYPE_FILLED_REGION:
				$a[0] = $x1;		$a[1] = $y1;
				$a[2] = $x1;		$a[3] = $y1_shift;
				$a[4] = $x2;		$a[5] = $y2_shift;
				$a[6] = $x2;		$a[7] = $y2;
				
//					SDI($a);
				
				ImageFilledPolygon($this->im,$a,4,$avg_color);
				break;
			case GRAPH_ITEM_DRAWTYPE_DOT:
				ImageFilledRectangle($this->im,$x1-1,$y1-1,$x1+1,$y1+1,$avg_color);
				ImageFilledRectangle($this->im,$x2-1,$y2-1,$x2+1,$y2+1,$avg_color);
				break;
			case GRAPH_ITEM_DRAWTYPE_DASHED_LINE:
				if( function_exists('imagesetstyle') ){ 
					
					/* Use ImageSetStyle+ImageLIne instead of bugged ImageDashedLine */
					$style = array($avg_color, $avg_color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
					ImageSetStyle($this->im, $style);
					ImageLine($this->im,$x1,$y1,$x2,$y2,IMG_COLOR_STYLED);
				}
				else{
					ImageDashedLine($this->im,$x1,$y1,$x2,$y2,$avg_color);
				}
				break;
		}
	}
	
// Calculation of minimum Y axis
	function calculateMinY($side){
		if($this->yaxistype==GRAPH_YAXIS_TYPE_FIXED){
			return $this->yaxismin;
		}
		else{
			unset($minY);
			for($i=0;$i<$this->num;$i++){
			
				if($this->items[$i]["axisside"] != $side)
					continue;

				foreach(array(GRAPH_ITEM_SIMPLE, GRAPH_ITEM_AGGREGATED) as $type){
				
					if(!isset($this->data[$this->items[$i]["itemid"]][$type]))
						continue;

					$data = &$this->data[$this->items[$i]["itemid"]][$type];

					if(!isset($data))	continue;

					if($type == GRAPH_ITEM_AGGREGATED)
						$calc_fnc = CALC_FNC_ALL;
					else
						$calc_fnc = $this->items[$i]["calc_fnc"];

					switch($calc_fnc){
						case CALC_FNC_ALL:	/* use min */
						case CALC_FNC_MIN:	$val = $data->min; $shift_val = $data->shift_min; break;
						case CALC_FNC_MAX:	$val = $data->max; $shift_val = $data->shift_max; break;
						case CALC_FNC_AVG:
						default:		$val = $data->avg; $shift_val = $data->shift_avg; 
					}

					if(!isset($val)) continue;

					if($this->type == GRAPH_TYPE_STACKED){
						$min_val_shift = min(count($val), count($shift_val));
						for($ci=0; $ci < $min_val_shift; $ci++){
							if($shift_val[$ci] < 0){
								$val[$ci] += $shift_val[$ci];
							} 
						}

					}

					if(!isset($minY)){
						if(isset($val) && count($val) > 0){
							$minY = min($val);
						}
					}
					else{
						$minY = min($minY, min($val));
					}
				}
			}
			
			if(isset($minY)&&($minY>0)){
				$minY = $minY - ($minY * 0.2) - 0.05;
			} 
			elseif(isset($minY)&&($minY<0)){
				$minY = $minY + ($minY * 0.2) - 0.05;
			} 
			else {
				$minY=0;
			}
			
			$minY = round($minY,1);
			return $minY;
		}
	}

// Calculation of maximum Y of a side (left/right)
	function calculateMaxY($side)
	{
		if($this->yaxistype==GRAPH_YAXIS_TYPE_FIXED)
		{
			return $this->yaxismax;
		}
		else
		{

			unset($maxY);
			for($i=0;$i<$this->num;$i++)
			{
				if($this->items[$i]["axisside"] != $side)
					continue;

				foreach(array(GRAPH_ITEM_SIMPLE, GRAPH_ITEM_AGGREGATED) as $type)
				{
					if(!isset($this->data[$this->items[$i]["itemid"]][$type]))
						continue;

					$data = &$this->data[$this->items[$i]["itemid"]][$type];

					if(!isset($data))	continue;

					if($type == GRAPH_ITEM_AGGREGATED)
						$calc_fnc = CALC_FNC_ALL;
					else
						$calc_fnc = $this->items[$i]["calc_fnc"];

					switch($calc_fnc)
					{
						case CALC_FNC_ALL:	/* use max */
						case CALC_FNC_MAX:	$val = $data->max; $shift_val = $data->shift_max; break;
						case CALC_FNC_MIN:	$val = $data->min; $shift_val = $data->shift_min; break;
						case CALC_FNC_AVG:
						default:		$val = $data->avg; $shift_val = $data->shift_avg;
					}
					
					if(!isset($val)) continue;

					for($ci=0; $ci < min(count($val),count($shift_val)); $ci++) $val[$ci] += $shift_val[$ci];

					if(!isset($maxY))
					{
						if(isset($val) && count($val) > 0)
						{
							$maxY = max($val);
						}
					}
					else
					{
						$maxY = max($maxY, max($val));
					}
					
				}
			}

			if(isset($maxY)&&($maxY>0)){
			
/*					$exp = round(log10($maxY));
				$mant = $maxY/pow(10,$exp);
				
				$mant=((round(($mant*11)/6)-1)*6)/10;
				$maxY = $mant*pow(10,$exp);//*/

				$maxY = round($maxY,1) + round($maxY,1)*0.2 + 0.05;
			} 
			elseif(isset($maxY)&&($maxY<0)){
				$maxY = round($maxY,1) - round($maxY,1)*0.2 + 0.05;
			} 
			else {
				$maxY=0.3;
			}
			return $maxY;
		}
	}

	function selectData(){

		$this->data = array();

		$now = time(NULL);

		if(isset($this->stime)){
			$this->from_time	= $this->stime;
			$this->to_time		= $this->stime + $this->period;
		}
		else{
			$this->to_time		= $now - 3600 * $this->from;
			$this->from_time	= $this->to_time - $this->period;
		}

		$p = $this->to_time - $this->from_time;		// graph size in time
		$z = $p - $this->from_time % $p;		// graphsize - mod(from_time,p) for Oracle...
		$x = $this->sizeX;		// graph size in px	

		for($i=0; $i < $this->num; $i++){
		
			$type = $this->items[$i]["calc_type"];

			if($type == GRAPH_ITEM_AGGREGATED) {
				/* skip current period */
				$from_time	= $this->from_time - $this->period * $this->items[$i]["periods_cnt"];
				$to_time	= $this->from_time;
			} else {
				$from_time	= $this->from_time;
				$to_time	= $this->to_time;
			}
			
			$calc_field = 'round('.$x.'*(mod('.zbx_dbcast_2bigint('clock').'+'.$z.','.$p.'))/('.$p.'),0)';  /* required for 'group by' support of Oracle */
			$sql_arr = array();
			
			if(($this->period / $this->sizeX) <= (ZBX_MAX_TREND_DIFF / ZBX_GRAPH_MAX_SKIP_CELL)){
				array_push($sql_arr,
					'select itemid,'.$calc_field.' as i,'.
					' count(*) as count,avg(value) as avg,min(value) as min,'.
					' max(value) as max,max(clock) as clock'.
					' from history where itemid='.$this->items[$i]['itemid'].' and clock>='.$from_time.
					' and clock<='.$to_time.' group by itemid,'.$calc_field
					,

					'select itemid,'.$calc_field.' as i,'.
					' count(*) as count,avg(value) as avg,min(value) as min,'.
					' max(value) as max,max(clock) as clock'.
					' from history_uint where itemid='.$this->items[$i]['itemid'].' and clock>='.$from_time.
					' and clock<='.$to_time.' group by itemid,'.$calc_field
					);
			}
			else{
				array_push($sql_arr,
					'select itemid,'.$calc_field.' as i,'.
					' sum(num) as count,avg(value_avg) as avg,min(value_min) as min,'.
					' max(value_max) as max,max(clock) as clock'.
					' from trends where itemid='.$this->items[$i]['itemid'].' and clock>='.$from_time.
					' and clock<='.$to_time.' group by itemid,'.$calc_field
					);
					
				$this->items[$i]['delay'] = max(($this->items[$i]['delay']*ZBX_GRAPH_MAX_DELAY),ZBX_MAX_TREND_DIFF)/ZBX_GRAPH_MAX_DELAY + 1;
			}
//SDI($sql_arr);
			$curr_data = &$this->data[$this->items[$i]["itemid"]][$type];
			$curr_data->count = NULL;
			$curr_data->min = NULL;
			$curr_data->max = NULL;
			$curr_data->avg = NULL;
			$curr_data->clock = NULL;

			foreach($sql_arr as $sql){
				$result=DBselect($sql);
				
				while($row=DBfetch($result)){
					$idx=$row["i"]-1;
					if($idx<0) continue; 
					/* --------------------------------------------------
					We are taking graph on 1px more than we need, 
					and here we are skiping first px, because of MOD (in SELECT), 
					it combines prelast point (it would be last point if not that 1px in begining)
					and first point, but we still losing prelast point :( 
					but now we've got the first point.
					--------------------------------------------------*/
					
					$curr_data->count[$idx]	= $row["count"];
					$curr_data->min[$idx]	= $row["min"];
					$curr_data->max[$idx]	= $row["max"];
					$curr_data->avg[$idx]	= $row["avg"];
					$curr_data->clock[$idx]	= $row["clock"];
					$curr_data->shift_min[$idx] = 0;
					$curr_data->shift_max[$idx] = 0;
					$curr_data->shift_avg[$idx] = 0;
					
					if($this->type == GRAPH_TYPE_STACKED){
						$this->CheckGraphOrientation($curr_data->min[$idx]);
					}
				}
				unset($row);
			}
			/* calculate missed points */
			$first_idx = 0;
			/* 
				first_idx - last existed point 
				ci - current index
				cj - count of missed in onetime
				dx - offset to first value (count to last existed point)
			//*/

			for($ci = 0, $cj=0; $ci < $this->sizeX; $ci++)
			{
				if(!isset($curr_data->count[$ci]) || $curr_data->count[$ci] == 0)
				{
					$curr_data->count[$ci] = 0;
					$curr_data->shift_min[$ci] = 0;
					$curr_data->shift_max[$ci] = 0;
					$curr_data->shift_avg[$ci] = 0;
					$cj++;
				}
				else if($cj > 0)
				{
					$dx = $cj + 1;

					$first_idx = $ci - $dx;

					if($first_idx < 0)	$first_idx = $ci; // if no data from start of graph get current data as first data

					for(;$cj > 0; $cj--){
					
/*							if(($first_idx == $ci) && ($dx < ($this->sizeX)) && ($this->type == GRAPH_TYPE_STACKED)){
							$curr_data->count[$ci - ($dx - $cj)] = 1;
						}
						else //*/
						if(($dx < ($this->sizeX/20)) && ($this->type == GRAPH_TYPE_STACKED)){
							$curr_data->count[$ci - ($dx - $cj)] = 1;
						}//*/
						
						foreach(array('clock','min','max','avg') as $var_name){
							$var = &$curr_data->$var_name;

							if($first_idx == $ci && $var_name == 'clock'){
								$var[$ci - ($dx - $cj)] = $var[$first_idx] - (($p / $this->sizeX) * ($dx - $cj));
								continue;
							}
							
							$dy = $var[$ci] - $var[$first_idx];
							$var[$ci - ($dx - $cj)] = $var[$first_idx] + ($cj * $dy) / $dx;
						}
					}
				}
			}
			
			if($cj > 0 && $ci > $cj){
				$dx = $cj + 1;

				$first_idx = $ci - $dx;

				for(;$cj > 0; $cj--){

//						if($dx < ($this->sizeX/20))			//($this->type == GRAPH_TYPE_STACKED) 
//							$curr_data->count[$first_idx + ($dx - $cj)] = 1;
						
					foreach(array('clock','min','max','avg') as $var_name){
						$var = &$curr_data->$var_name;

						if( $var_name == 'clock')
						{
							$var[$first_idx + ($dx - $cj)] = $var[$first_idx] + (($p / $this->sizeX) * ($dx - $cj));
							continue;
						}
						$var[$first_idx + ($dx - $cj)] = $var[$first_idx];
					}
				}
			}
			/* end of missed points calculation */
		}

		/* calculte shift for stacked graphs */
		
		if($this->type == GRAPH_TYPE_STACKED)
		{
			for($i=1; $i<$this->num; $i++)
			{
				$curr_data = &$this->data[$this->items[$i]["itemid"]][$this->items[$i]["calc_type"]];

				if(!isset($curr_data))	continue;

				for($j = $i-1; $j >= 0; $j--)
				{
					if($this->items[$j]["axisside"] != $this->items[$i]["axisside"]) continue;

					$prev_data = &$this->data[$this->items[$j]["itemid"]][$this->items[$j]["calc_type"]];

					if(!isset($prev_data))	continue;

					for($ci = 0; $ci < $this->sizeX; $ci++)
					{
						foreach(array('min','max','avg') as $var_name)
						{
							$shift_var_name	= 'shift_'.$var_name;
							$curr_shift	= &$curr_data->$shift_var_name;
							$curr_var	= &$curr_data->$var_name;
							$prev_shift	= &$prev_data->$shift_var_name;
							$prev_var	= &$prev_data->$var_name;
							$curr_shift[$ci] = $prev_var[$ci] + $prev_shift[$ci];
						}
					}
					break;
				}
			}
		}
		/* end calculation of stacked graphs */
	}

	function DrawLeftSide()
	{
		if($this->yaxisleft == 1)
		{
			$minY = $this->m_minY[GRAPH_YAXIS_SIDE_LEFT];
			$maxY = $this->m_maxY[GRAPH_YAXIS_SIDE_LEFT];

			for($item=0;$item<$this->num;$item++)
			{
				if($this->items[$item]["axisside"] == GRAPH_YAXIS_SIDE_LEFT)
				{
					$units=$this->items[$item]["units"];
					break;
				}
			}
			for($i=0;$i<=6;$i++)
			{
				$str = str_pad(convert_units($this->sizeY*$i/6*($maxY-$minY)/$this->sizeY+$minY,$units),10," ", STR_PAD_LEFT);
				ImageString($this->im, 1, 5, $this->sizeY-$this->sizeY*$i/6-4+$this->shiftY, $str, $this->GetColor("Dark Red No Alpha"));
			}
			if(($this->zero[GRAPH_YAXIS_SIDE_LEFT] != $this->sizeY+$this->shiftY) && 
				($this->zero[GRAPH_YAXIS_SIDE_LEFT] != $this->shiftY)){
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

	function DrawRightSide()
	{
		if($this->yaxisright == 1)
		{
			$minY = $this->m_minY[GRAPH_YAXIS_SIDE_RIGHT];
			$maxY = $this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT];

			for($item=0;$item<$this->num;$item++){
				if($this->items[$item]["axisside"] == GRAPH_YAXIS_SIDE_RIGHT){
					$units=$this->items[$item]["units"];
					break;
				}
			}
			for($i=0;$i<=6;$i++){
				$str = str_pad(convert_units($this->sizeY*$i/6*($maxY-$minY)/$this->sizeY+$minY,$units),10," ");
				ImageString($this->im, 1, $this->sizeX+$this->shiftXleft+2, $this->sizeY-$this->sizeY*$i/6-4+$this->shiftY, $str, $this->GetColor("Dark Red No Alpha"));
			}
			
			if(($this->zero[GRAPH_YAXIS_SIDE_RIGHT] != $this->sizeY+$this->shiftY) && 
				($this->zero[GRAPH_YAXIS_SIDE_RIGHT] != $this->shiftY)){
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
	
	function CalcZero(){
		$left = GRAPH_YAXIS_SIDE_LEFT;
		$right = GRAPH_YAXIS_SIDE_RIGHT;
		
		$this->unit2px[$right] = ($this->m_maxY[$right] - $this->m_minY[$right])/$this->sizeY;
		$this->unit2px[$left] = ($this->m_maxY[$left] - $this->m_minY[$left])/$this->sizeY;

		if($this->m_minY[$right]>0){
			$this->zero[$right] = $this->sizeY+$this->shiftY;
			$this->oxy[$right] = min(abs($this->m_minY[$right]),abs($this->m_maxY[$right]));
		} 
		elseif($this->m_maxY[$right]<0) {
			$this->zero[$right] = $this->shiftY;
			$this->oxy[$right] = min(abs($this->m_minY[$right]),abs($this->m_maxY[$right]));
		}
		else{
			$this->zero[$right] = $this->sizeY+$this->shiftY - (int)abs($this->m_minY[$right]/$this->unit2px[$right]);
			$this->oxy[$right] = 0;
		}			

		if($this->m_minY[$left]>0){
			$this->zero[$left] = $this->sizeY+$this->shiftY;
			$this->oxy[$left] = min(abs($this->m_minY[$left]),abs($this->m_maxY[$left]));
		}
		elseif($this->m_maxY[$left]<0){
			$this->zero[$left] = $this->shiftY;
			$this->oxy[$left] = min(abs($this->m_minY[$left]),abs($this->m_maxY[$left]));
		}
		else{
			$this->zero[$left] = $this->sizeY+$this->shiftY - (int)abs($this->m_minY[$left]/$this->unit2px[$left]);
			$this->oxy[$left] = 0;
		}
	}

	function Draw()
	{
		$start_time=getmicrotime();

		set_image_header();

		check_authorisation();
		
		$this->selectData();

		$this->m_minY[GRAPH_YAXIS_SIDE_LEFT]	= $this->calculateMinY(GRAPH_YAXIS_SIDE_LEFT);
		$this->m_minY[GRAPH_YAXIS_SIDE_RIGHT]	= $this->calculateMinY(GRAPH_YAXIS_SIDE_RIGHT);
		$this->m_maxY[GRAPH_YAXIS_SIDE_LEFT]	= $this->calculateMaxY(GRAPH_YAXIS_SIDE_LEFT);
		$this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT]	= $this->calculateMaxY(GRAPH_YAXIS_SIDE_RIGHT);

		$this->updateShifts();
		$this->calcTriggers();
		$this->CalcZero();
		
		$this->fullSizeX = $this->sizeX+$this->shiftXleft+$this->shiftXright+1;
		$this->fullSizeY = $this->sizeY+$this->shiftY+62+12*($this->num+ (($this->sizeY < 120) ? 0 : count($this->triggers)))+8;

		if(function_exists("ImageColorExactAlpha")&&function_exists("ImageCreateTrueColor")&&@imagecreatetruecolor(1,1))
			$this->im = imagecreatetruecolor($this->fullSizeX,$this->fullSizeY);
		else
			$this->im = imagecreate($this->fullSizeX,$this->fullSizeY);


		$this->initColors();
		$this->drawRectangle();
		$this->drawHeader();

		if($this->num==0)
		{
//				$this->noDataFound();
		}

		$this->drawWorkPeriod();
		$this->drawGrid();
		
		$maxX = $this->sizeX;
		
		// For each metric
		for($item = 0; $item < $this->num; $item++)
		{
			$minY = $this->m_minY[$this->items[$item]["axisside"]];
			$maxY = $this->m_maxY[$this->items[$item]["axisside"]];

			$data = &$this->data[$this->items[$item]["itemid"]][$this->items[$item]["calc_type"]];
			
			if(!isset($data))	continue;

			if($this->items[$item]["calc_type"] == GRAPH_ITEM_AGGREGATED){
				$drawtype	= GRAPH_ITEM_DRAWTYPE_LINE;

				$max_color	= $this->GetColor("HistoryMax");
				$avg_color	= $this->GetColor("HistoryAvg");
				$min_color	= $this->GetColor("HistoryMin");
				$minmax_color	= $this->GetColor("HistoryMinMax");

				$calc_fnc	= CALC_FNC_ALL;
			}
			elseif($this->type == GRAPH_TYPE_STACKED){
				$drawtype	= $this->items[$item]["drawtype"];

				$max_color	= $this->GetColor("ValueMax",GRAPH_STACKED_ALFA);
				$avg_color	= $this->GetColor($this->items[$item]["color"],GRAPH_STACKED_ALFA);
				$min_color	= $this->GetColor("ValueMin",GRAPH_STACKED_ALFA);
				$minmax_color	= $this->GetColor("ValueMinMax",GRAPH_STACKED_ALFA);

				$calc_fnc = $this->items[$item]["calc_fnc"];					
			}
			else{
				$drawtype	= $this->items[$item]["drawtype"];

				$max_color	= $this->GetColor("ValueMax");
				$avg_color	= $this->GetColor($this->items[$item]["color"]);
				$min_color	= $this->GetColor("ValueMin");
				$minmax_color	= $this->GetColor("ValueMinMax");

				$calc_fnc = $this->items[$item]["calc_fnc"];
			}
			// For each X
			for($i = 1, $j = 0; $i < $maxX; $i++) // new point
			{
				if(($data->count[$i] == 0) && ($i != ($maxX-1))) continue;

				$diff	= abs($data->clock[$i] - $data->clock[$j]);
				$cell	= ($this->to_time - $this->from_time)/$this->sizeX;
				$delay	= $this->items[$item]["delay"];
									
				if($cell > $delay)
					$draw = (boolean) ($diff < ZBX_GRAPH_MAX_SKIP_CELL * $cell);
				else		
					$draw = (boolean) ($diff < ZBX_GRAPH_MAX_SKIP_DELAY * $delay);

				if($draw == false && $this->items[$item]["calc_type"] == GRAPH_ITEM_AGGREGATED)
					$draw = $i - $j < 5;

				if($this->items[$item]["type"] == ITEM_TYPE_TRAPPER)
					$draw = true;
//					SDI($draw);
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
//					echo "\nDraw II \n"; printf("%0.4f",(getmicrotime()-$start_time));

				$j = $i;
			}
		}
		
		$this->DrawLeftSide();
		$this->DrawRightSide();
		$this->drawTriggers();

		$this->drawLogo();

		$this->drawLegend();
		
		$end_time=getmicrotime();
		$str=sprintf("%0.2f",(getmicrotime()-$start_time));
		ImageString($this->im, 0,$this->fullSizeX-120,$this->fullSizeY-12,"Generated in $str sec", $this->GetColor("Gray"));

		unset($this->items, $this->data);

		ImageOut($this->im); 
	}
}
?>
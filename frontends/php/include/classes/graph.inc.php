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

	define("GRAPH_TYPE_NORMAL",	0);
	define("GRAPH_TYPE_STACKED",	1);

	class	Graph
	{
		var $period;
		var $from;
		var $stime;
		var $sizeX;
		var $sizeY;
		var $shiftXleft;
		var $shiftXright;
		var $shiftY;
		var $border;

		var $fullSizeX;
		var $fullSizeY;

		var $m_showWorkPeriod;
		var $m_showTriggers;

		var $type; /* 0 - simple graph; 1 - stacked graph; */

		var $yaxistype;
		var $yaxismin;
		var $yaxismax;
		var $yaxisleft;
		var $yaxisright;
		var $m_minY;
		var $m_maxY;

		var $data = array();

		// items[num].data.min[max|avg]
		var $items;
		// $idnum[$num] is itemid
		var $min;
		var $max;
		var $avg;
		var $clock;
		var $count;
		// Number of items
		var $num;

		var $header;

		var $from_time;
		var $to_time;

		var $colors;
		var $im;

		var $triggers = array();

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
#			$this->sizeX = $this->sizeX - $this->shiftXleft-$this->shiftXright;
		}

		function initColors()
		{

			$colors = array(		/*  Red, Green, Blue, Alpha */
				"Red"			=> array(255,0,0,50),
				"Dark Red"		=> array(150,0,0,50),
				"Green"			=> array(0,255,0,50),
				"Dark Green"		=> array(0,150,0,50),
				"Blue"			=> array(0,0,255,50),
				"Dark Blue"		=> array(0,0,150,50),
				"Yellow"		=> array(255,255,0,50),
				"Dark Yellow"		=> array(150,150,0,50),
				"Cyan"			=> array(0,255,255,50),
				"Black"			=> array(0,0,0,50),
				"Gray"			=> array(150,150,150,50),
				"White"			=> array(255,255,255),
				"Dark Red No Alpha"	=> array(150,0,0),
				"Black No Alpha"	=> array(0,0,0),

				"HistoryMinMax"		=> array(90,150,185,50),
				"HistoryMax"		=> array(255,100,100,50),
				"HistoryMin"		=> array(50,255,50,50),
				"HistoryAvg"		=> array(50,50,50,50),

				"ValueMinMax"		=> array(255,255,150,50),
				"ValueMax"		=> array(255,180,180,50),
				"ValueMin"		=> array(100,255,100,50),

				"Priority Disaster"	=> array(255,0,0),
				"Priority Hight"	=> array(255,100,100),
				"Priority Average"	=> array(221,120,120),
				"Priority"		=> array(100,100,100),
				"Not Work Period"	=> array(230,230,230),

				"UncnownData"		=> array(130,130,130, 50)
			);

// I should rename No Alpha to Alpha at some point to get rid of some confusion
			foreach($colors as $name => $RGBA)
			{
				if(isset($RGBA[3]) && 
					function_exists("ImageColorExactAlpha") && 
					function_exists("ImageCreateTrueColor") && 
					@imagecreatetruecolor(1,1)
				)
				{
					$this->colors[$name]	= ImageColorExactAlpha($this->im,$RGBA[0],$RGBA[1],$RGBA[2],$RGBA[3]);
				}
				else
				{
					$this->colors[$name]	= ImageColorAllocate($this->im,$RGBA[0],$RGBA[1],$RGBA[2]);
				}
			}
		}

		function Graph($type = GRAPH_TYPE_NORMAL)
		{
			$this->period=3600;
			$this->from=0;
			$this->sizeX=900;
			$this->sizeY=200;
			$this->shiftXleft=10;
			$this->shiftXright=60;
			$this->shiftY=17;
			$this->border=1;
			$this->num=0;
			$this->type = $type;
			$this->yaxistype=GRAPH_YAXIS_TYPE_CALCULATED;
			$this->yaxisright=0;
			$this->yaxisleft=0;
			
			$this->m_showWorkPeriod = 1;
			$this->m_showTriggers = 1;

/*			if($this->period<=3600)
			{
				$this->date_format="H:i";
			}
			else
			{
				$this->date_format="m.d H:i";
			}*/

		}

		function ShowWorkPeriod($value)
		{
			$this->m_showWorkPeriod = $value == 1 ? 1 : 0;
		}

		function ShowTriggers($value)
		{
			$this->m_showTriggers = $value == 1 ? 1 : 0;;
		}
	
		function addItem($itemid, $axis, $calc_fnc = CALC_FNC_AVG, $color=null, $drawtype=null, $type=null, $periods_cnt=null)
		{
			$this->items[$this->num] = get_item_by_itemid($itemid);
			$this->items[$this->num]["description"]=item_description($this->items[$this->num]["description"],$this->items[$this->num]["key_"]);
			$host=get_host_by_hostid($this->items[$this->num]["hostid"]);

			$this->items[$this->num]["host"] = $host["host"];
			$this->items[$this->num]["color"] = is_null($color) ? "Dark Green" : $color;
			$this->items[$this->num]["drawtype"] = is_null($drawtype) ? GRAPH_DRAW_TYPE_LINE : $drawtype;
			$this->items[$this->num]["axisside"] = $axis;
			$this->items[$this->num]["calc_fnc"] = $calc_fnc;
			$this->items[$this->num]["type"] = is_null($type) ? GRAPH_ITEM_SIMPLE : $type;
			$this->items[$this->num]["periods_cnt"] = is_null($periods_cnt) ? 0 : $periods_cnt;

			if($axis==GRAPH_YAXIS_SIDE_LEFT)
				$this->yaxisleft=1;
			if($axis==GRAPH_YAXIS_SIDE_RIGHT)
				$this->yaxisright=1;
			$this->num++;
		}

		function setPeriod($period)
		{
			$this->period=$period;
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

		function setSTime($stime)
		{
			if($stime>200000000000 && $stime<220000000000)
			{
				$this->stime=mktime(substr($stime,8,2),substr($stime,10,2),0,substr($stime,4,2),substr($stime,6,2),substr($stime,0,4));
			}
		}

		function setFrom($from)
		{
			$this->from=$from;
		}

		function setWidth($value = NULL)
		{
// Avoid sizeX==0, to prevent division by zero later
			if($value <= 0) $value = NULL;
			if(is_null($value)) $value = 900;

			$this->sizeX = $value;
		}

		function setHeight($value = NULL)
		{
			if($value <= 0) $value = NULL;
			if(is_null($value)) $value = 900;

			$this->sizeY = $value;
		}

		function setBorder($border)
		{
			$this->border=$border;
		}

		function getLastValue($num)
		{
			$data = &$this->data[$this->items[$num]["itemid"]][$this->items[$num]["type"]];
			if(isset($data)) for($i=$this->sizeX-1;$i>=0;$i--)
			{
				if(isset($data->count[$i]) && ($data->count[$i] > 0))
				{
					switch($this->items[$num]["calc_fnc"])
					{
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

		function drawSmallRectangle()
		{
			DashedRectangle($this->im,
				$this->shiftXleft-1,
				$this->shiftY-1,
				$this->sizeX+$this->shiftXleft-1,
				$this->sizeY+$this->shiftY+1,
				$this->colors["Black No Alpha"]
				);
		}

		function drawRectangle()
		{
			ImageFilledRectangle($this->im,0,0,
				$this->fullSizeX,$this->fullSizeY,
				$this->colors["White"]);


			if($this->border==1)
			{
				ImageRectangle($this->im,0,0,$this->fullSizeX-1,$this->fullSizeY-1,$this->colors["Black No Alpha"]);
			}
		}

		function period2str($period)
		{
			$minute=60; $hour=$minute*60; $day=$hour*24;
			$str = " ( ";

			$days=floor($this->period/$day);
			$hours=floor(($this->period%$day)/$hour);
			$minutes=floor((($this->period%$day)%$hour)/$minute);
			$str.=($days>0 ? $days."d" : "").($hours>0 ?  $hours."h" : "").($minutes>0 ? $minutes."m" : "");
			$str.=" history ";

			$hour=1; $day=$hour*24;
			$days=floor($this->from/$day);
			$hours=floor(($this->from%$day)/$hour);
			$minutes=floor((($this->from%$day)%$hour)/$minute);
			$str.=($days>0 ? $days."d" : "").($hours>0 ?  $hours."h" : "").($minutes>0 ? $minutes."m" : "");
			$str.=($days+$hours+$minutes>0 ? " in past " : "");

			$str.=")";

			return $str;
		}

		function drawHeader()
		{
			if(!isset($this->header))
			{
				$str=$this->items[0]["host"].":".$this->items[0]["description"];
			}
			else
			{
				$str=$this->header;
			}

			$str=$str.$this->period2str($this->period);

			if($this->sizeX < 500)
			{
				$fontnum = 2;
			}
			else
			{
				$fontnum = 4;
			}
			$x=$this->fullSizeX/2-ImageFontWidth($fontnum)*strlen($str)/2;
			ImageString($this->im, $fontnum,$x,1, $str , $this->colors["Dark Red No Alpha"]);
		}

		function setHeader($header)
		{
			$this->header=$header;
		}

		function drawGrid()
		{
			$this->drawSmallRectangle();
			for($i=1;$i<=5;$i++)
			{
				DashedLine($this->im,$this->shiftXleft,$i*$this->sizeY/6+$this->shiftY,$this->sizeX+$this->shiftXleft,$i*$this->sizeY/6+$this->shiftY,$this->colors["Gray"]);
			}
		
			for($i=1;$i<=23;$i++)
			{
				DashedLine($this->im,$i*$this->sizeX/24+$this->shiftXleft,$this->shiftY,$i*$this->sizeX/24+$this->shiftXleft,$this->sizeY+$this->shiftY,$this->colors["Gray"]);
			}

			$old_day=-1;
			for($i=0;$i<=24;$i++)
			{
				ImageStringUp($this->im, 1,$i*$this->sizeX/24+$this->shiftXleft-3, $this->sizeY+$this->shiftY+57, date("      H:i",$this->from_time+$i*$this->period/24) , $this->colors["Black No Alpha"]);

				$new_day=date("d",$this->from_time+$i*$this->period/24);
				if( ($old_day != $new_day) ||($i==24))
				{
					$old_day=$new_day;
					ImageStringUp($this->im, 1,$i*$this->sizeX/24+$this->shiftXleft-3, $this->sizeY+$this->shiftY+57, date("m.d H:i",$this->from_time+$i*$this->period/24) , $this->colors["Dark Red No Alpha"]);

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
				$this->colors["Not Work Period"]);

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
					$this->colors["White"]);

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
					$this->colors[$trigger['color']]);
			}
			
		}

		function checkPermissions()
		{
			if(!check_right("Item","R",$this->items[0]["itemid"]))
			{
				$this->drawGrid();
				ImageString($this->im, 2,$this->sizeX/2 -50,$this->sizeY+$this->shiftY+3, "NO PERMISSIONS" , $this->colors["Dark Red No Alpha"]);
				ImageOut($this->im); 
				ImageDestroy($this->im); 
				exit;
			}
		}

		function drawLogo()
		{
			ImageStringUp($this->im,0,$this->fullSizeX-10,$this->fullSizeY-50, "http://www.zabbix.com", $this->colors["Gray"]);
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
				if($this->items[$i]["type"] == GRAPH_ITEM_AGGREGATED)
				{
					$fnc_name = "agr(".$this->items[$i]["periods_cnt"].")";
					$color = $this->colors["HistoryMinMax"];
				}
				else
				{
					$color = $this->colors[$this->items[$i]["color"]];
					switch($this->items[$i]["calc_fnc"])
					{
						case CALC_FNC_MIN:	$fnc_name = "min";	break;
						case CALC_FNC_MAX:	$fnc_name = "max";	break;
						case CALC_FNC_ALL:	$fnc_name = "all";	break;
						case CALC_FNC_AVG:
						default:		$fnc_name = "avg";
					}
				}

				$data = &$this->data[$this->items[$i]["itemid"]][$this->items[$i]["type"]];
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
				ImageRectangle($this->im,$this->shiftXleft,$this->sizeY+$this->shiftY+62+12*$i,$this->shiftXleft+5,$this->sizeY+$this->shiftY+5+62+12*$i,$this->colors["Black No Alpha"]);

				ImageString($this->im, 2,
					$this->shiftXleft+9,
					$this->sizeY+$this->shiftY+(62-5)+12*$i,
					$str,
					$this->colors["Black No Alpha"]);
			}

			if($this->sizeY < 120) return;

			foreach($this->triggers as $trigger)
			{
				ImageFilledEllipse($this->im,
					$this->shiftXleft + 2,
					$this->sizeY+$this->shiftY+2+62+12*$i,
					6,
					6,
					$this->colors[$trigger["color"]]);

				ImageEllipse($this->im,
					$this->shiftXleft + 2,
					$this->sizeY+$this->shiftY+2+62+12*$i,
					6,
					6,
					$this->colors["Black No Alpha"]);

				ImageString(
					$this->im, 
					2,
					$this->shiftXleft+9,
					$this->sizeY+$this->shiftY+(62-5)+12*$i,
					$trigger['description'],
					$this->colors["Black No Alpha"]);
				++$i;
			}
		}

		function drawElement(
			&$data, $from, $to, 
			$minX, $maxX, $minY, $maxY, 
			$drawtype, $max_color, $avg_color, $min_color, $minmax_color,
			$calc_fnc
			)
		{
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

			$y1min = $this->sizeY - ($this->sizeY*($min_from-$minY)/($maxY-$minY)) + $this->shiftY;
			$y2min = $this->sizeY - ($this->sizeY*($min_to-$minY)/($maxY-$minY)) + $this->shiftY;

			$y1max = $this->sizeY - ($this->sizeY*($max_from-$minY)/($maxY-$minY)) + $this->shiftY;
			$y2max = $this->sizeY - ($this->sizeY*($max_to-$minY)/($maxY-$minY)) + $this->shiftY;

			$y1avg = $this->sizeY - ($this->sizeY*($avg_from-$minY)/($maxY-$minY)) + $this->shiftY;
			$y2avg = $this->sizeY - ($this->sizeY*($avg_to-$minY)/($maxY-$minY)) + $this->shiftY;

			switch($calc_fnc)
			{
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
					$shift_from	= $shift_avg_from;
					$shift_to	= $shift_avg_to;

			}

			$y1_shift	= $this->sizeY - ($this->sizeY*$shift_from/($maxY-$minY)) + $this->shiftY;
			$y2_shift	= $this->sizeY - ($this->sizeY*$shift_to/($maxY-$minY)) + $this->shiftY;

			/* draw main line */
			switch($drawtype)
			{
				case GRAPH_DRAW_TYPE_BOLDLINE:
					ImageLine($this->im,$x1,$y1+1,$x2,$y2+1,$avg_color);
					// break; /* don't use break, must be drawed line also */
				case GRAPH_DRAW_TYPE_LINE:
					ImageLine($this->im,$x1,$y1,$x2,$y2,$avg_color);
					break;
				case GRAPH_DRAW_TYPE_FILL:
					$a[0] = $x1;		$a[1] = $y1;
					$a[2] = $x1;		$a[3] = $y1_shift;
					$a[4] = $x2;		$a[5] = $y2_shift;
					$a[6] = $x2;		$a[7] = $y2;

					ImageFilledPolygon($this->im,$a,4,$avg_color);
					break;
				case GRAPH_DRAW_TYPE_DOT:
					ImageFilledRectangle($this->im,$x1-1,$y1-1,$x1+1,$y1+1,$avg_color);
					ImageFilledRectangle($this->im,$x2-1,$y2-1,$x2+1,$y2+1,$avg_color);
					break;
			}
		}
// Calculation of maximum Y axis
		function calculateMinY($side)
		{
//			return 0;

			if($this->yaxistype==GRAPH_YAXIS_TYPE_FIXED)
			{
				return $this->yaxismin;
			}
			else
			{
				unset($minY);
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
							case CALC_FNC_ALL:	/* use min */
							case CALC_FNC_MIN:	$val = $data->min; $shift_val = $data->shift_min; break;
							case CALC_FNC_MAX:	$val = $data->max; $shift_val = $data->shift_max; break;
							case CALC_FNC_AVG:
							default:		$val = $data->avg; $shift_val = $data->shift_avg; 
						}

						if(!isset($val)) continue;

						if($this->type == GRAPH_TYPE_STACKED)
							for($ci=0; $ci < min(count($val), count($shift_val)); $ci++) 
								$val[$ci] -= $shift_val[$ci];

						if(!isset($minY))
						{
							if(isset($val) && count($val) > 0)
							{
								$minY = min($val);
							}
						}
						else
						{
							$minY = min($minY, min($val));
						}
					}
				}
	
				if(isset($minY)&&($minY>0))
				{
					$exp = round(log10($minY));
					$mant = $minY/pow(10,$exp);
				}
				else
				{
					$exp=0;
					$mant=0;
				}
	
				$mant=((round(($mant*11)/6)-1)*6)/10;
//				$mant=(floor($mant*1.1*10/6)+1)*6/10; /* MAX */
	
				$minY = $mant*pow(10,$exp);

				// Do not allow <0. However we may allow it, no problem.
				$minY = max(0,$minY);
	
				return $minY;
//				return 0;
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
	
				if(isset($maxY)&&($maxY>0))
				{
					$exp = floor(log10($maxY));
					$mant = $maxY/pow(10,$exp);
				}
				else
				{
					$exp=0;
					$mant=0;
				}
	
				$mant=(floor($mant*1.1*10/6)+1)*6/10;
	
				$maxY = $mant*pow(10,$exp);
	
				return $maxY;
			}
		}

		function selectData()
		{
			global $DB_TYPE;

			$this->data = array();

			$now = time(NULL);

			if(isset($this->stime))
			{
				$this->from_time	= $this->stime;
				$this->to_time		= $this->stime + $this->period;
			}
			else
			{
				$this->to_time		= $now - 3600 * $this->from;
				$this->from_time	= $this->to_time - $this->period;
			}

			$p = $this->to_time - $this->from_time;
			$z = $p - $this->from_time % $p;
			$x = $this->sizeX - 1;

			for($i=0; $i < $this->num; $i++)
			{
				$type = $this->items[$i]["type"];

				if($type == GRAPH_ITEM_AGGREGATED) {
					/* skip current period */
					$from_time	= $this->from_time - $this->period * $this->items[$i]["periods_cnt"];
					$to_time	= $this->from_time;
				} else {
					$from_time	= $this->from_time;
					$to_time	= $this->to_time;
				}
				
				$sql_arr = array();
				if($this->period <= 24*3600)
				{
					array_push($sql_arr,
						'select itemid,round('.$x.'*(mod(clock+'.$z.','.$p.'))/('.$p.'),0) as i,'.
						' count(*) as count,avg(value) as avg,min(value) as min,'.
						' max(value) as max,max(clock) as clock'.
						' from history where itemid='.$this->items[$i]['itemid'].' and clock>='.$from_time.
						' and clock<='.$to_time.' group by itemid, i' //' round('.$x.'*(mod(clock+'.$z.','.$p.'))/('.$p.'),0)',
						,

						'select itemid,round('.$x.'*(mod(clock+'.$z.','.$p.'))/('.$p.'),0) as i,'.
						' count(*) as count,avg(value) as avg,min(value) as min,'.
						' max(value) as max,max(clock) as clock'.
						' from history_uint where itemid='.$this->items[$i]['itemid'].' and clock>='.$from_time.
						' and clock<='.$to_time.' group by itemid, i' //' round('.$x.'*(mod(clock+'.$z.','.$p.'))/('.$p.'),0)'
						);
				}
				else
				{
					array_push($sql_arr,
						'select itemid,round('.$x.'*(mod(clock+'.$z.','.$p.'))/('.$p.'),0) as i,'.
						' sum(num) as count,avg(value_avg) as avg,min(value_min) as min,'.
						' max(value_max) as max,max(clock) as clock'.
						' from trends where itemid='.$this->items[$i]['itemid'].' and clock>='.$from_time.
						' and clock<='.$to_time.' group by itemid, i' //' round('.$x.'*(mod(clock+'.$z.','.$p.'))/('.$p.'),0)'
						);
				}

				$curr_data = &$this->data[$this->items[$i]["itemid"]][$type];
				$curr_data->count = NULL;
				$curr_data->min = NULL;
				$curr_data->max = NULL;
				$curr_data->avg = NULL;
				$curr_data->clock = NULL;

				foreach($sql_arr as $sql)
				{
					$result=DBselect($sql);
					while($row=DBfetch($result))
					{
						$idx=$row["i"];
						$curr_data->count[$idx]	= $row["count"];
						$curr_data->min[$idx]	= $row["min"];
						$curr_data->max[$idx]	= $row["max"];
						$curr_data->avg[$idx]	= $row["avg"];
						$curr_data->clock[$idx]	= $row["clock"];
						$curr_data->shift_min[$idx] = 0;
						$curr_data->shift_max[$idx] = 0;
						$curr_data->shift_avg[$idx] = 0;
					}
				}

				/* calculate missed points */
				$first_idx = 0;
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

						for(;$cj > 0; $cj--)
						{
							foreach(array('clock','min','max','avg') as $var_name)
							{
								$var = &$curr_data->$var_name;

								if($first_idx == $ci && $var_name == 'clock')
								{
									$var[$ci - $cj] = $var[$first_idx] - ($p / $this->sizeX * $cj);
									continue;
								}

								$dy = $var[$ci] - $var[$first_idx];
								$var[$ci - $cj] = $var[$first_idx] + ($cj * $dy) / $dx;
							}
						}
					}
				}
				if($cj > 0 && $ci > $cj)
				{
					$dx = $cj + 1;

					$first_idx = $ci - $dx;

					for(;$cj > 0; $cj--)
					{
						foreach(array('clock','min','max','avg') as $var_name)
						{
							$var = &$curr_data->$var_name;

							if($var_name == 'clock')
							{
								$var[$first_idx + $cj] = $var[$first_idx] + ($p / $this->sizeX * $cj);
								continue;
							}
							$var[$first_idx + $cj] = $var[$first_idx];
						}
					}
				}
				/* end of missed points calculation */

			}

			/* calculte shift for stacked graphs */
			if($this->type == GRAPH_TYPE_STACKED /* stacked graph */)
			{
				for($i=1; $i<$this->num; $i++)
				{
					$curr_data = &$this->data[$this->items[$i]["itemid"]][$this->items[$i]["type"]];

					if(!isset($curr_data))	continue;

					for($j = $i-1; $j >= 0; $j--)
					{
						if($this->items[$j]["axisside"] != $this->items[$i]["axisside"]) continue;

						$prev_data = &$this->data[$this->items[$j]["itemid"]][$this->items[$j]["type"]];

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
					ImageString($this->im, 1, 5, $this->sizeY-$this->sizeY*$i/6-4+$this->shiftY, $str, $this->colors["Dark Red No Alpha"]);
				}
			}
		}

		function DrawRightSide()
		{
			if($this->yaxisright == 1)
			{
				$minY = $this->m_minY[GRAPH_YAXIS_SIDE_RIGHT];
				$maxY = $this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT];

				for($item=0;$item<$this->num;$item++)
				{
					if($this->items[$item]["axisside"] == GRAPH_YAXIS_SIDE_RIGHT)
					{
						$units=$this->items[$item]["units"];
						break;
					}
				}
				for($i=0;$i<=6;$i++)
				{
					$str = str_pad(convert_units($this->sizeY*$i/6*($maxY-$minY)/$this->sizeY+$minY,$units),10," ");
					ImageString($this->im, 1, $this->sizeX+$this->shiftXleft+2, $this->sizeY-$this->sizeY*$i/6-4+$this->shiftY, $str, $this->colors["Dark Red No Alpha"]);
				}
			}
		}

		function Draw()
		{
			$start_time=getmicrotime();

//			$this->im = imagecreate($this->sizeX+$this->shiftX+61,$this->sizeY+2*$this->shiftY+40);

			set_image_header();

			check_authorisation();

			$this->selectData();

			$this->m_minY[GRAPH_YAXIS_SIDE_LEFT]	= $this->calculateMinY(GRAPH_YAXIS_SIDE_LEFT);
			$this->m_minY[GRAPH_YAXIS_SIDE_RIGHT]	= $this->calculateMinY(GRAPH_YAXIS_SIDE_RIGHT);
			$this->m_maxY[GRAPH_YAXIS_SIDE_LEFT]	= $this->calculateMaxY(GRAPH_YAXIS_SIDE_LEFT);
			$this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT]	= $this->calculateMaxY(GRAPH_YAXIS_SIDE_RIGHT);

			$this->updateShifts();

			$this->calcTriggers();

			$this->fullSizeX = $this->sizeX+$this->shiftXleft+$this->shiftXright+1;
			$this->fullSizeY = $this->sizeY+$this->shiftY+62+12*($this->num+ (($this->sizeY < 120) ? 0 : count($this->triggers)))+8;
		
			if(function_exists("ImageColorExactAlpha")&&function_exists("ImageCreateTrueColor")&&@imagecreatetruecolor(1,1))
				$this->im = ImageCreateTrueColor($this->fullSizeX,$this->fullSizeY);
			else
				$this->im = imagecreate($this->fullSizeX,$this->fullSizeY);

			$this->initColors();
			$this->drawRectangle();
			$this->drawHeader();

			if($this->num==0)
			{
//				$this->noDataFound();
			}

			$this->checkPermissions();


			$this->drawWorkPeriod();
			$this->drawGrid();

			$maxX = $this->sizeX;

			// For each metric
			for($item = 0; $item < $this->num; $item++)
			{
				$minY = $this->m_minY[$this->items[$item]["axisside"]];
				$maxY = $this->m_maxY[$this->items[$item]["axisside"]];

				$data = &$this->data[$this->items[$item]["itemid"]][$this->items[$item]["type"]];

				if(!isset($data))	continue;

				if($this->items[$item]["type"] == GRAPH_ITEM_AGGREGATED)
				{
					$drawtype	= GRAPH_DRAW_TYPE_LINE;

					$max_color	= $this->colors["HistoryMax"];
					$avg_color	= $this->colors["HistoryAvg"];
					$min_color	= $this->colors["HistoryMin"];
					$minmax_color	= $this->colors["HistoryMinMax"];

					$calc_fnc	= CALC_FNC_ALL;
				}
				else
				{
					$drawtype	= $this->items[$item]["drawtype"];

					$max_color	= $this->colors["ValueMax"];
					$avg_color	= $this->colors[$this->items[$item]["color"]];
					$min_color	= $this->colors["ValueMin"];
					$minmax_color	= $this->colors["ValueMinMax"];

					$calc_fnc = $this->items[$item]["calc_fnc"];
				}

				// For each X
				for($i = 1, $j = 0; $i < $maxX; $i++) // new point
				{
					if($data->count[$i] == 0) continue;

					$diff	= abs($data->clock[$i] - $data->clock[$j]);
					$cell	= ($this->to_time - $this->from_time)/$this->sizeX;
					$delay	= $this->items[$item]["delay"];

					if($cell > $delay)
						$draw = $diff < 16*$cell;
					else		
						$draw = $diff < 4*$delay;

					if($draw == false && $this->items[$item]["type"] == GRAPH_ITEM_AGGREGATED)
						$draw = $i - $j < 5;

					if($draw)
					{

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
							$calc_fnc
							);
					}
					$j = $i;
				}
			}
	

			$this->DrawLeftSide();
			$this->DrawRightSide();
			$this->drawTriggers();

			$this->drawLogo();

			$this->drawLegend();
		
			$end_time=getmicrotime();
			$str=sprintf("%0.2f",($end_time-$start_time));
			ImageString($this->im, 0,$this->fullSizeX-120,$this->fullSizeY-12,"Generated in $str sec", $this->colors["Gray"]);

			ImageOut($this->im); 
			ImageDestroy($this->im); 
		}
	}
?>

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

		var $yaxistype;
		var $yaxismin;
		var $yaxismax;
		var $yaxisleft;
		var $yaxisright;
		var $m_minY;
		var $m_maxY;

		// items[num].data.min[max|avg]
		var $items;
		// $idnum[$num] is itemid
		var $itemids;
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
// I should rename No Alpha to Alpha at some point to get rid of some confusion
			if(function_exists("ImageColorExactAlpha")&&function_exists("ImageCreateTrueColor")&&@imagecreatetruecolor(1,1))
			{
				$this->colors["Red"]=		ImageColorExactAlpha($this->im,255,0,0,50); 
				$this->colors["Dark Red"]=	ImageColorExactAlpha($this->im,150,0,0,50); 
				$this->colors["Green"]=		ImageColorExactAlpha($this->im,0,255,0,50); 
				$this->colors["Dark Green"]=	ImageColorExactAlpha($this->im,0,150,0,50); 
				$this->colors["Blue"]=		ImageColorExactAlpha($this->im,0,0,255,50); 
				$this->colors["Dark Blue"]=	ImageColorExactAlpha($this->im,0,0,150,50); 
				$this->colors["Yellow"]=	ImageColorExactAlpha($this->im,255,255,0,50); 
				$this->colors["Dark Yellow"]=	ImageColorExactAlpha($this->im,150,150,0,50); 
				$this->colors["Cyan"]=		ImageColorExactAlpha($this->im,0,255,255,50); 
				$this->colors["Black"]=		ImageColorExactAlpha($this->im,0,0,0,50); 
				$this->colors["Gray"]=		ImageColorExactAlpha($this->im,150,150,150,50);

			}
			else
			{
				$this->colors["Red"]=ImageColorAllocate($this->im,255,0,0); 
				$this->colors["Dark Red"]=ImageColorAllocate($this->im,150,0,0); 
				$this->colors["Green"]=ImageColorAllocate($this->im,0,255,0); 
				$this->colors["Dark Green"]=ImageColorAllocate($this->im,0,150,0); 
				$this->colors["Blue"]=ImageColorAllocate($this->im,0,0,255); 
				$this->colors["Dark Blue"]=ImageColorAllocate($this->im,0,0,150); 
				$this->colors["Yellow"]=ImageColorAllocate($this->im,255,255,0); 
				$this->colors["Dark Yellow"]=ImageColorAllocate($this->im,150,150,0); 
				$this->colors["Cyan"]=ImageColorAllocate($this->im,0,255,255); 
				$this->colors["Black"]=ImageColorAllocate($this->im,0,0,0); 
				$this->colors["Gray"]=ImageColorAllocate($this->im,150,150,150); 
			
			}
			$this->colors["White"]=			ImageColorAllocate($this->im,255,255,255);
			$this->colors["Dark Red No Alpha"]=	ImageColorAllocate($this->im,150,0,0); 
			$this->colors["Black No Alpha"]=	ImageColorAllocate($this->im,0,0,0); 

			$this->colors["Priority Disaster"]=	ImageColorAllocate($this->im,255,0,0); 
			$this->colors["Priority Hight"]=	ImageColorAllocate($this->im,255,100,100); 
			$this->colors["Priority Average"]=	ImageColorAllocate($this->im,221,120,120); 
			$this->colors["Priority"]=		ImageColorAllocate($this->im,100,100,100); 
//			$this->colors["Priority Disaster"]=		$this->colors["Dark Red No Alpha"]; 
//			$this->colors["Priority Hight"]=		$this->colors["Dark Red No Alpha"]; 
//			$this->colors["Priority Average"]=		$this->colors["Dark Red No Alpha"]; 
//			$this->colors["Priority"]=		$this->colors["Dark Red No Alpha"]; 

			$this->colors["Not Work Period"]=	ImageColorAllocate($this->im,230,230,230); 
		}

		function Graph()
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
			$this->yaxistype=GRAPH_YAXIS_TYPE_CALCULATED;
			$this->yaxisright=0;
			$this->yaxisleft=0;
			
			$this->m_showWorkPeriod = 1;
			$this->m_showTriggers = 1;

			$this->count=array();
			$this->min=array();
			$this->max=array();
			$this->avg=array();
			$this->clock=array();

			$this->itemids=array();


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
	
		function addItem($itemid, $axis)
		{
			$this->items[$this->num]=get_item_by_itemid($itemid);
			$this->items[$this->num]["description"]=item_description($this->items[$this->num]["description"],$this->items[$this->num]["key_"]);
			$host=get_host_by_hostid($this->items[$this->num]["hostid"]);
			$this->items[$this->num]["host"]=$host["host"];
			$this->itemids[$this->items[$this->num]["itemid"]]=$this->num;
			$this->items[$this->num]["color"]="Dark Green";
			$this->items[$this->num]["drawtype"]=GRAPH_DRAW_TYPE_LINE;
			$this->items[$this->num]["axisside"]=$axis;
			if($axis==GRAPH_YAXIS_SIDE_LEFT)
				$this->yaxisleft=1;
			if($axis==GRAPH_YAXIS_SIDE_RIGHT)
				$this->yaxisright=1;
			$this->num++;
		}

		function SetColor($itemid,$color)
		{
			$this->items[$this->itemids[$itemid]]["color"]=$color;
		}

		function setDrawtype($itemid,$drawtype)
		{
			$this->items[$this->itemids[$itemid]]["drawtype"]=$drawtype;
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

		function setWidth($width)
		{
// Avoid sizeX==0, to prevent division by zero later
			if($width>0)
			{
				$this->sizeX=$width-20;
			}
		}

		function setHeight($height)
		{
			$this->sizeY=$height;
		}

		function setBorder($border)
		{
			$this->border=$border;
		}

		function getLastValue($num)
		{
			for($i=899;$i>=0;$i--)
			{
				if(isset($this->count[$num][$i])&&($this->count[$num][$i]>0))
				{
					return $this->avg[$num][$i];
				}
			}
		}

		function drawSmallRectangle()
		{
			DashedLine($this->im,$this->shiftXleft+1,$this->shiftY,$this->shiftXleft+1,$this->sizeY+$this->shiftY,$this->colors["Black No Alpha"]);
			DashedLine($this->im,$this->shiftXleft+1,$this->shiftY,$this->sizeX+$this->shiftXleft,$this->shiftY,$this->colors["Black No Alpha"]);
			DashedLine($this->im,$this->sizeX+$this->shiftXleft,$this->shiftY,$this->sizeX+$this->shiftXleft,$this->sizeY+$this->shiftY,$this->colors["Black No Alpha"]);
			DashedLine($this->im,$this->shiftXleft+1,$this->shiftY+$this->sizeY,$this->sizeX+$this->shiftXleft,$this->sizeY+$this->shiftY,$this->colors["Black No Alpha"]);
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

			$db_triggers = DBselect('select distinct tr.triggerid,tr.expression,tr.priority from triggers tr,functions f,items i'.
				' where tr.triggerid=f.triggerid and f.function in ("last","min","max") and'.
				' tr.status='.TRIGGER_STATUS_ENABLED.' and f.itemid='.$this->items[0]["itemid"].' order by tr.priority');

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
				if(strlen($this->items[$i]["host"])>$max_host_len)	$max_host_len=strlen($this->items[$i]["host"]);
				if(strlen($this->items[$i]["description"])>$max_desc_len)	$max_desc_len=strlen($this->items[$i]["description"]);
			}

			for($i=0;$i<$this->num;$i++)
			{
				ImageFilledRectangle($this->im,$this->shiftXleft,$this->sizeY+$this->shiftY+62+12*$i,$this->shiftXleft+5,$this->sizeY+$this->shiftY+5+62+12*$i,$this->colors[$this->items[$i]["color"]]);
				ImageRectangle($this->im,$this->shiftXleft,$this->sizeY+$this->shiftY+62+12*$i,$this->shiftXleft+5,$this->sizeY+$this->shiftY+5+62+12*$i,$this->colors["Black No Alpha"]);

				if(isset($this->min[$i]))
				{
					$str=sprintf("%s: %s [min:%s max:%s last:%s]",
						str_pad($this->items[$i]["host"],$max_host_len," "),
						str_pad($this->items[$i]["description"],$max_desc_len," "),
						convert_units(min($this->min[$i]),$this->items[$i]["units"]),
						convert_units(max($this->max[$i]),$this->items[$i]["units"]),
						convert_units($this->getLastValue($i),$this->items[$i]["units"]));
				}
				else
				{
					$str=sprintf("%s: %s [ no data ]",
						str_pad($this->items[$i]["host"],$max_host_len," "),
						str_pad($this->items[$i]["description"],$max_desc_len," "));
				}
	
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

		function drawElement($item,$x1,$y1,$x2,$y2)
		{
			if($this->items[$item]["drawtype"] == GRAPH_DRAW_TYPE_LINE)
			{
				ImageLine($this->im,$x1,$y1,$x2,$y2,$this->colors[$this->items[$item]["color"]]);
			}
			else if($this->items[$item]["drawtype"] == GRAPH_DRAW_TYPE_BOLDLINE)
			{
				ImageLine($this->im,$x1,$y1,$x2,$y2,$this->colors[$this->items[$item]["color"]]);
				ImageLine($this->im,$x1,$y1+1,$x2,$y2+1,$this->colors[$this->items[$item]["color"]]);
			}
			else if($this->items[$item]["drawtype"] == GRAPH_DRAW_TYPE_FILL)
			{
				$a[0]=$x1;
				$a[1]=$y1;
				$a[2]=$x1;
				$a[3]=$this->shiftY+$this->sizeY;
				$a[4]=$x2;
				$a[5]=$this->shiftY+$this->sizeY;
				$a[6]=$x2;
				$a[7]=$y2;

				ImageFilledPolygon($this->im,$a,4,$this->colors[$this->items[$item]["color"]]);
			}
			else if($this->items[$item]["drawtype"] == GRAPH_DRAW_TYPE_DOT)
			{
				ImageFilledRectangle($this->im,$x1-1,$y1-1,$x1+1,$y1+1,$this->colors[$this->items[$item]["color"]]);
				ImageFilledRectangle($this->im,$x2-1,$y2-1,$x2+1,$y2+1,$this->colors[$this->items[$item]["color"]]);
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
					if($this->items[$i]["axisside"] != $side)	continue;
					if(!isset($minY)&&(isset($this->min[$i])))
					{
						if(count($this->max[$i])>0)
						{
							$minY=min($this->avg[$i]);
						}
					}
					else
					{
						$minY=@iif($minY>min($this->avg[$i]),min($this->avg[$i]),$minY);
					}
				}
	
				if(isset($minY)&&($minY>0))
				{
					$exp = floor(log10($minY));
					$mant = $minY/pow(10,$exp);
				}
				else
				{
					$exp=0;
					$mant=0;
				}
	
				$mant=(floor($mant*1.1*10/6)-1)*6/10;
//				$mant=(floor($mant*1.1*10/6)+1)*6/10;
	
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
					if($this->items[$i]["axisside"] != $side)	continue;
					if(!isset($maxY)&&(isset($this->max[$i])))
					{
						if(count($this->max[$i])>0)
						{
							$maxY=max($this->avg[$i]);
						}
					}
					else
					{
						$maxY=@iif($maxY<max($this->avg[$i]),max($this->avg[$i]),$maxY);
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
			$now = time(NULL);
			if(isset($this->stime))
			{
#				$this->to_time=$this->stime+24*3600;
#				$this->from_time=$this->stime;
				$this->from_time=$this->stime;
				$this->to_time=$this->stime+$this->period;
			}
			else
			{
				$this->to_time=$now-3600*$this->from;
				$this->from_time=$this->to_time-$this->period;
			}
		
			$p=$this->to_time-$this->from_time;
			$z=$p-$this->from_time%$p;

			$str=" ";
			for($i=0;$i<$this->num;$i++)
			{
				$str=$str.$this->items[$i]["itemid"].",";
			}
			$str=substr($str,0,strlen($str)-1);
			if($str=="")	$str=-1;

			$sql_arr = array();
			if($this->period<=24*3600)
			{
//				$sql="select itemid,round(900*((clock+$z)%($p))/($p),0) as i,count(*) as count,avg(value) as avg,min(value) as min,max(value) as max,max(clock) as clock from history where itemid in ($str) and clock>=".$this->from_time." and clock<=".$this->to_time." group by itemid,round(900*((clock+$z)%($p))/($p),0)";
				array_push($sql_arr,
					"select itemid,round(900*((clock+$z)%($p))/($p),0) as i,count(*) as count,avg(value) as avg,min(value) as min,max(value) as max,max(clock) as clock from history where itemid in ($str) and clock>=".$this->from_time." and clock<=".$this->to_time." group by itemid,round(900*((clock+$z)%($p))/($p),0)",
					"select itemid,round(900*((clock+$z)%($p))/($p),0) as i,count(*) as count,avg(value) as avg,min(value) as min,max(value) as max,max(clock) as clock from history_uint where itemid in ($str) and clock>=".$this->from_time." and clock<=".$this->to_time." group by itemid,round(900*((clock+$z)%($p))/($p),0)");
			}
			else
			{
				array_push($sql_arr,
					"select itemid,round(900*((clock+$z)%($p))/($p),0) as i,sum(num) as count,avg(value_avg) as avg,min(value_min) as min,max(value_max) as max,max(clock) as clock from trends where itemid in ($str) and clock>=".$this->from_time." and clock<=".$this->to_time." group by itemid,round(900*((clock+$z)%($p))/($p),0)");
			}
//			echo "<br>",$sql,"<br>";

			foreach($sql_arr as $sql)
			{
				$result=DBselect($sql);
				while($row=DBfetch($result))
				{
					$i=$row["i"];
					$this->count[$this->itemids[$row["itemid"]]][$i]=$row["count"];
					$this->min[$this->itemids[$row["itemid"]]][$i]=$row["min"];
					$this->max[$this->itemids[$row["itemid"]]][$i]=$row["max"];
					$this->avg[$this->itemids[$row["itemid"]]][$i]=$row["avg"];
					$this->clock[$this->itemids[$row["itemid"]]][$i]=$row["clock"];
				}
			}
		}

		function DrawLeftSide()
		{
			if($this->yaxisleft == 1)
			{
				$minY = $this->m_minY[GRAPH_YAXIS_SIDE_RIGHT];
				$maxY = $this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT];

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

//			Header( "Content-type:  text/html"); 
//*
			Header( "Content-type:  image/png"); 
			Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 
/**/

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

//			ImageString($this->im, 0, 100, 100, $this->shiftXright, $this->colors["Red"]);
//			ImageString($this->im, 0, 120, 120, $this->sizeX, $this->colors["Red"]);
		
			$maxX=900;
			$minX=0;

			// For each metric
			for($item=0;$item<$this->num;$item++)
			{
				$minY = $this->m_minY[$this->items[$item]["axisside"]];
				$maxY = $this->m_maxY[$this->items[$item]["axisside"]];

				// For each X
				for($i=0;$i<900;$i++)
				{
					if(isset($this->count[$item][$i])&&($this->count[$item][$i]>0))
					{
						for($j=$i-1;$j>=0;$j--)
						{
							if(isset($this->count[$item][$j])&&($this->count[$item][$j]>0))
							{
								$x1=$this->sizeX*($i-$minX)/($maxX-$minX);
								$y1=$this->sizeY*($this->avg[$item][$i]-$minY)/($maxY-$minY);
								$y1=$this->sizeY-$y1;

								$x2=$this->sizeX*($j-$minX)/($maxX-$minX);
								$y2=$this->sizeY*($this->avg[$item][$j]-$minY)/($maxY-$minY);
								$y2=$this->sizeY-$y2;

								// Do not draw anything if difference between two points is more than 4*(item refresh period)
//								if($this->clock[$item][$i]-$this->clock[$item][$j]<4*$this->items[$item]["delay"])
//								echo 8*($this->to_time-$this->from_time)/900,"<br>";
//								echo $this->clock[$item][$i]-$this->clock[$item][$j],"<br>";
								$diff=$this->clock[$item][$i]-$this->clock[$item][$j];
								$cell=($this->to_time-$this->from_time)/900;
								$delay=$this->items[$item]["delay"];
								if($cell>$delay)
								{
									if($diff<16*$cell)
										$this->drawElement($item, $x1+$this->shiftXleft,$y1+$this->shiftY,$x2+$this->shiftXleft+1,$y2+$this->shiftY);
								}
								else
								{
									if($diff<4*$delay)
										$this->drawElement($item, $x1+$this->shiftXleft,$y1+$this->shiftY,$x2+$this->shiftXleft+1,$y2+$this->shiftY);
								}
								break;
							}
						}
					}
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

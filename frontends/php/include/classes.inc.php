<?php 
/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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
		var $shiftX;
		var $shiftY;
		var $border;

		// items[num].data.min[max|avg]
		var $items;
		// $idnum[$num] is itemid
		var $itemids;
		var $min;
		var $max;
		var $avg;
		var $count;
		// Number of items
		var $num;
		// 1 - if thereis nothing to draw
		var $nodata;

		var $header;

		var $from_time;
		var $to_time;

		var $colors;
		var $im;

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

				$this->colors["White"]=			ImageColorAllocate($this->im,255,255,255);
				$this->colors["Dark Red No Alpha"]=	ImageColorAllocate($this->im,150,0,0); 
				$this->colors["Black No Alpha"]=	ImageColorAllocate($this->im,0,0,0); 
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
				$this->colors["White"]=ImageColorAllocate($this->im,255,255,255);

				$this->colors["Dark Red No Alpha"]=	ImageColorAllocate($this->im,150,0,0); 
				$this->colors["Black No Alpha"]=	ImageColorAllocate($this->im,0,0,0); 
			}
		}

		function Graph()
		{
			$this->period=3600;
			$this->from=0;
			$this->sizeX=900;
			$this->sizeY=200;
			$this->shiftX=10;
			$this->shiftY=17;
			$this->border=1;
			$this->num=0;
			$this->nodata=1;

			$this->count=array();
			$this->min=array();
			$this->max=array();
			$this->avg=array();

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

		function addItem($itemid)
		{
			$this->items[$this->num]=get_item_by_itemid($itemid);
			$host=get_host_by_hostid($this->items[$this->num]["hostid"]);
			$this->items[$this->num]["host"]=$host["host"];
			$this->itemids[$this->items[$this->num]["itemid"]]=$this->num;
			$this->items[$this->num]["color"]="Dark Green";
			$this->items[$this->num]["drawtype"]=GRAPH_DRAW_TYPE_LINE;
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
// Avoid sizeX==0, to prevent division bu zero later
			if($width>0)
			{
				$this->sizeX=$width;
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
			DashedLine($this->im,$this->shiftX+1,$this->shiftY,$this->shiftX+1,$this->sizeY+$this->shiftY,$this->colors["Black No Alpha"]);
			DashedLine($this->im,$this->shiftX+1,$this->shiftY,$this->shiftX+$this->sizeX,$this->shiftY,$this->colors["Black No Alpha"]);
			DashedLine($this->im,$this->shiftX+$this->sizeX,$this->shiftY,$this->shiftX+$this->sizeX,$this->sizeY+$this->shiftY,$this->colors["Black No Alpha"]);
			DashedLine($this->im,$this->shiftX+1,$this->shiftY+$this->sizeY,$this->shiftX+$this->sizeX,$this->sizeY+$this->shiftY,$this->colors["Black No Alpha"]);
		}

		function drawRectangle()
		{
			ImageFilledRectangle($this->im,0,0,$this->sizeX+$this->shiftX+61,$this->sizeY+$this->shiftY+62+12*$this->num+8,$this->colors["White"]);
			if($this->border==1)
			{
				ImageRectangle($this->im,0,0,imagesx($this->im)-1,imagesy($this->im)-1,$this->colors["Black No Alpha"]);
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


			if($this->sizeX < 300)
			{
				$fontnum = 2;
			}
			else
			{
				$fontnum = 4;
			}
			$x=imagesx($this->im)/2-ImageFontWidth($fontnum)*strlen($str)/2;
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
				DashedLine($this->im,$this->shiftX,$i*$this->sizeY/6+$this->shiftY,$this->sizeX+$this->shiftX,$i*$this->sizeY/6+$this->shiftY,$this->colors["Gray"]);
			}
		
			for($i=1;$i<=23;$i++)
			{
				DashedLine($this->im,$i*$this->sizeX/24+$this->shiftX,$this->shiftY,$i*$this->sizeX/24+$this->shiftX,$this->sizeY+$this->shiftY,$this->colors["Gray"]);
			}

// Some data exists, so draw time line
			if($this->nodata==0)
			{
				$old_day=-1;
				for($i=0;$i<=24;$i++)
				{
					ImageStringUp($this->im, 1,$i*$this->sizeX/24+$this->shiftX-3, $this->sizeY+$this->shiftY+57, date("      H:i",$this->from_time+$i*$this->period/24) , $this->colors["Black No Alpha"]);

					$new_day=date("d",$this->from_time+$i*$this->period/24);
					if( ($old_day != $new_day) ||($i==24))
					{
						$old_day=$new_day;
						ImageStringUp($this->im, 1,$i*$this->sizeX/24+$this->shiftX-3, $this->sizeY+$this->shiftY+57, date("m.d H:i",$this->from_time+$i*$this->period/24) , $this->colors["Dark Red No Alpha"]);

					}
				}
			}
		}

		function checkPermissions()
		{
			if(!check_right("Item","R",$this->items[0]["itemid"]))
			{
				$this->drawGrid();
				ImageString($this->im, 2,$this->sizeX/2 -50,$this->sizeY+$this->shiftY+3, "NO PERMISSIONS" , $this->colors["Dark Red No Alpha"]);
				ImagePng($this->im); 
				ImageDestroy($this->im); 
				exit;
			}
		}

		function noDataFound()
		{
			$this->drawGrid();

			ImageString($this->im, 2,$this->sizeX/2-50,                $this->sizeY+$this->shiftY+3, "NO DATA FOUND FOR THIS PERIOD" , $this->colors["Dark Red No Alpha"]);
			ImageStringUp($this->im,0,imagesx($this->im)-10,imagesy($this->im)-50, "http://www.zabbix.com", $this->colors["Gray"]);
			ImagePng($this->im); 
			ImageDestroy($this->im); 
			exit;
		}

		function drawLogo()
		{
			ImageStringUp($this->im,0,imagesx($this->im)-10,imagesy($this->im)-50, "http://www.zabbix.com", $this->colors["Gray"]);
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
				ImageFilledRectangle($this->im,$this->shiftX,$this->sizeY+$this->shiftY+62+12*$i,$this->shiftX+5,$this->sizeY+$this->shiftY+5+62+12*$i,$this->colors[$this->items[$i]["color"]]);
				ImageRectangle($this->im,$this->shiftX,$this->sizeY+$this->shiftY+62+12*$i,$this->shiftX+5,$this->sizeY+$this->shiftY+5+62+12*$i,$this->colors["Black No Alpha"]);

				if(isset($this->min[$i]))
				{
					$str=sprintf("%s: %s [min:%s max:%s last:%s]",
						str_pad($this->items[$i]["host"],$max_host_len," "),
						str_pad($this->items[$i]["description"],$max_desc_len," "),
						convert_units(min($this->min[$i]),$this->items[$i]["units"],$this->items[$i]["multiplier"]),
						convert_units(max($this->max[$i]),$this->items[$i]["units"],$this->items[$i]["multiplier"]),
						convert_units($this->getLastValue($i),$this->items[$i]["units"],$this->items[$i]["multiplier"]));
				}
				else
				{
					$str=sprintf("%s: %s [ no data ]",
						str_pad($this->items[$i]["host"],$max_host_len," "),
						str_pad($this->items[$i]["description"],$max_desc_len," "));
				}
	
				ImageString($this->im, 2,$this->shiftX+9,$this->sizeY+$this->shiftY+(62-5)+12*$i,$str, $this->colors["Black No Alpha"]);
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

// Calculation of maximum Y
		function calculateMaxY()
		{
			unset($maxY);
			for($i=0;$i<$this->num;$i++)
			{
				if(!isset($maxY))
				{
					if(count($this->max[$i])>0)
					{
						$maxY=max($this->max[$i]);
					}
				}
				else
				{
					$maxY=@iif($maxY<max($this->max[$i]),max($this->max[$i]),$maxY);
				}
			}

			if($maxY>0)
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

/*			if($mant<1.5)
			{
				$mant=1.5;
			}
			elseif($mant<2)
			{
				$mant=2;
			}
			elseif($mant<3)
			{
				$mant=3;
			}
			elseif($mant<5)
			{
				$mant=5;
			}
			elseif($mant<8)
			{
				$mant=8;
			}
			else
			{
				$mant=10;
			}
*/
			$maxY = $mant*pow(10,$exp);

			return $maxY;
		}

		function selectData()
		{
			$now = time(NULL);
			if(isset($this->stime))
			{
				$this->to_time=$this->stime+24*3600;
				$this->from_time=$this->stime;
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

			if($this->period<=24*3600)
			{
				$sql="select itemid,round(900*((clock+$z)%($p))/($p),0) as i,count(*) as count,avg(value) as avg,min(value) as min,max(value) as max from history where itemid in ($str) and clock>=".$this->from_time." and clock<=".$this->to_time." group by itemid,round(900*((clock+$z)%($p))/($p),0)";
			}
			else
			{
				$sql="select itemid,round(900*((clock+$z)%($p))/($p),0) as i,sum(num) as count,avg(value_avg) as avg,min(value_min) as min,max(value_max) as max from trends where itemid in ($str) and clock>=".$this->from_time." and clock<=".$this->to_time." group by itemid,round(900*((clock+$z)%($p))/($p),0)";
			}
//			echo $sql;

			$result=DBselect($sql);
			while($row=DBfetch($result))
			{
				$i=$row["i"];
				$this->count[$this->itemids[$row["itemid"]]][$i]=$row["count"];
				$this->min[$this->itemids[$row["itemid"]]][$i]=$row["min"];
				$this->max[$this->itemids[$row["itemid"]]][$i]=$row["max"];
				$this->avg[$this->itemids[$row["itemid"]]][$i]=$row["avg"];
				$this->nodata=0;
			}
		}

		function Draw()
		{
			$start_time=getmicrotime();

//			$this->im = imagecreate($this->sizeX+$this->shiftX+61,$this->sizeY+2*$this->shiftY+40);

//			Header( "Content-type:  text/html"); 
			Header( "Content-type:  image/png"); 
			Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 

			check_authorisation();
		
			if(function_exists("ImageColorExactAlpha")&&function_exists("ImageCreateTrueColor")&&@imagecreatetruecolor(1,1))
			{
				$this->im = ImageCreateTrueColor($this->sizeX+$this->shiftX+61,$this->sizeY+$this->shiftY+62+12*$this->num+8);
			}
			else
			{
				$this->im = imagecreate($this->sizeX+$this->shiftX+61,$this->sizeY+$this->shiftY+62+12*$this->num+8);
			}

			$this->initColors();
			$this->drawRectangle();
			$this->drawHeader();

			if($this->num==0)
			{
				$this->noDataFound();
			}
			$this->checkPermissions();

			$this->selectData();
			if($this->nodata==1)
			{
				$this->noDataFound();
			}

			$this->drawGrid();
		
			$maxX=900;
			$minX=0;

			$minY=0;
			$maxY=$this->calculateMaxY();

			for($item=0;$item<$this->num;$item++)
			{
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

								$this->drawElement($item, $x1+$this->shiftX,$y1+$this->shiftY,$x2+$this->shiftX+1,$y2+$this->shiftY);
								break;
							}
						}
					}
				}
			}
		
			if($this->nodata == 0)
			{
				for($i=0;$i<=6;$i++)
				{
					ImageString($this->im, 1, $this->sizeX+5+$this->shiftX, $this->sizeY-$this->sizeY*$i/6-4+$this->shiftY, convert_units($this->sizeY*$i/6*($maxY-$minY)/$this->sizeY+$minY,$this->items[0]["units"],$this->items[0]["multiplier"]) , $this->colors["Dark Red No Alpha"]);
				}
			}
			else
			{
				ImageString($this->im, 2,$this->sizeX/2 -50,$this->sizeY+$this->shiftY+3, "NO DATA FOR THIS PERIOD" , $this->colors["Dark Red No Alpha"]);
			}

			$this->drawLogo();

			$this->drawLegend();
		
			$end_time=getmicrotime();
			$str=sprintf("%0.2f",($end_time-$start_time));
			ImageString($this->im, 0,imagesx($this->im)-120,imagesy($this->im)-12,"Generated in $str sec", $this->colors["Gray"]);

			ImagePng($this->im); 
			ImageDestroy($this->im); 
		}
	}
?>

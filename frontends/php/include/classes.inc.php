<?php 
/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003 Alexei Vladishev
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
		var $date_format;

		function initColors()
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
			for($i=0;$i<900;$i++) $count[$i]=0;
			$this->min=array();
			$this->max=array();
			$this->avg=array();

			$this->itemids=array();


			if($this->period<=3600)
			{
				$this->date_format="H:i";
			}
			else
			{
				$this->date_format="m.d H:i";
			}
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

		function setFrom($from)
		{
			$this->from=$from;
		}

		function setWidth($width)
		{
			$this->sizeX=$width;
		}

		function setHeight($height)
		{
			$this->sizeY=$height;
		}

		function setBorder($border)
		{
			$this->border=$border;
		}

		function drawSmallRectangle()
		{
			ImageDashedLine($this->im,$this->shiftX+1,$this->shiftY,$this->shiftX+1,$this->sizeY+$this->shiftY,$this->colors["Black"]);
			ImageDashedLine($this->im,$this->shiftX+1,$this->shiftY,$this->shiftX+$this->sizeX,$this->shiftY,$this->colors["Black"]);
			ImageDashedLine($this->im,$this->shiftX+$this->sizeX,$this->shiftY,$this->shiftX+$this->sizeX,$this->sizeY+$this->shiftY,$this->colors["Black"]);
			ImageDashedLine($this->im,$this->shiftX+1,$this->shiftY+$this->sizeY,$this->shiftX+$this->sizeX,$this->sizeY+$this->shiftY,$this->colors["Black"]);
		}

		function drawRectangle()
		{
			ImageFilledRectangle($this->im,0,0,$this->sizeX+$this->shiftX+61,$this->sizeY+2*$this->shiftY+40,$this->colors["White"]);
			if($this->border==1)
			{
				ImageRectangle($this->im,0,0,imagesx($this->im)-1,imagesy($this->im)-1,$this->colors["Black"]);
			}
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
			$x=imagesx($this->im)/2-ImageFontWidth(4)*strlen($str)/2;
			ImageString($this->im, 4,$x,1, $str , $this->colors["Dark Red"]);
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
				ImageDashedLine($this->im,$this->shiftX,$i*$this->sizeY/6+$this->shiftY,$this->sizeX+$this->shiftX,$i*$this->sizeY/6+$this->shiftY,$this->colors["Gray"]);
			}
		
			for($i=1;$i<=23;$i++)
			{
				ImageDashedLine($this->im,$i*$this->sizeX/24+$this->shiftX,$this->shiftY,$i*$this->sizeX/24+$this->shiftX,$this->sizeY+$this->shiftY,$this->colors["Gray"]);
			}

// Some data exists, so draw time line
			if($this->nodata==0)
			{
				for($i=0;$i<=$this->sizeX;$i+=$this->sizeX/24)
				{
					ImageStringUp($this->im, 1,$i+$this->shiftX-3, $this->sizeY+$this->shiftY+29, date($this->date_format,$this->from_time+$i*$this->period/$this->sizeX) , $this->colors["Black"]);
				}
			}
		}

		function checkPermissions()
		{
			if(!check_right("Item","R",$this->items[0]["itemid"]))
			{
				ImagePng($this->im); 
				ImageDestroy($this->im); 
				exit;
			}
		}

		function noDataFound()
		{
			$this->drawGrid();

			ImageString($this->im, 2,$this->sizeX/2-50,                $this->sizeY+$this->shiftY+3, "NO DATA FOUND FOR THIS PERIOD" , $this->colors["Dark Red"]);
			ImageStringUp($this->im,0,imagesx($this->im)-10,imagesy($this->im)-50, "http://zabbix.sourceforge.net", $this->colors["Gray"]);
			ImagePng($this->im); 
			ImageDestroy($this->im); 
			exit;
		}

		function drawLogo()
		{
			ImageStringUp($this->im,0,imagesx($this->im)-10,imagesy($this->im)-50, "http://zabbix.sourceforge.net", $this->colors["Gray"]);
		}

		function drawLegend()
		{
			for($i=0;$i<$this->num;$i++)
			{
			}

				ImageFilledRectangle($this->im,$this->shiftX,$this->sizeY+$this->shiftY+35,$this->shiftX+5,$this->sizeY+$this->shiftY+5+35,$this->colors["Dark Green"]);
				ImageRectangle($this->im,$this->shiftX,$this->sizeY+$this->shiftY+35,$this->shiftX+5,$this->sizeY+$this->shiftY+5+35,$this->colors["Black"]);

				$max_host_len=strlen($this->items[0]["host"]);
				$max_desc_len=strlen($this->items[0]["description"]);
//		for($i=0;$i<DBnum_rows($result2);$i++)
//		{
//			$z=get_item_by_itemid($iids[$i]);
//			$h=get_host_by_hostid($z["hostid"]);
//			if(strlen($h["host"])>$max_host_len)		$max_host_len=strlen($h["host"]);
//			if(strlen($z["description"])>$max_desc_len)	$max_desc_len=strlen($z["description"]);
//		}
//		$i=get_item_by_itemid($iids[$item]);
			$str=sprintf("%s: %s [min:%s max:%s]",
				str_pad($this->items[0]["host"],$max_host_len," "),
				str_pad($this->items[0]["description"],$max_desc_len," "),
				convert_units(min($this->min[0]),$this->items[0]["units"],$this->items[0]["multiplier"]),
				convert_units(max($this->max[0]),$this->items[0]["units"],$this->items[0]["multiplier"]));

			ImageString($this->im, 2,$this->shiftX+9,$this->sizeY+$this->shiftY+30,$str, $this->colors["Black"]);
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
		}

		function SelectData()
		{
			$now = time(NULL);
			$this->to_time=$now-3600*$this->from;
			$this->from_time=$this->to_time-$this->period-3600*$this->from;
		
			$p=$this->to_time-$this->from_time;
			$z=$p-$this->from_time%$p;

			$str=" ";
			for($i=0;$i<$this->num;$i++)
			{
				$str=$str.$this->items[$i]["itemid"].",";
			}
			$str=substr($str,0,strlen($str)-1);

			$sql="select itemid,round(900*((clock+$z)%($p))/($p),0) as i,count(*) as count,avg(value) as avg,min(value) as min,max(value) as max from history where itemid in ($str) and clock>=".$this->from_time." and clock<=".$this->to_time." group by itemid,round(900*((clock+$z)%($p))/($p),0)";
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

			$this->im = imagecreate($this->sizeX+$this->shiftX+61,$this->sizeY+2*$this->shiftY+40);

//			Header( "Content-type:  text/html"); 
			Header( "Content-type:  image/png"); 
			Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 

			check_authorisation();
		
			$this->im = imagecreate($this->sizeX+$this->shiftX+61,$this->sizeY+2*$this->shiftY+40);

			$this->initColors();
			$this->drawRectangle();
			$this->drawHeader();

			if($this->num==0)
			{
				$this->noDataFound();
			}
			$this->checkPermissions();

			$this->SelectData();

			$this->drawGrid();
		
			$maxX=900;
			$minX=0;
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

			$minY=0;
// Calculation of maximum Y value
			for($i=-10;$i<11;$i++)
			{
				$m=pow(10,$i);
				if($m>$maxY)
				{
					if($m/2>$maxY)
					{
						$maxY=$m/2;
					}
					break;
				}
			}
		
			if(isset($minY)&&isset($maxY)&&($minX!=$maxX)&&($minY!=$maxY))
			{
				for($item=0;$item<$this->num;$item++)
				{
					for($i=0;$i<900;$i++)
					{
						if(isset($this->count[$item][$i])&&($this->count[$item][$i]>0))
						{
//							for($j=$i-1;($j>=0)&&($j>$i-10);$j--)
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

									$this->drawElement($item, $x1+$this->shiftX,$y1+$this->shiftY,$x2+$this->shiftX,$y2+$this->shiftY);
									break;
								}
							}
						}
					}
				}
			}
		
			if($this->nodata == 0)
			{
				for($i=0;$i<=6;$i++)
				{
					ImageString($this->im, 1, $this->sizeX+5+$this->shiftX, $this->sizeY-$this->sizeY*$i/6-4+$this->shiftY, convert_units($this->sizeY*$i/6*($maxY-$minY)/$this->sizeY+$minY,$this->items[0]["units"],$this->items[0]["multiplier"]) , $this->colors["Dark Red"]);
				}
			}
			else
			{
				ImageString($this->im, 2,$this->sizeX/2 -50,$this->sizeY+$this->shiftY+3, "NO DATA FOR THIS PERIOD" , $this->colors["Dark Red"]);
			}
			ImageStringUp($this->im,0,imagesx($this->im)-10,imagesy($this->im)-50, "http://zabbix.sourceforge.net", $this->colors["Gray"]);
	



			$this->drawLegend();
		
			$end_time=getmicrotime();
			$str=sprintf("%0.2f",($end_time-$start_time));
			ImageString($this->im, 0,imagesx($this->im)-120,imagesy($this->im)-12,"Generated in $str sec", $this->colors["Gray"]);

			ImagePng($this->im); 
			ImageDestroy($this->im); 
		}
	}
?>

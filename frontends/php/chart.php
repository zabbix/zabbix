<?php 
	include "include/config.inc.php";

	class	Graph
	{
		var $period;
		var $from;
		var $sizeX;
		var $sizeY;
		var $itemid;
		var $shiftX;
		var $shiftY;
		var $border;

		var $item;

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
			$this->colors["Yellow"]=ImageColorAllocate($this->im,255,255,0); 
			$this->colors["Dark Yellow"]=ImageColorAllocate($this->im,150,150,0); 
			$this->colors["Cyan"]=ImageColorAllocate($this->im,0,255,255); 
			$this->colors["Black"]=ImageColorAllocate($this->im,0,0,0); 
			$this->colors["Gray"]=ImageColorAllocate($this->im,150,150,150); 
			$this->colors["White"]=ImageColorAllocate($this->im,255,255,255); 
		}

		function Graph($itemid)
		{
			$this->period=3600;
			$this->from=0;
			$this->sizeX=900;
			$this->sizeY=200;
			$this->shiftX=10;
			$this->shiftY=17;
			$this->border=1;
			$this->itemid=$itemid;

			$this->item=get_item_by_itemid($this->itemid);
			$host=get_host_by_hostid($this->item["hostid"]);
			$this->item["host"]=$host["host"];

			$this->date_format="H:i";
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
			$this->width=$width;
		}

		function setBorder($border)
		{
			$this->border=$border;
		}

		function drawRectangle()
		{
			ImageFilledRectangle($this->im,0,0,$this->sizeX+$this->shiftX+61,$this->sizeY+2*$this->shiftY+40,$this->colors["White"]);
			if($this->border==1)
			{
				ImageRectangle($this->im,0,0,imagesx($this->im)-1,imagesy($this->im)-1,$this->colors["Black"]);
			}
			ImageDashedLine($this->im,$this->shiftX+1,$this->shiftY,$this->shiftX+1,$this->sizeY+$this->shiftY,$this->colors["Black"]);
			ImageDashedLine($this->im,$this->shiftX+1,$this->shiftY,$this->shiftX+$this->sizeX,$this->shiftY,$this->colors["Black"]);
			ImageDashedLine($this->im,$this->shiftX+$this->sizeX,$this->shiftY,$this->shiftX+$this->sizeX,$this->sizeY+$this->shiftY,$this->colors["Black"]);
			ImageDashedLine($this->im,$this->shiftX+1,$this->shiftY+$this->sizeY,$this->shiftX+$this->sizeX,$this->sizeY+$this->shiftY,$this->colors["Black"]);
		}

		function drawHeader()
		{
			$str=$this->item["host"].":".$this->item["description"];
			$x=imagesx($this->im)/2-ImageFontWidth(4)*strlen($str)/2;
			ImageString($this->im, 4,$x,1, $str , $this->colors["Dark Red"]);
		}

		function noDataFound()
		{
			ImageString($this->im, 2,$this->sizeX/2-50,                $this->sizeY+$this->shiftY+3, "NO DATA FOUND FOR THIS PERIOD" , $this->colors["Dark Red"]);
			ImageStringUp($this->im,0,imagesx($this->im)-10,imagesy($this->im)-50, "http://zabbix.sourceforge.net", $this->colors["Gray"]);
			ImagePng($this->im); 
			ImageDestroy($this->im); 
		}

		function drawLogo()
		{
			ImageStringUp($this->im,0,imagesx($this->im)-10,imagesy($this->im)-50, "http://zabbix.sourceforge.net", $this->colors["Gray"]);
		}

		function Draw()
		{
			$this->im = imagecreate($this->sizeX+$this->shiftX+61,$this->sizeY+2*$this->shiftY+40);
			$nodata=1;

//			Header( "Content-type:  text/html"); 
			Header( "Content-type:  image/png"); 
			Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 

			check_authorisation();
		
			$this->im = imagecreate($this->sizeX+$this->shiftX+61,$this->sizeY+2*$this->shiftY+40);

			$this->initColors();
			$this->drawRectangle();
			$this->drawHeader();

			if(!check_right("Item","R",$this->itemid))
			{
				ImagePng($this->im); 
				ImageDestroy($this->im); 
				exit;
			}

			$from_time = time(NULL)-$this->period-3600*$this->from;
			$to_time   = time(NULL)-3600*$this->from;
			$result=DBselect("select count(clock),min(clock),max(clock),min(value),max(value) from history where itemid=".$this->itemid." and clock>$from_time and clock<$to_time ");
			$count=DBget_field($result,0,0);
			if($count>0)
			{
				$nodata=0;
				$minX=DBget_field($result,0,1);
				$maxX=DBget_field($result,0,2);
				$minY=DBget_field($result,0,3);
				$maxY=DBget_field($result,0,4);
		
			}
			else
			{
				$this->noDataFound();
				exit;
			}

			$my_exp = floor(log10($maxY));
			$my_mant = $maxY/pow(10,$my_exp);

			if ($my_mant < 1.5 )
			{
				$my_mant = 1.5;
				$my_steps = 5;
			}
			elseif($my_mant < 2 )
			{
				$my_mant = 2;
				$my_steps = 4;
			}
			elseif($my_mant < 3 )
			{
				$my_mant = 3;
				$my_steps = 6;
			}
			elseif($my_mant < 5 )
			{
				$my_mant = 5;
				$my_steps = 5;
			}
			elseif($my_mant < 8 )
			{
				$my_mant = 8;
				$my_steps = 4;
			}
			else
			{
				$my_mant = 10;
				$my_steps = 5;
			}
			$maxY = $my_mant*pow(10,$my_exp);
			$minY = 0;
	
//	echo "MIN/MAX:",$minX," - ",$maxX," - ",$minY," - ",$maxY,"<Br>";

			if(isset($minX)&&($minX!=$maxX)&&($minY!=$maxY))
			{
				$result=DBselect("select clock,value from history where itemid=".$this->itemid." and clock>$from_time and clock<$to_time order by clock");
				for($i=0;$i<DBnum_rows($result)-1;$i++)
				{
					$x=DBget_field($result,$i,0);
					$x_next=DBget_field($result,$i+1,0);
					$y=DBget_field($result,$i,1);
					$y_next=DBget_field($result,$i+1,1);

					$x1=$this->sizeX*($x-$minX)/($maxX-$minX);
					$y1=$this->sizeY*($y-$minY)/($maxY-$minY);
					$x2=$this->sizeX*($x_next-$minX)/($maxX-$minX);
					$y2=$this->sizeY*($y_next-$minY)/($maxY-$minY);

					$y1=$this->sizeY-$y1;
					$y2=$this->sizeY-$y2;

					ImageLine($this->im,$x1+$this->shiftX,$y1+$this->shiftY,$x2+$this->shiftX,$y2+$this->shiftY,$this->colors["Dark Green"]);
//					ImageSetPixel($this->im,$x2+$this->shiftX,$y2+$this->shiftY-1,$this->colors["Dark Red"]);
				}
			}
			else
			{
				if(isset($minX))
				{
					ImageLine($this->im,$this->shiftX,$this->shiftY+$this->sizeY/2,$this->sizeX+$this->shiftX,$this->shiftY+$this->sizeY/2,$this->colors["Dark Green"]);
				}
			}

			$startTime=$minX;
			if (($maxX-$minX) < 300)
				$precTime=10;
			elseif (($maxX-$minX) < 3600 )
				$precTime=60;
			else
				$precTime=300;
		
			if (($maxX-$minX) < 1200 )
				$dateForm="H:i:s";
			else
				$dateForm="H:i:s";
		
			$correctTime=$startTime % $precTime;
			$stepTime=ceil(ceil(($maxX-$minX)/20)/$precTime)*(1.0*$precTime);

			for($i=1;$i<$my_steps;$i++)
			{
				ImageDashedLine($this->im,$this->shiftX,$i/$my_steps*$this->sizeY+$this->shiftY,$this->sizeX+$this->shiftX,$i/$my_steps*$this->sizeY+$this->shiftY,$this->colors["Gray"]);
			}
			for($j=$stepTime-$correctTime;$j<=($maxX-$minX);$j+=$stepTime)
			{
				ImageDashedLine($this->im,$this->shiftX+($this->sizeX*$j)/($maxX-$minX),$this->shiftY,$this->shiftX+($this->sizeX*$j)/($maxX-$minX),$this->sizeY+$this->shiftY,$this->colors["Gray"]);
			}

		
			if($nodata == 0)
			{
				for($i=0;$i<=$my_steps;$i++)
				{
					ImageString($this->im, 1, $this->sizeX+5+$this->shiftX, $i/$my_steps*$this->sizeY+$this->shiftY-4, convert_units($maxY-$i/$my_steps*($maxY-$minY),$this->item["units"],$this->item["multiplier"]) , $this->colors["Dark Red"]);
				}
				for($j=$stepTime-$correctTime;$j<=($maxX-$minX);$j+=$stepTime)
				{
					ImageStringUp($this->im,0,$this->shiftX+($this->sizeX*$j)/($maxX-$minX),$this->shiftY+$this->sizeY+53,date($dateForm,$startTime+$j),$this->colors["Black"]);
				}
		
				ImageString($this->im, 1,10,                $this->sizeY+$this->shiftY+5, date("dS of F Y H:i:s",$minX) , $this->colors["Dark Red"]);
				ImageString($this->im, 1,$this->sizeX+$this->shiftX-148,$this->sizeY+$this->shiftY+5, date("dS of F Y H:i:s",$maxX) , $this->colors["Dark Red"]);
			}
			else
			{
				ImageString($this->im, 2,$this->sizeX/2-50,                $this->sizeY+$this->shiftY+3, "NO DATA FOUND FOR THIS PERIOD" , $this->colors["Dark Red"]);
			}
		
			$this->drawLogo();
			
		
			ImagePng($this->im); 
			ImageDestroy($this->im); 
		}

		function Draw2()
		{
			$this->im = imagecreate($this->sizeX+$this->shiftX+61,$this->sizeY+2*$this->shiftY+40);
			$nodata=1;

			Header( "Content-type:  text/html"); 
//			Header( "Content-type:  image/png"); 
			Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 

			check_authorisation();
		
			$this->im = imagecreate($this->sizeX+$this->shiftX+61,$this->sizeY+2*$this->shiftY+40);

			$this->initColors();
			$this->drawRectangle();
			$this->drawHeader();

			if(!check_right("Item","R",$this->itemid))
			{
				ImagePng($this->im); 
				ImageDestroy($this->im); 
				exit;
			}

			$from_time = time(NULL)-$this->period-3600*$this->from;
			$to_time   = time(NULL)-3600*$this->from;

			for($i=0;$i<=$this->sizeX;$i+=$this->sizeX/24)
			{
//				ImageDashedLine($this->im,$i+$this->shiftX,$this->shiftY,$i+$this->shiftX,$this->sizeY+$this->shiftY,$this->colors["Gray"]);
				$label_format="H:i";
				ImageString($this->im, 1,$i+$this->shiftX-11, $this->sizeY+$this->shiftY+5, date($label_format,$from_time+$this->period*($i/$this->sizeX)) , $this->colors["Black"]);
			}

			$p=$to_time-$from_time;
			$z=$from_time%$p;
			$count=array();
			$min=array();
			$max=array();
			$avg=array();

			$sql="select round(900*((clock+$z)%($p))/($p)) as i,count(*) as count,avg(value) as avg,min(value) as min,max(value) as max from history where itemid=".$this->itemid ." and clock>$from_time and clock<$to_time group by round(900*((clock+$z)%($p))/($p))";
			$result=DBselect($sql);
			while($row=DBfetch($result))
			{
				$i=$row["i"];
				$count[$i]=$row["count"];
				$min[$i]=$row["min"];
				$max[$i]=$row["max"];
				$avg[$i]=$row["avg"];
				$nodata=0;
			}

			if($nodata!=0)
			{
				$this->noDataFound();
				exit;
			}

//	echo "MIN/MAX:",$minX," - ",$maxX," - ",$minY," - ",$maxY,"<Br>";
	$minX=0;
	$maxX=900;
	$maxY=max($avg);
	$minY=min($avg);

	if(isset($minY)&&($maxY)&&($minX!=$maxX)&&($minY!=$maxY))
	{
		for($i=0;$i<900;$i++)
		{
			if($count[$i]>0)
			{
				$x1=$this->sizeX*($i-$minX)/($maxX-$minX);
				$y1=$this->sizeY*($avg[$i]-$minY)/($maxY-$minY);
				$x1=$this->sizeX-$x1;
				$y1=$this->sizeY-$y1;

				for($j=$i-1;$j>=0;$j--)
				{
					if($count[$j]>0)
					{
						$x2=$this->sizeX*($j-$minX)/($maxX-$minX);
						$y2=$this->sizeY*($avg[$j]-$minY)/($maxY-$minY);
						$x2=$this->sizeX-$x2;
						$y2=$this->sizeY-$y2;
						ImageLine($this->im,$x1+$this->shiftX,$y1+$this->shiftY,$x2+$this->shiftX,$y2+$this->shiftY,$this->colors["Dark Green"]);
						break;
					}
				}
			}
//			echo $this->sizeX*($i-$minX)/($maxX-$minX),":",$y1,"<br>";
		}
	}

			$startTime=$minX;
			if (($maxX-$minX) < 300)
				$precTime=10;
			elseif (($maxX-$minX) < 3600 )
				$precTime=60;
			else
				$precTime=300;
		
			if (($maxX-$minX) < 1200 )
				$dateForm="H:i:s";
			else
				$dateForm="H:i:s";
		
			$correctTime=$startTime % $precTime;
			$stepTime=ceil(ceil(($maxX-$minX)/20)/$precTime)*(1.0*$precTime);

/*			for($i=1;$i<$my_steps;$i++)
			{
				ImageDashedLine($this->im,$this->shiftX,$i/$my_steps*$this->sizeY+$this->shiftY,$this->sizeX+$this->shiftX,$i/$my_steps*$this->sizeY+$this->shiftY,$this->colors["Gray"]);
			}
			for($j=$stepTime-$correctTime;$j<=($maxX-$minX);$j+=$stepTime)
			{
				ImageDashedLine($this->im,$this->shiftX+($this->sizeX*$j)/($maxX-$minX),$this->shiftY,$this->shiftX+($this->sizeX*$j)/($maxX-$minX),$this->sizeY+$this->shiftY,$this->colors["Gray"]);
			}*/

		
			if($nodata == 0)
			{
/*				for($i=0;$i<=$my_steps;$i++)
				{
					ImageString($this->im, 1, $this->sizeX+5+$this->shiftX, $i/$my_steps*$this->sizeY+$this->shiftY-4, convert_units($maxY-$i/$my_steps*($maxY-$minY),$this->item["units"],$this->item["multiplier"]) , $this->colors["Dark Red"]);
				}*/
				for($j=$stepTime-$correctTime;$j<=($maxX-$minX);$j+=$stepTime)
				{
//					ImageStringUp($this->im,0,$this->shiftX+($this->sizeX*$j)/($maxX-$minX),$this->shiftY+$this->sizeY+53,date($dateForm,$startTime+$j),$this->colors["Black"]);
				}
		
//				ImageString($this->im, 1,10,                $this->sizeY+$this->shiftY+5, date("dS of F Y H:i:s",$minX) , $this->colors["Dark Red"]);
//				ImageString($this->im, 1,$this->sizeX+$this->shiftX-148,$this->sizeY+$this->shiftY+5, date("dS of F Y H:i:s",$maxX) , $this->colors["Dark Red"]);
			}
			else
			{
				ImageString($this->im, 2,$this->sizeX/2-50,                $this->sizeY+$this->shiftY+3, "NO DATA FOUND FOR THIS PERIOD" , $this->colors["Dark Red"]);
			}
		
			$this->drawLogo();
			
		
			ImagePng($this->im); 
			ImageDestroy($this->im); 
		}

		function Draw3()
		{
			$start_time=time(NULL);

			$this->im = imagecreate($this->sizeX+$this->shiftX+61,$this->sizeY+2*$this->shiftY+40);
			$nodata=1;

//			Header( "Content-type:  text/html"); 
			Header( "Content-type:  image/png"); 
			Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 

			check_authorisation();
		
			$this->im = imagecreate($this->sizeX+$this->shiftX+61,$this->sizeY+2*$this->shiftY+40);

			$this->initColors();
			$this->drawRectangle();
			$this->drawHeader();
			if(!check_right("Item","R",$this->itemid))
			{
				ImagePng($this->im); 
				ImageDestroy($this->im); 
				exit;
			}

			$now = time(NULL);
//			$to_time=$now-$now%$this->period;
			$to_time=$now;
//			$from_time=$to_time-17*$this->period;
			$from_time=$to_time-$this->period;
		
			$count=array();
			for($i=0;$i<900;$i++) $count[$i]=0;
			$min=array();
			$max=array();
			$avg=array();
			$p=$to_time-$from_time;
//			$z=$from_time%$p;
			$z=$p-$from_time%$p;
			$sql="select round(900*((clock+$z)%($p))/($p)) as i,count(*) as count,avg(value) as avg,min(value) as min,max(value) as max from history where itemid=".$this->item["itemid"]." and clock>=$from_time and clock<=$to_time group by round(900*((clock+$z)%($p))/($p))";
//			$sql="select round(900*((clock+3*3600)%(3600))/(3600)) as i,count(*) as count,avg(value) as avg,min(value) as min,max(value) as max from history where itemid=".$this->item["itemid"]." and clock>=$from_time and clock<=$to_time group by round(900*((clock+3*3600)%($p))/($p))";
//			echo $sql,"<br>";
//			echo $to_time-$from_time,"<br>";

			$result=DBselect($sql);
			while($row=DBfetch($result))
			{
				$i=$row["i"];
				$count[$i]=$row["count"];
				$min[$i]=$row["min"];
				$max[$i]=$row["max"];
				$avg[$i]=$row["avg"];
				$nodata=0;
			}
		
		
			for($i=0;$i<=$this->sizeY;$i+=$this->sizeY/6)
			{
				ImageDashedLine($this->im,$this->shiftX,$i+$this->shiftY,$this->sizeX+$this->shiftX,$i+$this->shiftY,$this->colors["Gray"]);
			}
		
			for($i=0;$i<=$this->sizeX;$i+=$this->sizeX/24)
			{
				ImageDashedLine($this->im,$i+$this->shiftX,$this->shiftY,$i+$this->shiftX,$this->sizeY+$this->shiftY,$this->colors["Gray"]);
				if($nodata == 0)
				{
					ImageStringUp($this->im, 1,$i+$this->shiftX-3, $this->sizeY+$this->shiftY+29, date($this->date_format,$from_time+$i*$this->period/$this->sizeX) , $this->colors["Black"]);
//					echo $from_time," ",$to_time," ",$from_time+$i*$this->period/$this->sizeX,"<br>";
				}
			}
		
			$maxX=900;
			$minX=0;
			$maxY=max($max);
			$minY=min($min);
			$minY=0;
//			$maxY=30000;
		#	echo "MIN/MAX:",$minX," - ",$maxX," - ",$minY," - ",$maxY,"<Br>";
		
			if(isset($minY)&&($maxY)&&($minX!=$maxX)&&($minY!=$maxY))
			{
				for($i=0;$i<900;$i++)
				{
					if($count[$i]>0)
					{
						$x1=$this->sizeX*($i-$minX)/($maxX-$minX);
						$y1=$this->sizeY*($max[$i]-$minY)/($maxY-$minY);
						$y1=$this->sizeY-$y1;
						for($j=$i-1;$j>=0;$j--)
						{
							if($count[$j]>0)
							{
								$x2=$this->sizeX*($j-$minX)/($maxX-$minX);
								$y2=$this->sizeY*($max[$j]-$minY)/($maxY-$minY);
								$y2=$this->sizeY-$y2;
								ImageLine($this->im,$x1+$this->shiftX,$y1+$this->shiftY,$x2+$this->shiftX,$y2+$this->shiftY,$this->colors["Dark Red"]);
								break;
							}
						}

						$x1=$this->sizeX*($i-$minX)/($maxX-$minX);
						$y1=$this->sizeY*($avg[$i]-$minY)/($maxY-$minY);
						$y1=$this->sizeY-$y1;
						for($j=$i-1;$j>=0;$j--)
						{
							if($count[$j]>0)
							{
								$x2=$this->sizeX*($j-$minX)/($maxX-$minX);
								$y2=$this->sizeY*($avg[$j]-$minY)/($maxY-$minY);
								$y2=$this->sizeY-$y2;
								ImageLine($this->im,$x1+$this->shiftX,$y1+$this->shiftY,$x2+$this->shiftX,$y2+$this->shiftY,$this->colors["Dark Yellow"]);
								break;
							}
						}

						$x1=$this->sizeX*($i-$minX)/($maxX-$minX);
						$y1=$this->sizeY*($min[$i]-$minY)/($maxY-$minY);
						$y1=$this->sizeY-$y1;
						for($j=$i-1;$j>=0;$j--)
						{
							if($count[$j]>0)
							{
								$x2=$this->sizeX*($j-$minX)/($maxX-$minX);
								$y2=$this->sizeY*($min[$j]-$minY)/($maxY-$minY);
								$y2=$this->sizeY-$y2;
								ImageLine($this->im,$x1+$this->shiftX,$y1+$this->shiftY,$x2+$this->shiftX,$y2+$this->shiftY,$this->colors["Dark Green"]);
								break;
							}
						}
					}
				}
			}
		
			if($nodata == 0)
			{
				for($i=0;$i<=$this->sizeY;$i+=$this->sizeY/6)
				{
					ImageString($this->im, 1, $this->sizeX+5+$this->shiftX, $this->sizeY-$i-4+$this->shiftY, convert_units($i*($maxY-$minY)/$this->sizeY+$minY,$this->item["units"],$this->item["multiplier"]) , $this->colors["Dark Red"]);
				}
			}
			else
			{
				ImageString($this->im, 2,$this->sizeX/2 -50,$this->sizeY+$this->shiftY+3, "NO DATA FOR THIS PERIOD" , $this->colors["Dark Red"]);
			}

{
//		ImageFilledRectangle($this->im,$this->shiftX,$this->sizeY+$this->shiftY+25*(0)+20,$this->shiftX+5,$this->sizeY+$this->shiftY+9+25*(0)+20,$this->colors["Dark Green"]);
//		ImageRectangle($this->im,$this->shiftX,$this->sizeY+$this->shiftY+25*(0)+20,$this->shiftX+5,$this->sizeY+$this->shiftY+9+25*(0)+20,$this->colors["Black"]);
/*		ImageRectangle($im,$shiftX,$sizeY+$shiftYup+19+15*$item+45,$shiftX+5,$sizeY+$shiftYup+15+9+15*$item+45,$black);
		$max_host_len=0;
		$max_desc_len=0;
		for($i=0;$i<DBnum_rows($result2);$i++)
		{
			$z=get_item_by_itemid($iids[$i]);
			$h=get_host_by_hostid($z["hostid"]);
			if(strlen($h["host"])>$max_host_len)		$max_host_len=strlen($h["host"]);
			if(strlen($z["description"])>$max_desc_len)	$max_desc_len=strlen($z["description"]);
		}
		$i=get_item_by_itemid($iids[$item]);
		$str=sprintf("%s: %s [last:%s min:%s avg:%s max:%s]", str_pad($host[$item],$max_host_len," "), str_pad($desc[$item],$max_desc_len," "), convert_units($y[$item][$len[$item]-1],$i["units"],$i["multiplier"]), convert_units($itemMin,$i["units"],$i["multiplier"]), convert_units($itemAvg,$i["units"],$i["multiplier"]), convert_units($itemMax,$i["units"],$i["multiplier"]));
		if($width>600)
		{
			ImageString($im, 2,$shiftX+9,$sizeY+$shiftYup+15*$item+15+45,$str, $black);
		}
		else
		{
			ImageString($im, 0,$shiftX+9,$sizeY+$shiftYup+15*$item+17+45,$str, $black);
		}
	}*/
}
		
			ImageString($this->im, 1,$this->shiftX, $this->sizeY+$this->shiftY+35, "MIN" , $this->colors["Dark Green"]);
			ImageString($this->im, 1,$this->shiftX+20, $this->sizeY+$this->shiftY+35, "AVG" , $this->colors["Dark Yellow"]);
			ImageString($this->im, 1,$this->shiftX+40, $this->sizeY+$this->shiftY+35, "MAX" , $this->colors["Dark Red"]);
		
			ImageStringUp($this->im,0,imagesx($this->im)-10,imagesy($this->im)-50, "http://zabbix.sourceforge.net", $this->colors["Gray"]);
		
			$end_time=time(NULL);
			ImageString($this->im, 0,imagesx($this->im)-100,imagesy($this->im)-12,"Generated in ".($end_time-$start_time)." sec", $this->colors["Gray"]);
		
			ImagePng($this->im); 
			ImageDestroy($this->im); 
		}
	}
		
	$graph=new Graph($HTTP_GET_VARS["itemid"]);
	if(isset($HTTP_GET_VARS["period"]))
	{
		$graph->setPeriod($HTTP_GET_VARS["period"]);
	}
	if(isset($HTTP_GET_VARS["from"]))
	{
		$graph->setFrom($HTTP_GET_VARS["from"]);
	}
	if(isset($HTTP_GET_VARS["width"]))
	{
		$graph->setWidth($HTTP_GET_VARS["width"]);
	}
	if(isset($HTTP_GET_VARS["border"]))
	{
		$graph->setBorder(0);
	}

	$graph->Draw3();
?>


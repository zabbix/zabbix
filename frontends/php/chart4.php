<? 
	include "include/config.inc.php";

#	PARAMETERS:
	
#	itemid
#	type
#	trendavg

	if(!isset($type))
	{
		$type="week";
	}

	if($type == "month")
	{
		$period=24*30*2400;
	}
	else if($type == "week")
	{
		$period=7*24*3600;
	}
	else if($type == "year")
	{
		$period=12*30*24*3600;
	}
	else
	{
		$type="week";
		$period=7*24*3600;
	}

	$sizeX=900;
	$sizeY=200;

	$shiftX=12;
	$shiftY=15;

	$nodata=1;	


//	Header( "Content-type:  text/html"); 
	Header( "Content-type:  image/png"); 
	Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 

	$im = imagecreate($sizeX+$shiftX+61,$sizeY+2*$shiftY+10); 
  
	$red=ImageColorAllocate($im,255,0,0); 
	$darkred=ImageColorAllocate($im,150,0,0); 
	$green=ImageColorAllocate($im,0,255,0); 
	$darkgreen=ImageColorAllocate($im,0,150,0); 
	$blue=ImageColorAllocate($im,0,0,255); 
	$darkblue=ImageColorAllocate($im,0,0,150); 
	$yellow=ImageColorAllocate($im,255,255,0); 
	$darkyellow=ImageColorAllocate($im,150,150,0); 
	$cyan=ImageColorAllocate($im,0,255,255); 
	$black=ImageColorAllocate($im,0,0,0); 
	$gray=ImageColorAllocate($im,150,150,150); 
	$white=ImageColorAllocate($im,255,255,255); 

	$x=imagesx($im); 
	$y=imagesy($im);
  
	ImageFilledRectangle($im,0,0,$sizeX+$shiftX+61,$sizeY+2*$shiftY+10,$white);
	ImageRectangle($im,0,0,$x-1,$y-1,$black);

	$now = time(NULL);
	$to_time=$now;
	$from_time=$to_time-$period*24*3600;

	$count=array();
	$min=array();
	$max=array();
	$avg=array();

//	$result=DBselect("select clock,value from history where itemid=$itemid and clock>$from_time and clock<$to_time");
	$result=DBselect("select round(900*((clock+3*3600)%(24*3600))/(24*3600)) as i,count(*) as count,avg(value) as avg,min(value) as min,max(value) as max from history where itemid=$itemid and clock>$from_time and clock<$to_time group by round(900*((clock+3*3600)%(24*3600))/(24*3600))");
	while($row=DBfetch($result))
	{
		$i=$row["i"];

		$max[$i]=$row["max"];
		$min[$i]=$row["min"];
		$avg[$i]=$row["avg"];
		$count[$i]=$row["count"];
		$nodata=0;

//		echo $i," ",$avg[$i],"<br>";
	}

	$count_now=array();
	$avg_now=array();
	$to_time=$now;
	$from_time=$to_time-$period;
//	$result=DBselect("select clock,value from history where itemid=$itemid and clock>$from_time and clock<$to_time");
	$result=DBselect("select round(900*((clock+3*3600)%(24*3600))/(24*3600)) as i,count(*) as count,avg(value) as avg,min(value) as min,max(value) as max from history where itemid=$itemid and clock>$from_time and clock<$to_time group by round(900*((clock+3*3600)%(24*3600))/(24*3600))");
	while($row=DBfetch($result))
	{
		$i=$row["i"];
		$avg_now[$i]=$row["avg"];
		$count_now[$i]=$row["count"];
	}

	for($i=0;$i<=$sizeY;$i+=50)
	{
		ImageDashedLine($im,$shiftX,$i+$shiftY,$sizeX+$shiftX,$i+$shiftY,$gray);
	}

	for($i=0;$i<=$sizeX;$i+=50)
	{
		ImageDashedLine($im,$i+$shiftX,$shiftY,$i+$shiftX,$sizeY+$shiftY,$gray);
		if($nodata == 0)
		{
			ImageString($im, 1,$i+$shiftX-11, $sizeY+$shiftY+5, date($label_format,-3*3600+24*3600*$i/900) , $black);
		}
	}

	unset($maxY);
	unset($minY);

	if($nodata == 0)
	{
		if(isset($trendavg))
		{
			$maxY=max($avg);
			$minY=min($avg);
		}
		else
		{
			$maxY=max($max);
			$minY=min($min);
		}
	}

	$maxX=900;
	$minX=0;

	if(isset($minY)&&($maxY)&&($minX!=$maxX)&&($minY!=$maxY))
	{
		for($i=0;$i<900;$i++)
		{
			if($count[$i]>0)
			{
				if(!isset($trendavg))
				{
					$x1=$sizeX*($i-$minX)/($maxX-$minX);
					$y1=$sizeY*($max[$i]-$minY)/($maxY-$minY);
					$x2=$x1;
					$y2=0;
					$y1=$sizeY-$y1;
					$y2=$sizeY-$y2;

//					ImageLine($im,$x1+$shiftX,$y1+$shiftY,$x2+$shiftX,$y2+$shiftY,$darkred);
				}

				$x1=$sizeX*($i-$minX)/($maxX-$minX);
				$y1=$sizeY*($avg[$i]-$minY)/($maxY-$minY);
				$x2=$x1;
				$y2=0;
				$y1=$sizeY-$y1;
				$y2=$sizeY-$y2;
	
				ImageLine($im,$x1+$shiftX,$y1+$shiftY,$x2+$shiftX,$y2+$shiftY,$darkgreen);

				if(!isset($trendavg))
				{
					$x1=$sizeX*($i-$minX)/($maxX-$minX);
					$y1=$sizeY*($min[$i]-$minY)/($maxY-$minY);
					$x2=$x1;
					$y2=0;
					$y1=$sizeY-$y1;
					$y2=$sizeY-$y2;
	
//					ImageLine($im,$x1+$shiftX,$y1+$shiftY,$x2+$shiftX,$y2+$shiftY,$darkgreen);
				}
			}
			if(($count_now[$i]>0)&&($count_now[$i-1]>0))
			{
				if(!isset($trendavg)&&($i>0))
				{
					$x1=$sizeX*($i-$minX)/($maxX-$minX);
					$y1=$sizeY*($avg_now[$i]-$minY)/($maxY-$minY);
					$x2=$sizeX*($i-$minX-1)/($maxX-$minX);
					$y2=$sizeY*($avg_now[$i-1]-$minY)/($maxY-$minY);
//					$x2=$x1;
//					$y2=0;
					$y1=$sizeY-$y1;
					$y2=$sizeY-$y2;
	
					ImageLine($im,$x1+$shiftX,$y1+$shiftY,$x2+$shiftX,$y2+$shiftY,$darkred);
				}
			}


#			ImageStringUp($im, 1, $x1+10, $sizeY+$shiftY+15, $i , $red);
		}
	}
	else
	{
//		ImageLine($im,$shiftX,$shiftY+$sizeY/2,$sizeX+$shiftX,$shiftY+$sizeY/2,$green);
	}

	if($nodata == 0)
	{
		for($i=0;$i<=$sizeY;$i+=50)
		{
			ImageString($im, 1, $sizeX+5+$shiftX, $sizeY-$i-4+$shiftY, $i*($maxY-$minY)/$sizeY+$minY , $darkred);
		}

//		date("dS of F Y h:i:s A",DBget_field($result,0,0));

//		ImageString($im, 1,10,                $sizeY+$shiftY+5, date("dS of F Y h:i:s A",$minX) , $red);
//		ImageString($im, 1,$sizeX+$shiftX-168,$sizeY+$shiftY+5, date("dS of F Y h:i:s A",$maxX) , $red);
	}
	else
	{
		ImageString($im, 2,$sizeX/2 -50,                $sizeY+$shiftY+3, "NO DATA FOR THIS PERIOD" , $red);
	}

	ImageString($im, 1,$shiftX, $sizeY+$shiftY+15, "AVG (LAST $type)" , $darkgreen);
	ImageString($im, 1,$shiftX+80, $sizeY+$shiftY+15, "AVG (TODAY)" , $darkred);

	ImageStringUp($im,0,2*$shiftX+$sizeX+40,$sizeY+2*$shiftY, "http://zabbix.sourceforge.net", $gray);

	ImagePng($im); 
	ImageDestroy($im); 
?>

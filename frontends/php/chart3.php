<? 
	include "include/config.inc.php";

#	PARAMETERS:
	
#	itemid
#	type
#	trendavg

	if(!isset($type))
	{
		$type="15min";
	}

	if($type == "15min")
	{
		$period=900;
		$label_format="H:i";
	}
	else if($type == "30min")
	{
		$period=1800;
		$label_format="H:i";
	}
	else if($type == "4hours")
	{
		$period=4*3600;
		$label_format="H:i";
	}
	else if($type == "12hours")
	{
		$period=12*3600;
		$label_format="H:i";
	}
	else
	{
		$period=3600;
		$label_format="H:i";
	}

	$sizeX=900;
	$sizeY=200;

	$shiftX=10;
	$shiftY=15;

	$nodata=1;	


//	Header( "Content-type:  text/html"); 
	Header( "Content-type:  image/png"); 
	Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 

	$im = imagecreate($sizeX+$shiftX+61,$sizeY+2*$shiftY+10); 
  
	$red=ImageColorAllocate($im,255,0,0); 
	$green=ImageColorAllocate($im,0,255,0); 
	$darkgreen=ImageColorAllocate($im,0,150,0); 
	$blue=ImageColorAllocate($im,0,0,255); 
	$yellow=ImageColorAllocate($im,255,255,0); 
	$cyan=ImageColorAllocate($im,0,255,255); 
	$black=ImageColorAllocate($im,0,0,0); 
	$gray=ImageColorAllocate($im,150,150,150); 

	$x=imagesx($im); 
	$y=imagesy($im);
  
	ImageFilledRectangle($im,0,0,$sizeX+$shiftX+61,$sizeY+2*$shiftY+10,$black);

	$now = time(NULL);
	$to_time=$now;
	$from_time=$to_time-24*3600;

	$count=array();
	$min=array();
	$max=array();
	$avg=array();

	$result=DBselect("select clock,value from history where itemid=$itemid and clock>$from_time and clock<$to_time");
	while($row=DBfetch($result))
	{
		$value=$row["value"];
		$i=intval(900*($from_time-$row["clock"])/($from_time-$to_time));

		if( (!isset($max[$i])) || ($max[$i]<$value))
		{
			$max[$i]=$value;
		}
		if(!isset($min[$i]) || ($min[$i]>$value))	$min[$i]=$value;
//		$min[$i]=0;
		$avg[$i]=0;
//		$max[$i]=$row["value"];
//		echo "$from_time $to_time ".$row["clock"]," ",($from_time-$row["clock"])/($from_time-$to_time),"<br>";
//		echo intval(900*($from_time-$row["clock"])/($from_time-$to_time)),"<br>";
//		$max[$row["clock"]%900]=$row["value"];
//		$avg[$row["clock"]%900]=$row["value"];
		$count[$i]=1;
	}
	$nodata=0;

/*	for($i=0;$i<900;$i++)
	{
		$result=DBselect("select count(value),min(value),max(value),avg(value) from history where itemid=$itemid and clock>$from_time+$i*($to_time-$from_time)/(900-50) and clock<$from_time+($i+1)*($to_time-$from_time)/(900-50)");
		$count[$i]=DBget_field($result,0,0);
		if($count[$i]>0)
		{
			$min[$i]=DBget_field($result,0,1);
			$max[$i]=DBget_field($result,0,2);
			$avg[$i]=DBget_field($result,0,3);
			$nodata=0;
		}
	}*/

	for($i=0;$i<=$sizeY;$i+=50)
	{
		ImageDashedLine($im,$shiftX,$i+$shiftY,$sizeX+$shiftX,$i+$shiftY,$darkgreen);
	}

	for($i=0;$i<=$sizeX;$i+=50)
	{
		ImageDashedLine($im,$i+$shiftX,$shiftY,$i+$shiftX,$sizeY+$shiftY,$darkgreen);
		if($nodata == 0)
		{
			ImageString($im, 1,$i+$shiftX-11, $sizeY+$shiftY+5, date($label_format,$from_time+$period*($i/50)) , $red);
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

					ImageLine($im,$x1+$shiftX,$y1+$shiftY,$x2+$shiftX,$y2+$shiftY,$red);
				}

				$x1=$sizeX*($i-$minX)/($maxX-$minX);
				$y1=$sizeY*($avg[$i]-$minY)/($maxY-$minY);
				$x2=$x1;
				$y2=0;
				$y1=$sizeY-$y1;
				$y2=$sizeY-$y2;
	
				ImageLine($im,$x1+$shiftX,$y1+$shiftY,$x2+$shiftX,$y2+$shiftY,$yellow);

				if(!isset($trendavg))
				{
					$x1=$sizeX*($i-$minX)/($maxX-$minX);
					$y1=$sizeY*($min[$i]-$minY)/($maxY-$minY);
					$x2=$x1;
					$y2=0;
					$y1=$sizeY-$y1;
					$y2=$sizeY-$y2;
	
					ImageLine($im,$x1+$shiftX,$y1+$shiftY,$x2+$shiftX,$y2+$shiftY,$green);
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
			ImageString($im, 1, $sizeX+5+$shiftX, $sizeY-$i-4+$shiftY, $i*($maxY-$minY)/$sizeY+$minY , $red);
		}

//		date("dS of F Y h:i:s A",DBget_field($result,0,0));

//		ImageString($im, 1,10,                $sizeY+$shiftY+5, date("dS of F Y h:i:s A",$minX) , $red);
//		ImageString($im, 1,$sizeX+$shiftX-168,$sizeY+$shiftY+5, date("dS of F Y h:i:s A",$maxX) , $red);
	}
	else
	{
		ImageString($im, 2,$sizeX/2 -50,                $sizeY+$shiftY+3, "NO DATA FOR THIS PERIOD" , $red);
	}

	ImageString($im, 1,$shiftX, $sizeY+$shiftY+15, "MIN" , $green);
	ImageString($im, 1,$shiftX+20, $sizeY+$shiftY+15, "AVG" , $yellow);
	ImageString($im, 1,$shiftX+40, $sizeY+$shiftY+15, "MAX" , $red);

	ImageStringUp($im,0,2*$shiftX+$sizeX+40,$sizeY+2*$shiftY, "http://zabbix.sourceforge.net", $gray);

	ImagePng($im); 
	ImageDestroy($im); 
?>

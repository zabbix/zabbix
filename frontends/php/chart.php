<?php 
	include "include/config.inc.php";

#	PARAMETERS:
	
#	itemid
#	period
#	from

	if(isset($HTTP_GET_VARS["period"]))
	{
		$period=$HTTP_GET_VARS["period"];
	}
	else
	{
		$period=3600;
	}

	if(isset($HTTP_GET_VARS["from"]))
	{
		$from=$HTTP_GET_VARS["from"];
	}
	else
	{
		$from=0;
	}

	if(isset($HTTP_GET_VARS["width"])&&$HTTP_GET_VARS["width"]>0)
	{
		$sizeX=$HTTP_GET_VARS["width"];
	}
	else
	{
		$sizeX=900;
	}

	$sizeY=200;

	$shiftX=10;
	$shiftY=17;

	$nodata=1;	


//	Header( "Content-type:  text/html"); 
	Header( "Content-type:  image/png"); 
	Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 

	check_authorisation();

	$im = imagecreate($sizeX+$shiftX+61,$sizeY+2*$shiftY+40); 
  
	$red=ImageColorAllocate($im,255,0,0); 
	$darkred=ImageColorAllocate($im,150,0,0); 
	$green=ImageColorAllocate($im,0,255,0); 
	$darkgreen=ImageColorAllocate($im,0,150,0); 
	$blue=ImageColorAllocate($im,0,0,255); 
	$yellow=ImageColorAllocate($im,255,255,0); 
	$cyan=ImageColorAllocate($im,0,255,255); 
	$black=ImageColorAllocate($im,0,0,0); 
	$gray=ImageColorAllocate($im,150,150,150); 
	$white=ImageColorAllocate($im,255,255,255); 

	$x=imagesx($im); 
	$y=imagesy($im);
  
	ImageFilledRectangle($im,0,0,$sizeX+$shiftX+61,$sizeY+2*$shiftY+40,$white);
	ImageRectangle($im,0,0,$x-1,$y-1,$black);
	if(!check_right("Item","R",$HTTP_GET_VARS["itemid"]))
	{
//		show_table_header("<font color=\"AA0000\">No permissions !</font>");
//		show_footer();
		ImagePng($im); 
		ImageDestroy($im); 
		exit;
	}

//	for($i=0;$i<=$sizeY;$i+=$sizeY/5)
//	{
//		ImageDashedLine($im,$shiftX,$i+$shiftY,$sizeX+$shiftX,$i+$shiftY,$gray);
//	}
//	for($i=0;$i<=$sizeX;$i+=$sizeX/24)
//	{
//		ImageDashedLine($im,$i+$shiftX,$shiftY,$i+$shiftX,$sizeY+$shiftY,$gray);
//	}
//	$style=array($white,$white,$white,$white,$white,$white,$black,$black,$black,$black,$black,$black,$black);
//	ImageSetStyle($im,$style);
	ImageDashedLine($im,$shiftX+1,$shiftY,$shiftX+1,$sizeY+$shiftY,$black);
	ImageDashedLine($im,$shiftX+1,$shiftY,$shiftX+$sizeX,$shiftY,$black);
	ImageDashedLine($im,$shiftX+$sizeX,$shiftY,$shiftX+$sizeX,$sizeY+$shiftY,$black);
	ImageDashedLine($im,$shiftX+1,$shiftY+$sizeY,$shiftX+$sizeX,$sizeY+$shiftY,$black);

	$item=get_item_by_itemid($HTTP_GET_VARS["itemid"]);
	$host=get_host_by_hostid($item["hostid"]);

	$str=$host["host"].":".$item["description"];
	$x=imagesx($im)/2-ImageFontWidth(4)*strlen($str)/2;
	ImageString($im, 4,$x,1, $str , $darkred);
//	ImageString($im, 4,$sizeX/2-50,1, $host["host"].":".$item["description"] , $darkred);

	$from_time = time(NULL)-$period-3600*$from;
	$to_time   = time(NULL)-3600*$from;
	$result=DBselect("select count(clock),min(clock),max(clock),min(value),max(value) from history where itemid=".$HTTP_GET_VARS["itemid"]." and clock>$from_time and clock<$to_time ");
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
		unset($maxX);
		unset($maxY);
		unset($minX);
		unset($minY);

		ImageString($im, 2,$sizeX/2-50,                $sizeY+$shiftY+3, "NO DATA FOUND FOR THIS PERIOD" , $darkred);
		ImageStringUp($im,0,imagesx($im)-10,imagesy($im)-50, "http://zabbix.sourceforge.net", $gray);

		ImagePng($im); 
		ImageDestroy($im); 
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
		$result=DBselect("select clock,value from history where itemid=".$HTTP_GET_VARS["itemid"]." and clock>$from_time and clock<$to_time order by clock");
		for($i=0;$i<DBnum_rows($result)-1;$i++)
		{
			$x=DBget_field($result,$i,0);
			$x_next=DBget_field($result,$i+1,0);
			$y=DBget_field($result,$i,1);
			$y_next=DBget_field($result,$i+1,1);

			$x1=$sizeX*($x-$minX)/($maxX-$minX);
			$y1=$sizeY*($y-$minY)/($maxY-$minY);
			$x2=$sizeX*($x_next-$minX)/($maxX-$minX);
			$y2=$sizeY*($y_next-$minY)/($maxY-$minY);

			$y1=$sizeY-$y1;
			$y2=$sizeY-$y2;

			ImageLine($im,$x1+$shiftX,$y1+$shiftY,$x2+$shiftX,$y2+$shiftY,$darkgreen);
//			ImageSetPixel($im,$x2+$shiftX,$y2+$shiftY-1,$darkred);
		}
	}
	else
	{
		if(isset($minX))
		{
			ImageLine($im,$shiftX,$shiftY+$sizeY/2,$sizeX+$shiftX,$shiftY+$sizeY/2,$darkgreen);
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
		ImageDashedLine($im,$shiftX,$i/$my_steps*$sizeY+$shiftY,$sizeX+$shiftX,$i/$my_steps*$sizeY+$shiftY,$gray);
	}
	for($j=$stepTime-$correctTime;$j<=($maxX-$minX);$j+=$stepTime)
	{
		ImageDashedLine($im,$shiftX+($sizeX*$j)/($maxX-$minX),$shiftY,$shiftX+($sizeX*$j)/($maxX-$minX),$sizeY+$shiftY,$gray);
	}


	if($nodata == 0)
	{
//		for($i=0;$i<=$sizeY;$i+=$sizeY/5)
//		{
//			ImageString($im, 1, $sizeX+5+$shiftX, $sizeY-$i-4+$shiftY, $i*($maxY-$minY)/$sizeY+$minY , $darkred);
//		}
//		for($i=0;$i<=$sizeX;$i+=$sizeX/24)
//		{
//			ImageStringUp($im,0,$i+$shiftX-3,$shiftY+$sizeY+50,date("H:i:s",$i*($maxX-$minX)/$sizeX+$minX),$black);
//		}

		for($i=0;$i<=$my_steps;$i++)
		{
			ImageString($im, 1, $sizeX+5+$shiftX, $i/$my_steps*$sizeY+$shiftY-4, convert_units($maxY-$i/$my_steps*($maxY-$minY),$item["units"],$item["multiplier"]) , $darkred);
		}
		for($j=$stepTime-$correctTime;$j<=($maxX-$minX);$j+=$stepTime)
		{
			ImageStringUp($im,0,$shiftX+($sizeX*$j)/($maxX-$minX),$shiftY+$sizeY+53,date($dateForm,$startTime+$j),$black);
		}

//		ImageString($im, 1,10,                $sizeY+$shiftY+3, date("dS of F Y",$minX) , $darkred);
//		ImageString($im, 1,$sizeX+$shiftX-90,$sizeY+$shiftY+3, date("dS of F Y",$maxX) , $darkred);
		ImageString($im, 1,10,                $sizeY+$shiftY+5, date("dS of F Y H:i:s",$minX) , $darkred);
		ImageString($im, 1,$sizeX+$shiftX-148,$sizeY+$shiftY+5, date("dS of F Y H:i:s",$maxX) , $darkred);
	}
	else
	{
		ImageString($im, 2,$sizeX/2-50,                $sizeY+$shiftY+3, "NO DATA FOUND FOR THIS PERIOD" , $darkred);
	}

	ImageStringUp($im,0,imagesx($im)-10,imagesy($im)-50, "http://zabbix.sourceforge.net", $gray);

	ImagePng($im); 
	ImageDestroy($im); 
?>

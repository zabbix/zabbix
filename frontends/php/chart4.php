<? 
	include "include/config.inc.php";

#	PARAMETERS:
	
#	itemid
#	type

	$start_time=time(NULL);

	if(!isset($HTTP_GET_VARS["type"]))
	{
		$HTTP_GET_VARS["type"]="week";
	}

	if($HTTP_GET_VARS["type"] == "month")
	{
		$period=30*24*3600;
	}
	else if($HTTP_GET_VARS["type"] == "week")
	{
		$period=7*24*3600;
	}
	else if($HTTP_GET_VARS["type"] == "year")
	{
		$period=365*24*3600;
	}
	else
	{
		$period=7*24*3600;
		$type="week";
	}

	$sizeX=900;
	$sizeY=200;

	$shiftX=12;
	$shiftYup=13;
	$shiftYdown=7+15*3;


//	Header( "Content-type:  text/html"); 
	Header( "Content-type:  image/png"); 
	Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 

	$im = imagecreate($sizeX+$shiftX+61,$sizeY+$shiftYup+$shiftYdown+10); 
  
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
	$bg=ImageColorAllocate($im,6+6*16,7+7*16,8+8*16);

	$x=imagesx($im); 
	$y=imagesy($im);
  
	ImageFilledRectangle($im,0,0,$sizeX+$shiftX+61,$sizeY+$shiftYup+$shiftYdown+10,$white);
	ImageRectangle($im,0,0,$x-1,$y-1,$black);

	$now = time(NULL);
	$to_time=$now;
	$from_time=$to_time-$period;
	$from_time_now=$to_time-24*3600;

	$count_now=array();
	$true=array();
	for($i=0;$i<52;$i++)
	{
		$year=date("Y");
		$period_start=mktime(0,0,0,0,0,$year)+7*24*3600*$i;
		$period_end=mktime(0,0,0,0,0,$year)+7*24*3600*($i+1);
		$stat=calculate_availability($HTTP_GET_VARS["triggerid"],$period_start,$period_end);
		
		$true[$i]=$stat["true"];
		$false[$i]=$stat["false"];
		$unknown[$i]=$stat["unknown"];
		$count_now[$i]=1;
	}

	for($i=0;$i<=$sizeY;$i+=$sizeY/10)
	{
		ImageDashedLine($im,$shiftX,$i+$shiftYup,$sizeX+$shiftX,$i+$shiftYup,$gray);
	}

	for($i=0;$i<=$sizeX;$i+=$sizeX/52)
	{
		ImageDashedLine($im,$i+$shiftX,$shiftYup,$i+$shiftX,$sizeY+$shiftYup,$gray);
		ImageString($im, 1,$i+$shiftX-11, $sizeY+$shiftYup+5, date("H:i",-3*3600+24*3600*$i/900) , $black);
	}

	$maxY=100;
	$tmp=max($true);
	if($tmp>$maxY)
	{
		$maxY=$tmp;
	}
	$minY=0;

	$maxX=900;
	$minX=0;

	for($i=1;$i<52;$i++)
	{
		$x1=(900/52)*$sizeX*($i-$minX)/($maxX-$minX);
		$y1=$sizeY*($true[$i]-$minY)/($maxY-$minY);
		$x2=(900/52)*$sizeX*($i-$minX-1)/($maxX-$minX);
		$y2=$sizeY*($true[$i-1]-$minY)/($maxY-$minY);
		$y1=$sizeY-$y1;
		$y2=$sizeY-$y2;

		ImageLine($im,$x1+$shiftX,$y1+$shiftYup,$x2+$shiftX,$y2+$shiftYup,$darkred);

		$x1=(900/52)*$sizeX*($i-$minX)/($maxX-$minX);
		$y1=$sizeY*($false[$i]-$minY)/($maxY-$minY);
		$x2=(900/52)*$sizeX*($i-$minX-1)/($maxX-$minX);
		$y2=$sizeY*($false[$i-1]-$minY)/($maxY-$minY);
		$y1=$sizeY-$y1;
		$y2=$sizeY-$y2;

		ImageLine($im,$x1+$shiftX,$y1+$shiftYup,$x2+$shiftX,$y2+$shiftYup,$darkgreen);

		$x1=(900/52)*$sizeX*($i-$minX)/($maxX-$minX);
		$y1=$sizeY*($unknown[$i]-$minY)/($maxY-$minY);
		$x2=(900/52)*$sizeX*($i-$minX-1)/($maxX-$minX);
		$y2=$sizeY*($unknown[$i-1]-$minY)/($maxY-$minY);
		$y1=$sizeY-$y1;
		$y2=$sizeY-$y2;

		ImageLine($im,$x1+$shiftX,$y1+$shiftYup,$x2+$shiftX,$y2+$shiftYup,$darkyellow);

#			ImageStringUp($im, 1, $x1+10, $sizeY+$shiftYup+15, $i , $red);
	}

	for($i=0;$i<=$sizeY;$i+=$sizeY/10)
	{
		ImageString($im, 1, $sizeX+5+$shiftX, $sizeY-$i-4+$shiftYup, $i*($maxY-$minY)/$sizeY+$minY , $darkred);
	}

	ImageFilledRectangle($im,$shiftX,$sizeY+$shiftYup+19+15*0,$shiftX+5,$sizeY+$shiftYup+15+9+15*0,$darkgreen);
	ImageRectangle($im,$shiftX,$sizeY+$shiftYup+19+15*0,$shiftX+5,$sizeY+$shiftYup+15+9+15*0,$black);
	ImageString($im, 2,$shiftX+9,$sizeY+$shiftYup+15*0+15, "FALSE (%)", $black);

	ImageFilledRectangle($im,$shiftX,$sizeY+$shiftYup+19+15*1,$shiftX+5,$sizeY+$shiftYup+15+9+15*1,$darkred);
	ImageRectangle($im,$shiftX,$sizeY+$shiftYup+19+15*1,$shiftX+5,$sizeY+$shiftYup+15+9+15*1,$black);
	ImageString($im, 2,$shiftX+9,$sizeY+$shiftYup+15*1+15, "TRUE (%)", $black);

	ImageFilledRectangle($im,$shiftX,$sizeY+$shiftYup+19+15*2,$shiftX+5,$sizeY+$shiftYup+15+9+15*2,$darkyellow);
	ImageRectangle($im,$shiftX,$sizeY+$shiftYup+19+15*2,$shiftX+5,$sizeY+$shiftYup+15+9+15*2,$black);
	ImageString($im, 2,$shiftX+9,$sizeY+$shiftYup+15*2+15, "UNKNOWN (%)", $black);

	ImageStringUp($im,0,imagesx($im)-10,imagesy($im)-50, "http://zabbix.sourceforge.net", $gray);

	$end_time=time(NULL);
	ImageString($im, 0,imagesx($im)-100,imagesy($im)-12,"Generated in ".($end_time-$start_time)." sec", $gray);

	ImagePng($im); 
	ImageDestroy($im); 
?>

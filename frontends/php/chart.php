<? 
	include "include/config.inc.php";

#	PARAMETERS:
	
#	itemid
#	period
#	from

	if(!isset($period))
	{
		$period=3600;
	}

	if(!isset($from))
	{
		$from=0;
	}

	$sizeX=900;
	$sizeY=200;

	$shiftX=10;
	$shiftY=13;

	$nodata=1;	


//	Header( "Content-type:  text/html"); 
	Header( "Content-type:  image/png"); 
	Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 

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

	for($i=0;$i<=$sizeY;$i+=$sizeY/5)
	{
		ImageDashedLine($im,$shiftX,$i+$shiftY,$sizeX+$shiftX,$i+$shiftY,$gray);
	}
	for($i=0;$i<=$sizeX;$i+=$sizeX/24)
	{
		ImageDashedLine($im,$i+$shiftX,$shiftY,$i+$shiftX,$sizeY+$shiftY,$gray);
	}
	$item=get_item_by_itemid($itemid);
	$host=get_host_by_hostid($item["hostid"]);
	ImageString($im, 4,$sizeX/2-50,-1, $host["host"].":".$item["description"] , $darkred);
//	ImageStringUp($im,1,0,$sizeY, $host["host"].":".$item["description"], $darkred);

	$from_time = time(NULL)-$period-3600*$from;
	$to_time   = time(NULL)-3600*$from;
	$result=DBselect("select count(clock),min(clock),max(clock),min(value),max(value) from history where itemid=$itemid and clock>$from_time and clock<$to_time ");
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
	}
	
//	echo "MIN/MAX:",$minX," - ",$maxX," - ",$minY," - ",$maxY,"<Br>";

	if(isset($minX)&&($minX!=$maxX)&&($minY!=$maxY))
	{
		$result=DBselect("select clock,value from history where itemid=$itemid and clock>$from_time and clock<$to_time order by clock");
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

//		echo $x1," - ",$x2," - ",$y1," - ",$y2,"<Br>";
			ImageLine($im,$x1+$shiftX,$y1+$shiftY,$x2+$shiftX,$y2+$shiftY,$darkgreen);
//			ImageSetPixel($im,$x2+$shiftX,$y2+$shiftY,$darkred);
//			ImageSetPixel($im,$x2+$shiftX+1,$y2+$shiftY,$darkred);
//			ImageSetPixel($im,$x2+$shiftX-1,$y2+$shiftY,$darkred);
//			ImageSetPixel($im,$x2+$shiftX,$y2+$shiftY+1,$darkred);
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

	if($nodata == 0)
	{
		for($i=0;$i<=$sizeY;$i+=$sizeY/5)
		{
			ImageString($im, 1, $sizeX+5+$shiftX, $sizeY-$i-4+$shiftY, $i*($maxY-$minY)/$sizeY+$minY , $darkred);
		}
		for($i=0;$i<=$sizeX;$i+=$sizeX/24)
		{
			ImageStringUp($im,0,$i+$shiftX-3,$shiftY+$sizeY+50,date("H:i:s",$i*($maxX-$minX)/$sizeX+$minX),$black);
		}

		ImageString($im, 1,10,                $sizeY+$shiftY+3, date("dS of F Y",$minX) , $darkred);
		ImageString($im, 1,$sizeX+$shiftX-90,$sizeY+$shiftY+3, date("dS of F Y",$maxX) , $darkred);
	}
	else
	{
		ImageString($im, 2,$sizeX/2-50,                $sizeY+$shiftY+3, "NO DATA FOUND FOR THIS PERIOD" , $darkred);
	}

	ImageStringUp($im,0,2*$shiftX+$sizeX+40,$sizeY+2*$shiftY, "http://zabbix.sourceforge.net", $gray);

	ImagePng($im); 
	ImageDestroy($im); 
?>

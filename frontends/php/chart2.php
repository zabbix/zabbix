<? 
	include "include/config.inc.php";

#	PARAMETERS:
	
#	graphid

#	period
#	from

	$start_time=time(NULL);

	if(!isset($HTTP_GET_VARS["period"]))
	{
		$HTTP_GET_VARS["period"]=0;
	}

	if(!isset($HTTP_GET_VARS["from"]))
	{
		$HTTP_GET_VARS["from"]=0;
	}

	$result=DBselect("select name,width,height from graphs where graphid=".$HTTP_GET_VARS["graphid"]);

	$name=DBget_field($result,0,0);
	$width=DBget_field($result,0,1);
	$height=DBget_field($result,0,2);

	$sizeX=$width;
	$sizeY=$height;

	$nodata=1;	

//	Header( "Content-type:  text/html"); 
	Header( "Content-type:  image/png"); 
	Header( "Expires:  Mon, 17 Aug 1998 12:51:50 GMT"); 

	check_authorisation();

	$result2=DBselect("select gi.itemid,i.description,gi.color,h.host from graphs_items gi,items i,hosts h where gi.itemid=i.itemid and gi.graphid=".$HTTP_GET_VARS["graphid"]." and i.hostid=h.hostid order by gi.gitemid");

	$shiftX=10;
	$shiftYup=17;
	$shiftYdown=7+15*DBnum_rows($result2);

	$im = imagecreate($sizeX+$shiftX+61,$sizeY+$shiftYup+$shiftYdown+10+50); 
  
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
	$white=ImageColorAllocate($im,255,255,255); 
	$gray=ImageColorAllocate($im,150,150,150); 

	$colors=array();

	$colors["Black"]=$black;
	$colors["Green"]=$green;
	$colors["Dark Green"]=$darkgreen;
	$colors["Yellow"]=$yellow;
	$colors["Dark Yellow"]=$darkyellow;
	$colors["Blue"]=$blue;
	$colors["Dark Blue"]=$blue;
	$colors["White"]=$white;
	$colors["Cyan"]=$cyan;
	$colors["Red"]=$red;
	$colors["Dark Red"]=$darkred;

	$x=imagesx($im); 
	$y=imagesy($im);
  
	ImageFilledRectangle($im,0,0,$sizeX+$shiftX+61,$sizeY+$shiftYup+$shiftYdown+10+50,$white);
	ImageRectangle($im,0,0,$x-1,$y-1,$black);

	if(!check_right("Graph","R",$HTTP_GET_VARS["graphid"]))
	{
//		show_table_header("<font color=\"AA0000\">No permissions !</font>");
//		show_footer();
		ImagePng($im); 
		ImageDestroy($im); 
		exit;
	}


	for($i=0;$i<=$sizeY;$i+=$sizeY/5)
	{
		ImageDashedLine($im,$shiftX,$i+$shiftYup,$sizeX+$shiftX,$i+$shiftYup,$gray);
	}
	for($i=0;$i<=$sizeX;$i+=$sizeX/24)
	{
		ImageDashedLine($im,$i+$shiftX,$shiftYup,$i+$shiftX,$sizeY+$shiftYup,$gray);
	}

	$graph=get_graph_by_graphid($HTTP_GET_VARS["graphid"]);
	$str=$graph["name"];
	$x=imagesx($im)/2-ImageFontWidth(4)*strlen($str)/2;
	ImageString($im, 4,$x,1, $str , $darkred);

	$from_time = time(NULL)-$HTTP_GET_VARS["period"]-3600*$HTTP_GET_VARS["from"];
	$to_time   = time(NULL)-3600*$HTTP_GET_VARS["from"];


	$len=array();
	$x=array();
	$y=array();
	$desc=array();
	$color=array();

	unset($maxX);
	unset($maxY);
	unset($minX);
	unset($minY);

	for($item=0;$item<DBnum_rows($result2);$item++)
	{
		$itemid=DBget_field($result2,$item,0);
		$desc[$item]=DBget_field($result2,$item,1);
		$color[$item]=DBget_field($result2,$item,2);
		$host[$item]=DBget_field($result2,$item,3);
	
		$result=DBselect("select clock,value from history where itemid=$itemid and clock>$from_time and clock<$to_time order by clock");
		$len[$item]=0;
		$x[$item]=array();
		$y[$item]=array();
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$nodata=0;
			$x[$item][$len[$item]]=DBget_field($result,$i,0);
			$y[$item][$len[$item]]=DBget_field($result,$i,1);;
	//		echo $row[0]," - ",$y[$len],"<Br>";
			if((!isset($maxX))||($x[$item][$len[$item]]>$maxX))	{ $maxX=$x[$item][$len[$item]]; }
			if((!isset($minX))||($x[$item][$len[$item]]<$minX))	{ $minX=$x[$item][$len[$item]]; }
			if((!isset($maxY))||($y[$item][$len[$item]]>$maxY))	{ $maxY=$y[$item][$len[$item]]; }
			if((!isset($minY))||($y[$item][$len[$item]]<$minY))	{ $minY=$y[$item][$len[$item]]; }
			$len[$item]++;
		}
	}

//	echo "MIN/MAX:",$minX," - ",$maxX," - ",$minY," - ",$maxY,"<Br>";

//	$result2=DBselect("select itemid from graphs_items where graphid=".$HTTP_GET_VARS["graphid"]);
	for($item=0;$item<DBnum_rows($result2);$item++)
	{
		if(isset($minX)&&isset($minY)&&($minX!=$maxX)&&($minY!=$maxY))
		{
			for($i=0;$i<$len[$item]-1;$i++)
			{
				$x1=$sizeX*($x[$item][$i]-$minX)/($maxX-$minX);
				$y1=$sizeY*($y[$item][$i]-$minY)/($maxY-$minY);
				$x2=$sizeX*($x[$item][$i+1]-$minX)/($maxX-$minX);
				$y2=$sizeY*($y[$item][$i+1]-$minY)/($maxY-$minY);
	
				$y1=$sizeY-$y1;
				$y2=$sizeY-$y2;
	
	//		echo $x1," - ",$x2," - ",$y1," - ",$y2,"<Br>";
				ImageLine($im,$x1+$shiftX,$y1+$shiftYup,$x2+$shiftX,$y2+$shiftYup,$colors[$color[$item]]);
			}
		}
		else
		{
			if(isset($minX))
			{
				ImageLine($im,$shiftX,$shiftYup+$sizeY/2,$sizeX+$shiftX,$shiftYup+$sizeY/2,$colors[$color[$item]]);
			}
		}
#		ImageFilledRectangle($im,$shiftX+200*$item,$sizeY+$shiftYup+19,$shiftX+200*$item+5,$sizeY+$shiftYup+15+9,$colors[$color[$item]]);
#		ImageString($im, 2,$shiftX+200*$item+9,$sizeY+$shiftYup+15, $desc[$item], $gray);
		ImageFilledRectangle($im,$shiftX,$sizeY+$shiftYup+19+15*$item+45,$shiftX+5,$sizeY+$shiftYup+15+9+15*$item+45,$colors[$color[$item]]);
		ImageRectangle($im,$shiftX,$sizeY+$shiftYup+19+15*$item+45,$shiftX+5,$sizeY+$shiftYup+15+9+15*$item+45,$black);
		ImageString($im, 2,$shiftX+9,$sizeY+$shiftYup+15*$item+15+45, $host[$item].": ".$desc[$item], $black);
	}

	if($nodata == 0)
	{
		for($i=0;$i<=$sizeY;$i+=$sizeY/5)
		{
			ImageString($im, 1, $sizeX+5+$shiftX, $sizeY-$i-4+$shiftYup, $i*($maxY-$minY)/$sizeY+$minY , $darkred);
		}
		for($i=0;$i<=$sizeX;$i+=$sizeX/24)
		{
			ImageStringUp($im,0,$i+$shiftX-3,$shiftYup+$sizeY+53,date("H:i:s",$i*($maxX-$minX)/$sizeX+$minX),$black);
		}

//		date("dS of F Y h:i:s A",DBget_field($result,0,0));

		ImageString($im, 1,10,                $sizeY+$shiftYup+5, date("dS of F Y h:i:s A",$minX) , $darkred);
		ImageString($im, 1,$sizeX+$shiftX-168,$sizeY+$shiftYup+5, date("dS of F Y h:i:s A",$maxX) , $darkred);
	}
	else
	{
		ImageString($im, 2,$sizeX/2 -50,                $sizeY+$shiftYup+3, "NO DATA FOR THIS PERIOD" , $red);
	}

#	ImageString($im,0,$shiftX+$sizeX-85,$sizeY+$shiftYup+25, "http://zabbix.sourceforge.net", $gray);
	ImageStringUp($im,0,imagesx($im)-10,imagesy($im)-50, "http://zabbix.sourceforge.net", $gray);

	$end_time=time(NULL);
	ImageString($im, 0,imagesx($im)-100,imagesy($im)-12,"Generated in ".($end_time-$start_time)." sec", $gray);

	ImagePng($im); 
	ImageDestroy($im); 
?>

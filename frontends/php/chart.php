<?php 
	include "include/config.inc.php";
	include "include/classes.inc.php";

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
	if(isset($HTTP_GET_VARS["height"]))
	{
		$graph->setHeight($HTTP_GET_VARS["height"]);
	}
	if(isset($HTTP_GET_VARS["border"]))
	{
		$graph->setBorder(0);
	}

	$graph->Draw3();
?>


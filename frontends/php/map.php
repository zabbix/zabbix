<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
	require_once('include/config.inc.php');
	require_once('include/maps.inc.php');

	$page['title'] = 'S_MAP';
	$page['file'] = 'map.php';
	$page['type'] = PAGE_TYPE_IMAGE;

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'sysmapid'=>	array(T_ZBX_INT, O_MAND,P_SYS,	DB_ID,		NULL),
		'noedit'=>	array(T_ZBX_INT, O_OPT,	NULL,	IN('0,1'),	NULL),
		'border'=>	array(T_ZBX_INT, O_OPT,	NULL,	IN('0,1'),	NULL)
	);

	check_fields($fields);
?>
<?php
	if(!sysmap_accessible($_REQUEST['sysmapid'],PERM_READ_ONLY)){
		access_deny();
	}

	if(!$map = get_sysmap_by_sysmapid($_REQUEST['sysmapid'])){
		include_once('include/page_footer.php');
	}

	$name		= $map['name'];
	$width		= $map['width'];
	$height		= $map['height'];
	$backgroundid	= $map['backgroundid'];
	$label_type	= $map['label_type'];

	if(function_exists('imagecreatetruecolor')&&@imagecreatetruecolor(1,1)){
		$im = imagecreatetruecolor($width,$height);
	}
	else{
		$im = imagecreate($width,$height);
	}

	$red		= imagecolorallocate($im,255,0,0);
	$darkred	= imagecolorallocate($im,150,0,0);
	$green		= imagecolorallocate($im,0,255,0);
	$darkgreen	= imagecolorallocate($im,0,150,0);
	$blue		= imagecolorallocate($im,0,0,255);
	$yellow		= imagecolorallocate($im,255,255,0);
	$darkyellow	= imagecolorallocate($im,150,127,0);
	$cyan		= imagecolorallocate($im,0,255,255);
	$white		= imagecolorallocate($im,255,255,255);
	$black		= imagecolorallocate($im,0,0,0);
	$gray		= imagecolorallocate($im,150,150,150);

	$colors['Red']		= imagecolorallocate($im,255,0,0);
	$colors['Dark Red']	= imagecolorallocate($im,150,0,0);
	$colors['Green']	= imagecolorallocate($im,0,255,0);
	$colors['Dark Green']	= imagecolorallocate($im,0,150,0);
	$colors['Blue']		= imagecolorallocate($im,0,0,255);
	$colors['Dark Blue']	= imagecolorallocate($im,0,0,150);
	$colors['Yellow']	= imagecolorallocate($im,255,255,0);
	$colors['Dark Yellow']	= imagecolorallocate($im,150,150,0);
	$colors['Cyan']		= imagecolorallocate($im,0,255,255);
	$colors['Black']	= imagecolorallocate($im,0,0,0);
	$colors['Gray']		= imagecolorallocate($im,150,150,150);
	$colors['White']	= imagecolorallocate($im,255,255,255);

	$x=imagesx($im);
	$y=imagesy($im);

	imagefilledrectangle($im,0,0,$width,$height,$white);

	if(($db_image = get_image_by_imageid($backgroundid))){
		$back = imagecreatefromstring($db_image['image']);
		imagecopy($im,$back,0,0,0,0,imagesx($back),imagesy($back));
	}
	else{
		$x=imagesx($im)/2-ImageFontWidth(4)*strlen($name)/2;
		imagetext($im, 10, 0, $x, 25, $darkred, $name);
	}
	unset($db_image);

	$str = date('m.d.Y H:i:s',time(NULL));
	imagestring($im, 0,imagesx($im)-120,imagesy($im)-12,$str, $gray);

	if(!isset($_REQUEST['noedit'])){
		$grid = 50;

		$dims = imageTextSize(8, 0, '11');
		for($x=$grid; $x<$width; $x+=$grid){
			MyDrawLine($im,$x,0,$x,$height,$black, MAP_LINK_DRAWTYPE_DASHED_LINE);
			imageText($im, 8, 0, $x+3, $dims['height']+3, $black,$x);
		}
		for($y=$grid;$y<$height;$y+=$grid){
			MyDrawLine($im,0,$y,$width,$y,$black, MAP_LINK_DRAWTYPE_DASHED_LINE);
			imageText($im, 8, 0, 3, $y+$dims['height']+3, $black, $y);
		}

		imageText($im, 8, 0, 2, $dims['height']+3, $black, 'Y X:');
	}

// Draw connectors

	$links = DBselect('select * from sysmaps_links where sysmapid='.$_REQUEST['sysmapid']);
	while($link = DBfetch($links)){
		list($x1, $y1) = get_icon_center_by_selementid($link['selementid1']);
		list($x2, $y2) = get_icon_center_by_selementid($link['selementid2']);

		$drawtype = $link['drawtype'];
		$color = convertColor($im,$link['color']);

		$triggers = get_link_triggers($link['linkid']);


		if(!empty($triggers)){
			$max_severity=0;
			$options = array();
			$options['nopermissions'] = 1;
			$options['extendoutput'] = 1;
			$options['triggerids'] = array();

			foreach($triggers as $id => $link_trigger){
				$triggers[$id] = zbx_array_merge($link_trigger,get_trigger_by_triggerid($link_trigger['triggerid']));
				if(($triggers[$id]['status'] == TRIGGER_STATUS_ENABLED) && ($triggers[$id]['value'] == TRIGGER_VALUE_TRUE)){
					if($triggers[$id]['priority'] >= $max_severity){
						$drawtype = $triggers[$id]['drawtype'];
						$color = convertColor($im,$triggers[$id]['color']);
						$max_severity = $triggers[$id]['priority'];
					}
				}
			}
		}
		MyDrawLine($im,$x1,$y1,$x2,$y2,$color,$drawtype);
	}

// Draw elements
	$icons=array();
	$db_elements = DBselect('select * from sysmaps_elements where sysmapid='.$_REQUEST['sysmapid']);

	while($db_element = DBfetch($db_elements)){
		if($img = get_png_by_selementid($db_element['selementid'])){
			imagecopy($im,$img,$db_element['x'],$db_element['y'],0,0,ImageSX($img),ImageSY($img));
		}

		if($label_type==MAP_LABEL_TYPE_NOTHING)	continue;

		$color		= $darkgreen;
		$label_color	= $black;
		$info_line	= '';

		$label_location = is_null($db_element['label_location']) ? $map['label_location'] : $db_element['label_location'];

		$label_line = expand_map_element_label_by_data($db_element);

		$el_info = get_info_by_selementid($db_element['selementid']);

		$info_line	= $el_info['info'];
		$color		= $el_info['color'];

		if($label_type==MAP_LABEL_TYPE_STATUS){
			$label_line = '';
		}
		else if($label_type==MAP_LABEL_TYPE_NAME){
			$label_line = $el_info['name'];
		}

		if(isset($el_info['disabled']) && $el_info['disabled'] == 1){
			$info_line = 'DISABLED';
			$label_color = $gray;
		}

		unset($el_info);

		if($db_element['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST){
			$host = get_host_by_hostid($db_element['elementid']);

			if( $label_type==MAP_LABEL_TYPE_IP )
				$label_line=$host['ip'];
		}

		if($db_element['elementtype'] == SYSMAP_ELEMENT_TYPE_IMAGE){
			$label_line = expand_map_element_label_by_data($db_element);
		}

		if($label_line=='' && $info_line=='')	continue;

		$label_line = str_replace("\r", '', $label_line);
		$strings = explode("\n", $label_line);
		array_push($strings, $info_line);
		$cnt = count($strings);
		$num = 0;

		$x = $db_element['x'];
		$y = $db_element['y'];
		$h = ImageFontHeight(2);

		$x_info = $db_element['x'];
		$y_info = $db_element['y'];

		if($label_location == MAP_LABEL_LOC_TOP)
			$y -= $h * $cnt;
		else if ($label_location == MAP_LABEL_LOC_LEFT || $label_location == MAP_LABEL_LOC_RIGHT)
			$y += imagesy($img) / 2 - $h * $cnt / 2;
		else	/* MAP_LABEL_LOC_BOTTOM */
			$y += imagesy($img);

		foreach($strings as $str){
			$num++;
			$dims = imageTextSize(8,0,$str);

			if ($label_location == MAP_LABEL_LOC_TOP || $label_location == MAP_LABEL_LOC_BOTTOM)
				$x_label = $x + imagesx($img) / 2 - $dims['width'] / 2;
			else if ($label_location == MAP_LABEL_LOC_LEFT)
				$x_label = $x - $dims['width'];
			else	/* MAP_LABEL_LOC_RIGHT */
				$x_label = $x + imagesx($img);


			imagefilledrectangle($im, $x_label-1, $y+$dims['height']+1, $x_label + $dims['width']+1, $y - 1, $white);
			imagetext($im, 8, 0, $x_label, $y+$dims['height'], ($num == $cnt)?$color:$label_color, $str);

			$y += $dims['height']+4;
		}
	}

	imagestringup($im,0,imagesx($im)-10,imagesy($im)-50, S_ZABBIX_URL, $gray);

	if(!isset($_REQUEST['border'])){
		imagerectangle($im,0,0,$width-1,$height-1,$colors['Black']);
	}

	ImageOut($im);

	ImageDestroy($im);
?>
<?php

include_once 'include/page_footer.php';

?>

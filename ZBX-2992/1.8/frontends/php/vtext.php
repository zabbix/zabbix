<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
define('ZBX_PAGE_NO_AUTHORIZATION', 1);

require_once('include/config.inc.php');

$page['file'] = 'vtext.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'text'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,			null),
		'font'=>		array(T_ZBX_INT, O_OPT,	null,	BETWEEN(1,5),	null),
		'color'=>		array(T_ZBX_STR, O_OPT,	null,	null,			null),
	);

	check_fields($fields);
?>
<?php

	$text = get_request('text', ' ');
	$font = get_request('font', 9);
	$color = get_request('color', 'black');

	switch($color){
		case 'white':
			$color = array('red' => 255, 'green' => 255, 'blue' => 255);
			$shadow = array('red' => 105, 'green' => 105, 'blue' => 105);
			break;
		case 'black':
		default:
			$color = array('red' => 0, 'green' => 0, 'blue' => 0);
			$shadow = array('red' => 175, 'green' => 175, 'blue' => 175);
	}

	$size = imageTextSize($font, $angle, $text);

	$im = imagecreatetruecolor($size['width']+4, $size['height']+4);

	$width = imagesx($im);
	$height = imagesy($im);
	
	$white = imagecolorallocate($im, $shadow['red'], $shadow['green'], $shadow['blue']);
	imagefilledrectangle($im, 0 ,0, $width-1, $height-1, $white);	

	$text_color = imagecolorallocate($im, $color['red'], $color['green'], $color['blue']);
	imageText($im, $font, 0, 0, $size['height'], $text_color, $text);
	
	
	$newImage = imagecreatetruecolor($height, $width);	
	$white = imagecolorallocate($newImage, $shadow['red'], $shadow['green'], $shadow['blue']);
	
	// imagealphablending($newImage, false);
	// imagesavealpha($newImage, true);
	for($w=0; $w<$width; $w++){
		for($h=0; $h<$height; $h++){
			$ref = imagecolorat($im, $w, $h);
			imagesetpixel($newImage, $h, ($width-1)-$w, $ref);
		}
	}
	imagecolortransparent($newImage, $white);
	
	imageOut($newImage);
	imagedestroy($newImage);
	imagedestroy($im);

?>
<?php

include_once('include/page_footer.php');

?>
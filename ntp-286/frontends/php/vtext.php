<?php 
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	define('ZBX_PAGE_NO_AUTHERIZATION', 1);
	
	require_once "include/config.inc.php";

	$page['file'] = 'vtext.php';
	$page['type'] = PAGE_TYPE_IMAGE;

include_once "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"text"=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,		null),
		"font"=>		array(T_ZBX_INT, O_OPT,	null,	BETWEEN(1,5),	null),
	);

	check_fields($fields);
?>
<?php

	$text = get_request("text","");;
	$font = get_request("font",3);
	
	$width = ImageFontWidth($font) * strlen($text);
	$height = ImageFontHeight($font);

	$im = imagecreate($height,$width); 
  
	$backgroud_color = ImageColorAllocate($im,255,255,255); 
	$text_color = ImageColorAllocate($im,0,0,0); 

	ImageStringUp($im,$font,0,$width-1,$text,$text_color);
	imagecolortransparent($im,$backgroud_color);

	ImageOut($im); 
	ImageDestroy($im); 
?>
<?php

include_once "include/page_footer.php";

?>

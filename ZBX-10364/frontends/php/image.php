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
	require_once('include/config.inc.php');
	require_once('include/images.inc.php');

	$page['file']	= 'image.php';
	$page['title']	= 'S_IMAGE';
	$page['type']	= PAGE_TYPE_IMAGE;

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'imageid'=>		array(T_ZBX_INT, O_MAND,P_SYS,	DB_ID,	NULL),
		'width'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(1,2000),	NULL),
		'height'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(1,2000),	NULL),
	);
	check_fields($fields);
?>
<?php

	$resize = 0;

	if(isset($_REQUEST['width']) || isset($_REQUEST['height'])){
		$resize = 1;
		$th_width = get_request('width',0);
		$th_height = get_request('height',0);
	}

	if(!($row = get_image_by_imageid($_REQUEST['imageid']))){
		error('Incorrect image index');
		include_once('include/page_footer.php');
	}

	$source = imagecreatefromstring($row['image']);

	unset($row);

	if($resize == 1){
		$src_width	= imagesx($source);
		$src_height	= imagesy($source);

		if($src_width > $th_width || $src_height > $th_height){
			if($th_width == 0){
				$th_width = $th_height * $src_width/$src_height;
			}
			else if($th_height == 0){
				$th_height = $th_width * $src_height/$src_width;
			}
			else {
				$a = $th_width/$th_height;
				$b = $src_width/$src_height;

				if($a > $b){
					$th_width  = $b * $th_height;
					$th_height = $th_height;
				}
				else {
					$th_height = $th_width/$b;
					$th_width  = $th_width;
				}
			}

			if(function_exists('imagecreatetruecolor') && @imagecreatetruecolor(1,1)){
				$thumb = imagecreatetruecolor($th_width,$th_height);
			}
			else{
				$thumb = imagecreate($th_width,$th_height);
			}

			imagecopyresized(
				$thumb, $source,
				0, 0,
				0, 0,
				$th_width, $th_height,
				$src_width, $src_height);

			imagedestroy($source);

			$source = $thumb;
		}
	}
	imageout($source);
?>
<?php

include_once "include/page_footer.php";

?>

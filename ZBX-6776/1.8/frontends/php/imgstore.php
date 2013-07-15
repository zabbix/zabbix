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
define('ZBX_PAGE_NO_AUTHERIZATION', 1);

require_once('include/config.inc.php');
require_once('include/maps.inc.php');

$page['file'] = 'imgstore.php';
$page['type'] = detect_page_type(PAGE_TYPE_IMAGE);

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'css'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	null,		null),
		'imageid'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,		null),
		'iconid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),
	);

	check_fields($fields);
?>
<?php
	if(isset($_REQUEST['css'])){
		$css= 'div.sysmap_iconid_0{'.
				' height: 50px; '.
				' width: 50px; '.
				' background-image: url("images/general/no_icon.png"); }'."\n";

		$options = array(
			'filter'=> array('imagetype'=> IMAGE_TYPE_ICON),
			'output'=> API_OUTPUT_EXTEND,
			'select_image'=> 1
		);
		$images = CImage::get($options);
		foreach($images as $inum => $image){
//SDI($image['image']);
			$image['image'] = base64_decode($image['image']);

			$ico = imagecreatefromstring($image['image']);
			$w = imagesx($ico);
			$h = imagesy($ico);

			$css.= 'div.sysmap_iconid_'.$image['imageid'].'{'.
						' height: '.$h.'px; '.
						' width: '.$w.'px; '.
						' background-image: url("imgstore.php?iconid='.$image['imageid'].'");'.
						' background-repeat:no-repeat; }'."\n";
		}

		print($css);
	}
	else if(isset($_REQUEST['iconid'])){
		$iconid = get_request('iconid',0);

		if($iconid > 0){
			$image = get_image_by_imageid($iconid);
			print($image['image']);
		}
		else{
			$image = get_default_image(true);
			ImageOut($image);
		}
	}
	else if(isset($_REQUEST['imageid'])){
		session_start();
		$imageid = get_request('imageid',0);

		if(isset($_SESSION['image_id'][$imageid])){
			echo $_SESSION['image_id'][$imageid];
			unset($_SESSION['image_id'][$imageid]);
		}
	}
?>
<?php

include_once('include/page_footer.php');

?>

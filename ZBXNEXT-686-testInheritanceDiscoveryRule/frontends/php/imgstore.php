<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


define('ZBX_PAGE_NO_AUTHERIZATION', 1);

require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/maps.inc.php';

$page['file'] = 'imgstore.php';
$page['type'] = detect_page_type(PAGE_TYPE_IMAGE);

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR		TYPE	OPTIONAL 	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'css' =>		array(T_ZBX_INT, O_OPT, P_SYS, null,				null),
	'imageid' =>	array(T_ZBX_STR, O_OPT, P_SYS, null,				null),
	'iconid' =>		array(T_ZBX_INT, O_OPT, P_SYS, DB_ID,				null),
	'width' =>		array(T_ZBX_INT, O_OPT, P_SYS, BETWEEN(1, 2000),	null),
	'height' =>		array(T_ZBX_INT, O_OPT, P_SYS, BETWEEN(1, 2000),	null),
);
check_fields($fields);

$resize = false;
if (isset($_REQUEST['width']) || isset($_REQUEST['height'])) {
	$resize = true;
	$width = get_request('width', 0);
	$height = get_request('height', 0);
}

if (isset($_REQUEST['css'])) {
	$css = 'div.sysmap_iconid_0 {'.
			' height: 50px;'.
			' width: 50px;'.
			' background-image: url("images/general/no_icon.png"); }'."\n";

	$images = API::Image()->get(array(
		'filter' => array('imagetype' => IMAGE_TYPE_ICON),
		'output' => API_OUTPUT_EXTEND,
		'select_image' => 1
	));
	foreach ($images as $image) {
		$image['image'] = base64_decode($image['image']);
		$ico = imagecreatefromstring($image['image']);

		if ($resize) {
			$ico = imageThumb($ico, $width, $height);
		}
		$w = imagesx($ico);
		$h = imagesy($ico);

		$css .= 'div.sysmap_iconid_'.$image['imageid'].'{'.
					' height: '.$h.'px;'.
					' width: '.$w.'px;'.
					' background: url("imgstore.php?iconid='.$image['imageid'].'&width='.$w.'&height='.$h.'") no-repeat center center;}'."\n";
	}
	echo $css;
}
elseif (isset($_REQUEST['iconid'])) {
	$iconid = get_request('iconid', 0);

	if ($iconid > 0) {
		$image = get_image_by_imageid($iconid);
		$image = $image['image'];
		$source = imageFromString($image);
	}
	else {
		$source = get_default_image();
	}

	if ($resize) {
		$source = imageThumb($source, $width, $height);
	}
	imageOut($source);
}
elseif (isset($_REQUEST['imageid'])) {
	$imageid = get_request('imageid', 0);

	session_start();
	if (isset($_SESSION['image_id'][$imageid])) {
		echo $_SESSION['image_id'][$imageid];
		unset($_SESSION['image_id'][$imageid]);
	}
	session_write_close();
}

require_once dirname(__FILE__).'/include/page_footer.php';

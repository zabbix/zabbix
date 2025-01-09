<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/maps.inc.php';

$page['file'] = 'imgstore.php';
$page['type'] = detect_page_type(PAGE_TYPE_IMAGE);

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR		TYPE	OPTIONAL 	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'css' =>			[T_ZBX_INT, O_OPT, P_SYS, null,				null],
	'imageid' =>		[T_ZBX_STR, O_OPT, P_SYS, null,				null],
	'iconid' =>			[T_ZBX_INT, O_OPT, P_SYS, DB_ID,				null],
	'width' =>			[T_ZBX_INT, O_OPT, P_SYS, BETWEEN(1, 2000),	null],
	'height' =>			[T_ZBX_INT, O_OPT, P_SYS, BETWEEN(1, 2000),	null],
	'unavailable' =>	[T_ZBX_INT, O_OPT, null, IN([0, 1]),		null]
];
check_fields($fields);

$resize = false;
if (isset($_REQUEST['width']) || isset($_REQUEST['height'])) {
	$resize = true;
	$width = getRequest('width', 0);
	$height = getRequest('height', 0);
}

if (isset($_REQUEST['css'])) {
	$css = '';

	$images = API::Image()->get([
		'output' => ['imageid'],
		'filter' => ['imagetype' => IMAGE_TYPE_ICON],
		'select_image' => true
	]);
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
					' width: '.$w.'px;}'."\n";
	}
	echo $css;
}
elseif (isset($_REQUEST['iconid'])) {
	$iconid = getRequest('iconid', 0);
	$unavailable = getRequest('unavailable', 0);

	if ($iconid > 0) {
		$image = get_image_by_imageid($iconid);

		$source = $image['image'] ? imageFromString($image['image']) : get_default_image();

		list(,, $img_type) = getimagesizefromstring($image['image']);

		$img_types = [
			IMAGETYPE_GIF => IMAGE_FORMAT_GIF,
			IMAGETYPE_JPEG => IMAGE_FORMAT_JPEG,
			IMAGETYPE_PNG => IMAGE_FORMAT_PNG
		];
	}
	else {
		$source = get_default_image();
	}

	if ($resize) {
		$source = imageThumb($source, $width, $height);
	}

	if ($unavailable == 1) {
		imagefilter($source, IMG_FILTER_GRAYSCALE);
		imagefilter($source, IMG_FILTER_BRIGHTNESS, 75);
	}

	if ($iconid > 0 && !$resize && $unavailable != 1 && array_key_exists($img_type, $img_types)) {
		set_image_header($img_types[$img_type]);

		echo $image['image'];
	}
	else {
		imageOut($source);
	}
}
elseif (isset($_REQUEST['imageid'])) {
	$imageid = getRequest('imageid', 0);

	if (CSessionHelper::has('image_id')) {
		$image_data = CSessionHelper::get('image_id');
		if (array_key_exists($imageid, $image_data)) {
			echo $image_data[$imageid];
			unset($image_data[$imageid]);
			CSessionHelper::set('image_id', $image_data);
		}
	}
}

require_once dirname(__FILE__).'/include/page_footer.php';

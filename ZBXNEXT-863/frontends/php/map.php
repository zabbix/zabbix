<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
$page['type'] = detect_page_type(PAGE_TYPE_IMAGE);

include_once('include/page_header.php');

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields=array(
	'sysmapid'=>		array(T_ZBX_INT, O_MAND,P_SYS,	DB_ID,		NULL),

	'selements'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	DB_ID,		NULL),
	'links'=>			array(T_ZBX_STR, O_OPT,	P_SYS,	DB_ID,		NULL),
	'noselements'=>		array(T_ZBX_INT, O_OPT,	NULL,	IN("0,1"),	NULL),
	'nolinks'=>			array(T_ZBX_INT, O_OPT,	NULL,	IN("0,1"),	NULL),
	'nocalculations'=>	array(T_ZBX_INT, O_OPT,	NULL,	IN("0,1"),	NULL),

	'show_triggers'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1,2,3"),	NULL),
	'grid'=>			array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(0,500),	NULL),
	'border'=>			array(T_ZBX_INT, O_OPT,	NULL,	IN('0,1'),		NULL),
	'base64image'=>		array(T_ZBX_INT, O_OPT,	NULL,	IN('0,1'),		NULL),
);

check_fields($fields);
?>
<?php

$options = array(
	'sysmapids' => $_REQUEST['sysmapid'],
	'selectSelements' => API_OUTPUT_EXTEND,
	'selectLinks' => API_OUTPUT_EXTEND,
	'output' => API_OUTPUT_EXTEND,
	'preservekeys' => true,
);
$maps = API::Map()->get($options);
$map = reset($maps);

if(!$map){
	access_deny();
}


class CCanvas{

	protected $canvas;
	protected $width;
	protected $height;
	protected $colors = array();

	public function __construct($w, $h){
		$this->width = $w;
		$this->height = $h;

		if(function_exists('imagecreatetruecolor') && @imagecreatetruecolor(1, 1)){
			$this->canvas = imagecreatetruecolor($this->width, $this->height);
		}
		else{
			$this->canvas = imagecreate($this->width, $this->height);
		}

		$this->allocateColors();
	}

	public function getWidth(){
		return $this->width;
	}

	public function getHeight(){
		return $this->height;
	}

	public function fill($color){
		imagefilledrectangle($this->canvas, 0, 0, $this->width, $this->height, $this->getColor($color));
	}

	public function setBgImage($image){
		$bg = imagecreatefromstring($image);
		imagecopy($this->canvas, $bg, 0, 0, 0, 0, imagesx($bg), imagesy($bg));
	}

	public function drawTitle($text, $color){
		$x = $this->width / 2 - imagefontwidth(4) * zbx_strlen($text) / 2;
		imagetext($this->canvas, 10, 0, $x, 25, $this->getColor($color), $text);
	}

	public function drawBorder($color){
		imagerectangle($this->canvas, 0, 0, $this->width - 1, $this->height - 1, $this->getColor($color));
	}

	public function getCanvas(){
		$date = zbx_date2str(S_MAPS_DATE_FORMAT);
		imagestring($this->canvas, 0, $this->width - 120, $this->height - 12, $date, $this->getColor('gray'));
		imagestringup($this->canvas, 0, $this->width - 10, $this->height - 50, S_ZABBIX_URL, $this->getColor('gray'));

		return $this->canvas;
	}

	public function drawLine($x1, $y1, $x2, $y2, $color, $drawtype){
		MyDrawLine($this->canvas, $x1, $y1, $x2, $y2, $this->getColor($color), $drawtype);
	}

	public function drawText($fontsize, $angle, $x, $y, $color, $string){
		imageText($this->canvas, $fontsize, $angle, $x, $y, $this->getColor($color), $string);
	}

	public function drawRectangle($x1, $y1, $x2, $y2, $color){
		imagerectangle($this->canvas, $x1, $y1, $x2, $y2, $this->getColor($color));
	}

	public function drawRoundedRectangle($x1, $y1, $x2, $y2, $radius, $color){
		$color = $this->getColor($color);
		$arcRadius = $radius * 2;
		imagearc($this->canvas, $x1 + $radius, $y1 + $radius, $arcRadius, $arcRadius, 180, 270, $color);
		imagearc($this->canvas, $x1 + $radius, $y2 - $radius, $arcRadius, $arcRadius, 90, 180, $color);
		imagearc($this->canvas, $x2 - $radius, $y1 + $radius, $arcRadius, $arcRadius, 270, 0, $color);
		imagearc($this->canvas, $x2 - $radius, $y2 - $radius, $arcRadius, $arcRadius, 0, 90, $color);

		imageline($this->canvas, $x1 + $radius, $y1, $x2 - $radius, $y1, $color);
		imageline($this->canvas, $x1 + $radius, $y2, $x2 - $radius, $y2, $color);
		imageline($this->canvas, $x1, $y1 + $radius, $x1, $y2 - $radius, $color);
		imageline($this->canvas, $x2, $y1 + $radius, $x2, $y2 - $radius, $color);
	}

	protected function getColor($color){
		if(!isset($this->colors[$color])){
			throw new Exception('Color "'.$color.'" is not allocated.');
		}
		return $this->colors[$color];
	}

	protected function allocateColors(){
		$this->colors['red'] = imagecolorallocate($this->canvas, 255, 0, 0);
		$this->colors['darkred'] = imagecolorallocate($this->canvas, 150, 0, 0);
		$this->colors['green'] = imagecolorallocate($this->canvas, 0, 255, 0);
		$this->colors['darkgreen'] = imagecolorallocate($this->canvas, 0, 150, 0);
		$this->colors['blue'] = imagecolorallocate($this->canvas, 0, 0, 255);
		$this->colors['darkblue'] = imagecolorallocate($this->canvas, 0, 0, 150);
		$this->colors['yellow'] = imagecolorallocate($this->canvas, 255, 255, 0);
		$this->colors['darkyellow'] = imagecolorallocate($this->canvas, 150, 150, 0);
		$this->colors['cyan'] = imagecolorallocate($this->canvas, 0, 255, 255);
		$this->colors['black'] = imagecolorallocate($this->canvas, 0, 0, 0);
		$this->colors['gray'] = imagecolorallocate($this->canvas, 150, 150, 150);
		$this->colors['gray1'] = imagecolorallocate($this->canvas, 180, 180, 180);
		$this->colors['gray2'] = imagecolorallocate($this->canvas, 210, 210, 210);
		$this->colors['gray3'] = imagecolorallocate($this->canvas, 240, 240, 240);
		$this->colors['white'] = imagecolorallocate($this->canvas, 255, 255, 255);
		$this->colors['orange'] = imagecolorallocate($this->canvas, 238, 96, 0);
	}
}

class CMapPainter{

	protected $canvas;
	protected $mapData;
	protected $options;

	public function __construct(array $mapData, array $options = array()){
		$this->options = array(
			'map' => array(
				'bgColor' => 'white',
				'titleColor' => 'darkred',
				'border' => true,
				'drawAreas' => true,
			),
			'grid' => array(
				'size' => 50,
				'color' => 'black',
			),
		);
		foreach($options as $key => $option){
			$this->options[$key] = array_merge($this->options[$key], $option);
		}

		$this->canvas = new CCanvas($mapData['width'], $mapData['height']);

		$this->mapData = $mapData;
	}

	public function paint(){

		$this->paintBackground();
		$this->paintTitle();
		$this->paintGrid();

		if($this->options['map']['drawAreas']){
			$this->paintAreas();
		}

		return $this->canvas->getCanvas();
	}

	protected function paintBackground(){
		$this->canvas->fill($this->options['map']['bgColor']);
		if($this->mapData['backgroundid'] && ($bgImage = get_image_by_imageid($this->mapData['backgroundid']))){
			$this->canvas->setBgImage($bgImage['image']);
		}
	}

	protected function paintTitle(){
		$this->canvas->drawTitle($this->mapData['name'], $this->options['map']['titleColor']);
	}

	protected function paintGrid(){
		$size = $this->options['grid']['size'];
		if(!$size) return;

		$width = $this->canvas->getWidth();
		$height = $this->canvas->getHeight();
		$maxSize = max($width, $height);

		$dims = imageTextSize(8, 0, '00');
		for($xy = $size; $xy < $maxSize; $xy += $size){
			if($xy < $width){
				$this->canvas->drawLine($xy, 0, $xy, $height, $this->options['grid']['color'], MAP_LINK_DRAWTYPE_DASHED_LINE);
				$this->canvas->drawText(8, 0, $xy + 3, $dims['height'] + 3, $this->options['grid']['color'], $xy);
			}
			if($xy < $height){
				$this->canvas->drawLine(0, $xy, $width, $xy, $this->options['grid']['color'], MAP_LINK_DRAWTYPE_DASHED_LINE);
				$this->canvas->drawText(8, 0, 3, $xy + $dims['height'] + 3, $this->options['grid']['color'], $xy);
			}
		}

		$this->canvas->drawText(8, 0, 2, $dims['height'] + 3, 'black', 'Y X:');

	}

	protected function paintAreas(){
		foreach($this->mapData['selements'] as $selement){
			if($selement['elementsubtype'] == SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS
					&& $selement['areatype'] == SYSMAP_ELEMENT_AREA_TYPE_CUSTOM)
			{
				$this->canvas->drawRectangle(
					$selement['x'] + 1,
					$selement['y'] + 1,
					$selement['x'] + $selement['width'] - 1,
					$selement['y'] + $selement['height'] - 1,
					'gray1'
				);
				$this->canvas->drawRectangle(
					$selement['x'],
						$selement['y'],
						$selement['x'] + $selement['width'],
						$selement['y'] + $selement['height'],
					'gray2'
				);
				$this->canvas->drawRectangle(
					$selement['x'] - 1,
					$selement['y'] - 1,
						$selement['x'] + $selement['width'] + 1,
						$selement['y'] + $selement['height'] + 1,
					'gray3'
				);
			}
		}
	}
}

$mapOptions = array(
	'map' => array(
		'drawAreas' => (!isset($_REQUEST['selements']) && !isset($_REQUEST['noselements'])),
	),
	'grid' => array(
		'size' => get_request('grid', 0),
	),
);
$mapPainter = new CMapPainter($map, $mapOptions);

$im = $mapPainter->paint();


$colors['Red'] = imagecolorallocate($im, 255, 0, 0);
$colors['Dark Red'] = imagecolorallocate($im, 150, 0, 0);
$colors['Green'] = imagecolorallocate($im, 0, 255, 0);
$colors['Dark Green'] = imagecolorallocate($im, 0, 150, 0);
$colors['Blue'] = imagecolorallocate($im, 0, 0, 255);
$colors['Dark Blue'] = imagecolorallocate($im, 0, 0, 150);
$colors['Yellow'] = imagecolorallocate($im, 255, 255, 0);
$colors['Dark Yellow'] = imagecolorallocate($im, 150, 150, 0);
$colors['Cyan'] = imagecolorallocate($im, 0, 255, 255);
$colors['Black'] = imagecolorallocate($im, 0, 0, 0);
$colors['Gray'] = imagecolorallocate($im, 150, 150, 150);
$colors['White'] = imagecolorallocate($im, 255, 255, 255);
$colors['Orange'] = imagecolorallocate($im, 238, 96, 0);



$x = imagesx($im);
$y = imagesy($im);

// ACTION /////////////////////////////////////////////////////////////////////////////

$json = new CJSON();

if(isset($_REQUEST['selements']) || isset($_REQUEST['noselements'])){
	$map['selements'] = get_request('selements', '[]');
	$map['selements'] = $json->decode($map['selements'], true);
}

if(isset($_REQUEST['links']) || isset($_REQUEST['nolinks'])){
	$map['links'] = get_request('links', '[]');
	$map['links'] = $json->decode($map['links'], true);
}

$nocalculations = get_request('nocalculations', false);
if($nocalculations){
	$map_info = array();
	foreach($map['selements'] as $selement){
		$map_info[$selement['selementid']] = array(
			'iconid' => $selement['iconid_off'],
			'icon_type' => SYSMAP_ELEMENT_ICON_OFF,
		);
		if($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_IMAGE){
			$map_info[$selement['selementid']]['name'] = _('Image');
		}
		else{
			$map_info[$selement['selementid']]['name'] = $selement['elementName'];
		}
	}
	$allLinks = true;
}
else{
	$areas = populateFromMapAreas($map);
	$map_info = getSelementsInfo($map);
	processAreasCoordinates($map, $areas, $map_info);
	$allLinks = false;
}

// Draw MAP
drawMapConnectors($im, $map, $map_info, $allLinks);

if(!isset($_REQUEST['noselements'])){
	drawMapHighligts($im, $map, $map_info);
	drawMapSelements($im, $map, $map_info);
}

drawMapLabels($im, $map, $map_info, !$nocalculations);
drawMapLinkLabels($im, $map, $map_info, !$nocalculations);

if(!isset($_REQUEST['noselements']) && ($map['markelements'] == 1)){
	drawMapSelementsMarks($im, $map, $map_info);
}
//--


show_messages();

if(get_request('base64image')){
	ob_start();
	imagepng($im);
	$imageSource = ob_get_contents();
	ob_end_clean();
	$json = new CJSON();
	echo $json->encode(array('result' => base64_encode($imageSource)));
	imagedestroy($im);
}
else{
	imageOut($im);
}

include_once('include/page_footer.php');

?>

<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/services.inc.php';

$page['file'] = 'chart5.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'serviceid' => [T_ZBX_INT, O_MAND, P_SYS, DB_ID, null]
];
if (!check_fields($fields)) {
	exit();
}

/*
 * Permissions
 */
$service = API::Service()->get([
	'output' => ['serviceid', 'name'],
	'serviceids' => $_REQUEST['serviceid']
]);
$service = reset($service);
if (!$service) {
	access_deny();
}

/*
 * Display
 */
$start_time = microtime(true);

$sizeX = 900;
$sizeY = 300;

$shiftX = 12;
$shiftYup = 25;
$shiftYdown = 25 + 15 * 3;

if (function_exists('imagecolorexactalpha') && function_exists('imagecreatetruecolor') && @imagecreatetruecolor(1, 1)) {
	$im = imagecreatetruecolor($sizeX + $shiftX + 61, $sizeY + $shiftYup + $shiftYdown + 10);
}
else {
	$im = imagecreate($sizeX + $shiftX + 61, $sizeY + $shiftYup + $shiftYdown + 10);
}

$graphtheme = [
	'theme' => 'blue-theme',
	'textcolor' => '1F2C33',
	'highlightcolor' => 'E33734',
	'backgroundcolor' => 'FFFFFF',
	'graphcolor' => 'FFFFFF',
	'gridcolor' => 'CCD5D9',
	'maingridcolor' => 'ACBBC2',
	'gridbordercolor' => 'ACBBC2',
	'nonworktimecolor' => 'EBEBEB',
	'leftpercentilecolor' => '429E47',
	'righttpercentilecolor' => 'E33734'
];

$themes = DB::find('graph_theme', [
	'theme' => getUserTheme(CWebUser::$data)
]);
if ($themes) {
	$graphtheme = $themes[0];
}

$black = get_color($im, '000000');
$green = get_color($im, '34AF67');
$red = get_color($im, 'D64E4E');
$grey = get_color($im, '969696', 50);
$backgroundcolor = get_color($im, $graphtheme['backgroundcolor']);
$gridcolor = get_color($im, $graphtheme['gridcolor']);
$textcolor = get_color($im, $graphtheme['textcolor']);
$highlightcolor = get_color($im, $graphtheme['highlightcolor']);

$x = imagesx($im);
$y = imagesy($im);

imagefilledrectangle($im, 0, 0, $x, $y, $backgroundcolor);

$d = zbx_date2str(_x('Y', DATE_FORMAT_CONTEXT));
$str = _s('%1$s (year %2$s)', $service['name'], $d);
$x = imagesx($im) / 2 - imagefontwidth(4) * mb_strlen($str) / 2;
imageText($im, 10, 0, $x, 14, $textcolor, $str);

$now = time(null);
$to_time = $now;

$count_now = [];
$problem = [];

$start = mktime(0, 0, 0, 1, 1, date('Y'));

$wday = date('w', $start);
if ($wday == 0) {
	$wday = 7;
}
$start = $start - ($wday - 1) * 24 * 3600;

$weeks = (int) date('W') + ($wday ? 1 : 0);

$intervals = [];
for ($i = 0; $i < 52; $i++) {
	if (($period_start = $start + 7 * 24 * 3600 * $i) > time()) {
		break;
	}

	if (($period_end = $start + 7 * 24 * 3600 * ($i + 1)) > time()) {
		$period_end = time();
	}

	$intervals[] = [
		'from' => $period_start,
		'to' => $period_end
	];
}

$sla = API::Service()->getSla([
	'serviceids' => $service['serviceid'],
	'intervals' => $intervals
]);
$sla = reset($sla);

foreach ($sla['sla'] as $i => $intervalSla) {
	$problem[$i] = 100 - $intervalSla['problemTime'];
	$ok[$i] = $intervalSla['sla'];
	$count_now[$i] = 1;
}

for ($i = 0; $i <= $sizeY; $i += $sizeY / 10) {
	dashedLine($im, $shiftX, $i + $shiftYup, $sizeX + $shiftX, $i + $shiftYup, $gridcolor);
}

for ($i = 0, $period_start = $start; $i <= $sizeX; $i += $sizeX / 52) {
	dashedLine($im, $i + $shiftX, $shiftYup, $i + $shiftX, $sizeY + $shiftYup, $gridcolor);
	imageText($im, 6, 90, $i + $shiftX + 4, $sizeY + $shiftYup + 35, $textcolor, zbx_date2str(_('d.M'), $period_start));
	$period_start += 7 * 24 * 3600;
}

$maxY = max(max($problem), 100);
$minY = 0;

$maxX = $sizeX;
$minX = 0;

for ($i = 1; $i <= $weeks; $i++) {
	if (!isset($ok[$i-1])) {
		continue;
	}
	$x2 = ($sizeX / 52) * ($i - 1 - $minX) * $sizeX / ($maxX - $minX);
	$y2 = $sizeY * ($ok[$i - 1] - $minY) / ($maxY - $minY);

	$maxSizeY = $sizeY;
	if ($i == $weeks) {
		$maxSizeY = $sizeY * (date('w') / 7);
		$y2 = $maxSizeY * ($ok[$i - 1] - $minY) / ($maxY - $minY);
	}

	if ($y2 != 0) {
		imagefilledrectangle(
			$im,
			$x2 + $shiftX, $shiftYup + $sizeY - $y2,
			$x2 + $shiftX + 8, $shiftYup + $sizeY,
			$green
		);
	}
	if ($y2 != $maxSizeY) {
		imagefilledrectangle(
			$im,
			$x2 + $shiftX, $shiftYup + $sizeY - $maxSizeY,
			$x2 + $shiftX + 8, $shiftYup + $sizeY - $y2,
			$red
		);
	}
}

for ($i = 0; $i <= $sizeY; $i += $sizeY / 10) {
	imageText($im, 7, 0, $sizeX + 5 + $shiftX, $sizeY - $i - 4 + $shiftYup + 8, $highlightcolor,
		($i * ($maxY - $minY) / $sizeY + $minY).'%'
	);
}

$x = $shiftX;
$y = $sizeY + $shiftYup + 31;

imagefilledrectangle($im, $x, $y + 15 * 1, $x + 10, $y + 10 + 15 * 1, $green);
imagerectangle($im, $x, $y + 15 * 1, $x + 10, $y + 10 + 15 * 1, $black);
imageText($im, 8, 0, $x + 14, $y + 10 + 15 * 1, $textcolor, _('OK').' (%)');

imagefilledrectangle($im, $x, $y + 15 * 2, $x + 10, $y + 10 + 15 * 2, $red);
imagerectangle($im, $x, $y + 15 * 2, $x + 10, $y + 10 + 15 * 2, $black);
imageText($im, 8, 0, $x + 14, $y + 10 + 15 * 2, $textcolor, _('PROBLEM').' (%)');

imagestringup($im, 1, imagesx($im) - 10, imagesy($im) - 50, ZABBIX_HOMEPAGE, $grey);

$str = sprintf('%0.2f', microtime(true) - $start_time);
$str = _s('Generated in %s sec', $str);
$strSize = imageTextSize(6, 0, $str);
imageText($im, 6, 0, imagesx($im) - $strSize['width'] - 5, imagesy($im) - 5, $grey, $str);
imageOut($im);
imagedestroy($im);

require_once dirname(__FILE__).'/include/page_footer.php';

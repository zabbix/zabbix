<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
$fields = array(
	'serviceid' => array(T_ZBX_INT, O_MAND, P_SYS, DB_ID, null)
);
if (!check_fields($fields)) {
	exit();
}

/*
 * Permissions
 */
$service = API::Service()->get(array(
	'output' => array('serviceid', 'name'),
	'serviceids' => $_REQUEST['serviceid']
));
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
$shiftYup = 17;
$shiftYdown = 25 + 15 * 3;

$im = imagecreate($sizeX + $shiftX + 61, $sizeY + $shiftYup + $shiftYdown + 10);

$red = imagecolorallocate($im, 255, 0, 0);
$darkred = imagecolorallocate($im, 150, 0, 0);
$green = imagecolorallocate($im, 0, 255, 0);
$darkgreen = imagecolorallocate($im, 0, 150, 0);
$blue = imagecolorallocate($im, 0, 0, 255);
$darkblue = imagecolorallocate($im, 0, 0, 150);
$yellow = imagecolorallocate($im, 255, 255, 0);
$darkyellow = imagecolorallocate($im, 150, 150, 0);
$cyan = imagecolorallocate($im, 0, 255, 255);
$black = imagecolorallocate($im, 0, 0, 0);
$gray = imagecolorallocate($im, 150, 150, 150);
$white = imagecolorallocate($im, 255, 255, 255);
$bg = imagecolorallocate($im, 6 + 6 * 16, 7 + 7 * 16, 8 + 8 * 16);

$x = imagesx($im);
$y = imagesy($im);

imagefilledrectangle($im, 0, 0, $x, $y, $white);
imagerectangle($im, 0, 0, $x-1, $y-1, $black);

$d = zbx_date2str('Y');
$str = _s('%1$s (year %2$s)', $service['name'], $d);
$x = imagesx($im) / 2 - imagefontwidth(4) * zbx_strlen($str) / 2;
imageText($im, 10, 0, $x, 14, $darkred, $str);

$now = time(null);
$to_time = $now;

$count_now = array();
$problem = array();

$start = mktime(0, 0, 0, 1, 1, date('Y'));

$wday = date('w', $start);
if ($wday == 0) {
	$wday = 7;
}
$start = $start - ($wday - 1) * 24 * 3600;

$weeks = (int) date('W') + ($wday ? 1 : 0);

$intervals = array();
for ($i = 0; $i < 52; $i++) {
	if (($period_start = $start + 7 * 24 * 3600 * $i) > time()) {
		break;
	}

	if (($period_end = $start + 7 * 24 * 3600 * ($i + 1)) > time()) {
		$period_end = time();
	}

	$intervals[] = array(
		'from' => $period_start,
		'to' => $period_end
	);
}

$sla = API::Service()->getSla(array(
	'serviceids' => $service['serviceid'],
	'intervals' => $intervals
));
$sla = reset($sla);

foreach ($sla['sla'] as $i => $intervalSla) {
	$problem[$i] = 100 - $intervalSla['problemTime'];
	$ok[$i] = $intervalSla['sla'];
	$count_now[$i] = 1;
}

for ($i = 0; $i <= $sizeY; $i += $sizeY / 10) {
	dashedLine($im, $shiftX, $i + $shiftYup, $sizeX + $shiftX, $i + $shiftYup, $gray);
}

for ($i = 0, $period_start = $start; $i <= $sizeX; $i += $sizeX / 52) {
	dashedLine($im, $i + $shiftX, $shiftYup, $i + $shiftX, $sizeY + $shiftYup, $gray);
	imageText($im, 6, 90, $i + $shiftX + 4, $sizeY + $shiftYup + 35, $black, zbx_date2str(_('d.M'), $period_start));
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

	imagefilledrectangle(
		$im,
		$x2 + $shiftX, $shiftYup + $sizeY - $y2,
		$x2 + $shiftX + 8, $shiftYup + $sizeY,
		imagecolorallocate($im, 120, 235, 120)
	);
	imagerectangle(
		$im,
		$x2 + $shiftX, $shiftYup + $sizeY - $y2,
		$x2 + $shiftX + 8, $shiftYup + $sizeY,
		$black
	);
	imagefilledrectangle(
		$im,
		$x2 + $shiftX, $shiftYup + $sizeY - $maxSizeY,
		$x2 + $shiftX + 8, $shiftYup + $sizeY - $y2,
		imagecolorallocate($im, 235, 120, 120)
	);
	imagerectangle(
		$im,
		$x2 + $shiftX, $shiftYup + $sizeY - $maxSizeY,
		$x2 + $shiftX + 8, $shiftYup + $sizeY - $y2,
		$black
	);
}

for ($i = 0; $i <= $sizeY; $i += $sizeY / 10) {
	imageText($im, 7, 0, $sizeX + 5 + $shiftX, $sizeY - $i - 4 + $shiftYup + 8, $darkred, ($i * ($maxY - $minY) / $sizeY + $minY).'%');
}

imagefilledrectangle($im, $shiftX, $sizeY + $shiftYup + 34 + 15 * 1, $shiftX + 5, $sizeY + $shiftYup + 30 + 9 + 15 * 1, imagecolorallocate($im, 120, 235, 120));
imagerectangle($im, $shiftX, $sizeY + $shiftYup + 34 + 15 * 1, $shiftX + 5, $sizeY + $shiftYup + 30 + 9 + 15 * 1, $black);
imageText($im, 8, 0, $shiftX + 9, $sizeY + $shiftYup + 15 * 1 + 41, $black, _('OK').' (%)');

imagefilledrectangle($im, $shiftX, $sizeY + $shiftYup + 34 + 15 * 2, $shiftX + 5, $sizeY + $shiftYup + 30 + 9 + 15 * 2, $darkred);
imagerectangle($im, $shiftX, $sizeY + $shiftYup + 34 + 15 * 2, $shiftX + 5, $sizeY + $shiftYup + 30 + 9 + 15 * 2, $black);
imageText($im, 8, 0, $shiftX + 9, $sizeY + $shiftYup + 15 * 2 + 41, $black, _('PROBLEM').' (%)');
imagestringup($im, 0, imagesx($im) - 10, imagesy($im) - 50, 'http://www.zabbix.com', $gray);

$str = sprintf('%0.2f', microtime(true) - $start_time);
$str = _s('Generated in %s sec', $str);
$strSize = imageTextSize(6, 0, $str);
imageText($im, 6, 0, imagesx($im) - $strSize['width'] - 5, imagesy($im) - 5, $gray, $str);
imageOut($im);
imagedestroy($im);

require_once dirname(__FILE__).'/include/page_footer.php';

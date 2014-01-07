<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
require_once dirname(__FILE__).'/include/triggers.inc.php';

$page['file'] = 'chart4.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'triggerid' => array(T_ZBX_INT, O_MAND, P_SYS, DB_ID, null)
);
check_fields($fields);

/*
 * Permissions
 */
if (!isset($_REQUEST['triggerid'])) {
	fatal_error(_('No triggers defined.'));
}

$db_data = API::Trigger()->get(array(
	'triggerids' => $_REQUEST['triggerid'],
	'output' => API_OUTPUT_EXTEND,
	'nodeids' => get_current_nodeid(true),
	'expandDescription' => true
));
if (empty($db_data)) {
	access_deny();
}
else {
	$db_data = reset($db_data);
}

/*
 * Display
 */
$start_time = microtime(true);

$sizeX		= 900;
$sizeY		= 300;

$shiftX		= 12;
$shiftYup	= 17;
$shiftYdown	= 25 + 15 * 3;

$im = imagecreate($sizeX + $shiftX + 61, $sizeY + $shiftYup + $shiftYdown + 10);

$red		= imagecolorallocate($im, 255, 0, 0);
$darkred	= imagecolorallocate($im, 150, 0, 0);
$green		= imagecolorallocate($im, 0, 255, 0);
$darkgreen	= imagecolorallocate($im, 0, 150, 0);
$bluei		= imagecolorallocate($im, 0, 0, 255);
$darkblue	= imagecolorallocate($im, 0, 0, 150);
$yellow		= imagecolorallocate($im, 255, 255, 0);
$darkyellow	= imagecolorallocate($im, 150, 150, 0);
$cyan		= imagecolorallocate($im, 0, 255, 255);
$black		= imagecolorallocate($im, 0, 0, 0);
$gray		= imagecolorallocate($im, 150, 150, 150);
$white		= imagecolorallocate($im, 255, 255, 255);
$bg			= imagecolorallocate($im, 6 + 6 * 16, 7 + 7 * 16, 8 + 8 * 16);

$x = imagesx($im);
$y = imagesy($im);

imagefilledrectangle($im, 0, 0, $x, $y, $white);
imagerectangle($im, 0, 0, $x - 1, $y - 1, $black);

$str = _s('%1$s (year %2$s)', $db_data['description'], zbx_date2str('Y'));
$x = imagesx($im) / 2 - imagefontwidth(4) * zbx_strlen($str) / 2;
imageText($im, 10, 0, $x, 14, $darkred, $str);

$now = time(null);
$count_now = array();
$true = array();
$false = array();
$unknown = array();

$start = mktime(0, 0, 0, 1, 1, date('Y'));

$wday = date('w', $start);
if ($wday == 0) {
	$wday = 7;
}
$start = $start - ($wday - 1) * SEC_PER_DAY;

$weeks = (int) (date('z') / 7 + 1);

for ($i = 0; $i < $weeks; $i++) {
	$period_start = $start + SEC_PER_WEEK * $i;
	$period_end = $start + SEC_PER_WEEK * ($i + 1);

	$stat = calculate_availability($_REQUEST['triggerid'], $period_start, $period_end);
	$true[$i] = $stat['true'];
	$false[$i] = $stat['false'];
	$unknown[$i] = $stat['unknown'];
	$count_now[$i] = 1;
}

for ($i = 0; $i <= $sizeY; $i += $sizeY / 10) {
	dashedLine($im, $shiftX, $i + $shiftYup, $sizeX + $shiftX, $i + $shiftYup, $gray);
}

for ($i = 0, $period_start = $start; $i <= $sizeX; $i += $sizeX / 52) {
	dashedLine($im, $i + $shiftX, $shiftYup, $i + $shiftX, $sizeY + $shiftYup, $gray);
	imageText($im, 6, 90, $i + $shiftX + 4, $sizeY + $shiftYup + 30, $black, zbx_date2str(_('d.M'), $period_start));

	$period_start += SEC_PER_WEEK;
}

$maxY = max(max($true), 100);
$minY = 0;

$maxX = 900;
$minX = 0;

for ($i = 1; $i <= $weeks; $i++) {
	$x1 = (900 / 52) * $sizeX * ($i - 1 - $minX) / ($maxX - $minX);

	$yt = $sizeY * $true[$i - 1] / 100;
	if ($yt > 0) {
		imagefilledrectangle($im, $x1 + $shiftX, $shiftYup, $x1 + $shiftX + 8, $yt + $shiftYup, imagecolorallocate($im, 235, 120, 120)); // red
	}

	$yu = (int) ($sizeY * $unknown[$i - 1] / 100 + 0.5);
	if ($yu > 0) {
		imagefilledrectangle($im, $x1 + $shiftX, $yt + $shiftYup, $x1 + $shiftX + 8, $yt + $yu + $shiftYup, imagecolorallocate($im, 235, 235, 235)); // unknown
	}

	$yf = $sizeY * $false[$i - 1] / 100;
	if ($yf > 0) {
		imagefilledrectangle($im, $x1 + $shiftX, $yt + $yu + $shiftYup, $x1 + $shiftX + 8, $sizeY + $shiftYup, imagecolorallocate($im, 120, 235, 120)); // green
	}

	if ($yt + $yf + $yu > 0) {
		imagerectangle($im, $x1 + $shiftX, $shiftYup, $x1 + $shiftX + 8, $sizeY + $shiftYup, $black);
	}
}

for ($i = 0; $i <= $sizeY; $i += $sizeY / 10) {
	imageText($im, 7, 0, $sizeX + 5 + $shiftX, $sizeY - $i - 4 + $shiftYup + 8, $darkred, $i * ($maxY - $minY) / $sizeY + $minY);
}

imagefilledrectangle($im, $shiftX, $sizeY + $shiftYup + 39 + 15 * 0, $shiftX + 5, $sizeY + $shiftYup + 35 + 9 + 15 * 0, imagecolorallocate($im, 120, 235, 120));
imagerectangle($im, $shiftX, $sizeY + $shiftYup + 39 + 15 * 0, $shiftX + 5, $sizeY + $shiftYup + 35 + 9 + 15 * 0, $black);
imageText($im, 8, 0, $shiftX + 9, $sizeY + $shiftYup + 15 * 0 + 45, $black, _('OK').' (%)');

imagefilledrectangle($im, $shiftX, $sizeY + $shiftYup + 39 + 15 * 1, $shiftX + 5, $sizeY + $shiftYup + 35 + 9 + 15 * 1, imagecolorallocate($im, 235, 120, 120));
imagerectangle($im, $shiftX, $sizeY + $shiftYup + 39 + 15 * 1, $shiftX + 5, $sizeY + $shiftYup + 15 + 9 + 35 * 1, $black);
imageText($im, 8, 0, $shiftX + 9, $sizeY + $shiftYup + 15 * 1 + 45, $black, _('Problems').' (%)');

imagefilledrectangle($im, $shiftX, $sizeY + $shiftYup + 39 + 15 * 2, $shiftX + 5, $sizeY + $shiftYup + 35 + 9 + 15 * 2, imagecolorallocate($im, 220, 220, 220));
imagerectangle($im, $shiftX, $sizeY + $shiftYup + 39 + 15 * 2, $shiftX + 5, $sizeY + $shiftYup + 35 + 9 + 15 * 2, $black);
imageText($im, 8, 0, $shiftX + 9, $sizeY + $shiftYup + 15 * 2 + 45, $black, _('Unknown').' (%)');

imagestringup($im, 0, imagesx($im) - 10, imagesy($im) - 50, 'http://www.zabbix.com', $gray);

$str = sprintf('%0.2f', microtime(true) - $start_time);
$str = _s('Generated in %s sec', $str);
$strSize = imageTextSize(6, 0, $str);
imageText($im, 6, 0, imagesx($im) - $strSize['width'] - 5, imagesy($im) - 5, $gray, $str);

imageOut($im);
imagedestroy($im);

require_once dirname(__FILE__).'/include/page_footer.php';

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
require_once dirname(__FILE__).'/include/triggers.inc.php';

$page['file'] = 'chart4.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'triggerid' => [T_ZBX_INT, O_MAND, P_SYS, DB_ID, null]
];
if (!check_fields($fields)) {
	session_write_close();
	exit();
}

/*
 * Permissions
 */
if (!hasRequest('triggerid')) {
	fatal_error(_('No triggers defined.'));
}

$dbTriggers = API::Trigger()->get([
	'output' => ['description'],
	'triggerids' => getRequest('triggerid'),
	'expandDescription' => true
]);

if (!$dbTriggers) {
	access_deny();
}

$dbTrigger = reset($dbTriggers);

/*
 * Display
 */
$debug_mode = CWebUser::getDebugMode();
if ($debug_mode) {
	$start_time = microtime(true);
}

$sizeX = 900;
$sizeY = 300;

$shiftX = 12;
$shiftYup = 17;
$shiftYdown = 55;

$im = imagecreate($sizeX + $shiftX + 61, $sizeY + $shiftYup + $shiftYdown + 10);

$red = imagecolorallocate($im, 255, 0, 0);
$darkred = imagecolorallocate($im, 150, 0, 0);
$green = imagecolorallocate($im, 0, 255, 0);
$darkgreen = imagecolorallocate($im, 0, 150, 0);
$bluei = imagecolorallocate($im, 0, 0, 255);
$darkblue = imagecolorallocate($im, 0, 0, 150);
$yellow = imagecolorallocate($im, 255, 255, 0);
$darkyellow = imagecolorallocate($im, 150, 150, 0);
$cyan = imagecolorallocate($im, 0, 255, 255);
$black = imagecolorallocate($im, 0, 0, 0);
$gray = imagecolorallocate($im, 150, 150, 150);
$white = imagecolorallocate($im, 255, 255, 255);
$bg = imagecolorallocate($im, 102, 119, 136);

$x = imagesx($im);
$y = imagesy($im);

imagefilledrectangle($im, 0, 0, $x, $y, $white);
imagerectangle($im, 0, 0, $x - 1, $y - 1, $black);

$str = _s('%1$s (year %2$s)', $dbTrigger['description'], zbx_date2str(_x('Y', DATE_FORMAT_CONTEXT)));
$x = imagesx($im) / 2 - imagefontwidth(4) * mb_strlen($str) / 2;
imageText($im, 10, 0, $x, 14, $darkred, $str);

$now = time();
$count_now = [];
$true = [];
$false = [];

$start = mktime(0, 0, 0, 1, 1, date('Y'));

$wday = date('w', $start);
if ($wday == 0) {
	$wday = 7;
}
$start = $start - ($wday - 1) * SEC_PER_DAY;

$weeks = (int) (date('z') / 7 + 1);

for ($i = 0; $i < $weeks; $i++) {
	$periodStart = $start + SEC_PER_WEEK * $i;
	$periodEnd = $start + SEC_PER_WEEK * ($i + 1);

	$stat = calculateAvailability(getRequest('triggerid'), $periodStart, $periodEnd - 1);
	$true[$i] = $stat['true'];
	$false[$i] = $stat['false'];
	$count_now[$i] = 1;
}

for ($i = 0; $i <= $sizeY; $i += $sizeY / 10) {
	dashedLine($im, $shiftX, $i + $shiftYup, $sizeX + $shiftX, $i + $shiftYup, $gray);
}

for ($i = 0, $periodStart = $start; $i <= $sizeX; $i += $sizeX / 52) {
	dashedLine($im, $i + $shiftX, $shiftYup, $i + $shiftX, $sizeY + $shiftYup, $gray);
	imageText($im, 6, 90, $i + $shiftX + 4, $sizeY + $shiftYup + 30, $black, zbx_date2str(_('d.M'), $periodStart));

	$periodStart += SEC_PER_WEEK;
}

$maxY = max(max($true), 100);
$minY = 0;

$maxX = 900;
$minX = 0;

for ($i = 1; $i <= $weeks; $i++) {
	$x1 = (int) ((900 / 52) * $sizeX * ($i - 1 - $minX) / ($maxX - $minX));

	$yt = (int) ($sizeY * $true[$i - 1] / 100);
	if ($yt > 0) {
		imagefilledrectangle($im, $x1 + $shiftX, $shiftYup, $x1 + $shiftX + 8, $yt + $shiftYup, imagecolorallocate($im, 235, 120, 120)); // red
	}

	$yf = $sizeY * $false[$i - 1] / 100;
	if ($yf > 0) {
		imagefilledrectangle($im, $x1 + $shiftX, $yt + $shiftYup, $x1 + $shiftX + 8, $sizeY + $shiftYup, imagecolorallocate($im, 120, 235, 120)); // green
	}

	if ($yt + $yf > 0) {
		imagerectangle($im, $x1 + $shiftX, $shiftYup, $x1 + $shiftX + 8, $sizeY + $shiftYup, $black);
	}
}

for ($i = 0; $i <= $sizeY; $i += $sizeY / 10) {
	imageText($im, 7, 0, $sizeX + 5 + $shiftX, $sizeY - $i - 4 + $shiftYup + 8, $darkred, $i * ($maxY - $minY) / $sizeY + $minY);
}

imagefilledrectangle($im, $shiftX, $sizeY + $shiftYup + 39, $shiftX + 5, $sizeY + $shiftYup + 44, imagecolorallocate($im, 120, 235, 120));
imagerectangle($im, $shiftX, $sizeY + $shiftYup + 39, $shiftX + 5, $sizeY + $shiftYup + 44, $black);
imageText($im, 8, 0, $shiftX + 9, $sizeY + $shiftYup + 45, $black, _('OK').' (%)');

imagefilledrectangle($im, $shiftX, $sizeY + $shiftYup + 54, $shiftX + 5, $sizeY + $shiftYup + 59, imagecolorallocate($im, 235, 120, 120));
imagerectangle($im, $shiftX, $sizeY + $shiftYup + 54, $shiftX + 5, $sizeY + $shiftYup + 59, $black);
imageText($im, 8, 0, $shiftX + 9, $sizeY + $shiftYup + 60, $black, _('Problems').' (%)');

if ($debug_mode) {
	$str = sprintf('%0.2f', microtime(true) - $start_time);
	$str = _s('Generated in %1$s sec', $str);
	$str_size = imageTextSize(6, 0, $str);
	imageText($im, 6, 0, imagesx($im) - $str_size['width'] - 5, imagesy($im) - 5, $gray, $str);
}

imageOut($im);
imagedestroy($im);

require_once dirname(__FILE__).'/include/page_footer.php';

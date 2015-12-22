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
?>
<?php
require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/images.inc.php';

$page['file'] = 'image.php';
$page['title'] = _('Image');
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';
?>
<?php
//	VAR		TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'imageid' =>	array(T_ZBX_INT, O_MAND, P_SYS, DB_ID,				null),
	'width' =>		array(T_ZBX_INT, O_OPT, P_SYS,	BETWEEN(1, 2000),	null),
	'height' =>		array(T_ZBX_INT, O_OPT, P_SYS,	BETWEEN(1, 2000),	null),
);
check_fields($fields);
?>
<?php
$resize = false;
if (isset($_REQUEST['width']) || isset($_REQUEST['height'])) {
	$resize = true;
	$width = get_request('width', 0);
	$height = get_request('height', 0);
}
if (!($row = get_image_by_imageid($_REQUEST['imageid']))) {
	error(_('Incorrect image index.'));
	require_once dirname(__FILE__).'/include/page_footer.php';
}
$source = imageFromString($row['image']);
unset($row);

if ($resize) {
	$source = imageThumb($source, $width, $height);
}
imageout($source);

require_once 'include/page_footer.php';
?>

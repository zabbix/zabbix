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


function getImageByIdent($ident) {
	zbx_value2array($ident);

	if (!isset($ident['name'])) {
		return 0;
	}

	static $images;

	if ($images === null) {
		$images = array();

		$dbImages = API::Image()->get(array(
			'output' => array('imageid', 'name')
		));

		foreach ($dbImages as $image) {
			if (!isset($images[$image['name']])) {
				$images[$image['name']] = array();
			}

			$images[$image['name']][] = $image;
		}
	}

	$ident['name'] = trim($ident['name'], ' ');

	return isset($images[$ident['name']]) ? reset($images[$ident['name']]) : 0;
}

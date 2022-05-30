<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


function get_default_image() {
	$image = imagecreate(50, 50);
	$color = imagecolorallocate($image, 250, 50, 50);
	imagefill($image, 0, 0, $color);

	return $image;
}

/**
 * Get image data from db, cache is used
 * @param  $imageid
 * @return array image data from db
 */
function get_image_by_imageid($imageid) {
	static $images = [];

	if (!isset($images[$imageid])) {
		$row = DBfetch(DBselect('SELECT i.* FROM images i WHERE i.imageid='.zbx_dbstr($imageid)));
		$images[$imageid] = $row;
	}
	return $images[$imageid];
}

/**
 * Resizes the given image resource to the specified size keeping the original
 * proportions of the image.
 *
 * @param resource $source
 * @param int $thumbWidth
 * @param int $thumbHeight
 *
 * @return resource
 */
function imageThumb($source, $thumbWidth = 0, $thumbHeight = 0) {
	$srcWidth	= imagesx($source);
	$srcHeight	= imagesy($source);

	if ($srcWidth > $thumbWidth || $srcHeight > $thumbHeight) {
		if ($thumbWidth == 0) {
			$thumbWidth = $thumbHeight * $srcWidth / $srcHeight;
		}
		elseif ($thumbHeight == 0) {
			$thumbHeight = $thumbWidth * $srcHeight / $srcWidth;
		}
		else {
			$a = $thumbWidth / $thumbHeight;
			$b = $srcWidth / $srcHeight;

			if ($a > $b) {
				$thumbWidth = $b * $thumbHeight;
			}
			else {
				$thumbHeight = $thumbWidth / $b;
			}
		}

		$thumbWidth = (int) round($thumbWidth);
		$thumbHeight = (int) round($thumbHeight);

		$thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);

		// preserve png transparency
		imagealphablending($thumb, false);
		imagesavealpha($thumb, true);

		imagecopyresampled(
			$thumb, $source,
			0, 0,
			0, 0,
			$thumbWidth, $thumbHeight,
			$srcWidth, $srcHeight
		);

		imagedestroy($source);
		$source = $thumb;
	}

	return $source;
}

/**
 * Creates an image from a string preserving PNG transparency.
 *
 * @param $imageString
 *
 * @return resource
 */
function imageFromString($imageString) {
	$image = imagecreatefromstring($imageString);

	// preserve PNG transparency
	imagealphablending($image, false);
	imagesavealpha($image, true);
	return $image;
}

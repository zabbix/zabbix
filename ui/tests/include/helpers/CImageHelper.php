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

/**
 * Helper for image related operations.
 */
class CImageHelper {

	/**
	 * Image compare threshold.
	 *
	 * @var integer
	 */
	protected static $threshold = 0;

	/**
	 * Default color used to erase regions.
	 *
	 * @var array
	 */
	protected static $erase_color = [255, 0, 255];

	/**
	 * Get image resource from image data string.
	 *
	 * @param string $data    image data string
	 *
	 * @return resource
	 *
	 * @throws Exception    on error
	 */
	public static function getImageResource($data) {
		$image = @imagecreatefromstring($data);
		if ($image === false) {
			throw new Exception('Failed to load image.');
		}

		return $image;
	}

	/**
	 * Get image data string from image resource.
	 *
	 * @param resource $image    image resource
	 *
	 * @return string
	 */
	public static function getImageString($image) {
		ob_start();
		imagepng($image);

		return ob_get_clean();
	}

	/**
	 * Set compare threshold.
	 *
	 * @param float $threshold    threshold in %.
	 */
	public static function setThreshold($threshold) {
		self::$threshold = min($threshold, 100) * 7.68;
	}

	/**
	 * Set erase color.
	 *
	 * @param mixed $color    hex color #XXXXXX, integer or an array
	 */
	public static function setEraseColor($color) {
		$components = self::getColorComponents($color);
		if ($components !== null) {
			self::$erase_color = $components;
		}
	}

	/**
	 * Get part of an image defined by coordinates, width and height.
	 *
	 * @param string $image     image string
	 * @param array  $rect      array with x, y, width and height keys
	 *
	 * @return string
	 *
	 * @throws Exception    on error
	 */
	public static function getImageRegion($image, $rect) {
		$source = self::getImageResource($image);

		if (!array_key_exists('x', $rect) || !array_key_exists('y', $rect) || !array_key_exists('width', $rect)
				|| !array_key_exists('height', $rect) || $rect['x'] < 0 || $rect['y'] < 0
				|| ($rect['x'] + $rect['width']) >= imagesx($source)
				|| ($rect['y'] + $rect['height']) >= imagesy($source)) {

			throw new Exception('Requested image region is invalid.');
		}

		$target = imagecrop($source, $rect);
		imagedestroy($source);

		$result = self::getImageString($target);
		imagedestroy($target);

		return $result;
	}

	/**
	 * Parse color and return color component values as array.
	 *
	 * @param mixed $color    hex color #XXXXXX, integer or an array
	 *
	 * @return array|null
	 */
	private static function getColorComponents($color) {
		if (is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
			return sscanf($color, "#%02x%02x%02x");
		}
		elseif (is_int($color)) {
			return [(0xff & $color), ((0xff00 & $color) >> 8), ((0xff0000 & $color) >> 16)];
		}
		elseif (is_array($color) && array_key_exists(0, $color) && array_key_exists(1, $color)
				&& array_key_exists(2, $color)) {
			return $color;
		}

		return null;
	}

	/**
	 * Get image with some regions covered.
	 * Regions are covered with magenta color if no color is specified for region.
	 *
	 * @param string $data       image data
	 * @param array  $regions    regions to be covered
	 *
	 * @return string
	 */
	public static function getImageWithoutRegions($data, $regions = []) {
		if (!$regions) {
			return $data;
		}

		$image = self::getImageResource($data);
		$default = imagecolorallocate($image, self::$erase_color[0], self::$erase_color[1], self::$erase_color[2]);
		foreach ($regions as $region) {
			$color = (array_key_exists('color', $region)) ? self::getColorComponents($region['color']) : null;
			if ($color === null) {
				$color = $default;
			}
			else {
				$color = imagecolorallocate($image, $color[0], $color[1], $color[2]);
			}

			imagefilledrectangle($image, $region['x'] - 1, $region['y'] - 1, $region['x'] + $region['width'] + 2,
					$region['y'] + $region['height'] + 2, $color
			);
		}

		$result = self::getImageString($image);
		imagedestroy($image);

		return $result;
	}

	/**
	 * Compare two images and get result of compare.
	 *
	 * @param string $source     reference image data (image is used as a reference)
	 * @param string $current    current image data (image is compared to the reference)
	 *
	 * @return array
	 */
	public static function compareImages($source, $current) {
		$result = [
			'match' => true,
			'delta'	=> 0,
			'error' => null,
			'diff'	=> null,
			'ref'	=> null
		];

		if (md5($source) === md5($current)) {
			return $result;
		}

		try {
			$delta = 0;
			$reference = self::getImageResource($source);
			$target = self::getImageResource($current);

			$width = imagesx($reference);
			$height = imagesy($reference);

			if ($width !== imagesx($target) || $height !== imagesy($target)) {
				$result['ref'] = self::getImageString($reference);
				$message = 'Image size ('.imagesx($target).'x'.imagesy($target).
						') doesn\'t match size of reference image ('.$width.'x'.$height.')';
				imagedestroy($reference);
				imagedestroy($target);

				throw new Exception($message);
			}

			$mask = imagecreatetruecolor($width, $height);
			imagealphablending($mask, true);
			imagecopy($mask, $reference, 0, 0, 0, 0, $width, $height);
			imagefilledrectangle($mask, 0, 0, $width, $height, imagecolorallocatealpha($mask, 255, 255, 255, 64));

			$red = imagecolorallocatealpha($mask, 255, 0, 0, 64);
			for ($y = 0; $y < $height; $y++) {
				for ($x = 0; $x < $width; $x++) {
					$color1 = imagecolorat($reference, $x, $y);
					$color2 = imagecolorat($target, $x, $y);

					if ($color1 === $color2) {
						continue;
					}

					if (self::$threshold === 0) {
						$delta++;
						imagesetpixel($mask, $x, $y, $red);
						continue;
					}

					$diff = ($color1 ^ $color2);
					if ((((0xff0000 & $diff) >> 16) + ((0xff00 & $diff) >> 8) + (0xff & $diff)) > self::$threshold) {
						$delta++;
						imagesetpixel($mask, $x, $y, $red);
					}
				}
			}

			imagedestroy($target);

			if ($delta !== 0) {
				$result['match'] = false;
				$delta /= $width * $height / 100;

				if ($delta < 0.01) {
					$delta = 0.01;
				}

				$result['delta'] = round($delta, 2);
			}

			if ($result['match'] === false) {
				$result['ref'] = self::getImageString($reference);
				$result['diff'] = self::getImageString($mask);
			}

			imagedestroy($reference);
			imagedestroy($mask);
		}
		catch (Exception $e) {
			$result['match'] = false;
			$result['error'] = $e->getMessage();
		}

		return $result;
	}
}


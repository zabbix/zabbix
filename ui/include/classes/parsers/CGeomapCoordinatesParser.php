<?php declare(strict_types = 0);
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
 * Class is used to parse comma separated string of geographical coordinates and zoom level.
 *
 * Valid token must match one of following formats:
 * - <latitude>,<longitude>,<zoom>
 * - <latitude>,<longitude>
 */
class CGeomapCoordinatesParser extends CParser {

	/**
	 * @var array
	 */
	public $result;

	/**
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$this->result = [];

		$regex = '/^(?P<latitude>-?\d+(\.\d+)?),\s*(?P<longitude>-?\d+(\.\d+)?)(,(?P<zoom>\d+))?$/';
		if (!preg_match($regex, substr($source, $pos), $matches)) {
			return self::PARSE_FAIL;
		}

		if ((float) $matches['latitude'] < GEOMAP_LAT_MIN || (float) $matches['latitude'] > GEOMAP_LAT_MAX
				|| (float) $matches['longitude'] < GEOMAP_LNG_MIN || (float) $matches['longitude'] > GEOMAP_LNG_MAX) {
			return self::PARSE_FAIL;
		}

		$this->match = $matches[0];
		$this->length = strlen($this->match);
		$this->result = array_intersect_key($matches, array_flip(['latitude', 'longitude', 'zoom']));

		return self::PARSE_SUCCESS;
	}
}

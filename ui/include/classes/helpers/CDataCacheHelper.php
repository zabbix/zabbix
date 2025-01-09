<?php declare(strict_types = 0);
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


/**
 * A class for manage values cached in "tmp" file.
 */
class CDataCacheHelper {

	/**
	 * Cache buffer.
	 *
	 * @var array
	 */
	protected static $data = null;

	/**
	 * File name to store cache.
	 *
	 * @var string
	 */
	protected const DATA_CACHE_FILE_NAME = 'zbx_config_cache';

	/**
	 * Save value in cache.
	 *
	 * @param array $values  Array of key-value pairs to store in cache.
	 */
	public static function setValueArray(array $values): void {
		if (self::$data === null) {
			self::loadCache();
		}

		foreach ($values as $key => $value) {
			self::$data[$key] = $value;
		}

		self::saveCache();
	}

	/**
	 * Get cached value by particular key.
	 *
	 * @param string $key    Key of requested value.
	 * @param type $default  (optional) Default value.
	 *
	 * @return mixed
	 */
	public static function getValue(string $key, $default = null) {
		if (self::$data === null) {
			self::loadCache();
		}

		return (array_key_exists($key, self::$data)) ? self::$data[$key] : $default;
	}

	/**
	 * Clear values of given keys from cache.
	 *
	 * @param array $keys  List of keys to erase from cache.
	 */
	public static function clearValues(array $keys): void {
		if (self::$data === null) {
			self::loadCache();
		}

		foreach ($keys as $key) {
			unset(self::$data[$key]);
		}

		if (count(self::$data) > 0) {
			self::saveCache();
		}
		else {
			self::deleteCacheFile();
		}
	}

	/**
	 * Load cached values from file to buffer.
	 */
	protected static function loadCache(): void {
		self::$data = (is_file(self::getDataCacheFileName()) && self::checkCacheTTL())
			? (array) json_decode(file_get_contents(self::getDataCacheFileName()))
			: [];
	}

	/**
	 * Save values stored in buffer into file.
	 */
	protected static function saveCache(): void {
		if (ZBX_DATA_CACHE_TTL == 0) {
			self::deleteCacheFile();
		}
		else {
			file_put_contents(self::getDataCacheFileName(), json_encode(self::$data));
		}
	}

	/**
	 * Delete cache file.
	 */
	protected static function deleteCacheFile(): void {
		if (is_file(self::getDataCacheFileName())) {
			unlink(self::getDataCacheFileName());
		}
	}

	/**
	 * Return absolute path to "tmp" file to store cache.
	 *
	 * @return string
	 */
	protected static function getDataCacheFileName(): string {
		return sys_get_temp_dir().'/'.self::DATA_CACHE_FILE_NAME;
	}

	/**
	 * Check if cached data is not expired.
	 *
	 * @return bool
	 */
	protected static function checkCacheTTL(): bool {
		return (ZBX_DATA_CACHE_TTL != 0 && filemtime(self::getDataCacheFileName()) + ZBX_DATA_CACHE_TTL > time());
	}
}

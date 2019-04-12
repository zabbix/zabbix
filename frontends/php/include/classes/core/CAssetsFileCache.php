<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CAssetsFileCache {

	// Root directory.
	const WEBCACHE_PATH = 'assets';

	// Directory permissions on created version folder and sub folders.
	const WEBCACHE_PATH_MODE = 0755;

	// TTL seconds for old assets versions.
	const WEBCACHE_OLD_TTL = 10;

	private $version;

	private $cache_dir;
	private $assets_dir;
	private $assets_files;
	private $assets_scan_rules = ['styles/*.css', 'img/*.png', 'img/*.svg'];

	/**
	 * @param string $root_dir    Path to web root directory, ending directory separator should be omited.
	 *
	 * @return CAssetsFileCache
	 */
	public function __construct($root_dir) {
		$this->assets_dir = $root_dir.DIRECTORY_SEPARATOR.static::WEBCACHE_PATH;

		$this->assets_files = $this->getAssetsFiles();

		$this->version = base_convert(max(array_map('filemtime', $this->assets_files)), 10, 34);

		$this->cache_dir = $this->assets_dir.DIRECTORY_SEPARATOR.$this->version;
	}

	/**
	 * Will check existence of cache directory. If directory for current path does not exist will call init.
	 * Returns true when cache is ready to be used, false otherwise.
	 *
	 * @return bool
	 */
	public function build() {
		// Do not check is the cache dir writeable to allow after assets being build remove directory write permissions.
		return file_exists($this->cache_dir) || $this->init();
	}

	/**
	 * Scan and remove old cached entries. Will be called after successful cold boot.
	 */
	public function maintenance() {
		$ttl = time() - static::WEBCACHE_OLD_TTL;
		$skip = [$this->version, 'img', 'styles', 'fonts', '.', '..'];

		foreach (new DirectoryIterator($this->assets_dir) as $entry) {
			$name = $entry->getFilename();

			if ($entry->isDir() && !in_array($name, $skip) && base_convert($name, 34, 10) < $ttl) {
				$this->rmdir($this->assets_dir.DIRECTORY_SEPARATOR.$name);
			}
		}
	}

	/**
	 * Initializes cache directory for cache by copy cacheable files to cache sub directory.
	 * Returns true when cache is initialized and ready to be used, false otherwise.
	 *
	 * @return bool
	 */
	public function init() {
		$css_dir = $this->cache_dir.DIRECTORY_SEPARATOR.'styles'.DIRECTORY_SEPARATOR;

		$img_dir = $this->cache_dir.DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR;

		$status = mkdir($this->cache_dir, static::WEBCACHE_PATH_MODE, true)
			&& mkdir($css_dir, static::WEBCACHE_PATH_MODE, true)
			&& mkdir($img_dir, static::WEBCACHE_PATH_MODE, true);

		if ($status) {
			foreach ($this->assets_files as $file) {
				$dst_dir = substr($file, -4) === '.css' ? $css_dir : $img_dir;
				$status = copy($file, $dst_dir.basename($file));

				if (!$status) {
					break;
				}
			}
		}

		if ($status) {
			$this->maintenance();
		}
		else if (file_exists($this->cache_dir)) {
			$this->rmdir($this->cache_dir);
		}

		return $status;
	}

	/**
	 * Return URL path to assets.
	 *
	 * @return string
	 */
	public function getAssetsUrl()
	{
		return static::WEBCACHE_PATH.'/'.(file_exists($this->cache_dir) ? $this->version.'/' : '');
	}

	/**
	 * Return absolute path to main assets directory.
	 *
	 * @return string
	 */
	public function getAssetsDirectory() {
		return $this->assets_dir;
	}

	/**
	 * Return array of path to every cacheable file.
	 *
	 * @return array
	 */
	private function getAssetsFiles() {
		$paths = [];

		foreach ($this->assets_scan_rules as $rule) {
			$paths = array_merge($paths, glob($this->assets_dir.DIRECTORY_SEPARATOR.$rule));
		}

		return $paths;
	}

	/**
	 * Removes directory and it content recursively.
	 *
	 * @param string $dir    Path to directory.
	 */
	private function rmdir($dir) {
		foreach (new DirectoryIterator($dir) as $entry) {
			if (!$entry->isDot() && $entry->isDir()) {
				$this->rmdir($entry->getPathname());
			}
			elseif ($entry->isFile()) {
				unlink($entry->getPathname());
			}
		}

		rmdir($dir);
	}
}

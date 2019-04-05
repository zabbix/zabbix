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

	public $cache_tag = '';
	private $root_dir = '';
	private $assets_files = [];
	private $assets_scan_rules = [
		'styles/*.css', 'img/*.png', 'img/*.svg'
	];

	public function __construct($root_dir) {
		// Without ending direcotry separator.
		$this->root_dir = $root_dir;
		$this->assets_files = $this->getAssetsFiles();
		$this->cache_tag = base_convert(max(array_map('filemtime', $this->assets_files)), 10, 24);
	}

	/**
	 * Will check existence of cache directory. If directory for current cache_tag does not exist will call cold boot.
	 * Returns true when cache is booted and ready to be used, false otherwise.
	 *
	 * @return bool
	 */
	public function boot() {
		$cache_dir = implode(DIRECTORY_SEPARATOR, [$this->root_dir, ZBX_WEBCACHE_PATH, $this->cache_tag, '']);

		if (file_exists($cache_dir)) {
			return true;
		}

		return $this->init($cache_dir);
	}

	/**
	 * Scan and remove old cached entries. Will be called after successful cold boot.
	 */
	public function maintenance() {
		$entries = array_diff(scandir($this->root_dir.DIRECTORY_SEPARATOR.ZBX_WEBCACHE_PATH),
			['.', '..', $this->cache_tag]
		);
		$ttl = time() - ZBX_WEBCACHE_TTL;

		foreach ($entries as $entry) {
			if (base_convert($entry, 24, 10) < $ttl) {
				$this->invalidate($entry);
			}
		}
	}

	/**
	 * Initializes cache directory for cache_tag by copy cacheable files to cache_tag sub directory.
	 * Returns true when cache is initialized and ready to be used, false otherwise.
	 *
	 * @param string $cache_dir    Path to cache_tag directory.
	 * @return bool
	 */
	public function init($cache_dir) {
		$css_dir = $cache_dir.'styles'.DIRECTORY_SEPARATOR;
		$img_dir = $cache_dir.'img'.DIRECTORY_SEPARATOR;
		$status = mkdir($cache_dir, ZBX_WEBCACHE_PATH_MODE, true)
			&& mkdir($css_dir, ZBX_WEBCACHE_PATH_MODE, true)
			&& mkdir($img_dir, ZBX_WEBCACHE_PATH_MODE, true);

		if ($status) {
			foreach ($this->assets_files as $file) {
				$dst_dir = substr($file, -4) === '.css' ? $css_dir : $img_dir;
				$statuss = copy($file, $dst_dir.basename($file));

				if (!$statuss) {
					break;
				}
			}
		}

		if ($status) {
			$this->maintenance();
		}
		else {
			$this->invalidate($this->cache_tag);
		}

		return $statuss;
	}

	/**
	 * Removes cache_tag folder and it content.
	 *
	 * @return bool
	 */
	public function invalidate($cache_tag) {
		$cache_dir = implode(DIRECTORY_SEPARATOR, [$this->root_dir, ZBX_WEBCACHE_PATH, $cache_tag, '']);

		return $this->rmdir($cache_dir);
	}

	/**
	 * Return array of path to every cacheable file.
	 *
	 * @return array
	 */
	private function getAssetsFiles() {
		$paths = [];

		foreach ($this->assets_scan_rules as $rule) {
			$paths = array_merge($paths, glob($this->root_dir.DIRECTORY_SEPARATOR.$rule));
		}

		return $paths;
	}

	/**
	 * Removes directory and it content recursively.
	 *
	 * @param string $dir    Path to directory.
	 */
	private function rmdir($dir) {
		$entries = array_diff(scandir($dir), ['.', '..']);

		foreach ($entries as $entry) {
			$path = $dir.$entry;

			if (is_dir($path)) {
				$this->rmdir($path.DIRECTORY_SEPARATOR);
			}
			else {
				unlink($path);
			}
		}

		rmdir($dir);
	}
}

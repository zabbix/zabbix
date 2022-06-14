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
 * Class for rendering views.
 */
class CView {

	/**
	 * Directory list of MVC views ordered by search priority.
	 *
	 * @static
	 *
	 * @var array
	 */
	private static $directories = ['local/app/views', 'app/views', 'include/views'];

	/**
	 * Indicates support of web layout modes.
	 *
	 * @var boolean
	 */
	private $layout_modes_enabled = false;

	/**
	 * Explicitly set layout mode.
	 *
	 * @var int
	 */
	private $layout_mode;

	/**
	 * View name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Data provided for view.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Directory where the view file was found.
	 *
	 * @var string
	 */
	private $directory;

	/**
	 * List of JavaScript files for inclusion into a HTML page using <script src="...">.
	 *
	 * @var array
	 */
	private $js_files = [];

	/**
	 * List of CSS files for inclusion into a HTML page using <link rel="stylesheet" type="text/css" src="...">.
	 *
	 * @var array
	 */
	private $css_files = [];

	/**
	 * Create a view based on view name and data.
	 *
	 * @param string $name  View name to search for.
	 * @param array  $data  Accessible data within the view.
	 *
	 * @throws InvalidArgumentException if view name not valid.
	 * @throws RuntimeException if view not found or not readable.
	 */
	public function __construct($name, array $data = []) {
		if (!preg_match('/^[a-z]+(\/[a-z]+)*(\.[a-z]+)*$/', $name)) {
			throw new InvalidArgumentException(sprintf('Invalid view name: "%s".', $name));
		}

		$file_path = null;

		foreach (self::$directories as $directory) {
			$file_path = $directory.'/'.$name.'.php';
			if (is_file($file_path)) {
				$this->directory = $directory;
				break;
			}
		}

		if ($this->directory === null) {
			throw new RuntimeException(sprintf('View not found: "%s".', $name));
		}

		if (!is_readable($file_path)) {
			throw new RuntimeException(sprintf('View not readable: "%s".', $file_path));
		}

		$this->name = $name;
		$this->data = $data;
	}

	/**
	 * Render view and return the output.
	 * Note: view should only output textual content like HTML, JSON, scripts or similar.
	 *
	 * @throws RuntimeException if view not found, not readable or returned false.
	 *
	 * @return string
	 */
	public function getOutput() {
		$data = $this->data;

		$file_path = $this->directory.'/'.$this->name.'.php';

		ob_start();

		if ((include $file_path) === false) {
			ob_end_clean();

			throw new RuntimeException(sprintf('Cannot render view: "%s".', $file_path));
		}

		return ob_get_clean();
	}

	/**
	 * Get the contents of a PHP-preprocessed JavaScript file.
	 * Notes:
	 *   - JavaScript file will be searched in the "js" subdirectory of the view file.
	 *   - A copy of $data variable will be available for using within the file.
	 *
	 * @param string $file_name
	 * @param array  $data
	 *
	 * @throws RuntimeException if the file not found, not readable or returned false.
	 *
	 * @return string
	 */
	public function readJsFile(string $file_name, ?array $data = null): string {
		$data = ($data === null) ? $this->data : $data;

		$file_path = $this->directory.'/js/'.$file_name;

		ob_start();

		if ((include $file_path) === false) {
			ob_end_clean();

			throw new RuntimeException(sprintf('Cannot read file: "%s".', $file_path));
		}

		return ob_get_clean();
	}

	/**
	 * Include a PHP-preprocessed JavaScript file inline.
	 * Notes:
	 *   - JavaScript file will be searched in the "js" subdirectory of the view file.
	 *   - A copy of $data variable will be available for using within the file.
	 *
	 * @param string $file_name
	 * @param array  $data
	 *
	 * @throws RuntimeException if the file not found, not readable or returned false.
	 */
	public function includeJsFile(string $file_name, array $data = null) {
		echo $this->readJsFile($file_name, $data);
	}

	/**
	 * Add a native JavaScript file to this view.
	 *
	 * @param string $src
	 */
	public function addJsFile($src) {
		$this->js_files[] = $src;
	}

	/**
	 * Get list of native JavaScript files added to this view.
	 *
	 * @return array
	 */
	public function getJsFiles() {
		return $this->js_files;
	}

	/**
	 * Add a CSS file to this view.
	 *
	 * @param string $src
	 */
	public function addCssFile($src) {
		$this->css_files[] = $src;
	}

	/**
	 * Get list of CSS files added to this view.
	 *
	 * @return array
	 */
	public function getCssFiles() {
		return $this->css_files;
	}

	/**
	 * Enable support of web layout modes.
	 */
	public function enableLayoutModes() {
		$this->layout_modes_enabled = true;
	}

	/**
	 * Set layout mode explicitly.
	 *
	 * @param int $layout_mode  ZBX_LAYOUT_NORMAL | ZBX_LAYOUT_KIOSKMODE
	 */
	public function setLayoutMode(int $layout_mode): void {
		$this->layout_mode = $layout_mode;
	}

	/**
	 * Get current layout mode.
	 *
	 * @return int  ZBX_LAYOUT_NORMAL | ZBX_LAYOUT_KIOSKMODE
	 */
	public function getLayoutMode() {
		if ($this->layout_modes_enabled) {
			return ($this->layout_mode !== null) ? $this->layout_mode : CViewHelper::loadLayoutMode();
		}

		return ZBX_LAYOUT_NORMAL;
	}

	/**
	 * Register custom directory of MVC views. The last registered will have the first priority.
	 *
	 * @param string $directory
	 */
	public static function registerDirectory($directory) {
		if (!in_array($directory, self::$directories)) {
			array_unshift(self::$directories, $directory);
		}
	}
}

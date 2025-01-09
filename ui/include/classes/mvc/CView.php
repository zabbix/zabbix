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
 * Class for rendering views.
 */
class CView {

	/**
	 * Directory list of MVC views ordered by search priority.
	 */
	private static array $directories = ['local/app/views', 'app/views', 'include/views'];

	/**
	 * Indicates support of web layout modes.
	 */
	private bool $layout_modes_enabled = false;

	/**
	 * Explicitly set layout mode.
	 */
	private ?int $layout_mode = null;

	/**
	 * Directory where the view file was found.
	 */
	private ?string $directory = null;

	private string $assets_path = 'assets';

	/**
	 * View name.
	 */
	private string $name;

	/**
	 * List of JavaScript files for inclusion into HTML page using <script src="...">.
	 */
	private array $js_files = [];

	/**
	 * List of CSS files for inclusion into HTML page using <link rel="stylesheet" type="text/css" src="...">.
	 */
	private array $css_files = [];

	/**
	 * Data provided for view.
	 */
	private array $data;

	/**
	 * Create a view based on view name and data.
	 *
	 * @param string $name  View name to search for.
	 * @param array  $data  Accessible data within the view.
	 *
	 * @throws InvalidArgumentException if view name not valid.
	 * @throws RuntimeException if view not found or not readable.
	 */
	public function __construct(string $name, array $data = []) {
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

	public function getDirectory(): string {
		return $this->directory;
	}

	public function setAssetsPath(string $asset_path): self {
		$this->assets_path = $asset_path;

		return $this;
	}

	public function getAssetsPath(): string {
		RETURN $this->assets_path;
	}

	/**
	 * Render view and return the output.
	 * Note: view should only output textual content like HTML, JSON, scripts or similar.
	 *
	 * @throws RuntimeException if view not found, not readable or returned false.
	 */
	public function getOutput(): string {
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
	 * @param string     $file_name
	 * @param array|null $data
	 *
	 * @return string
	 */
	public function readJsFile(string $file_name, array $data = null, $relative_dir = '/js'): string {
		$data = $data ?? $this->data;

		$file_path = $this->directory.$relative_dir.'/'.$file_name;

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
	 * @throws RuntimeException if the file not found, not readable or returned false.
	 */
	public function includeJsFile(string $file_name, array $data = null): self {
		echo $this->readJsFile($file_name, $data);

		return $this;
	}

	/**
	 * Add a native JavaScript file to this view.
	 */
	public function addJsFile(string $js): self {
		$this->js_files[] = $js;

		return $this;
	}

	/**
	 * Get list of native JavaScript files added to this view.
	 */
	public function getJsFiles(): array {
		return $this->js_files;
	}

	/**
	 * Add a CSS file to this view.
	 */
	public function addCssFile($css): self {
		$this->css_files[] = $css;

		return $this;
	}

	/**
	 * Get list of CSS files added to this view.
	 *
	 * @return array
	 */
	public function getCssFiles(): array {
		return $this->css_files;
	}

	/**
	 * Enable support of web layout modes.
	 */
	public function enableLayoutModes(): self {
		$this->layout_modes_enabled = true;

		return $this;
	}

	/**
	 * Set layout mode explicitly.
	 *
	 * @param int $layout_mode  ZBX_LAYOUT_NORMAL | ZBX_LAYOUT_KIOSKMODE
	 */
	public function setLayoutMode(int $layout_mode): self {
		$this->layout_mode = $layout_mode;

		return $this;
	}

	/**
	 * Get current layout mode.
	 *
	 * @return int  ZBX_LAYOUT_NORMAL | ZBX_LAYOUT_KIOSKMODE
	 */
	public function getLayoutMode(): int {
		if ($this->layout_modes_enabled) {
			return $this->layout_mode ?? CViewHelper::loadLayoutMode();
		}

		return ZBX_LAYOUT_NORMAL;
	}

	/**
	 * Register custom directory of MVC views. The last registered will have the first priority.
	 */
	public static function registerDirectory(string $directory): void {
		if (!in_array($directory, self::$directories, true)) {
			array_unshift(self::$directories, $directory);
		}
	}
}

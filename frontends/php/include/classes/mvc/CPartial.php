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


/**
 * Class for rendering partial-views. Partials are useful for reusing the same partial-views or updating specific parts
 * of the page asynchronously, i.e., partials can be included into a view initially and updated in AJAX manner, later.
 */
class CPartial {

	/**
	 * Partial directory list ordered by searh priority.
	 *
	 * @static
	 *
	 * @var array
	 */
	protected static $directories = ['local/app/partials', 'app/partials'];

	/**
	 * Partial file path.
	 *
	 * @var mixed
	 */
	protected $file_path;

	/**
	 * Data provided for partial.
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * Create a partial based on partial name and data.
	 *
	 * @param string $name  Partial name to search for.
	 * @param array  $data  Accessible data within the partial.
	 *
	 * @throws InvalidArgumentException if partial name not valid.
	 * @throws RuntimeException if partial not found or not readable.
	 */
	public function __construct($name, array $data = []) {
		if (!preg_match('/^[a-z\.]+$/', $name)) {
			throw new InvalidArgumentException(_s('Invalid partial name: "%s".', $name));
		}

		$found = false;
		foreach (self::$directories as $directory) {
			$this->file_path = $directory.'/'.$name.'.php';
			if (file_exists($this->file_path)) {
				$found = true;
				break;
			}
		}

		if (!$found || !is_file($this->file_path) || !is_readable($this->file_path)) {
			throw new RuntimeException(_s('Partial not found or not readable: "%s".', $this->file_path));
		}

		$this->data = $data;
	}

	/**
	 * Render partial and return the output.
	 * Notes:
	 *   - Partial should output textual content, like HTML or scripts. Returning anything will have no effect.
	 *   - Scripts need to be included in HTML partials by using a standard <script> tag.
	 *
	 * @throws RuntimeException if partial not found, not readable or returned false.
	 *
	 * @return string
	 */
	public function getOutput() {
		$data = $this->data;

		ob_start();

		if ((include $this->file_path) === false) {
			ob_end_clean();

			throw new RuntimeException(_s('Partial not found or not readable: "%s".', $this->file_path));
		}

		return ob_get_clean();
	}
}

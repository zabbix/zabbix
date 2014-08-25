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


class CView {

	/**
	 * @var string - name of the template file without extension, for example 'general.search'
	 */
	private $filePath;

	/**
	 * @var array - hash of 'variable_name'=>'variable_value' to be used inside template
	 */
	private $data;

	/**
	 * @var CTag - actual template object being shown
	 */
	private $template;

	/**
	 * @var string - scripts on page
	 */
	private $scripts;

	/**
	 * @const string - directory where views are stored
	 */
	const viewsDir = 'include/views';

	/**
	 * Creates a new view based on provided template file.
	 * @param string $view name of a view, located under include/views
	 * @param array $data deprecated parameter, use set() and get() methods for passing variables to views
	 * @example $scriptForm = new CView('administration.script.edit');
	 */
	public function __construct($view, $data = array()) {
		$this->assign($view);
		$this->data = $data;
	}

	public function assign($view) {
		if (!preg_match("/[a-z\.]+/", $view)) {
			throw new Exception(_s('Invalid view name given "%s". Allowed chars: "a-z" and ".".', $view));
		}

		$this->filePath = self::viewsDir.'/'.$view.'.php';

		if (!file_exists($this->filePath)) {
			throw new Exception(_s('File provided to a view does not exist. Tried to find "%s".', $this->filePath));
		}
	}

	/**
	 * Assign value to a named variable.
	 * @param string $var variable name
	 * @param any $value variable value
	 * @example set('hostName','Host ABC')
	 */
	public function set($var, $value) {
		$this->data[$var] = $value;
	}

	/**
	 * Get value by variable name.
	 * @param string $var name of the variable.
	 * @return string variable value. Returns empty string if the variable is not defined.
	 * @example get('hostName')
	 */
	public function get($var) {
		return isset($this->data[$var]) ? $this->data[$var] : '';
	}

	/**
	 * Get variable of type array by variable name.
	 * @param string $var name of the variable.
	 * @return array variable value. Returns empty array if the variable is not defined or not an array.
	 * @example getArray('hosts')
	 */
	public function getArray($var) {
		return isset($this->data[$var]) && is_array($this->data[$var]) ? $this->data[$var] : array();
	}

	/**
	 * Load and execute view.
	 * TODO It outputs JavaScript code immediately, should be done in show() or processed separately.
	 * @return object GUI object.
	 */
	public function render() {
		// $data this variable will be used in included file
		$data = $this->data;
		ob_start();
		$this->template = include($this->filePath);
		if ($this->template === false) {
			throw new Exception(_s('Cannot include view file "%s".', $this->filePath));
		}
		$this->scripts = ob_get_clean();

		/* TODO It is for output of JS code. Should be moved to show() method. */
		echo $this->scripts;
		return $this->template;
	}

	/**
	 * The method outputs HTML code based on rendered template.
	 * It calls render() if not called already.
	 * @return NULL
	 */
	public function show() {
		if (!isset($this->template)) {
			throw new Exception(_('View is not rendered.'));
		}
		$this->template->show();
	}
}

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


class CView {

	/**
	 * Name of the template file without extension, for example 'configuration.item.edit'.
	 *
	 * @var string
	 */
	private $filePath;

	/**
	 * Hash of 'variable_name'=>'variable_value' to be used inside template.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Actual template object being shown.
	 *
	 * @var CTag
	 */
	private $template;

	/**
	 * Scripts on page.
	 *
	 * @var string
	 *
	 * @deprecated
	 */
	private $scripts;

	/**
	 * Javascript code for inclusions on page.
	 *
	 * @var array
	 */
	private $jsIncludePost = [];

	/**
	 * Javascript files for inclusions on page, pre-processed by PHP.
	 *
	 * @var array
	 */
	private $jsIncludeFiles = [];

	/**
	 * Javascript files for inclusions on page, included as <script src="..."></script>.
	 *
	 * @var array
	 */
	private $jsFiles = [];

	/**
	 * Don't include jsLoader to the page.
	 *
	 * @static
	 *
	 * @var bool
	 */
	public static $js_loader_disabled = false;

	/**
	 * Directories where views are stored, ordered by priority include/views should be removed once fully move to MVC.
	 *
	 * @static
	 *
	 * @var array
	 */
	static $viewsDir = ['local/app/views', 'app/views', 'include/views'];

	/**
	 * Web layout mode enabled flag.
	 *
	 * @static
	 *
	 * @var boolean
	 */
	static $has_web_layout_mode = false;

	/**
	 * Creates a new view based on provided template file.
	 *
	 * @param string $view  Name of a view, located under include/views.
	 * @param array  $data  Deprecated parameter, use set() and get() methods for passing variables to views.
	 *
	 * @throws Exception if file does not exist.
	 *
	 * @example $scriptForm = new CView('administration.script.edit');
	 */
	public function __construct($view, $data = []) {
		$this->assign($view);
		$this->data = $data;
	}

	/**
	 * Search file and assigns path to the view file.
	 *
	 * @param string $view  Name of the template file without extension.
	 *
	 * @throws Exception if invalid filename or file does not exist.
	 */
	public function assign($view) {
		if (!preg_match("/[a-z\.]+/", $view)) {
			throw new Exception(_s('Invalid view name given "%s". Allowed chars: "a-z" and ".".', $view));
		}

		$found = false;
		foreach (self::$viewsDir as $dir) {
			$this->filePath = $dir.'/'.$view.'.php';
			if (file_exists($this->filePath)) {
				$found = true;
				break;
			}
		}

		if ($found == false) {
			throw new Exception(_s('File provided to a view does not exist. Tried to find "%s".', $this->filePath));
		}
	}

	/**
	 * Assign value to a named variable.
	 *
	 * @param string $var
	 * @param mixed  $value
	 *
	 * @example set('hostName','Host ABC')
	 */
	public function set($var, $value) {
		$this->data[$var] = $value;
	}

	/**
	 * Get value by variable name.
	 *
	 * @param string $var
	 *
	 * @return mixed|string  Variable value. Returns empty string if the variable is not defined.
	 *
	 * @example get('hostName')
	 *
	 * @deprecated use $data instead
	 */
	public function get($var) {
		return isset($this->data[$var]) ? $this->data[$var] : '';
	}

	/**
	 * Get variable of type array by variable name.
	 *
	 * @param string $var
	 *
	 * @return array  Variable value. Returns empty array if the variable is not defined or not an array.
	 *
	 * @example getArray('hosts')
	 *
	 * @deprecated use $data instead
	 */
	public function getArray($var) {
		return isset($this->data[$var]) && is_array($this->data[$var]) ? $this->data[$var] : [];
	}

	/**
	 * Load and execute view.
	 * TODO It outputs JavaScript code immediately, should be done in show() or processed separately.
	 *
	 * @deprecated  Will not be supported when we fully move to MVC.
	 *
	 * @throws Exception if cannot include view file.
	 *
	 * @return object  GUI object.
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
	 * The method outputs HTML code based on rendered template. It calls render() if not called already.
	 *
	 * @deprecated  Will not be supported when we fully move to MVC.
	 *
	 * @throws Exception if view is not rendered.
	 */
	public function show() {
		if (!isset($this->template)) {
			throw new Exception(_('View is not rendered.'));
		}
		$this->template->show();
	}

	/**
	* The method returns HTML/JSON/CVS/etc text based on rendered template.
	* show() and render() should be made deprecated. View should only output text, no objects, nothing.
	*/
	public function getOutput() {
		$data = $this->data;
		ob_start();
		include($this->filePath);
		return ob_get_clean();
	}

	/**
	 * Include Java Script code to be executed after page load.
	 *
	 * @param string $js  Java Script code.
	 */
	public function addPostJS($js) {
		$this->jsIncludePost[] = $js;
	}

	/**
	 * Include Java Script file required for the view into HTML.
	 *
	 * @param string $filename  Name of java Script file, will be pre-processed by PHP.
	 */
	public function includeJSfile($filename) {
		$this->jsIncludeFiles[] = $filename;
	}

	/**
	 * Add Java Script file required for the view as <script src="..."></script>.
	 *
	 * @param string $filename  Name of java Script file.
	 */
	public function addJsFile($filename) {
		$this->jsFiles[] = $filename;
	}

	/**
	 * Get content of all Java Script code.
	 *
	 * @return string  Java Script code.
	 */
	public function getPostJS() {
		if ($this->jsIncludePost) {
			return get_js(implode("\n", $this->jsIncludePost));
		}

		return '';
	}

	/**
	 * Get content of all included Java Script files.
	 *
	 * @throws Exception if cannot include JS file.
	 *
	 * @return string  Empty string or content of included JS files.
	 */
	public function getIncludedJS() {
		ob_start();
		foreach ($this->jsIncludeFiles as $filename) {
			if((include $filename) === false) {
				throw new Exception(_s('Cannot include JS file "%s".', $filename));
			}
		}
		return ob_get_clean();
	}

	/**
	 * Get content of all included Java Script files.
	 *
	 * @return array|string  Empty string or array of path of included JS files.
	 */
	public function getAddedJS() {
		return $this->jsFiles;
	}

	/**
	 * Return layout mode setting.
	 *
	 * @return int
	 */
	public static function getLayoutMode() {
		if (self::$has_web_layout_mode) {
			return (int) CProfile::get('web.layout.mode', ZBX_LAYOUT_NORMAL);
		}
		else {
			return ZBX_LAYOUT_NORMAL;
		}
	}

	/**
	 * Update layout mode setting
	 *
	 * @param int $layout_mode  Possible values ZBX_LAYOUT_NORMAL|ZBX_LAYOUT_FULLSCREEN|ZBX_LAYOUT_KIOSKMODE.
	 */
	public static function setLayoutMode($layout_mode) {
		CProfile::update('web.layout.mode', $layout_mode, PROFILE_TYPE_INT);
	}

	/**
	 * Don't include jsLoader to the page.
	 *
	 * @return CView
	 */
	public function disableJsLoader() {
		self::$js_loader_disabled = true;

		return $this;
	}
}

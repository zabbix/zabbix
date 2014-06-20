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


/**
 * Class for rendering html page head part.
 */
class CPageHeader {

	/**
	 * @var string page title
	 */
	protected $title;

	/**
	 * @var array of css file paths
	 */
	protected $cssFiles = array();

	/**
	 * @var array of css styles
	 */
	protected $styles = array();

	/**
	 * @var array of js file paths
	 */
	protected $jsFiles = array();

	/**
	 * @var array of js scripts to render before js files
	 */
	protected $jsBefore = array();

	/**
	 * @var array of js scripts to render after js files
	 */
	protected $js = array();

	/**
	 * @param string $title
	 */
	public function __construct($title = '') {
		$this->title = $title;
	}

	/**
	 * Add path to css file to render in page head.
	 *
	 * @param string $path
	 */
	public function addCssFile($path) {
		$this->cssFiles[$path] = $path;
	}

	/**
	 * Add initial css files.
	 */
	public function addCssInit() {
		$this->cssFiles[] = 'styles/default.css';
		$this->cssFiles[] = 'styles/color.css';
		$this->cssFiles[] = 'styles/icon.css';
		$this->cssFiles[] = 'styles/blocks.css';
		$this->cssFiles[] = 'styles/pages.css';
	}

	/**
	 * Add css style to render in page head.
	 *
	 * @param string $style
	 */
	public function addStyle($style) {
		$this->styles[] = $style;
	}

	/**
	 * Add path to js file to render in page head.
	 *
	 * @param string $path
	 */
	public function addJsFile($path) {
		$this->jsFiles[$path] = $path;
	}

	/**
	 * Add js script to render in page head after js file includes are rendered.
	 *
	 * @param string $js
	 */
	public function addJs($js) {
		$this->js[] = $js;
	}

	/**
	 * Add js script to render in page head before js file includes are rendered.
	 *
	 * @param string $js
	 */
	public function addJsBeforeScripts($js) {
		$this->jsBefore[] = $js;
	}

	/**
	 * Display page head html.
	 */
	public function display() {
		echo <<<HTML
<!doctype html>
<html>
	<head>
		<meta http-equiv="X-UA-Compatible" content="IE=Edge"/>
		<title>$this->title</title>
		<meta name="Author" content="Zabbix SIA" />
		<meta charset="utf-8" />
		<link rel="shortcut icon" href="images/general/zabbix.ico" />

HTML;

		foreach ($this->cssFiles as $path) {
			echo '<link rel="stylesheet" type="text/css" href="'.$path.'" />'."\n";
		}

		if (!empty($this->styles)) {
			echo '<style type="text/css">';
			echo implode("\n", $this->styles);
			echo '</style>';
		}

		if (!empty($this->jsBefore)) {
			echo '<script>';
			echo implode("\n", $this->jsBefore);
			echo '</script>';
		}

		foreach ($this->jsFiles as $path) {
			echo '<script src="'.$path.'"></script>'."\n";
		}

		if (!empty($this->js)) {
			echo '<script>';
			echo implode("\n", $this->js);
			echo '</script>';
		}

		echo '</head>'."\n";
	}
}

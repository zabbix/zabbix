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
 * Class for rendering html page head part.
 */
class CPageHeader {

	/**
	 * @var string page title
	 */
	protected $title;

	/**
	 * @var string  Language attribute.
	 */
	protected $lang;

	/**
	 * @var array of css file paths
	 */
	protected $cssFiles = [];

	/**
	 * @var array of css styles
	 */
	protected $styles = [];

	/**
	 * @var array of js file paths
	 */
	protected $jsFiles = [];

	/**
	 * @var array of js scripts to render before js files
	 */
	protected $jsBefore = [];

	/**
	 * @var array of js scripts to render after js files
	 */
	protected $js = [];

	/**
	* @var {string} sid
	*/
	protected $sid;

	/**
	 * @param string $title
	 * @param string $lang
	 */
	public function __construct(string $title, string $lang) {
		$this->title = CHtml::encode($title);
		$this->lang = $lang;
		$this->sid = substr(CSessionHelper::getId(), 16, 16);
	}

	/**
	 * Add path to css file to render in page head.
	 *
	 * @param string $path
	 */
	public function addCssFile($path) {
		$this->cssFiles[$path] = $path;
		return $this;
	}

	/**
	 * Add css style to render in page head.
	 *
	 * @param string $style
	 */
	public function addStyle($style) {
		$this->styles[] = $style;
		return $this;
	}

	/**
	 * Add path to js file to render in page head.
	 *
	 * @param string $path
	 */
	public function addJsFile($path) {
		$this->jsFiles[$path] = $path;
		return $this;
	}

	/**
	 * Add js script to render in page head after js file includes are rendered.
	 *
	 * @param string $js
	 */
	public function addJs($js) {
		$this->js[] = $js;
		return $this;
	}

	/**
	 * Add js script to render in page head before js file includes are rendered.
	 *
	 * @param string $js
	 */
	public function addJsBeforeScripts($js) {
		$this->jsBefore[] = $js;
		return $this;
	}

	/**
	 * Display page head html.
	 */
	public function display() {
		echo '<!DOCTYPE html>'."\n";
		echo '<html lang="'.$this->lang.'">'."\n";
		echo <<<HTML
	<head>
		<meta http-equiv="X-UA-Compatible" content="IE=Edge"/>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="Author" content="Zabbix SIA" />
		<title>$this->title</title>
		<link rel="icon" href="favicon.ico">
		<link rel="apple-touch-icon-precomposed" sizes="76x76" href="assets/img/apple-touch-icon-76x76-precomposed.png">
		<link rel="apple-touch-icon-precomposed" sizes="120x120" href="assets/img/apple-touch-icon-120x120-precomposed.png">
		<link rel="apple-touch-icon-precomposed" sizes="152x152" href="assets/img/apple-touch-icon-152x152-precomposed.png">
		<link rel="apple-touch-icon-precomposed" sizes="180x180" href="assets/img/apple-touch-icon-180x180-precomposed.png">
		<link rel="icon" sizes="192x192" href="assets/img/touch-icon-192x192.png">
		<meta name="csrf-token" content="$this->sid"/>
		<meta name="msapplication-TileImage" content="assets/img/ms-tile-144x144.png">
		<meta name="msapplication-TileColor" content="#d40000">
		<meta name="msapplication-config" content="none"/>

HTML;

		foreach ($this->cssFiles as $path) {
			if (parse_url($path, PHP_URL_QUERY) === null) {
				$path .= '?'.(int) filemtime($path);
			}

			echo '<link rel="stylesheet" type="text/css" href="'.htmlspecialchars($path).'" />'."\n";
		}

		if ($this->styles) {
			echo '<style type="text/css">';
			echo implode("\n", $this->styles);
			echo '</style>';
		}

		if ($this->jsBefore) {
			echo '<script>';
			echo implode("\n", $this->jsBefore);
			echo '</script>';
		}

		foreach ($this->jsFiles as $path) {
			if (parse_url($path, PHP_URL_QUERY) === null) {
				$path .= '?'.(int) filemtime($path);
			}

			echo '<script src="'.htmlspecialchars($path).'"></script>'."\n";
		}

		if ($this->js) {
			echo '<script>';
			echo implode("\n", $this->js);
			echo '</script>';
		}

		echo '</head>'."\n";
		return $this;
	}
}

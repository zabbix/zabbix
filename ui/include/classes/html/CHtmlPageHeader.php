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
 * Class for rendering html page head part.
 */
class CHtmlPageHeader {

	/**
	 * Page title.
	 */
	protected string $title;

	/**
	 * Language attribute.
	 */
	protected string $lang;

	/**
	 * Theme attribute.
	 */
	protected string $theme = ZBX_DEFAULT_THEME;

	/**
	 * CSS files list.
	 */
	protected array $css_files = [];

	/**
	 * Inline CSS styles.
	 */
	protected array $styles = [];

	/**
	 * JavaScripts to render before JS files.
	 */
	protected array $js = [];

	/**
	 * JS files list.
	 */
	protected array $js_files = [];

	public function __construct(string $title, string $lang) {
		$this->title = $title;
		$this->lang = $lang;
	}

	public function setTheme(string $theme): self {
		$this->theme = $theme;

		return $this;
	}

	public function getTheme(): string {
		return $this->theme;
	}

	/**
	 * Add path to css file to render in page head.
	 */
	public function addCssFile(string $css_file): self {
		$this->css_files[$css_file] = $css_file;

		return $this;
	}

	/**
	 * Add css style to render in page head.
	 */
	public function addStyle(string $style): self {
		$this->styles[] = $style;

		return $this;
	}

	/**
	 * Add JavaScript to render in page head before js file includes are rendered.
	 */
	public function addJavaScript(string $js): self {
		$this->js[] = $js;

		return $this;
	}

	/**
	 * Add path to js file to render in page head.
	 */
	public function addJsFile(string $js_file): self {
		$this->js_files[$js_file] = $js_file;

		return $this;
	}

	public function addJsTranslationStrings(array $translations_strings): self {
		foreach ($translations_strings as $orig_string => $string) {
			$this->addJavaScript('locale[\''.$orig_string.'\'] = '.json_encode($string, JSON_THROW_ON_ERROR).';');
		}

		return $this;
	}

	/**
	 * Show page head html.
	 */
	public function show(): CHtmlPageHeader {
		echo '<!DOCTYPE html>';
		echo (new CTag('html'))
			->setAttribute('lang', $this->lang)
			->setAttribute('theme', $this->theme)
			->setAttribute('color-scheme', APP::getColorScheme($this->theme));
		echo <<<HTML
			<head>
				<meta http-equiv="X-UA-Compatible" content="IE=Edge"/>
				<meta charset="utf-8" />
				<meta name="viewport" content="width=device-width, initial-scale=1">
				<meta name="Author" content="Zabbix SIA" />
		HTML;

		if ($this->title !== '') {
			echo (new CTag('title', true))->addItem($this->title);
		}

		echo <<<HTML
				<link rel="icon" href="favicon.ico">
				<link rel="apple-touch-icon-precomposed" sizes="76x76" href="assets/img/apple-touch-icon-76x76-precomposed.png">
				<link rel="apple-touch-icon-precomposed" sizes="120x120" href="assets/img/apple-touch-icon-120x120-precomposed.png">
				<link rel="apple-touch-icon-precomposed" sizes="152x152" href="assets/img/apple-touch-icon-152x152-precomposed.png">
				<link rel="apple-touch-icon-precomposed" sizes="180x180" href="assets/img/apple-touch-icon-180x180-precomposed.png">
				<link rel="icon" sizes="192x192" href="assets/img/touch-icon-192x192.png">
				<meta name="msapplication-TileImage" content="assets/img/ms-tile-144x144.png">
				<meta name="msapplication-TileColor" content="#d40000">
				<meta name="msapplication-config" content="none"/>
		HTML;

		foreach ($this->css_files as $path) {
			if (parse_url($path, PHP_URL_QUERY) === null) {
				$path .= '?'.(int) filemtime($path);
			}

			echo (new CTag('link'))
				->setAttribute('rel', 'stylesheet')
				->setAttribute('type', 'text/css')
				->setAttribute('href', $path);
		}

		if ($this->styles) {
			echo '<style>';
			echo implode("\n", $this->styles);
			echo '</style>';
		}

		if ($this->js) {
			echo '<script>';
			echo implode("\n", $this->js);
			echo '</script>';
		}

		foreach ($this->js_files as $path) {
			if (parse_url($path, PHP_URL_QUERY) === null) {
				$path .= '?'.(int) filemtime($path);
			}

			echo (new CTag('script', true))->setAttribute('src', $path);
		}

		echo '</head>'."\n";

		return $this;
	}
}

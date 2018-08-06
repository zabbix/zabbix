<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
 * A class for Zabbix re-branding.
 */
class CBrand {

	/**
	 * An instance of brand.
	 *
	 * @var CBrand
	 */
	protected static $instance;

	/**
	 * Brand configuration if exist ?
	 * @var CBrand
	 */
	protected $config;

	/**
	 * Url that should be used with all "Help" links.
	 * @var string
	 */
	protected $help_url;

	/**
	 * Text that should be placed in page footer.
	 * @var string
	 */
	protected $brand_footer;

	public function __construct($rootDir) {
		$configFile = $rootDir.CConfigFile::BRAND_CONFIG_FILE_PATH;
        $this->config = $this->loadConfig($configFile);
	}

	protected function __clone() {
	}

	/**
	 * Returns an instance of brand.
	 *
	 * @return CBrand
	 */
	public static function getInstance($rootDir = null) {
		if (self::$instance === null) {
			$class = __CLASS__;
			self::$instance = new $class($rootDir);
		}

		return self::$instance;
	}

	/**
	 * @return CBrand
	 */
	private function loadConfig($configFile) {
		$return = null;

		if (file_exists($configFile)) {
			ob_start();
			include($configFile);
			ob_end_clean();
			$this->help_url = isset($HELP_URL) ? $HELP_URL : null;
			$this->brand_footer = isset($BRAND_FOOTER) ? $BRAND_FOOTER : null;
			$return = $this;
		}
		return $return;
	}

	/**
	 * @return boolean
	 */
	public static function isBrand() {
		return ((bool) self::getInstance()->config);
	}

	/**
	 * @return string
	 */
	public static function getHelpUrl() {
		return self::getInstance()->help_url;
	}

	/**
	 * @return string
	 */
	public static function getBrandFooter() {
		return self::getInstance()->brand_footer;
	}
}

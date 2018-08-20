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
class CBrandHelper {

	/**
	 * Brand configuration array
	 * @var array
	 */
	private static $config = [];

	/**
	 * Lazy loading configuration
	 */
	private static function loadConfig() {
		if (!self::$config) {
			$config_file_path = realpath(dirname(__FILE__).'/../../../local/conf/brand.conf.php');

			if (file_exists($config_file_path)) {
				self::$config = include $config_file_path;
				self::$config['IS_REBRANDED'] = true;
			}
		}
	}

	/**
	 * Get value by key from configuration (load configuration if need).
	 *
	 * @param string $key
	 * @param mixed $default	Default value
	 * @param mixed $pattern	Pattern for extraction
	 *
	 * @return mixed
	 */
	private static function getValue($key, $default = false, $pattern = false) {
		self::loadConfig();

		return (array_key_exists($key, self::$config) ? self::extractValue($key, $pattern) : $default);
	}

	/**
	 * Extracting value from configuration according to the type of the pattern.
	 *
	 * @param string $key
	 * @param mixed $pattern
	 *
	 * @return mixed
	 */
	private static function extractValue($key, $pattern) {
		if (is_string($pattern)) {
			$value = sprintf($pattern, self::$config[$key]);
		}
		elseif (is_array($pattern)) {
			$value = [self::$config[$key]];
		}
		else {
			$value = self::$config[$key];
		}

		return $value;
	}

	/**
	 * Is branding active ?
	 *
	 * @return boolean
	 */
	public static function isRebranded() {
		return self::getValue('IS_REBRANDED');
	}

	/**
	 * Get Help URL.
	 *
	 * @return string
	 */
	public static function getHelpUrl() {
		return self::getValue('BRAND_HELP_URL', 'https://www.zabbix.com/documentation/4.0/', '%s');
	}

	/**
	 * Get Logo style.
	 *
	 * @return string
	 */
	public static function getLogoStyle() {
		return self::getValue('BRAND_LOGO', null,
			'background: url("%s") no-repeat center center; background-size: contain;');
	}

	/**
	 * Get Footer Label.
	 *
	 * @param boolean $with_version
	 *
	 * @return string
	 */
	public static function getFooterLabel($with_version) {
		return self::getValue(
			'BRAND_FOOTER',
			[
				$with_version ? 'Zabbix '.ZABBIX_VERSION.'. ' : null,
				'&copy; '.ZABBIX_COPYRIGHT_FROM.'&ndash;'.ZABBIX_COPYRIGHT_TO.', ',
				(new CLink('Zabbix SIA', 'http://www.zabbix.com/'))
					->addClass(ZBX_STYLE_GREY)
					->addClass(ZBX_STYLE_LINK_ALT)
					->setAttribute('target', '_blank')
			],
			[]
		);
	}
}

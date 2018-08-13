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

	const BRAND_CONFIG = [
		'HELP_URL' => 'https://www.zabbix.com/documentation/4.0/',
	];

	/**
	 * Brand configuration array
	 * @var array
	 */
	private static $config = null;

	/**
	 * Is branding active
	 * @var boolean
	 */
	private static $is_active = false;

	/**
	 * Lazy loading configuration
	 */
	private static function loadConfig() {
		if (is_null(self::$config)) {
			$config_file_path = realpath(dirname(__FILE__).'/../../../local/conf/brand.conf.php');

			if (file_exists($config_file_path)) {
				$config = include $config_file_path;
				self::$config = array_merge(self::BRAND_CONFIG, $config);
				self::$is_active = true;
			}
			else {
				self::$config = self::BRAND_CONFIG;
			}
		}
	}

	/**
	 * Is branding active ?
	 *
	 * @return boolean
	 */
	public static function isActive() {
		self::loadConfig();
		return self::$is_active;
	}

	/**
	 * Get Help URL.
	 *
	 * @return string
	 */
	public static function getHelpUrl() {
		self::loadConfig();
		return self::$config['HELP_URL'];
	}

	/**
	 * Get Logo style.
	 *
	 * @return string
	 */
	public static function getLogoStyle() {
		self::loadConfig();
		return 	isset(self::$config['LOGO'])
			? 'background: url('.self::$config['LOGO'].') no-repeat 0 0; background-size: contain;'
			: null;
	}

	/**
	 * Get Footer Label.
	 *
	 * @param boolean $with_version
	 * @return string
	 */
	public static function getFooterLabel($with_version) {
		self::loadConfig();
		return (self::$is_active && array_key_exists('BRAND_FOOTER', self::$config))
			? [self::$config['BRAND_FOOTER']]
			: [
				$with_version ? 'Zabbix '.ZABBIX_VERSION.'. ' : null,
				'&copy; '.ZABBIX_COPYRIGHT_FROM.'&ndash;'.ZABBIX_COPYRIGHT_TO.', ',
				(new CLink('Zabbix SIA', 'http://www.zabbix.com/'))
					->addClass(ZBX_STYLE_GREY)
					->addClass(ZBX_STYLE_LINK_ALT)
					->setAttribute('target', '_blank')
			];
	}
}

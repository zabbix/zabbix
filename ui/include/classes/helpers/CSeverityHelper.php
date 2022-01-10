<?php declare(strict_types = 1);
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


class CSeverityHelper {

	/**
	 * Get severity name by given state and configuration.
	 *
	 * @param int $severity
	 *
	 * @return string
	 */
	public static function getName(int $severity): string {
		switch ($severity) {
			case ZBX_SEVERITY_OK:
				return _('OK');
			case TRIGGER_SEVERITY_NOT_CLASSIFIED:
				return _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_0));
			case TRIGGER_SEVERITY_INFORMATION:
				return _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_1));
			case TRIGGER_SEVERITY_WARNING:
				return _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_2));
			case TRIGGER_SEVERITY_AVERAGE:
				return _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_3));
			case TRIGGER_SEVERITY_HIGH:
				return _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_4));
			case TRIGGER_SEVERITY_DISASTER:
				return _(CSettingsHelper::get(CSettingsHelper::SEVERITY_NAME_5));
			default:
				return _('Unknown');
		}
	}

	/**
	 * Get severity css style name.
	 *
	 * @param int|null $severity
	 * @param bool     $type
	 *
	 * @return string|null
	 */
	public static function getStyle(?int $severity, bool $type = true): ?string {
		if (!$type) {
			return ZBX_STYLE_NORMAL_BG;
		}

		switch ($severity) {
			case ZBX_SEVERITY_OK:
				return ZBX_STYLE_NORMAL_BG;
			case TRIGGER_SEVERITY_DISASTER:
				return ZBX_STYLE_DISASTER_BG;
			case TRIGGER_SEVERITY_HIGH:
				return ZBX_STYLE_HIGH_BG;
			case TRIGGER_SEVERITY_AVERAGE:
				return ZBX_STYLE_AVERAGE_BG;
			case TRIGGER_SEVERITY_WARNING:
				return ZBX_STYLE_WARNING_BG;
			case TRIGGER_SEVERITY_INFORMATION:
				return ZBX_STYLE_INFO_BG;
			case TRIGGER_SEVERITY_NOT_CLASSIFIED:
				return ZBX_STYLE_NA_BG;
			default:
				return null;
		}
	}

	/**
	 * Get severity status css style name.
	 *
	 * @param int $severity
	 *
	 * @return string|null
	 */
	public static function getStatusStyle(int $severity): ?string {
		switch ($severity) {
			case TRIGGER_SEVERITY_DISASTER:
				return ZBX_STYLE_STATUS_DISASTER_BG;
			case TRIGGER_SEVERITY_HIGH:
				return ZBX_STYLE_STATUS_HIGH_BG;
			case TRIGGER_SEVERITY_AVERAGE:
				return ZBX_STYLE_STATUS_AVERAGE_BG;
			case TRIGGER_SEVERITY_WARNING:
				return ZBX_STYLE_STATUS_WARNING_BG;
			case TRIGGER_SEVERITY_INFORMATION:
				return ZBX_STYLE_STATUS_INFO_BG;
			case TRIGGER_SEVERITY_NOT_CLASSIFIED:
				return ZBX_STYLE_STATUS_NA_BG;
			default:
				return null;
		}
	}

	/**
	 * Get severity color from configuration.
	 *
	 * @param int $severity
	 *
	 * @return string|null
	 */
	public static function getColor(int $severity): ?string {
		switch ($severity) {
			case TRIGGER_SEVERITY_DISASTER:
				return CSettingsHelper::get(CSettingsHelper::SEVERITY_COLOR_5);
			case TRIGGER_SEVERITY_HIGH:
				return CSettingsHelper::get(CSettingsHelper::SEVERITY_COLOR_4);
			case TRIGGER_SEVERITY_AVERAGE:
				return CSettingsHelper::get(CSettingsHelper::SEVERITY_COLOR_3);
			case TRIGGER_SEVERITY_WARNING:
				return CSettingsHelper::get(CSettingsHelper::SEVERITY_COLOR_2);
			case TRIGGER_SEVERITY_INFORMATION:
				return CSettingsHelper::get(CSettingsHelper::SEVERITY_COLOR_1);
			case TRIGGER_SEVERITY_NOT_CLASSIFIED:
			default:
				return CSettingsHelper::get(CSettingsHelper::SEVERITY_COLOR_0);
		}
	}

	/**
	 * Generate array with severities options.
	 *
	 * @param int $min  Minimal severity.
	 * @param int $max  Maximum severity.
	 *
	 * @return array
	 */
	public static function getSeverities(int $min = TRIGGER_SEVERITY_NOT_CLASSIFIED,
			int $max = TRIGGER_SEVERITY_COUNT - 1): array {
		$severities = [];

		foreach (range($min, $max) as $severity) {
			$severities[] = [
				'name' => self::getName($severity),
				'value' => $severity,
				'style' => self::getStyle($severity)
			];
		}

		return $severities;
	}

	/**
	 * Returns HTML representation of severity cell containing severity name and color.
	 *
	 * @param int               $severity       Trigger, Event or Problem severity.
	 * @param array|string|null $text           Trigger severity name.
	 * @param bool              $force_normal   True to return 'normal' class, false to return corresponding severity class.
	 * @param bool              $return_as_div  True to return severity cell as DIV element.
	 *
	 * @return CDiv|CCol
	 */
	public static function makeSeverityCell(int $severity, $text = null, bool $force_normal = false,
			bool $return_as_div = false) {
		if ($text === null) {
			$text = CHtml::encode(self::getName($severity));
		}

		if ($force_normal) {
			return new CCol($text);
		}

		$return = $return_as_div ? new CDiv($text) : new CCol($text);
		return $return->addClass(self::getStyle($severity));
	}
}

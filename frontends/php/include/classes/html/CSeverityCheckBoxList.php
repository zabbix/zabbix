<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


class CSeverityCheckBoxList extends CCheckBoxList {

	/**
	 * Create check box list with severities.
	 *
	 * @param string  $name      Field name in form.
	 * @param int     $max_rows  Number of rows.
	 */
	public function __construct(string $name, int $max_rows = 2) {
		parent::__construct($name);

		$this
			->setOptions(self::getOrderedSeverities(false, $max_rows))
			->addClass(ZBX_STYLE_COLUMNS)
			->addClass(ZBX_STYLE_COLUMNS_3);
	}

	/**
	 * Generate array with data for severities options ordered for showing by rows.
	 *
	 * @static
	 *
	 * @param bool $only_names  Return only name as array value.
	 * @param int  $max_rows    Number of rows.
	 *
	 * @return array
	 */
	public static function getOrderedSeverities(bool $only_names = false, int $max_rows = 2): array {

		$severities = self::getSeverities();
		$severities_count = count($severities);
		$ordered = [];

		foreach (range(0, $max_rows - 1) as $row) {
			for ($i = TRIGGER_SEVERITY_NOT_CLASSIFIED; $i < $severities_count; $i += $max_rows) {
				$ordered[$row + $i] = ($only_names) ? $severities[$row + $i]['name'] : $severities[$row + $i];
			}
		}

		return $ordered;
	}

	/**
	 * Generate array with severities options.
	 *
	 * @static
	 *
	 * @return array
	 */
	public static function getSeverities(): array {
		$config = select_config();
		$severities = [];
		foreach (range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1) as $severity) {
			$severities[] = [
				'name' => getSeverityName($severity, $config),
				'value' => $severity,
				'style' => getSeverityStyle($severity)
			];
		}

		return $severities;
	}
}

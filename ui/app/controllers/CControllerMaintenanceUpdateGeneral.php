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


abstract class CControllerMaintenanceUpdateGeneral extends CController {

	/**
	 * Function to compare values from fields "Active since" and "Active till".
	 */
	final protected function validateTimePeriods(): bool {
		$absolute_time_parser = new CAbsoluteTimeParser();

		$absolute_time_parser->parse($this->getInput('active_since'));
		$active_since_ts = $absolute_time_parser->getDateTime(true)->getTimestamp();

		$absolute_time_parser->parse($this->getInput('active_till'));
		$active_till_ts = $absolute_time_parser->getDateTime(true)->getTimestamp();

		if ($active_since_ts >= $active_till_ts) {
			$this->addFormError('/active_till', _s('Must be greater than "%1$s".', _('Active since')),
				CFormValidator::ERROR_LEVEL_PRIMARY
			);

			return false;
		}

		return true;
	}

	final protected function processTimePeriods(array $timeperiods): array {
		$timeperiod_fields = [
			TIMEPERIOD_TYPE_ONETIME => ['timeperiod_type', 'start_date', 'period'],
			TIMEPERIOD_TYPE_DAILY => ['timeperiod_type', 'every', 'start_time', 'period'],
			TIMEPERIOD_TYPE_WEEKLY => ['timeperiod_type', 'every', 'dayofweek', 'start_time', 'period'],
			TIMEPERIOD_TYPE_MONTHLY => ['timeperiod_type', 'every', 'month', 'dayofweek', 'day', 'start_time', 'period']
		];

		foreach ($timeperiods as &$timeperiod) {
			$timeperiod = array_intersect_key($timeperiod,
				array_flip($timeperiod_fields[$timeperiod['timeperiod_type']])
			);
		}

		return $timeperiods;
	}

	final protected function processMaintenance(array &$maintenance): void {
		if ($maintenance['maintenance_type'] == MAINTENANCE_TYPE_NORMAL) {
			$maintenance += [
				'tags_evaltype' => $this->getInput('tags_evaltype'),
				'tags' => []
			];

			foreach ($this->getInput('tags', []) as $tag) {
				if (array_key_exists('tag', $tag) && array_key_exists('value', $tag)
					&& ($tag['tag'] !== '' || $tag['value'] !== '')) {
					$maintenance['tags'][] = $tag;
				}
			}
		}
	}

	final protected function parseActiveTime(string $active_time): int {
		$absolute_time_parser = new CAbsoluteTimeParser();
		$absolute_time_parser->parse($active_time);

		return $absolute_time_parser->getDateTime(true)->getTimestamp();;
	}
}

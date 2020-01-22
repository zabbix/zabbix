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


/**
 * A class with SLA calculation logic.
 *
 * Class CServicesSlaCalculator
 */
class CServicesSlaCalculator {

	/**
	 * Calculates the SLA for the given service during the given period.
	 *
	 * Returns the following information:
	 * - ok             - the percentage of time the service was in OK state;
	 * - problem        - the percentage of time the service was in problem state;
	 * - okTime         - the time the service was in OK state, in seconds;
	 * - problemTime    - the time the service was in problem state, in seconds;
	 * - downtimeTime   - the time the service was down, in seconds;
	 * - dt;
	 * - ut.
	 *
	 * @param array $service_alarms
	 * @param array $service_times
	 * @param int   $period_start
	 * @param int   $period_end
	 * @param int   $start_value        the value of the last service alarm
	 *
	 * @return array
	 */
	public function calculateSla(array $service_alarms, array $service_times, $period_start, $period_end,
			$start_value) {
		/**
		 * structure of "$data":
		 * - alarm	- on/off status (0,1 - off; >1 - on)
		 * - dt_s	- count of downtime starts
		 * - dt_e	- count of downtime ends
		 * - ut_s	- count of uptime starts
		 * - ut_e	- count of uptime ends
		 * - clock	- time stamp
		 *
		 * Key in $data array contains unique value to sort by.
		 */
		$data = [];
		$latest = 0; // Timestamp of last database record.

		foreach ($service_alarms as $alarm) {
			if ($alarm['clock'] >= $period_start && $alarm['clock'] <= $period_end) {
				$data[$alarm['servicealarmid']] = [
					'alarm' => $alarm['value'],
					'clock' => $alarm['clock']
				];
				if ($alarm['clock'] > $latest) {
					$latest = $alarm['clock'];
				}
			}
		}

		if ($period_end != $latest) {
			$data[] = ['clock' => $period_end];
		}

		$unmarkedPeriodType = 'ut';

		$service_time_data = [];
		foreach ($service_times as $time) {
			if ($time['type'] == SERVICE_TIME_TYPE_UPTIME) {
				$this->expandPeriodicalTimes($service_time_data, $period_start, $period_end, $time['ts_from'],
					$time['ts_to'], 'ut'
				);

				// if an uptime period exists - unmarked time is downtime
				$unmarkedPeriodType = 'dt';
			}
			elseif ($time['type'] == SERVICE_TIME_TYPE_DOWNTIME) {
				$this->expandPeriodicalTimes($service_time_data, $period_start, $period_end, $time['ts_from'],
					$time['ts_to'], 'dt'
				);
			}
			elseif ($time['type'] == SERVICE_TIME_TYPE_ONETIME_DOWNTIME && $time['ts_to'] >= $period_start
					&& $time['ts_from'] <= $period_end) {
				if ($time['ts_from'] < $period_start) {
					$time['ts_from'] = $period_start;
				}
				if ($time['ts_to'] > $period_end) {
					$time['ts_to'] = $period_end;
				}

				if (isset($service_time_data[$time['ts_from']]['dt_s'])) {
					$service_time_data[$time['ts_from']]['dt_s']++;
				}
				else {
					$service_time_data[$time['ts_from']]['dt_s'] = 1;
				}

				if (isset($service_time_data[$time['ts_to']]['dt_e'])) {
					$service_time_data[$time['ts_to']]['dt_e']++;
				}
				else {
					$service_time_data[$time['ts_to']]['dt_e'] = 1;
				}
			}
		}

		if ($service_time_data) {
			ksort($service_time_data);

			/*
			 * If 'downtime' service time is active at moment of $period_start and service is in problem state, move
			 * $start_value to moment when service time ends.
			 */
			if ($start_value > 1) {
				$first_service_time_start_time = key($service_time_data);
				$first_service_time = $service_time_data[$first_service_time_start_time];
				if ($period_start == $first_service_time_start_time && array_key_exists('dt_s', $first_service_time)) {
					foreach (array_keys($service_time_data) as $service_time_ts) {
						if (array_key_exists('dt_e', $service_time_data[$service_time_ts])) {
							$data[] = [
								'alarm' => $start_value,
								'clock' => $service_time_ts
							];
							$start_value = 0;
							break;
						}
					}
				}
			}

			/*
			 * For next foreach we need incrementally increasing keys (starting with n > 0) but entries still need to
			 * be sorted by 'clock'.
			 */
			CArrayHelper::sort($data, [['field' => 'clock', 'order' => ZBX_SORT_UP]]);
			$data = array_combine(range(1, count($data)), array_values($data));

			// Put service times between alarms at right positions.
			$prev_time = $period_start;
			$prev_alarmid = 0;
			foreach ($data as $alarmid => $val) {
				/**
				 * Search what service times was in force during the alarm interval and put selected services right
				 * before the end of service alarm interval.
				 */
				$service_times = CArrayHelper::getByKeysRange($service_time_data, $prev_time, $val['clock']);
				foreach ($service_times as $ts => $service_time) {
					$data[$prev_alarmid.'.'.$ts] = $service_time + ['clock' => $ts];
				}

				$prev_time = $val['clock'] + 1; // Next range begins in next second.
				$prev_alarmid = $alarmid;
			}
		}

		// Sort chronologically.
		ksort($data);

		// calculate times
		$dtCnt = 0;
		$utCnt = 0;
		$slaTime = [
			'dt' => ['problemTime' => 0, 'okTime' => 0],
			'ut' => ['problemTime' => 0, 'okTime' => 0]
		];
		$prevTime = $period_start;

		// Count active uptimes/downtimes at the beginning of calculated period.
		foreach ($data as $val) {
			if ($period_start != $val['clock']) {
				continue;
			}

			if (array_key_exists('ut_s', $val)) {
				$utCnt += $val['ut_s'];
			}
			if (array_key_exists('ut_e', $val)) {
				$utCnt -= $val['ut_e'];
			}
			if (array_key_exists('dt_s', $val)) {
				$dtCnt += $val['dt_s'];
			}
			if (array_key_exists('dt_e', $val)) {
				$dtCnt -= $val['dt_e'];
			}

			break;
		}

		foreach ($data as $val) {
			// skip first data [already read]
			if ($val['clock'] == $period_start) {
				continue;
			}

			if ($dtCnt > 0) {
				$periodType = 'dt';
			}
			elseif ($utCnt > 0) {
				$periodType = 'ut';
			}
			else {
				$periodType = $unmarkedPeriodType;
			}

			// Calculate the duration of current state. Negative durations are ignored.
			$duration = max($val['clock'] - $prevTime, 0);

			// state=0,1 [OK] (1 - information severity of trigger), >1 [PROBLEMS] (trigger severity)
			if ($start_value > 1) {
				$slaTime[$periodType]['problemTime'] += $duration;
			}
			else {
				$slaTime[$periodType]['okTime'] += $duration;
			}

			if (isset($val['ut_s'])) {
				$utCnt += $val['ut_s'];
			}
			if (isset($val['ut_e'])) {
				$utCnt -= $val['ut_e'];
			}
			if (isset($val['dt_s'])) {
				$dtCnt += $val['dt_s'];
			}
			if (isset($val['dt_e'])) {
				$dtCnt -= $val['dt_e'];
			}
			if (isset($val['alarm'])) {
				$start_value = $val['alarm'];
			}

			$prevTime = $val['clock'];
		}

		$slaTime['problemTime'] = &$slaTime['ut']['problemTime'];
		$slaTime['okTime'] = &$slaTime['ut']['okTime'];
		$slaTime['downtimeTime'] = $slaTime['dt']['okTime'] + $slaTime['dt']['problemTime'];

		$fullTime = $slaTime['problemTime'] + $slaTime['okTime'];
		if ($fullTime > 0) {
			$slaTime['problem'] = 100 * $slaTime['problemTime'] / $fullTime;
			$slaTime['ok'] = 100 * $slaTime['okTime'] / $fullTime;
		}
		else {
			$slaTime['problem'] = 100;
			$slaTime['ok'] = 100;
		}

		return $slaTime;
	}

	/**
	 * Adds information about a weekly scheduled uptime or downtime to the $data array.
	 *
	 * @param array     $data
	 * @param int       $period_start     start of the SLA calculation period
	 * @param int       $period_end       end of the SLA calculation period
	 * @param int       $ts_from          start of the scheduled uptime or downtime
	 * @param int       $ts_to            end of the scheduled uptime or downtime
	 * @param string    $type             "ut" for uptime and "dt" for downtime
	 */
	protected function expandPeriodicalTimes(array &$data, $period_start, $period_end, $ts_from, $ts_to, $type) {
		$weekStartDate = new DateTime();
		$weekStartDate->setTimestamp($period_start);

		$days = $weekStartDate->format('w');
		$hours = $weekStartDate->format('H');
		$minutes = $weekStartDate->format('i');
		$seconds = $weekStartDate->format('s');

		$weekStartDate->modify('-'.$days.' day -'.$hours.' hour -'.$minutes.' minute -'.$seconds.' second');

		$weekStartTimestamp = $weekStartDate->getTimestamp();

		for (; $weekStartTimestamp < $period_end; $weekStartTimestamp += $this->secondsPerNextWeek($weekStartTimestamp)) {

			$weekStartDate->setTimestamp($weekStartTimestamp);
			$weekStartDate->modify('+'.$ts_from.' second');
			$_s = $weekStartDate->getTimestamp();

			$weekStartDate->setTimestamp($weekStartTimestamp);
			$weekStartDate->modify('+'.$ts_to.' second');
			$_e = $weekStartDate->getTimestamp();

			if ($period_end < $_s || $period_start >= $_e) {
				continue;
			}

			if ($_s < $period_start) {
				$_s = $period_start;
			}
			if ($_e > $period_end) {
				$_e = $period_end;
			}

			if (isset($data[$_s][$type.'_s'])) {
				$data[$_s][$type.'_s']++;
			}
			else {
				$data[$_s][$type.'_s'] = 1;
			}

			if (isset($data[$_e][$type.'_e'])) {
				$data[$_e][$type.'_e']++;
			}
			else {
				$data[$_e][$type.'_e'] = 1;
			}
		}
	}


	/**
	 * Return seconds in next week relative to given week start timestamp.
	 *
	 * @param int $currentWeekStartTimestamp
	 *
	 * @return int
	 */
	protected function secondsPerNextWeek($currentWeekStartTimestamp) {
		$currentWeekStartDate = new DateTime();
		$currentWeekStartDate->setTimestamp($currentWeekStartTimestamp);

		$currentWeekStartDate->modify('+7 day');

		$result = $currentWeekStartDate->getTimestamp() - $currentWeekStartTimestamp;

		return $result;
	}
}

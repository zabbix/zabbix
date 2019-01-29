<?php

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
	 * @param array $serviceAlarms
	 * @param array $serviceTimes
	 * @param int   $periodStart
	 * @param int   $periodEnd
	 * @param int   $startValue        the value of the last service alarm
	 *
	 * @return array
	 */
	public function calculateSla(array $serviceAlarms, array $serviceTimes, $periodStart, $periodEnd, $startValue) {
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

		foreach ($serviceAlarms as $alarm) {
			if ($alarm['clock'] >= $periodStart && $alarm['clock'] <= $periodEnd) {
				$data[$alarm['servicealarmid']] = [
					'alarm' => $alarm['value'],
					'clock' => $alarm['clock']
				];
				if ($alarm['clock'] > $latest) {
					$latest = $alarm['clock'];
				}
			}
		}

		if ($periodEnd != $latest) {
			$data[] = ['clock' => $periodEnd];
		}

		$unmarkedPeriodType = 'ut';

		$service_time_data = [];
		foreach ($serviceTimes as $time) {
			if ($time['type'] == SERVICE_TIME_TYPE_UPTIME) {
				$this->expandPeriodicalTimes($service_time_data, $periodStart, $periodEnd, $time['ts_from'],
					$time['ts_to'], 'ut'
				);

				// if an uptime period exists - unmarked time is downtime
				$unmarkedPeriodType = 'dt';
			}
			elseif ($time['type'] == SERVICE_TIME_TYPE_DOWNTIME) {
				$this->expandPeriodicalTimes($service_time_data, $periodStart, $periodEnd, $time['ts_from'],
					$time['ts_to'], 'dt'
				);
			}
			elseif($time['type'] == SERVICE_TIME_TYPE_ONETIME_DOWNTIME && $time['ts_to'] >= $periodStart
					&& $time['ts_from'] <= $periodEnd) {
				if ($time['ts_from'] < $periodStart) {
					$time['ts_from'] = $periodStart;
				}
				if ($time['ts_to'] > $periodEnd) {
					$time['ts_to'] = $periodEnd;
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

		// Put service times between alarms at right positions.
		if ($service_time_data) {
			ksort($service_time_data);

			$prev_time = $periodStart;
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
		$prevTime = $periodStart;

		if (isset($data[$periodStart]['ut_s'])) {
			$utCnt += $data[$periodStart]['ut_s'];
		}
		if (isset($data[$periodStart]['ut_e'])) {
			$utCnt -= $data[$periodStart]['ut_e'];
		}
		if (isset($data[$periodStart]['dt_s'])) {
			$dtCnt += $data[$periodStart]['dt_s'];
		}
		if (isset($data[$periodStart]['dt_e'])) {
			$dtCnt -= $data[$periodStart]['dt_e'];
		}

		foreach ($data as $val) {
			// skip first data [already read]
			if ($val['clock'] == $periodStart) {
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
			if ($startValue > 1) {
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
				$startValue = $val['alarm'];
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

<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CControllerReportServices extends CController {

	const YEAR_LEFT_SHIFT = 5;
	private $service = null;

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'serviceid' =>		'fatal|required|db services.serviceid',
			'period' =>			'in daily,weekly,monthly,yearly',
			'year' =>			'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() < USER_TYPE_ZABBIX_USER) {
			return false;
		}

		$service = API::Service()->get([
			'output' => ['serviceid', 'name', 'showsla', 'goodsla'],
			'serviceids' => $this->getInput('serviceid')
		]);

		$this->service = reset($service);

		return (bool)$this->service;
	}

	protected function doAction() {
		// default values
		$data = [
			'period' => $this->getInput('period', 'yearly'),
			'service' => $this->service,
			'year' => $this->getInput('year', date('Y')),
			'YEAR_LEFT_SHIFT' => $this::YEAR_LEFT_SHIFT
		];

		switch ($data['period']) {
			case 'yearly':
				$from = date('Y') - $this::YEAR_LEFT_SHIFT;
				$to = date('Y');

				function get_time($year, $y) {
					return mktime(0, 0, 0, 1, 1, $y);
				}
				break;
			case 'monthly':
				$from = 1;
				$to = 12;

				function get_time($year, $m) {
					return mktime(0, 0, 0, $m, 1, $year);
				}
				break;
			case 'daily':
				$from = 1;
				$to = DAY_IN_YEAR;

				function get_time($year, $d) {
					return mktime(0, 0, 0, 1, $d, $year);
				}
				break;
			case 'weekly':
			default:
				$from = 0;
				$to = 52;

				function get_time($year, $w) {
					$time = mktime(0, 0, 0, 1, 1, $year);
					$wd = date('w', $time);
					$wd = $wd == 0 ? 6 : $wd - 1;
					$beg =  $time - $wd * SEC_PER_DAY;

					return strtotime("+$w week", $beg);
				}
				break;
		}

		$intervals = [];
		for ($t = $from; $t <= $to; $t++) {
			if (($start = get_time($data['year'], $t)) > time()) {
				break;
			}

			if (($end = get_time($data['year'], $t + 1)) > time()) {
				$end = time();
			}

			$intervals[] = [
				'from' => $start,
				'to' => $end
			];
		}

		$sla = API::Service()->getSla([
			'serviceids' => $this->service['serviceid'],
			'intervals' => $intervals
		]);
		$data['sla'] = reset($sla);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('IT services availability report'));
		$this->setResponse($response);
	}
}

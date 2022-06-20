<?php declare(strict_types = 0);
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


class CControllerSlaExcludedDowntimeValidate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	/**
	 * @throws Exception
	 */
	protected function checkInput(): bool {
		$fields = [
			'row_index' =>			'required|int32',
			'name' =>				'required|db sla.name|not_empty',
			'start_time' =>			'required|abs_time',
			'duration_days' =>		'required|ge 0',
			'duration_hours' =>		'required|in '.implode(',', range(0, 23)),
			'duration_minutes' =>	'required|in '.implode(',', range(0, 59))
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_SERVICES_SLA) && $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA);
	}

	/**
	 * @throws Exception
	 */
	protected function doAction(): void {
		$parser = new CAbsoluteTimeParser();
		$parser->parse($this->getInput('start_time'));
		$datetime_from = $parser->getDateTime(true);

		$duration_days = $this->getInput('duration_days');
		$duration_hours = $this->getInput('duration_hours');
		$duration_minutes = $this->getInput('duration_minutes');

		$duration = new DateInterval("P{$duration_days}DT{$duration_hours}H{$duration_minutes}M");

		$datetime_to = clone $datetime_from;
		$datetime_to->add($duration);

		$period_from = $datetime_from->getTimestamp();
		$period_to = $datetime_to->getTimestamp();

		$data = [
			'body' => [
				'row_index' => $this->getInput('row_index'),
				'name' => $this->getInput('name'),
				'period_from' => $period_from,
				'period_to' => $period_to,
				'start_time' => zbx_date2str(DATE_TIME_FORMAT, $period_from),
				'duration' => convertUnitsS($period_to - $period_from, true)
			]
		];

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}

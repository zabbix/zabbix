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


class CControllerMaintenanceCreate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'mname' =>				'required|string|not_empty',
			'maintenance_type' =>	'required|in '.implode(',', [MAINTENANCE_TYPE_NODATA, MAINTENANCE_TYPE_NORMAL]),
			'active_since' =>		'required|abs_time',
			'active_till' =>		'required|abs_time',
			'timeperiods' =>		'required|array',
			'groupids' =>			'array_db hosts.hostid',
			'hostids' => 			'array_db hstgrp.groupid',
			'tags_evaltype' =>		'in '.implode(',', [MAINTENANCE_TAG_EVAL_TYPE_AND_OR, MAINTENANCE_TAG_EVAL_TYPE_OR]),
			'maintenance_tags' =>	'array',
			'description' =>		'required|string'
		];

		$ret = $this->validateInput($fields);

		if ($ret && !$this->hasInput('groupids') && !$this->hasInput('hostids')) {
			error(_('At least one host group or host must be selected.'));

			$ret = false;
		}

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot add maintenance period'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_MAINTENANCE)
			&& $this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAINTENANCE);
	}

	/**
	 * @throws Exception
	 */
	protected function doAction(): void {
		$absolute_time_parser = new CAbsoluteTimeParser();

		$absolute_time_parser->parse($this->getInput('active_since'));
		$active_since_date = $absolute_time_parser->getDateTime(true);

		$absolute_time_parser->parse($this->getInput('active_till'));
		$active_till_date = $absolute_time_parser->getDateTime(true);

		$timeperiods = $this->getInput('timeperiods', []);
		$type_fields = [
			TIMEPERIOD_TYPE_ONETIME => ['timeperiod_type', 'start_date', 'period'],
			TIMEPERIOD_TYPE_DAILY => ['timeperiod_type', 'start_time', 'every', 'period'],
			TIMEPERIOD_TYPE_WEEKLY => ['timeperiod_type', 'start_time', 'every', 'dayofweek', 'period'],
			TIMEPERIOD_TYPE_MONTHLY => ['timeperiod_type', 'start_time', 'every', 'day', 'dayofweek', 'month', 'period']
		];

		foreach ($timeperiods as &$timeperiod) {
			if ($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_ONETIME) {
				$absolute_time_parser->parse($timeperiod['start_date']);
				$timeperiod['start_date'] = $absolute_time_parser
					->getDateTime(true)
					->getTimestamp();
			}

			$timeperiod = array_intersect_key($timeperiod, array_flip($type_fields[$timeperiod['timeperiod_type']])			);
		}
		unset($timeperiod);

		$maintenance = [
			'name' => $this->getInput('mname'),
			'maintenance_type' => $this->getInput('maintenance_type'),
			'description' => $this->getInput('description'),
			'active_since' => $active_since_date->getTimestamp(),
			'active_till' => $active_till_date->getTimestamp(),
			'groups' => zbx_toObject($this->getInput('groupids', []), 'groupid'),
			'hosts' => zbx_toObject($this->getInput('hostids', []), 'hostid'),
			'timeperiods' => $timeperiods
		];

		if ($maintenance['maintenance_type'] != MAINTENANCE_TYPE_NODATA) {
			$maintenance += [
				'tags_evaltype' => $this->getInput('tags_evaltype', MAINTENANCE_TAG_EVAL_TYPE_AND_OR),
				'tags' => $this->getInput('maintenance_tags', [])
			];

			foreach ($maintenance['tags'] as $tnum => $tag) {
				if ($tag['tag'] === '' && $tag['value'] === '') {
					unset($maintenance['tags'][$tnum]);
				}
			}
		}

		$result = API::Maintenance()->create($maintenance);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Maintenance period added');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot add maintenance period'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));

	}
}

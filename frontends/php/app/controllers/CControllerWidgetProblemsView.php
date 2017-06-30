<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/blocks.inc.php';
require_once dirname(__FILE__).'/../../include/hostgroups.inc.php';

class CControllerWidgetProblemsView extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'name'			=> 'string',
			'fullscreen'	=> 'in 0,1',
			'fields'		=> 'required|array'
		];

		$ret = $this->validateInput($fields);
		/*
		 * @var array        $fields
		 * @var array|string $fields['groupids']    (optional)
		 * @var array|string $fields['hostids']     (optional)
		 * @var int          $fields['show_lines']  (optional) BETWEEN 1,100
		 */

		if (!$ret) {
			// TODO VM: prepare propper response for case of incorrect fields
			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson('')]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$fields = $this->getInput('fields') + [
			'show' => TRIGGERS_OPTION_IN_PROBLEM,
			'groupids' => [],
			'hostids' => [],
			'show_lines' => ZBX_DEFAULT_WIDGET_LINES
		];

/*		$filter = [
			'maintenance' => null,
			'severity' => null,
			'trigger_name' => '',
			'extAck' => 0,
		];

		if (CProfile::get('web.dashconf.filter.enable', 0) == 1) {
			// groups
			if (CProfile::get('web.dashconf.groups.grpswitch', 0) == 1) {
				$hide_groupids = zbx_objectValues(CFavorite::get('web.dashconf.groups.hide.groupids'), 'value');

				if ($hide_groupids) {
					// get all groups if no selected groups defined
					if ($filter['groupids'] === null) {
						$filter['groupids'] = array_keys(
							API::HostGroup()->get([
								'output' => [],
								'preservekeys' => true
							])
						);
					}

					$filter['groupids'] = array_diff($filter['groupids'], $hide_groupids);

					// get available hosts
					$hostids_available = array_keys(
						API::Host()->get([
							'output' => [],
							'groupids' => $filter['groupids'],
							'preservekeys' => true
						])
					);

					$hostids_hidden = array_keys(
						API::Host()->get([
							'output' => [],
							'groupids' => $hide_groupids,
							'preservekeys' => true
						])
					);

					$filter['hostids'] = array_diff($hostids_available, $hostids_hidden);
				}
			}

			// hosts
			$maintenance = CProfile::get('web.dashconf.hosts.maintenance', 1);
			$filter['maintenance'] = ($maintenance == 0) ? 0 : null;

			// triggers
			$severity = CProfile::get('web.dashconf.triggers.severity', null);
			$filter['severity'] = zbx_empty($severity) ? null : explode(';', $severity);
			$filter['severity'] = zbx_toHash($filter['severity']);
			$filter['trigger_name'] = CProfile::get('web.dashconf.triggers.name', '');

			$filter['extAck'] = $config['event_ack_enable'] ? CProfile::get('web.dashconf.events.extAck', 0) : 0;
		}*/

		$config = select_config();

		$data = CScreenProblem::getData([
			'show' => $fields['show'],
			'groupids' => getSubGroups((array) $fields['groupids']),
			'hostids' => (array) $fields['hostids'],
		], $config, true);
		$data = CScreenProblem::sortData($data, $config, 'clock', ZBX_SORT_DOWN);

		$info = _n('%1$d of %3$d%2$s problem is shown', '%1$d of %3$d%2$s problems are shown',
			min($fields['show_lines'], count($data['problems'])),
			(count($data['problems']) > $config['search_limit']) ? '+' : '',
			min($config['search_limit'], count($data['problems']))
		);
		$data['problems'] = array_slice($data['problems'], 0, $fields['show_lines'], true);

		$data = CScreenProblem::makeData($data, [
			'show' => $fields['show'],
			'details' => 0
		], $config, true);

		if ($data['problems']) {
			$data['triggers_hosts'] = getTriggersHostsList($data['triggers']);
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', CWidgetConfig::getKnownWidgetTypes()[WIDGET_PROBLEMS]),
			'fields' => [
				'show' => $fields['show']
			],
			'config' => [
				'event_ack_enable' => $config['event_ack_enable']
			],
			'data' => $data,
			'info' => $info,
//			'filter' => $filter,
			'fullscreen' => $this->getInput('fullscreen', 0),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}

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


class CControllerHintboxEventlist extends CController {

	/**
	 * @var array
	 */
	protected $trigger;

	protected function init(): void {
		$this->disableSIDvalidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'triggerid' =>			'required|db triggers.triggerid',
			'eventid_till' =>		'required|db events.eventid',
			'show_timeline' =>		'required|in 0,1',
			'show_tags' =>			'required|in '.implode(',', [SHOW_TAGS_NONE, SHOW_TAGS_1, SHOW_TAGS_2, SHOW_TAGS_3]),
			'filter_tags' =>		'array',
			'tag_name_format' =>	'required|in '.implode(',', [TAG_NAME_FULL, TAG_NAME_SHORTENED, TAG_NAME_NONE]),
			'tag_priority' =>		'required|string'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$triggers = API::Trigger()->get([
				'output' => ['triggerid', 'expression', 'comments', 'url', 'manual_close'],
				'triggerids' => $this->getInput('triggerid')
			]);

			if (!$triggers) {
				error(_('No permissions to referred object or it does not exist!'));
				$ret = false;
			}
			else {
				$this->trigger = $triggers[0];
			}
		}

		if ($ret) {
			$ret = $this->validateInputFilterTags();
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseData([]));
		}

		return $ret;
	}

	private function validateInputFilterTags(): bool {
		$filter_tags = $this->getInput('filter_tags', []);

		foreach ($filter_tags as $filter_tag) {
			$fields = [
				'tag' =>		'required|string',
				'operator' =>	'required|in '.implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL, TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS]),
				'value' =>		'required|string'
			];

			$validator = new CNewValidator($filter_tag, $fields);
			array_map('error', $validator->getAllErrors());

			if ($validator->isError() || $validator->isErrorFatal()) {
				return false;
			}
		}

		return true;
	}

	protected function checkPermissions(): bool {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction(): void {
		$trigger = $this->trigger;
		$trigger['url'] = CMacrosResolverHelper::resolveTriggerUrl($trigger + [
				'eventid' => $this->getInput('eventid_till')
			],
			$url
		) ? $url : '';

		$options = [
			'output' => ['eventid', 'r_eventid', 'clock', 'ns', 'acknowledged'],
			'select_acknowledges' => ['action'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'eventid_till' => $this->getInput('eventid_till'),
			'objectids' => $trigger['triggerid'],
			'value' => TRIGGER_VALUE_TRUE,
			'sortfield' => ['eventid'],
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => ZBX_WIDGET_ROWS
		];

		if ($this->getInput('show_tags') != SHOW_TAGS_NONE) {
			$options['selectTags'] = ['tag', 'value'];
		}

		$problems = API::Event()->get($options);

		CArrayHelper::sort($problems, [
			['field' => 'clock', 'order' => ZBX_SORT_DOWN],
			['field' => 'ns', 'order' => ZBX_SORT_DOWN]
		]);

		$r_eventids = [];

		foreach ($problems as $problem) {
			$r_eventids[$problem['r_eventid']] = true;
		}
		unset($r_eventids[0]);

		$r_events = $r_eventids
			? API::Event()->get([
				'output' => ['clock', 'correlationid', 'userid'],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'eventids' => array_keys($r_eventids),
				'preservekeys' => true
			])
			: [];

		foreach ($problems as &$problem) {
			if (array_key_exists($problem['r_eventid'], $r_events)) {
				$problem['r_clock'] = $r_events[$problem['r_eventid']]['clock'];
				$problem['correlationid'] = $r_events[$problem['r_eventid']]['correlationid'];
				$problem['userid'] = $r_events[$problem['r_eventid']]['userid'];
			}
			else {
				$problem['r_clock'] = 0;
				$problem['correlationid'] = 0;
				$problem['userid'] = 0;
			}

			if (bccomp($problem['eventid'], $this->getInput('eventid_till')) == 0) {
				$trigger['comments'] = CMacrosResolverHelper::resolveTriggerDescription($trigger + [
						'clock' => $problem['clock'],
						'ns' => $problem['ns']
					], ['events' => true]);
			}
		}
		unset($problem);

		$this->setResponse(new CControllerResponseData([
			'trigger' => array_intersect_key($trigger, array_flip(['triggerid', 'comments', 'url'])),
			'problems' => $problems,
			'show_timeline' => (bool) $this->getInput('show_timeline'),
			'show_tags' => $this->getInput('show_tags'),
			'filter_tags' => $this->getInput('filter_tags', []),
			'tag_name_format' => $this->getInput('tag_name_format'),
			'tag_priority' => $this->getInput('tag_priority'),
			'allowed_ui_problems' => $this->checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS),
			'allowed_add_comments' => $this->checkAccess(CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS),
			'allowed_change_severity' => $this->checkAccess(CRoleHelper::ACTIONS_CHANGE_SEVERITY),
			'allowed_acknowledge' => $this->checkAccess(CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS),
			'allowed_close' => ($trigger['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED
				&& $this->checkAccess(CRoleHelper::ACTIONS_CLOSE_PROBLEMS)
			)
		]));
	}
}

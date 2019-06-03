<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CControllerNotificationsGet extends CController {

	protected function checkInput() {
		$fields = [
			'validate_eventids' => 'array_db events.eventid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$data = json_encode(['error' => _('Invalid request.')]);
			$this->setResponse(new CControllerResponseData(['main_block' => $data]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return (!CWebUser::isGuest() && $this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$msg_settings = getMessageSettings();
		$trigger_limit = 15;

		$timeout = (int) timeUnitToSeconds($msg_settings['timeout']);
		$config = select_config();
		$ok_timeout = (int) timeUnitToSeconds($config['ok_period']);

		$result = [
			'notifications' => [],
			'listid' => '',
			'settings' => [
				'enabled' => (bool) $msg_settings['enabled'],
				'alarm_timeout' => (int) $msg_settings['sounds.repeat'],
				'msg_recovery_timeout' => min([$timeout, $ok_timeout]),
				'msg_timeout' => $timeout,
				'muted' => (bool) $msg_settings['sounds.mute'],
				'severity_styles' => [
					-1                              => ZBX_STYLE_NORMAL_BG,
					TRIGGER_SEVERITY_AVERAGE        => ZBX_STYLE_AVERAGE_BG,
					TRIGGER_SEVERITY_DISASTER       => ZBX_STYLE_DISASTER_BG,
					TRIGGER_SEVERITY_HIGH           => ZBX_STYLE_HIGH_BG,
					TRIGGER_SEVERITY_INFORMATION    => ZBX_STYLE_INFO_BG,
					TRIGGER_SEVERITY_NOT_CLASSIFIED => ZBX_STYLE_NA_BG,
					TRIGGER_SEVERITY_WARNING        => ZBX_STYLE_WARNING_BG
				],
				'files' => [
					-1                              => $msg_settings['sounds.recovery'],
					TRIGGER_SEVERITY_AVERAGE        => $msg_settings['sounds.3'],
					TRIGGER_SEVERITY_DISASTER       => $msg_settings['sounds.5'],
					TRIGGER_SEVERITY_HIGH           => $msg_settings['sounds.4'],
					TRIGGER_SEVERITY_INFORMATION    => $msg_settings['sounds.1'],
					TRIGGER_SEVERITY_NOT_CLASSIFIED => $msg_settings['sounds.0'],
					TRIGGER_SEVERITY_WARNING        => $msg_settings['sounds.2']
				]
			]
		];

		if (!$msg_settings['triggers.severities'] || !$msg_settings['enabled']) {
			return $this->setResponse(new CControllerResponseData(['main_block' => json_encode($result)]));
		}

		/*
		 * Problems API will not retrun resolved event if it's "ok" display time has passed or if client has lost
		 * read permission for an item. Missing information is synced with client via this argument.
		 */
		$validate_eventids = $this->getInput('validate_eventids', []);
		$time_from = max([$msg_settings['last.clock'], time() - $timeout]);
		$problems = $this->getLastProblems([
			'time_from'       => $time_from,
			'show_recovered'  => $msg_settings['triggers.recovery'],
			'show_suppressed' => $msg_settings['show_suppressed'],
			'severities'      => array_keys($msg_settings['triggers.severities']),
			'limit'           => 15
		], $validate_eventids);

		$validate_eventids = array_unique($validate_eventids);
		$valid_events = $validate_eventids ? API::Event()->get([
			'output' => ['eventid', 'r_eventid', 'clock'],
			'eventids' => $validate_eventids,
			'sortfield' => 'clock',
			'sortorder' => ZBX_SORT_DOWN,
			'preservekeys' => true
		]) : [];

		foreach ($problems as $problem) {
			if ($problem['clock'] < $time_from) {
				continue;
			}

			$notification = $this->problemToNotification($problem, $msg_settings);

			$result['listid'] .= $notification['uid'] . '_';
			$result['notifications'][$notification['eventid']] = $notification;
		}

		foreach ($valid_events as $eventid => $event) {
			if (array_key_exists($eventid, $result['notifications'])) {
				continue;
			}

			$uid = $eventid . '_' . ($event['r_eventid'] == 0 ? 0 : 1);
			$result['listid'] .= $uid . '_';
			$result['notifications'][$eventid] = [
				'uid' => $uid,
				'clock' => $event['clock'],
				'eventid' => $eventid,
				'resolved' => (bool) ($event['r_eventid'] != 0)
			];
		}

		CArrayHelper::sort($result['notifications'], [
			['field' => 'clock', 'order' => ZBX_SORT_DOWN],
			['field' => 'priority', 'order' => ZBX_SORT_DOWN],
			['field' => 'eventid', 'order' => ZBX_SORT_DOWN]
		]);

		$result['listid'] = sprintf('%u:%u:%u',
			crc32($result['listid']), $result['settings']['alarm_timeout'], $result['settings']['msg_timeout']
		);

		$result['notifications'] = array_values($result['notifications']);

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($result)]));
	}

	/**
	 * @param array $problem
	 * @param array $msg_settings
	 *
	 * @return array
	 */
	protected function problemToNotification(array $problem, array $msg_settings) {
		$url_problems = (new CUrl('zabbix.php'))
			->setArgument('action', 'problem.view')
			->setArgument('filter_hostids[]', $problem['host']['hostid'])
			->setArgument('filter_set', '1')
			->getUrl();
		$url_events = (new CUrl('zabbix.php'))
			->setArgument('action', 'problem.view')
			->setArgument('filter_triggerids[]', $problem['objectid'])
			->setArgument('filter_set', '1')
			->getUrl();

		$url_tr_events = 'tr_events.php?eventid=' . $problem['eventid'] . '&triggerid=' . $problem['objectid'];

		return [
			'uid' => sprintf('%d_%d', $problem['eventid'], $problem['resolved']),
			'eventid' => $problem['eventid'],
			'clock' => $problem['clock'],
			'resolved' => (int) $problem['resolved'],
			'severity' => $problem['severity'],
			'title' => sprintf('[url=%s]%s[/url]', $url_problems, CHtml::encode($problem['host']['name'])),
			'body' => [
				'[url=' . $url_events . ']' . CHtml::encode($problem['description']) . '[/url]',
				'[url=' . $url_tr_events . ']' .
					zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']) . '[/url]',
			],
			'timeout' => $msg_settings['timeout']
		];
	}

	/**
	 * Selects recent problems.
	 *
	 * @param array  $options  Variate selection set using options. All fields are mandatory.
	 * @param string $options['time_from']
	 * @param bool   $options['show_recovered']
	 * @param bool   $options['show_suppressed']
	 * @param array  $options['severities']
	 * @param int    $options['limit']
	 *
	 * @return array
	 */
	protected function getLastProblems(array $options, array &$validate_eventids = []) {
		$problem_options = [
			'output' => ['eventid', 'r_eventid', 'objectid', 'severity', 'clock', 'r_clock', 'name'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'severities' => $options['severities'],
			'sortorder' => ZBX_SORT_DOWN,
			'sortfield' => ['eventid'],
			'limit' => $options['limit']
		];

		if ($options['show_recovered']) {
			$problem_options['recent'] = true;
		}
		else {
			$problem_options['time_from'] = $options['time_from'];
		}

		if (!$options['show_suppressed']) {
			$problem_options['suppressed'] = false;
		}

		$db_problems = API::Problem()->get($problem_options);
		$triggers = $db_problems
			? API::Trigger()->get([
				'output' => [],
				'selectHosts' => ['hostid', 'name'],
				'triggerid' => zbx_objectValues($db_problems, 'objectid'),
				'lastChangeSince' => $options['time_from'],
				'preservekeys' => true
			])
			: [];

		$problems = [];

		foreach ($db_problems as $problem) {
			$resolved = ($problem['r_eventid'] != 0);

			if (!array_key_exists($problem['objectid'], $triggers)) {
				continue;
			}

			$trigger = $triggers[$problem['objectid']];
			$problems[] = [
				'resolved' => $resolved,
				'triggerid' => $problem['objectid'],
				'objectid' => $problem['objectid'],
				'eventid' => $problem['eventid'],
				'description' => $problem['name'],
				'host' => reset($trigger['hosts']),
				'severity' => $problem['severity'],
				'clock' => $resolved ? $problem['r_clock'] : $problem['clock']
			];
			$validate_eventids[] = $problem['eventid'];
		}

		return $problems;
	}
}

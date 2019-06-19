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

// TODO listid is no longer used, remove it
// TODO collapse resolved notifications into one for multiple event generating trigger.
// receive 2 problms (from multiple) - then resolve them,

class CControllerNotificationsGet extends CController {

	protected function checkInput() {
		$fields = [
			'validate_eventids' => 'array_db events.eventid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setErrorResponse(_('Invalid request.'));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return (!CWebUser::isGuest() && $this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	public function setErrorResponse($message) {
		parent::setResponse(new CControllerResponseData(['main_block' => json_encode(['error' => $message])]));
	}

	public function setResponse() {
		/* $this->listid = time(); */
		/* array_splice($this->notifications, 1, 2); */
		sdFile(time());
		sdFile('RESP: this->notifications');
		sdFile($this->notifications);

		parent::setResponse(new CControllerResponseData(['main_block' => json_encode([
			'notifications' => $this->notifications,
			'listid' => $this->listid,
			'settings' => $this->settings
		])]));
	}

	protected function initProperties() {
		$this->notifications = [];
		$this->listid = '';

		$this->validate_eventids = array_flip($this->getInput('validate_eventids', []));
		sdFile('this->validate_eventids');
		sdFile($this->validate_eventids);

		$this->user_msg_settings = getMessageSettings();

		$config = select_config();
		$ok_timeout = (int) timeUnitToSeconds($config['ok_period']);
		$timeout = (int) timeUnitToSeconds($this->user_msg_settings['timeout']);

		$this->time_from = max([$this->user_msg_settings['last.clock'], time() - $timeout]);

		$this->user_msg_settings['timeout'] = $timeout;
		$this->user_msg_settings['ok_timeout'] = min([$timeout, $ok_timeout]);
		$this->user_msg_settings['show_recovered'] = (int) $this->user_msg_settings['triggers.recovery'];

		$this->settings = [
			'enabled' => (bool) $this->user_msg_settings['enabled'],
			'alarm_timeout' => (int) $this->user_msg_settings['sounds.repeat'],
			'msg_recovery_timeout' => $this->user_msg_settings['ok_timeout'],
			'msg_timeout' => $this->user_msg_settings['timeout'],
			'muted' => (bool) $this->user_msg_settings['sounds.mute'],
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
				-1                              => $this->user_msg_settings['sounds.recovery'],
				TRIGGER_SEVERITY_AVERAGE        => $this->user_msg_settings['sounds.3'],
				TRIGGER_SEVERITY_DISASTER       => $this->user_msg_settings['sounds.5'],
				TRIGGER_SEVERITY_HIGH           => $this->user_msg_settings['sounds.4'],
				TRIGGER_SEVERITY_INFORMATION    => $this->user_msg_settings['sounds.1'],
				TRIGGER_SEVERITY_NOT_CLASSIFIED => $this->user_msg_settings['sounds.0'],
				TRIGGER_SEVERITY_WARNING        => $this->user_msg_settings['sounds.2']
			]
		];
	}

	protected function collectDetailsFromEventApi(array $problems_by_triggerid) {
		$triggers = API::Trigger()->get([
			'output' => ['triggerid'],
			'selectHosts' => ['hostid', 'name'],
			'triggerid' => array_keys($problems_by_triggerid),
			'lastChangeSince' => $this->time_from,
			'preservekeys' => true
		]);

		foreach ($triggers as $triggerid => $trigger) {
			$trigger_host = $trigger['hosts'][0];

			$url_problems = (new CUrl('zabbix.php'))
				->setArgument('action', 'problem.view')
				->setArgument('filter_hostids[]', $tigger_host['hostid'])
				->setArgument('filter_set', '1')
				->getUrl();

			$url_events = (new CUrl('zabbix.php'))
				->setArgument('action', 'problem.view')
				->setArgument('filter_triggerids[]', $triggerid)
				->setArgument('filter_set', '1')
				->getUrl();

			$url_trigger_events_pt = (new CUrl('tr_events.php'))
				->setArgument('triggerid', $triggerid);

			$problem_title = sprintf('[url=%s]%s[/url]', $url_problems, CHtml::encode($trigger_host['name']));

			foreach ($problems_by_triggerid[$triggerid] as $db_problem) {
				$url_trigger_events = $url_trigger_events_pt->setArgument('eventid', $db_problem['eventid'])->getUrl();

				$this->notifications[] = [
					'eventid'  => $db_problem['eventid'],
					'clock'    => (int) ($db_problem['r_eventid'] == 0) ? $db_problem['clock'] : $db_problem['r_clock'],
					'resolved' => (int) ($db_problem['r_eventid'] != 0),
					'severity' => (int) $db_problem['severity'],
					'title'    => $problem_title,
					'body'     => [
						'[url=' . $url_events . ']' . CHtml::encode($db_problem['name']) . '[/url]',
						'[url=' . $url_trigger_events . ']' .
							zbx_date2str(DATE_TIME_FORMAT_SECONDS, $db_problem['clock']) . '[/url]',
					]
				];
			}
		}
	}

	protected function collectFromEventApi() {
		$db_events = API::Event()->get([
			'output' => ['eventid', 'r_eventid', 'clock', 'severity'],
			'eventids' => array_keys($this->validate_eventids),
			'sortfield' => 'clock',
			'sortorder' => ZBX_SORT_DOWN,
			'preservekeys' => true
		]);

		foreach ($db_events as $db_event) {
			$this->notifications[] = [
				'eventid'  => $db_event['eventid'],
				'resolved' => (int) ($db_event['r_eventid'] != 0),
				'severity' => (int) $db_event['severity'],
				'clock'    => (int) $db_event['clock']
			];
		}
	}

	protected function doAction() {
		$this->initProperties();

		if (!$this->user_msg_settings['enabled'] || !$this->user_msg_settings['triggers.severities']) {
			return $this->setResponse();
		}

		$problem_options = [
			'output'          => ['eventid', 'r_eventid', 'objectid', 'severity', 'clock', 'r_clock', 'name'],
			'source'          => EVENT_SOURCE_TRIGGERS,
			'object'          => EVENT_OBJECT_TRIGGER,
			'sortorder'       => ZBX_SORT_DOWN,
			'sortfield'       => ['eventid'],
			'severities'      => array_keys($this->user_msg_settings['triggers.severities']),
			'show_suppressed' => !$this->user_msg_settings['show_suppressed'] ? false : null,
			'preservekeys'    => true,
			'limit'           => 15
		];

		if ($this->user_msg_settings['show_recovered']) {
			$problem_options['recent'] = true;
		}
		else {
			$problem_options['time_from'] = $this->time_from;
		}

		$db_problems = API::Problem()->get($problem_options);

		$problems_by_triggerid = [];
		foreach ($db_problems as $eventid => $db_problem) {
			// When user uses this setting Problems API has no "time_from" option to limit all results.
			if ($this->user_msg_settings['show_recovered'] && $db_problem['clock'] < $this->time_from) {
				continue;
			}

			// Trigger API is used to select hostname only for notifications that client cannot recover from cache.
			if (!array_key_exists($eventid, $this->validate_eventids)) {
				$problems_by_triggerid[$db_problem['objectid']][] = $db_problem;
			}

			/*
			 * It is trusted client will be render notification from cache so traffic can be minimized.
			 * Client uses 'resolved' and 'eventid' keys to render.
			 * Other keys are used for sorting before output.
			 */
			else {
				$this->notifications[] = [
					'eventid'  => $db_problem['eventid'],
					'resolved' => (int) ($db_problem['r_eventid'] != 0),
					'severity' => (int) $db_problem['severity'],
					'clock'    => (int) $db_problem['r_eventid'] == 0 ? $db_problem['clock'] : $db_problem['r_clock']
				];
			}

			// If user uses very short "Show OK triggers for" timeout option, they are no more in Problems API.
			unset($this->validate_eventids[$eventid]);
		}

		if ($problems_by_triggerid) {
			$this->collectDetailsFromEventApi($problems_by_triggerid);
		}

		if ($this->validate_eventids) {
			$this->collectFromEventApi();
		}

		$this->prepResponse();
		$this->setResponse();
	}

	protected function prepResponse() {
		CArrayHelper::sort($this->notifications, [
			['field' => 'clock', 'order' => ZBX_SORT_DOWN],
			['field' => 'severity', 'order' => ZBX_SORT_DOWN],
			['field' => 'eventid', 'order' => ZBX_SORT_DOWN]
		]);

		$this->notifications = array_values($this->notifications);

		$listid = '';
		foreach ($this->notifications as &$notification) {
			$listid .= ($notification['resolved'] ? '>' : '<') . $notification['eventid'];
			unset($notification['clock']);
			if (!array_key_exists('title', $notification)) {
				unset($notification['severity']);
			}
		}
		unset($notification);

		// TODO settings changed id?
		$this->listid = crc32($listid);
	}

}

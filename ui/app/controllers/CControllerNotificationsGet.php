<?php
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


/**
 * Controller class to maintain server-side notification generation tasks.
 */
class CControllerNotificationsGet extends CController {

	protected function init() {
		parent::init();

		$this->notifications = [];
		$this->settings = getMessageSettings();
		$ok_timeout = (int) timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::OK_PERIOD));
		$timeout = (int) timeUnitToSeconds($this->settings['timeout']);
		$this->settings['timeout'] = $timeout;
		$this->settings['ok_timeout'] = min([$timeout, $ok_timeout]);
		$this->settings['show_recovered'] = (bool) $this->settings['triggers.recovery'];
		$this->settings['show_suppressed'] = (bool) $this->settings['show_suppressed'];
		if (!$this->settings['triggers.severities']) {
			$this->settings['enabled'] = true;
		}

		$this->timeout_time = time() - $this->settings['timeout'];
		$this->time_from = max([$this->settings['last.clock'], $this->timeout_time]);
	}

	protected function checkInput() {
		$fields = [
			'known_eventids' => 'array_db events.eventid'
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

	protected function checkPermissions() {
		return (!CWebUser::isGuest() && $this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		if (!$this->settings['enabled']) {
			$this->setResponse(new CControllerResponseData(['main_block' => $this->makeResponseData()]));
			return;
		}

		// Server returns only basic details for events already known by client-side.
		$this->known_eventids = array_flip($this->getInput('known_eventids', []));
		$this->loadNotifications();

		$this->setResponse(new CControllerResponseData(['main_block' => $this->makeResponseData()]));
	}

	protected function loadNotifications() {
		// Select problem events.
		$options = [
			'output' => ['eventid', 'r_eventid', 'objectid', 'severity', 'clock', 'r_clock', 'name'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'severities' => array_keys($this->settings['triggers.severities']),
			'suppressed' => $this->settings['show_suppressed'] ? null : false,
			'sortorder' => ZBX_SORT_DOWN,
			'sortfield' => 'eventid',
			'limit' => 15,
			'preservekeys' => true
		];

		$options += $this->settings['show_recovered']
			? ['recent' => true]
			: ['time_from' => $this->time_from];

		$events = API::Problem()->get($options);

		// Select latest status for already known events that are no longer available in problems table.
		$other_still_shown_eventids = $this->settings['show_recovered']
			? array_diff(array_keys($this->known_eventids), array_keys($events))
			: [];

		if ($other_still_shown_eventids) {
			$resolved_events = API::Event()->get([
				'output' => ['eventid', 'r_eventid', 'clock', 'severity'],
				'eventids' => $other_still_shown_eventids,
				'sortfield' => 'clock',
				'sortorder' => ZBX_SORT_DOWN,
				'preservekeys' => true
			]);

			$r_eventids = [];

			foreach ($resolved_events as $eventid => $resolved_event) {
				if ($resolved_event['r_eventid'] != 0) {
					$r_eventids[$eventid] = $resolved_event['r_eventid'];
				}
			}

			if ($r_eventids) {
				$r_clocks = API::Event()->get([
					'output' => ['clock'],
					'eventids' => array_values($r_eventids),
					'sortfield' => 'clock',
					'sortorder' => ZBX_SORT_DOWN,
					'preservekeys' => true
				]);

				foreach ($r_eventids as $eventid => &$r_eventid) {
					$resolved_events[$eventid]['r_clock'] = $r_clocks[$r_eventid]['clock'];
				}
				unset($r_eventid);
			}

			$events += $resolved_events;
		}

		// Append selected events to notifications array.
		$problems_by_triggerid = [];

		foreach ($events as $eventid => $event) {
			if ($this->settings['show_recovered']) {
				if (array_key_exists('r_clock', $event) && $event['r_clock'] >= $this->time_from) {
					/*
					 * This happens if trigger is recovered and is already removed from the list of known eventids.
					 * Do nothing here. This statement is needed just to catch specific case before next IF statement.
					 */
				}
				elseif (array_key_exists($event['eventid'], $this->known_eventids)
						&& $event['clock'] < $this->timeout_time) {
					/*
					 * This exception is needed to add notifications that are delayed in front-end, for example, in case
					 * if user has logged in between 30th and 60th second after event was generated. Since notification
					 * is still in response, front-end will remove that message using client side timeout.
					 */
				}
				// Filter by problem start time, because that is not done by API if show_recovered is enabled.
				elseif ($event['clock'] < $this->time_from && !in_array($eventid, $other_still_shown_eventids)) {
					continue;
				}
			}

			// Trigger API is used to select hostname only for notifications that client cannot recover from cache.
			if (!array_key_exists($event['eventid'], $this->known_eventids)) {
				$problems_by_triggerid[$event['objectid']][] = $eventid;
			}

			$this->notifications[$eventid] = [
				'eventid' => $event['eventid'],
				'resolved' => (int) ($event['r_eventid'] != 0),
				'severity' => (int) $event['severity'],
				'clock' => ((int) $event['r_eventid'] == 0) ? $event['clock'] : $event['r_clock'],
				'name' => array_key_exists('name', $event) ? $event['name'] : ''
			];
		}

		// Add additional details newly discovered events.
		if ($problems_by_triggerid) {
			$triggers = API::Trigger()->get([
				'output' => [],
				'selectHosts' => ['hostid', 'name'],
				'triggerids' => array_keys($problems_by_triggerid),
				'lastChangeSince' => $this->time_from,
				'preservekeys' => true
			]);

			foreach ($problems_by_triggerid as $triggerid => $notification_eventids) {
				$trigger = $triggers[$triggerid];

				$url_problems = (new CUrl('zabbix.php'))
					->setArgument('action', 'problem.view')
					->setArgument('filter_name', '')
					->setArgument('hostids[]', $trigger['hosts'][0]['hostid'])
					->getUrl();

				$url_events = (new CUrl('zabbix.php'))
					->setArgument('action', 'problem.view')
					->setArgument('filter_name', '')
					->setArgument('triggerids[]', $triggerid)
					->getUrl();

				$url_trigger_events_pt = (new CUrl('tr_events.php'))->setArgument('triggerid', $triggerid);

				foreach ($notification_eventids as $eventid) {
					$notification = &$this->notifications[$eventid];

					$url_trigger_events = $url_trigger_events_pt
						->setArgument('eventid', $notification['eventid'])
						->getUrl();

					$notification += [
						'title' => sprintf('[url=%s]%s[/url]', $url_problems,
							CHtml::encode($trigger['hosts'][0]['name'])
						),
						'body' => [
							'[url='.$url_events.']'.CHtml::encode($notification['name']).'[/url]',
							'[url='.$url_trigger_events.']'.
								zbx_date2str(DATE_TIME_FORMAT_SECONDS, $notification['clock']).
							'[/url]'
						]
					];
				}
			}
		}

		$this->notifications = array_values($this->notifications);
	}

	protected function makeResponseData() {
		CArrayHelper::sort($this->notifications, [
			['field' => 'clock', 'order' => ZBX_SORT_DOWN],
			['field' => 'severity', 'order' => ZBX_SORT_DOWN],
			['field' => 'eventid', 'order' => ZBX_SORT_DOWN]
		]);

		$this->notifications = array_values($this->notifications);

		foreach ($this->notifications as &$notification) {
			unset($notification['clock']);
			unset($notification['name']);
			if (!array_key_exists('title', $notification)) {
				unset($notification['severity']);
			}
		}
		unset($notification);

		return json_encode([
			'notifications' => $this->notifications,
			'settings' => [
				'enabled' => (bool) $this->settings['enabled'],
				'alarm_timeout' => (int) $this->settings['sounds.repeat'],
				'msg_recovery_timeout' => $this->settings['ok_timeout'],
				'msg_timeout' => $this->settings['timeout'],
				'muted' => (bool) $this->settings['sounds.mute'],
				'severity_styles' => [
					-1 => ZBX_STYLE_NORMAL_BG,
					TRIGGER_SEVERITY_AVERAGE => ZBX_STYLE_AVERAGE_BG,
					TRIGGER_SEVERITY_DISASTER => ZBX_STYLE_DISASTER_BG,
					TRIGGER_SEVERITY_HIGH  => ZBX_STYLE_HIGH_BG,
					TRIGGER_SEVERITY_INFORMATION => ZBX_STYLE_INFO_BG,
					TRIGGER_SEVERITY_NOT_CLASSIFIED => ZBX_STYLE_NA_BG,
					TRIGGER_SEVERITY_WARNING => ZBX_STYLE_WARNING_BG
				],
				'files' => [
					-1 => $this->settings['sounds.recovery'],
					TRIGGER_SEVERITY_AVERAGE => $this->settings['sounds.'.TRIGGER_SEVERITY_AVERAGE],
					TRIGGER_SEVERITY_DISASTER => $this->settings['sounds.'.TRIGGER_SEVERITY_DISASTER],
					TRIGGER_SEVERITY_HIGH => $this->settings['sounds.'.TRIGGER_SEVERITY_HIGH],
					TRIGGER_SEVERITY_INFORMATION => $this->settings['sounds.'.TRIGGER_SEVERITY_INFORMATION],
					TRIGGER_SEVERITY_NOT_CLASSIFIED => $this->settings['sounds.'.TRIGGER_SEVERITY_NOT_CLASSIFIED],
					TRIGGER_SEVERITY_WARNING => $this->settings['sounds.'.TRIGGER_SEVERITY_WARNING]
				]
			]
		]);
	}
}

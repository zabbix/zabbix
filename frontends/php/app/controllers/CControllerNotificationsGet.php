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
		return true;
	}

	protected function checkPermissions() {
		return (!CWebUser::isGuest() && $this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$msg_settings = getMessageSettings();
		$trigger_limit = 15;

		$result = [
			'notifications' => [],
			'listid' => '',
			'settings' => [
				'enabled' => (bool) $msg_settings['enabled'],
				'alarm_timeout' => intval($msg_settings['sounds.repeat']),
				'msg_timeout' => intval($msg_settings['timeout']),
				'muted' => (bool) $msg_settings['sounds.mute'],
				'files' => [
					'-1' => $msg_settings['sounds.recovery'],
					'0' => $msg_settings['sounds.0'],
					'1' => $msg_settings['sounds.1'],
					'2' => $msg_settings['sounds.2'],
					'3' => $msg_settings['sounds.3'],
					'4' => $msg_settings['sounds.4'],
					'5' => $msg_settings['sounds.5']
				]
			]
		];

		if (!$msg_settings['triggers.severities'] || !$msg_settings['enabled']) {
			return $this->setResponse(new CControllerResponseData(['main_block' => json_encode($result)]));
		}

		$timeout = timeUnitToSeconds($msg_settings['timeout']);
		$problems = getLastProblems([
			'time_from'       => max([$msg_settings['last.clock'], time() - $timeout]),
			'show_recovered'  => $msg_settings['triggers.recovery'],
			'show_suppressed' => $msg_settings['show_suppressed'],
			'severities'      => array_keys($msg_settings['triggers.severities']),
			'limit'           => 15
		]);

		$used_triggers = [];
		foreach ($problems as $problem) {
			if (array_key_exists($problem['triggerid'], $used_triggers)) {
				continue;
			}

			$used_triggers[$problem['triggerid']] = true;

			$notification = $this->problemToNotification($problem, $msg_settings);

			$result['listid'] .= $notification['uid'];
			$result['notifications'][] = $notification;
		}

		CArrayHelper::sort($result['notifications'], ['time', 'priority']);

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

		if ($problem['resolved']) {
			$severity = 0;
			$title = _('Resolved');
			$fileid = '-1';
		}
		else {
			$severity = $problem['severity'];
			$title = _('Problem on');
			$fileid = $problem['severity'];
		}

		return [
			'id' => $problem['eventid'],
			'time' => $problem['clock'],
			'resolved' => (int) $problem['resolved'],
			'uid' => sprintf('%d_%d', $problem['eventid'], $problem['resolved']),
			'priority' => $severity,
			'file' => $fileid,
			'severity_style' => getSeverityStyle($severity, !$problem['resolved']),
			'title' => $title . ' [url=' . $url_problems . ']' .
				CHtml::encode($problem['host']['name']) . '[/url]',
			'body' => [
				'[url=' . $url_events . ']' . CHtml::encode($problem['description']) . '[/url]',
				'[url=' . $url_tr_events . ']' .
					zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']) . '[/url]',
			],
			'timeout' => $msg_settings['timeout']
		];
	}
}

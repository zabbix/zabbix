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


class CControllerMenuPopup extends CController {

	protected function checkInput() {
		$fields = [
			'type' => 'required|in history,host,item,item_prototype,trigger,triggerMacro',
			'data' => 'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$data = $this->hasInput('data') ? $this->getInput('data') : [];

		$output = [];

		switch ($this->getInput('type')) {
			case 'history':
				$items = API::Item()->get([
					'output' => ['value_type'],
					'itemids' => $data['itemid'],
					'webitems' => true
				]);

				if ($items) {
					$value_type = $items[0]['value_type'];

					$output['data'] = [
						'type' => 'history',
						'itemid' => $data['itemid'],
						'hasLatestGraphs' => in_array($value_type, [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT])
					];
				}
				else {
					error(_('No permissions to referred object or it does not exist!'));
				}
				break;

			case 'host':
				$has_goto = !array_key_exists('has_goto', $data) || $data['has_goto'];

				$hosts = $has_goto
					? API::Host()->get([
						'output' => ['hostid', 'status'],
						'selectGraphs' => API_OUTPUT_COUNT,
						'selectScreens' => API_OUTPUT_COUNT,
						'hostids' => $data['hostid']
					])
					: API::Host()->get([
						'output' => ['hostid'],
						'hostids' => $data['hostid']
					]);

				if ($hosts) {
					$host = $hosts[0];

					$scripts = API::Script()->getScriptsByHosts([$data['hostid']])[$data['hostid']];

					$output['data'] = [
						'type' => 'host',
						'hostid' => $host['hostid'],
						'hasGoTo' => (bool) $has_goto
					];

					if ($has_goto) {
						$output['data']['showGraphs'] = (bool) $host['graphs'];
						$output['data']['showScreens'] = (bool) $host['screens'];
						$output['data']['showTriggers'] = ($host['status'] == HOST_STATUS_MONITORED);
					}

					foreach ($scripts as &$script) {
						$script['name'] = trimPath($script['name']);
					}
					unset($script);

					CArrayHelper::sort($scripts, ['name']);

					foreach (array_values($scripts) as $script) {
						$output['data']['scripts'][] = [
							'name' => $script['name'],
							'scriptid' => $script['scriptid'],
							'confirmation' => $script['confirmation']
						];
					}
				}
				else {
					error(_('No permissions to referred object or it does not exist!'));
				}
				break;

			case 'item':
				$items = API::Item()->get([
					'output' => ['hostid', 'name', 'value_type'],
					'itemids' => $data['itemid'],
					'webitems' => true
				]);

				if ($items) {
					$item = $items[0];
					$triggers = [];

					if (in_array($item['value_type'],
							[ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_TEXT])) {
						$db_triggers = API::Trigger()->get([
							'output' => ['triggerid', 'description', 'recovery_mode'],
							'selectFunctions' => API_OUTPUT_EXTEND,
							'itemids' => $data['itemid']
						]);

						foreach ($db_triggers as $db_trigger) {
							if ($db_trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
								continue;
							}

							foreach ($db_trigger['functions'] as $function) {
								if (!str_in_array($function['function'], ['regexp', 'iregexp'])) {
									continue 2;
								}
							}

							$triggers[] = [
								'triggerid' => $db_trigger['triggerid'],
								'name' => $db_trigger['description']
							];
						}
					}

					$output['data'] = [
						'type' => 'item',
						'itemid' => $data['itemid'],
						'hostid' => $item['hostid'],
						'name' => $item['name'],
						'triggers' => $triggers
					];
				}
				else {
					error(_('No permissions to referred object or it does not exist!'));
				}
				break;

			case 'item_prototype':
				$item_prototypes = API::ItemPrototype()->get([
					'output' => ['name'],
					'selectDiscoveryRule' => ['itemid'],
					'itemids' => $data['itemid']
				]);

				if ($item_prototypes) {
					$item_prototype = $item_prototypes[0];

					$output['data'] = [
						'type' => 'item_prototype',
						'itemid' => $data['itemid'],
						'name' => $item_prototype['name'],
						'parent_discoveryid' => $item_prototype['discoveryRule']['itemid']
					];
				}
				else {
					error(_('No permissions to referred object or it does not exist!'));
				}
				break;

			case 'trigger':
				$triggers = API::Trigger()->get([
					'output' => ['triggerid', 'expression', 'url', 'flags', 'comments'],
					'selectHosts' => ['hostid', 'name', 'status'],
					'selectItems' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
					'triggerids' => $data['triggerid']
				]);

				if ($triggers) {
					$triggers = CMacrosResolverHelper::resolveTriggerUrls($triggers);

					$trigger = $triggers[0];
					$trigger['items'] = CMacrosResolverHelper::resolveItemNames($trigger['items']);

					$hosts = [];
					$showEvents = true;

					foreach ($trigger['hosts'] as $host) {
						$hosts[$host['hostid']] = $host['name'];

						if ($host['status'] != HOST_STATUS_MONITORED) {
							$showEvents = false;
						}
					}

					foreach ($trigger['items'] as &$item) {
						$item['hostname'] = $hosts[$item['hostid']];
					}
					unset($item);

					CArrayHelper::sort($trigger['items'], ['name', 'hostname', 'itemid']);

					$hostCount = count($hosts);
					$items = [];

					foreach ($trigger['items'] as $item) {
						$items[] = [
							'name' => ($hostCount > 1)
								? $hosts[$item['hostid']].NAME_DELIMITER.$item['name_expanded']
								: $item['name_expanded'],
							'params' => [
								'itemid' => $item['itemid'],
								'action' =>
									in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])
										? HISTORY_GRAPH
										: HISTORY_VALUES
							]
						];
					}

					$options = [
						'show_description' => !array_key_exists('show_description', $data) || $data['show_description']
					];

					if ($options['show_description']) {
						$rw_triggers = API::Trigger()->get([
							'output' => [],
							'triggerids' => $trigger['triggerid'],
							'editable' => true
						]);

						$editable = (bool) $rw_triggers;
						$options['description_enabled'] = ($trigger['comments'] !== ''
							|| ($editable && $trigger['flags'] == ZBX_FLAG_DISCOVERY_NORMAL));
					}

					$acknowledge = array_key_exists('acknowledge', $data) ? $data['acknowledge'] : [];

					$output['data'] = [
						'type' => 'trigger',
						'triggerid' => $trigger['triggerid'],
						'items' => $items,
						'showEvents' => $showEvents,
						'configuration' =>
							in_array(CWebUser::$data['type'], [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])
					];

					if (array_key_exists('show_description', $options) && $options['show_description'] === false) {
						$output['data']['show_description'] = false;
					}
					else if (array_key_exists('description_enabled', $options)
							&& $options['description_enabled'] === false) {
						$output['data']['description_enabled'] = false;
					}

					if ($acknowledge !== null) {
						$output['data']['acknowledge'] = $acknowledge;
					}

					if ($trigger['url'] !== '') {
						$output['data']['url'] = CHtmlUrlValidator::validate($trigger['url'])
							? $trigger['url']
							: 'javascript: alert(\''._s('Provided URL "%1$s" is invalid.', zbx_jsvalue($trigger['url'],
									false, false)).'\');';
					}
				}
				else {
					error(_('No permissions to referred object or it does not exist!'));
				}
				break;

			case 'triggerMacro':
				$output['data'] = ['type' => 'triggerMacro'];
				break;
		}

		if (($messages = getMessages()) !== null) {
			$output['errors'] = $messages->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]));
	}
}

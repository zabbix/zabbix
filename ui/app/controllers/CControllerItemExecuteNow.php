<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CControllerItemExecuteNow extends CController {

	/*
	 * @var array  List of selected items from API stored in this item cache variable.
	 */
	private $item_cache = [];

	/**
	 * @var bool  Whether the request is for items (false) or LLD rules (true).
	 */
	private $is_discovery_rule;

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'itemids' => 'required|array_db items.itemid',
			'discovery_rule' => 'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => CMessageHelper::getTitle(),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		$this->is_discovery_rule = (bool) $this->getInput('discovery_rule', 0);
		$min_user_type = $this->is_discovery_rule ? USER_TYPE_ZABBIX_ADMIN : USER_TYPE_ZABBIX_USER;

		return ($this->getUserType() >= $min_user_type);
	}

	protected function doAction(): void {
		$output = [];

		// List of item IDs that are coming from input (later overwritten).
		$itemids = $this->getInput('itemids');

		// Error message details.
		$errors = [];

		// Find items or LLD rules.
		if ($this->is_discovery_rule) {
			$items = API::DiscoveryRule()->get([
				'output' => ['type', 'name', 'status', 'flags', 'master_itemid'],
				'selectHosts' => ['name', 'status'],
				'itemids' => $itemids,
				'editable' => !$this->checkAccess(CRoleHelper::ACTIONS_INVOKE_EXECUTE_NOW),
				'preservekeys' => true
			]);
		}
		else {
			$items = CArrayHelper::renameObjectsKeys(API::Item()->get([
				'output' => ['type', 'name_resolved', 'status', 'flags', 'master_itemid'],
				'selectHosts' => ['name', 'status'],
				'itemids' => $itemids,
				'editable' => !$this->checkAccess(CRoleHelper::ACTIONS_INVOKE_EXECUTE_NOW),
				'webitems' => true,
				'preservekeys' => true
			]), ['name_resolved' => 'name']);
		}

		if ($items) {
			/*
			 * If some items were not found (deleted or web items) or user may not have permissions, set error message.
			 * In case of partial success, error message will not be visible, but in case there are no items to create
			 * tasks for, error message will be visible.
			 */
			if (count($itemids) != count($items)) {
				$errors = [_('No permissions to referred object or it does not exist!')];
			}

			// Get all allowed item types including dependent items, which will be processed separately.
			$allowed_types = checkNowAllowedTypes();

			// Collects master item IDs if some or all items are dependent items.
			$master_itemids = [];

			// Item IDs that are given to task.create API method. These are all top level item IDs.
			$itemids = [];

			foreach ($items as $itemid => $item) {
				if (!in_array($item['type'], $allowed_types)) {
					// In case items (dependent or master) are not allowed, store the error message.
					if (!$errors) {
						// If no errors exist yet, set the first error message.
						$msg_part = ($item['flags'] == ZBX_FLAG_DISCOVERY_RULE)
							? _('wrong discovery rule type')
							: _('wrong item type');
						$errors = [_s('Cannot send request: %1$s.', $msg_part)];
					}

					// Stop processing this item, since it is not valid.
					continue;
				}

				if ($item['status'] != ITEM_STATUS_ACTIVE || $item['hosts'][0]['status'] != HOST_STATUS_MONITORED) {
					// In case items (dependent or master) or host is not monitored, store the error message.
					$host_name = $item['hosts'][0]['name'];

					if (!$errors) {
						// If no errors exist yet, set the first error message.
						$msg_part = ($item['flags'] == ZBX_FLAG_DISCOVERY_RULE)
							? _s('discovery rule "%1$s" on host "%2$s" is not monitored', $item['name'], $host_name)
							: _s('item "%1$s" on host "%2$s" is not monitored', $item['name'], $host_name);
						$errors = [_s('Cannot send request: %1$s.', $msg_part)];
					}

					// Stop processing this item, since it is not valid.
					continue;
				}

				if ($item['type'] == ITEM_TYPE_DEPENDENT) {
					// So far it is not known if master items are allowed or not. Collect IDs to process them later.
					$master_itemids[$item['master_itemid']] = true;
				}
				else {
					// These item IDs are top level IDs and will be passed to task.create method.
					$itemids[$itemid] = true;
				}
			}

			/*
			 * If all or some dependent items are found, find master items. Keep looping till all dependent items are
			 * processed.
			 */
			if ($master_itemids) {
				// Store already found items in item cache.
				$this->item_cache = $items;

				while ($master_itemids) {
					// Get already known items from cache or DB.
					$master_items = $this->getMasterItems(array_keys($master_itemids));

					// Reset master item IDs, so this loop will eventually end.
					$master_itemids = [];

					foreach ($master_items as $itemid => $item) {
						if (!in_array($item['type'], $allowed_types)) {
							// In case parent item (dependent or master) is not allowed, store the error message.

							if (!$errors) {
								// If no errors exist yet, set the first error message.
								$errors = [_s('Cannot send request: %1$s.', _('wrong master item type'))];
							}

							// Stop processing this item, since it is not valid.
							continue;
						}

						if ($item['status'] != ITEM_STATUS_ACTIVE
								|| $item['hosts'][0]['status'] != HOST_STATUS_MONITORED) {
							// In case items (dependent or master) or host is not monitored, store the error message.
							if (!$errors) {
								// If no errors exist yet, set the first error message.
								$errors = [_s('Cannot send request: %1$s.', _s(
									'item "%1$s" on host "%2$s" is not monitored', $item['name'],
									$item['hosts'][0]['name']
								))];
							}

							// Stop processing this item, since it is not valid.
							continue;
						}

						if ($item['type'] == ITEM_TYPE_DEPENDENT) {
							// Again some dependent items found. Keep looping.
							$master_itemids[$item['master_itemid']] = true;
						}
						else {
							// These item IDs are top level IDs and will be passed to task.create method.
							$itemids[$itemid] = true;
						}
					}
				}
			}
		}
		else {
			// User has no permissions to any of the selected items or they are deleted or web items.
			$errors = [_('No permissions to referred object or it does not exist!')];
		}

		if ($itemids) {
			// If all or some items were valid, create tasks.
			$create_tasks = [];
			$itemids = array_keys($itemids);

			foreach ($itemids as $itemid) {
				$create_tasks[] = [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => $itemid
					]
				];
			}

			$result = (bool) API::Task()->create($create_tasks);

			if ($result) {
				// If tasks were created, return either partial success result or full success result.
				$output['success'] = ['title' => $errors
					? _('Request sent successfully. Some items are filtered due to access permissions or type.')
					: _('Request sent successfully')
				];
			}
			else {
				// If task API failed, return with full error message from API.
				$output['error'] = [
					'title' => _('Cannot execute operation'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				];
			}
		}
		else {
			// No items left to send to task.create. Returns a full error message that was set before.
			$output['error'] = [
				'title' => _('Cannot execute operation'),
				'messages' => $errors
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	/**
	 * Find master items by given item IDs either stored in cache or DB. Returns the item found. Discovery rules
	 * cannot depend on other discovery rules, so only regular items are requested.
	 *
	 * @param array $itemids  An array of master item IDs.
	 *
	 * @return array
	 */
	private function getMasterItems(array $itemids): array {
		$items = [];

		// First try get items from cache if possible.
		foreach ($itemids as $num => $itemid) {
			if (array_key_exists($itemid, $this->item_cache)) {
				$item = $this->item_cache[$itemid];
				$items[$itemid] = $item;
				unset($itemids[$num]);
			}
		}

		// If some items were not found in cache, select them from DB.
		if ($itemids) {
			$items = CArrayHelper::renameObjectsKeys(API::Item()->get([
				'output' => ['type', 'name_resolved', 'status', 'master_itemid'],
				'selectHosts' => ['name', 'status'],
				'itemids' => $itemids,
				'editable' => !$this->checkAccess(CRoleHelper::ACTIONS_INVOKE_EXECUTE_NOW),
				'webitems' => true,
				'preservekeys' => true
			]), ['name_resolved' => 'name']);

			/*
			 * Master item could be removed during the process, which means dependent item is also removed. If that is
			 * the case, then this whole function will not execute and it is safe to pass the result to item_cache,
			 * since there will always be items returned.
			 */

			// Add newly found items to cache.
			$this->item_cache += $items;
		}

		// Return only requested items either from cache or DB.
		return $items;
	}
}

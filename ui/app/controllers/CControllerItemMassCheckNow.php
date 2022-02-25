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


class CControllerItemMassCheckNow extends CController {

	/*
	 * @var array  List of selected items from API stored in this item cache variable.
	 */
	private $item_cache = [];

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
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction(): void {
		$output = [];
		$itemids = $this->getInput('itemids');

		// Item intial count from the input IDs.
		$items_cnt = count($itemids);

		// True if request comes from LLD rule list view.
		$is_discovery_rule = (bool) $this->getInput('discovery_rule', 0);

		// Error message details.
		$details = [];

		// True if result was successful or partially successful.
		$result = true;

		// True no errors occurred and result was only partially successful.
		$partial_success = false;

		// Find items or LLD rules.
		if ($is_discovery_rule) {
			$items = API::DiscoveryRule()->get([
				'output' => ['type', 'name', 'status', 'flags', 'master_itemid'],
				'selectHosts' => ['name', 'status'],
				'itemids' => $itemids,
				'editable' => !$this->checkAccess(CRoleHelper::ACTIONS_INVOKE_EXECUTE_NOW),
				'preservekeys' => true
			]);
		}
		else {
			$items = API::Item()->get([
				'output' => ['type', 'name', 'status', 'flags', 'master_itemid'],
				'selectHosts' => ['name', 'status'],
				'itemids' => $itemids,
				'editable' => !$this->checkAccess(CRoleHelper::ACTIONS_INVOKE_EXECUTE_NOW),
				'preservekeys' => true
			]);
		}

		if ($items) {
			// Some or all items are found and user has permissions to some of the items.

			// Not all given item IDs exist. Some non-existent items are filtered out already.
			if ($items_cnt != count($items)) {
				$partial_success = true;
			}

			// Reset item count to only items that are found.
			$items_cnt = count($items);

			// Get all allowed item types including dependent items, which will be processed separately.
			$allowed_types = checkNowAllowedTypes();

			// Collects master item IDs if some or all items are dependent items.
			$master_itemids = [];

			// Item IDs that are given to task.create API method. These are all top level item IDs.
			$itemids = [];

			// List if items that are not allowed by type. This does not include dependent items.
			$non_allowed_items_type = [];

			// List if items that are allowed by type, but are not monitored.
			$non_monitored_items = [];

			// Structure the initial top level items and collect master item IDs.
			foreach ($items as $itemid => $item) {
				if ($item['type'] == ITEM_TYPE_DEPENDENT) {
					// So far it is not known if master items are allowed or not. Collect IDs to process them later.
					$master_itemids[$item['master_itemid']] = true;
				}
				else {
					// Gather all other non-dependent items.
					if (in_array($item['type'], $allowed_types)) {
						if ($item['status'] == ITEM_STATUS_ACTIVE
								&& $item['hosts'][0]['status'] == HOST_STATUS_MONITORED) {
							// Collect allowed and monitored item IDs.
							$itemids[$itemid] = true;
						}
						else {
							// If top level item is not monitored, store it to later show error message.
							$non_monitored_items[$itemid] = $item;
						}
					}
					else {
						// Add a custom flag to pass on for error message. Will show non-master item error message.
						$item['master'] = false;

						// If top level item is not allowed by type, store it to later show error message.
						$non_allowed_items_type[$itemid] = $item;
					}
				}
			}

			// If all or some dependent items are found.
			if ($master_itemids) {
				// Store already found items in item cache.
				$this->item_cache = $items;

				while ($master_itemids) {
					// Get already known items from cache or DB.
					$master_items = $this->getMasterItems(array_keys($master_itemids));
					/*
					 * There is no need for additional check to see if master items exist. Master items must exist.
					 * If they do not exist, so does the dependent. In that case this code block will not execute,
					 * because the dependet item IDs, that do not exist, cannot be here in the first place.
					 */

					// Reset master item IDs, so this loop will eventually end.
					$master_itemids = [];

					// Now check the master items if they are allowed and monitored.
					foreach ($master_items as $itemid => $item) {
						if ($item['type'] == ITEM_TYPE_DEPENDENT) {
							// Again some dependent items found. Keep looping.
							$master_itemids[$item['master_itemid']] = true;
						}
						else {
							// Check non-dependent master items.
							if (in_array($item['type'], $allowed_types)) {
								if ($item['status'] == ITEM_STATUS_ACTIVE
										&& $item['hosts'][0]['status'] == HOST_STATUS_MONITORED) {
									// Collect allowed and monitored item IDs.
									$itemids[$itemid] = true;
								}
								else {
									// If master item is not monitored, store it to later show error message.
									$non_monitored_items[$itemid] = $item;
								}
							}
							else {
								// Add a custom flag to pass on for error message. Will show master item error message.
								$item['master'] = true;

								// If master item is not allowed by type, store it to later show error message.
								$non_allowed_items_type[$itemid] = $item;
							}
						}
					}
				}
			}

			// Reset item count to only master items that are found. All allowed and non-allowed.
			if ($this->item_cache) {
				// If there are dependent items, include these as well.
				$items = array_filter($this->item_cache, function($item) {
					if ($item['type'] != ITEM_TYPE_DEPENDENT) {
						return $item;
					}
				});
			}
			$items_cnt = count($items);

			if ($non_monitored_items) {
				// Some or all non-monitored items found.

				if ($items_cnt == count($non_monitored_items)
						|| count($non_allowed_items_type) + count($non_monitored_items) == $items_cnt) {
					// Either all of the found items are not monitored or they are both not monitored and not allowed.

					// This case will result in error.
					$result = false;

					// Get first non-monitored item. All of them are not monitored. So it does not matter which one.
					$item = reset($non_monitored_items);
					$host_name = $item['hosts'][0]['name'];

					// Set the error message details.
					$msg_part = ($item['flags'] == ZBX_FLAG_DISCOVERY_RULE)
						? _s('discovery rule "%1$s" on host "%2$s" is not monitored', $item['name'], $host_name)
						: _s('item "%1$s" on host "%2$s" is not monitored', $item['name'], $host_name);
					$details = [_s('Cannot send request: %1$s.', $msg_part)];
				}
				elseif ($items_cnt == count($non_allowed_items_type)) {
					// All found items are not allowed by type, but all are monitored. Shows different error message.

					// This case will result in error.
					$result = false;

					// Get first non-allowed item. All of them are not allowed. So it does not matter which one.
					$item = reset($non_allowed_items_type);

					// Set the error message details.
					$msg_part = ($item['flags'] == ZBX_FLAG_DISCOVERY_RULE)
						? _('wrong discovery rule type')
						: ($item['master'] ? _('wrong master item type') : _('wrong item type'));
					$details = [_s('Cannot send request: %1$s.', $msg_part)];
				}
				else {
					// Some items found are not allowed or some are not monitored. Some are allowed and monitored.

					$partial_success = true;
				}
			}
			elseif ($non_allowed_items_type) {
				if ($items_cnt == count($non_allowed_items_type)) {
					// All found items are not allowed by type, but all of them are monitored.

					// This case will result in error.
					$result = false;

					// Get first non-allowed item. All of them are not allowed. So it does not matter which one.
					$item = reset($non_allowed_items_type);

					// Set the error message details.
					$msg_part = ($item['flags'] == ZBX_FLAG_DISCOVERY_RULE)
						? _('wrong discovery rule type')
						: ($item['master'] ? _('wrong master item type') : _('wrong item type'));
					$details = [_s('Cannot send request: %1$s.', $msg_part)];
				}
				else {
					// Some found items are not allowed.

					$partial_success = true;
				}
			}
		}
		else {
			// User has no permissions to any of the selected items or they are deleted.

			$result = false;
			$details = [_('No permissions to referred object or it does not exist!')];
		}

		if ($result && $itemids) {
			// If ther are no errors, create tasks for these item IDs. These items exist are all valid.
			$create_tasks = [];
			$itemids = array_keys($itemids);

			foreach ($itemids as $itemid) {
				$create_tasks[] = [
					'type' => ZBX_TM_DATA_TYPE_CHECK_NOW,
					'request' => [
						'itemid' => $itemid
					]
				];
			}

			$result = (bool) API::Task()->create($create_tasks);
			if (!$result) {
				// In case something still goes wrong in API, get the error message and store it in details.
				$details = array_column(get_and_clear_messages(), 'message');
			}
		}

		// Prepare the output message.
		if ($result) {
			$output['success'] = ['title' => $partial_success
				? _('Request sent successfully. Some items are filtered due to access permissions or type.')
				: _('Request sent successfully')
			];
		}
		else {
			$output['error'] = [
				'title' => _('Cannot execute operation'),
				'messages' => $details
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
			$items = API::Item()->get([
				'output' => ['type', 'name', 'status', 'flags', 'master_itemid'],
				'selectHosts' => ['name', 'status'],
				'itemids' => $itemids,
				'editable' => !$this->checkAccess(CRoleHelper::ACTIONS_INVOKE_EXECUTE_NOW),
				'preservekeys' => true
			]);

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

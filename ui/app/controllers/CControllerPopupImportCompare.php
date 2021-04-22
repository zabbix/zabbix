<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CControllerPopupImportCompare extends CController {

	public const CHANGE_NONE = 0;
	public const CHANGE_ADDED = 1;
	public const CHANGE_REMOVED = 2;
	public const CHANGE_UPDATED = 3;

	private $toc = [];
	private $toc_id = 0;

	protected function checkInput() {
		$fields = [
			'import' => 'in 1',
			'rules_preset' => 'in template',
			'rules' => 'array',
			'parent_overlayid' => 'required|string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		$user_type = $this->getUserType();

		switch ($this->getInput('rules_preset', '')) {
			case 'template':
				return ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN);

			default:
				return false;
		}
	}

	protected function doAction() {
		// TODO VM: (?) Do I merge this somehow with CControllerPopupImport? I would prefer not to, as they are similar, but stil different.

		$rules = [
			'groups' => ['updateExisting' => false, 'createMissing' => false],
			'hosts' => ['updateExisting' => false, 'createMissing' => false],
			'templates' => ['updateExisting' => false, 'createMissing' => false],
			'templateDashboards' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'templateLinkage' => ['createMissing' => false, 'deleteMissing' => false],
			'items' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'discoveryRules' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'triggers' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'graphs' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'httptests' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'maps' => ['updateExisting' => false, 'createMissing' => false],
			'images' => ['updateExisting' => false, 'createMissing' => false],
			'mediaTypes' => ['updateExisting' => false, 'createMissing' => false],
			'valueMaps' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false]
		];

		// Adjust defaults for given rule preset, if specified.
		switch ($this->getInput('rules_preset')) {
			case 'template':
				$rules['groups'] = ['updateExisting' => true, 'createMissing' => true];
				$rules['templates'] = ['updateExisting' => true, 'createMissing' => true];
				$rules['templateDashboards'] = ['updateExisting' => true, 'createMissing' => true,
					'deleteMissing' => false
				];
				$rules['items'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false];
				$rules['discoveryRules'] = ['updateExisting' => true, 'createMissing' => true,
					'deleteMissing' => false
				];
				$rules['triggers'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false];
				$rules['graphs'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false];
				$rules['httptests'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false];
				$rules['templateLinkage'] = ['createMissing' => true, 'deleteMissing' => false];
				$rules['valueMaps'] = ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => false];
				break;
		}

		$request_rules = array_intersect_key($this->getInput('rules', []), $rules);
		$request_rules += array_fill_keys(array_keys($rules), []);
		$options = array_fill_keys(['updateExisting', 'createMissing', 'deleteMissing'], false);

		foreach ($request_rules as $rule_name => &$rule) {
			$rule = array_map('boolval', array_intersect_key($rule + $options, $rules[$rule_name]));
		}
		unset($rule);

		$result = false;

		if (!isset($_FILES['import_file'])) {
			error(_('No file was uploaded.'));
		} else {
			// CUploadFile throws exceptions, so we need to catch them
			try {
				$file = new CUploadFile($_FILES['import_file']);

				$result = API::Configuration()->importcompare([
					'format' => CImportReaderFactory::fileExt2ImportFormat($file->getExtension()),
					'source' => $file->getContent(),
					'rules' => $request_rules
				]);
			}
			catch (Exception $e) {
				error($e->getMessage());
			}
		}

		$data = [
			'title' => _('Templates'),
			'errors' => null,
			'parent_overlayid' => $this->getInput('parent_overlayid'),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		if (!$result) {
			CMessageHelper::setErrorTitle(_('Import failed'));
			$data['errors'] = makeMessageBox(false, filter_messages(), CMessageHelper::getTitle())->toString();
		}
		else {
			$data['diff'] = $this->blocksToDiff($result, 1);
			$data['diff_toc'] = $this->normalizeToc($this->toc);
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	protected function normalizeToc($toc) {
		// Order TOC.
		$toc = array_replace(array_flip(['removed', 'updated', 'added']), $toc);
		$new_toc = [];

		$toc_keys = [
			'removed' => _('Removed'),
			'updated' => _('Updated'),
			'added' => _('Added')
		];
		$names = [
			'groups' => _('Groups'),
			'templates' => _('Templates'),
			'triggers' => _('Triggers'),
			'graphs' => _('Graphs'),
			'items' => _('Items'),
			'discovery_rules' => _('Discovery rules'),
			'dashboards' => _('Dashboards'),
			'httptests' => _('Web scenarios'),
			'valuemaps' => _('Value mappings'),
			'item_prototypes' => _('Item prototypes'),
			'trigger_prototypes' => _('Trigger prototypes'),
			'graph_prototypes' => _('Graph prototypes'),
			'host_prototypes' => _('Host prototypes')
		];

		foreach ($toc as $toc_key => $changes) {
			if (!is_array($changes)) {
				continue;
			}

			$new_changes = [];
			foreach ($changes as $key => $values) {
				$new_key = $names[$key];
				$new_changes[$new_key] = $values;
			}
			$new_toc_key = $toc_keys[$toc_key];
			$new_toc[$new_toc_key] = $new_changes;
		}

		return $new_toc;
	}

	/**
	 * Show exactly which array elements were added/removed/updated. Only on first depth level.
	 *
	 * @param string $key
	 * @param array $before
	 * @param array $after
	 * @param int $depth
	 *
	 * @return array
	 */
	protected function arrayToRows($key, $before, $after, $depth): array {
		$rows = [];
		$rows[] = [
			'value' => $key . ':',
			'depth' => $depth,
			'change_type' => CControllerPopupImportCompare::CHANGE_NONE
		];

		$is_hash = CArrayHelper::isHash($before) || CArrayHelper::isHash($after);

		// Make sure, order changes are also taken into account.
		// TODO VM: (?) It may look nicer, if longest chains of unchanged entries will be searched, but it will make it even more complex.
		$unchanged_map = [];
		$before_keys = array_keys($before);
		$after_keys = array_keys($after);
		$last_after_index = -1;

		for ($i = 0; $i < count($before_keys); $i++) {
			$before_key = $before_keys[$i];

			for ($j = $last_after_index + 1; $j < count($after_keys); $j++) {
				$after_key = $after_keys[$j];

				if ($is_hash) {
					if ($before_key === $after_key && $before[$before_key] === $after[$after_key]) {
						$unchanged_map[$before_key] = $after_key;
						$last_after_index = $j;
					}
				}
				else {
					if ($before[$before_key] === $after[$after_key]) {
						$unchanged_map[$before_key] = $after_key;
						$last_after_index = $j;
					}
				}
			}
		}
		unset($before_key, $after_key, $last_after_index);

		$unchanged_before_keys = array_keys($unchanged_map);
		$next_unchanged_before_index = 0;
		$next_after_index = 0;

		foreach ($before_keys as $before_key) {
			if (array_key_exists($next_unchanged_before_index, $unchanged_before_keys)
					&& $before_key === $unchanged_before_keys[$next_unchanged_before_index]) {
				// Show all added after entries
				while (array_key_exists($next_after_index, $after_keys)
						&& $after_keys[$next_after_index] !== $unchanged_map[$before_key]) {
					$after_key = $after_keys[$next_after_index];
					$yaml_key = $this->prepareYamlKey($after_key, $is_hash);
					$rows[] = [
						'value' => $this->convertToYaml([$yaml_key => $after[$after_key]]),
						'depth' => $depth + 1,
						'change_type' => CControllerPopupImportCompare::CHANGE_ADDED
					];
					$next_after_index++;
				}

				// Show unchanged entry
				$yaml_key = $this->prepareYamlKey($before_key, $is_hash);
				$rows[] = [
					'value' => $this->convertToYaml([$yaml_key => $before[$before_key]]),
					'depth' => $depth + 1,
					'change_type' => CControllerPopupImportCompare::CHANGE_NONE
				];
				$next_unchanged_before_index++;
				$next_after_index++;
			}
			else {
				// Show all removed before entries
				$yaml_key = $this->prepareYamlKey($before_key, $is_hash);
				$rows[] = [
					'value' => $this->convertToYaml([$yaml_key => $before[$before_key]]),
					'depth' => $depth + 1,
					'change_type' => CControllerPopupImportCompare::CHANGE_REMOVED
				];
			}
		}

		// Show remaining after entries
		for ($after_index = $next_after_index; $after_index < count($after_keys); $after_index++) {
			$after_key = $after_keys[$after_index];
			$yaml_key = $this->prepareYamlKey($after_key, $is_hash);
			$rows[] = [
				'value' => $this->convertToYaml([$yaml_key => $after[$after_key]]),
				'depth' => $depth + 1,
				'change_type' => CControllerPopupImportCompare::CHANGE_ADDED
			];
		}

		return $rows;
	}

	/**
	 * Prepares key, with which each array element will be passed to YAML converter. Makes sure the key will be
	 * properly converted.
	 *
	 * @param mixed $key
	 * @param boolean $is_hash
	 *
	 * @return mixed
	 */
	protected function prepareYamlKey ($key, $is_hash) {
		if ($is_hash) {
			// Make sure, array with "zero" key element is not considered as indexed array in YAML converter.
			// \xE2\x80\x8B is UTF-8 code for zero width space.
			// TOOD VM: (?) really borderline case, as I don;t expect key "0" in non hash array, but it could be possible. May be a problem, if someone will copy this from popup.
			$yaml_key = ($key === 0) ? "\xE2\x80\x8B".'0' : $key;
		}
		else {
			// For indexed array any index should be replaced by "-" in YAML converter.
			// Passing single-entry array with '0' key, does exactly that.
			$yaml_key = 0;
		}

		return $yaml_key;
	}

	protected function objectToRows($before, $after, $depth, $id) {
		if ($before && $after) {
			$outer_change_type = CControllerPopupImportCompare::CHANGE_NONE;
		}
		else if ($before) {
			$outer_change_type = CControllerPopupImportCompare::CHANGE_REMOVED;
		}
		else if ($after) {
			$outer_change_type = CControllerPopupImportCompare::CHANGE_ADDED;
		}
		else {
			$outer_change_type = CControllerPopupImportCompare::CHANGE_NONE;
		}

		$rows = [];
		$rows[] = [
			'value' => '-',
			'depth' => $depth,
			'change_type' => $outer_change_type,
			'id' => $id
		];

		$all_keys = [];

		foreach (array_keys($before) as $key) {
			if (array_key_exists($key, $after)) {
				if ($before[$key] == $after[$key]) {
					$all_keys[$key] = 'no_change';
				}
				else if (is_array($before[$key])) {
					$all_keys[$key] = 'updated_array';
				}
				else {
					$all_keys[$key] = 'updated';
				}
			}
			else {
				$all_keys[$key] = 'removed';
			}
		}

		foreach (array_keys($after) as $key) {
			if (!array_key_exists($key, $before)) {
				$all_keys[$key] = 'added';
			}
		}

		// TODO VM: don't show UUID (after testing)
//		unset($all_keys['uuid']);

		foreach ($all_keys as $key => $change_type) {
			switch ($change_type) {
				case 'no_change':
					$rows[] = [
						'value' => $this->convertToYaml([$key => $before[$key]]),
						'depth' => $depth + 1,
						'change_type' => CControllerPopupImportCompare::CHANGE_NONE
					];

					break;

				case 'updated':
					$rows[] = [
						'value' => $this->convertToYaml([$key => $before[$key]]),
						'depth' => $depth + 1,
						'change_type' => CControllerPopupImportCompare::CHANGE_REMOVED
					];
					$rows[] = [
						'value' => $this->convertToYaml([$key => $after[$key]]),
						'depth' => $depth + 1,
						'change_type' => CControllerPopupImportCompare::CHANGE_ADDED
					];

					break;

				case 'removed':
					$rows[] = [
						'value' => $this->convertToYaml([$key => $before[$key]]),
						'depth' => $depth + 1,
						'change_type' => CControllerPopupImportCompare::CHANGE_REMOVED
					];

					break;

				case 'added':
					$rows[] = [
						'value' => $this->convertToYaml([$key => $after[$key]]),
						'depth' => $depth + 1,
						'change_type' => CControllerPopupImportCompare::CHANGE_ADDED
					];

					break;

				case 'updated_array':
					$rows = array_merge($rows, $this->arrayToRows($key, $before[$key], $after[$key], $depth + 1));

					break;
			}
		}

		return $rows;
	}

	protected function blocksToDiff($blocks, $depth, $outer_change_type = 'updated') {
		$change_types = [
			'added' => CControllerPopupImportCompare::CHANGE_ADDED,
			'removed' => CControllerPopupImportCompare::CHANGE_REMOVED,
			'updated' => CControllerPopupImportCompare::CHANGE_NONE,
		];

		$rows = [];
		foreach ($blocks as $entity_type => $changes) {
			$rows[] = [
				'value' => $entity_type . ':',
				'depth' => $depth,
				'change_type' => $change_types[$outer_change_type]
			];

			foreach ($changes as $change_type => $entities) {
				foreach ($entities as $entity) {
					$before = array_key_exists('before', $entity) ? $entity['before'] : [];
					$after = array_key_exists('after', $entity) ? $entity['after'] : [];
					$object = $before ? $before : $after;
					unset($entity['before'], $entity['after']);

					// TODO VM: (?) while we are sure to setup each such, as it has UUID, this precaution is not needed.
					$id = array_key_exists('uuid', $object) ? $object['uuid'] : $this->toc_id++;
					// TODO VM: (?) it might be bad idea to relay on every entity having 'name', even if currently it is true.
					$this->toc[$change_type][$entity_type][] = [
						'name' => array_key_exists('name', $object) ? $object['name'] : 'Unknown',
						'id' => $id
					];

					$rows = array_merge($rows, $this->objectToRows($before, $after, $depth+1, $id));

					// Process any subentities.
					if ($entity) {
						// TODO VM: I don't really like change type here, but otherwise triggers for added item will not be marked as green.
						$rows = array_merge($rows, $this->blocksToDiff($entity, $depth+2, $change_type));
					}
				}
			}
		}
		return $rows;
	}

	protected function convertToYaml($object) {
		$writer = new CYamlExportWriter();

		$yaml = $writer->write($object);

		return $yaml;
	}
}

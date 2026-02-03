<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerPopupImportCompare extends CController {

	public const CHANGE_NONE = 0;
	public const CHANGE_ADDED = 1;
	public const CHANGE_REMOVED = 2;

	private $toc = [];
	private $id_counter = 0;

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'import' => 'in 1',
			'rules_preset' => 'in template,dashboard',
			'rules' => 'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		$user_type = $this->getUserType();

		switch ($this->getInput('rules_preset', '')) {
			case 'template':
				return ($user_type === USER_TYPE_ZABBIX_ADMIN || $user_type === USER_TYPE_SUPER_ADMIN);

			case 'dashboard':
				return $this->checkAccess(CRoleHelper::UI_MONITORING_DASHBOARD)
					&& $this->checkAccess(CRoleHelper::ACTIONS_EDIT_DASHBOARDS);

			default:
				return false;
		}
	}

	protected function doAction(): void {
		$title = '';

		$return_missing_objects = false;
		$missing_objects_warning_title = '';
		$missing_objects_warning_foot_note = '';

		$rules = [
			'host_groups' => ['updateExisting' => false, 'createMissing' => false],
			'template_groups' => ['updateExisting' => false, 'createMissing' => false],
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
			'valueMaps' => ['updateExisting' => false, 'createMissing' => false, 'deleteMissing' => false],
			'dashboards' => ['updateExisting' => false, 'createMissing' => false]
		];

		// Adjust defaults for given rule preset, if specified.
		switch ($this->getInput('rules_preset')) {
			case 'template':
				$title = _('Templates');

				$rules['host_groups'] = ['updateExisting' => true, 'createMissing' => true];
				$rules['template_groups'] = ['updateExisting' => true, 'createMissing' => true];
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

			case 'dashboard':
				$title = _('Dashboards');
				$return_missing_objects = true;
				$missing_objects_warning_title = _('References to missing objects will be ignored:');
				$missing_objects_warning_foot_note = _('Check and reconfigure widgets after the import is completed.');

				$rules['dashboards'] = ['updateExisting' => true, 'createMissing' => true];

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
		}
		else {
			// CUploadFile throws exceptions, so we need to catch them
			try {
				$file = new CUploadFile($_FILES['import_file']);

				$result = API::Configuration()->importcompare([
					'format' => CImportReaderFactory::fileExt2ImportFormat($file->getExtension()),
					'source' => $file->getContent(),
					'returnMissingObjects' => $return_missing_objects,
					'rules' => $request_rules
				]);
			}
			catch (Exception $e) {
				error($e->getMessage());
			}
		}

		$data = [
			'title' => $title,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		if ($result === false) {
			$data['error'] = [
				'title' => _('Import failed'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}
		else {
			if ($return_missing_objects) {
				$data['missing_objects'] = $result['missingObjects'];
				$data['missing_objects_warning_title'] = $missing_objects_warning_title;
				$data['missing_objects_warning_foot_note'] = $missing_objects_warning_foot_note;

				unset($result['missingObjects']);
			}

			$data['diff'] = $this->blocksToDiff($result, 1);
			$data['diff_toc'] = $this->normalizeToc($this->toc);

			// Check if at least one entity is removed.
			$data['with_removed_entities'] = array_key_exists('removed', $this->toc);
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	private function normalizeToc(array $toc): array {
		// Order TOC.
		$toc = array_replace(array_flip(['removed', 'updated', 'added']), $toc);
		$new_toc = [];

		$toc_keys = [
			'removed' => _('Removed'),
			'updated' => _('Updated'),
			'added' => _('Added')
		];
		$names = [
			'host_groups' => _('Host groups'),
			'template_groups' => _('Template groups'),
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
			'host_prototypes' => _('Host prototypes'),
			'pages' => _('Dashboard pages'),
			'widgets' => _('Widgets')
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
	private function arrayToRows(string $key, array $before, array $after, int $depth): array {
		$rows = [[
			'value' => $key . ':',
			'depth' => $depth,
			'change_type' => self::CHANGE_NONE
		]];

		$is_hash = CArrayHelper::isHash($before) || CArrayHelper::isHash($after);

		// Make sure, order changes are also taken into account.
		$unchanged_map = [];
		$before_keys = array_keys($before);
		$after_keys = array_keys($after);
		$last_after_index = -1;

		foreach ($before_keys as $before_key) {
			$after_keys_count = count($after_keys);
			for ($j = $last_after_index + 1; $j < $after_keys_count; $j++) {
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
		unset($after_key, $last_after_index);

		$unchanged_before_keys = array_keys($unchanged_map);
		$next_unchanged_before_index = 0;
		$next_after_index = 0;

		foreach ($before_keys as $before_key) {
			if (array_key_exists($next_unchanged_before_index, $unchanged_before_keys)
					&& $before_key === $unchanged_before_keys[$next_unchanged_before_index]) {
				// Show all added after entries.
				while (array_key_exists($next_after_index, $after_keys)
						&& $after_keys[$next_after_index] !== $unchanged_map[$before_key]) {
					$after_key = $after_keys[$next_after_index];
					$yaml_key = $this->prepareYamlKey($after_key, $is_hash);
					$rows[] = [
						'value' => $this->convertToYaml([$yaml_key => $after[$after_key]]),
						'depth' => $depth + 1,
						'change_type' => self::CHANGE_ADDED
					];
					$next_after_index++;
				}

				// Show unchanged entry.
				$yaml_key = $this->prepareYamlKey($before_key, $is_hash);
				$rows[] = [
					'value' => $this->convertToYaml([$yaml_key => $before[$before_key]]),
					'depth' => $depth + 1,
					'change_type' => self::CHANGE_NONE
				];
				$next_unchanged_before_index++;
				$next_after_index++;
			}
			else {
				// Show all removed before entries.
				$yaml_key = $this->prepareYamlKey($before_key, $is_hash);
				$rows[] = [
					'value' => $this->convertToYaml([$yaml_key => $before[$before_key]]),
					'depth' => $depth + 1,
					'change_type' => self::CHANGE_REMOVED
				];
			}
		}

		// Show remaining after entries.
		$after_keys_count = count($after_keys);
		for ($after_index = $next_after_index; $after_index < $after_keys_count; $after_index++) {
			$after_key = $after_keys[$after_index];
			$yaml_key = $this->prepareYamlKey($after_key, $is_hash);
			$rows[] = [
				'value' => $this->convertToYaml([$yaml_key => $after[$after_key]]),
				'depth' => $depth + 1,
				'change_type' => self::CHANGE_ADDED
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
	private function prepareYamlKey($key, bool $is_hash) {
		if ($is_hash) {
			// Make sure, array with "zero" key element is not considered as indexed array in YAML converter.
			// \xE2\x80\x8B is UTF-8 code for zero width space.
			$yaml_key = ($key === 0) ? "\xE2\x80\x8B".'0' : $key;
		}
		else {
			// For indexed array any index should be replaced by "-" in YAML converter.
			// Passing single-entry array with '0' key, does exactly that.
			$yaml_key = 0;
		}

		return $yaml_key;
	}

	private function objectToRows(array $before, array $after, int $depth, int $id): array {
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

		unset($all_keys['uuid']);
		unset($all_keys['_index']);

		$rows = [];

		foreach ($all_keys as $key => $change_type) {
			switch ($change_type) {
				case 'no_change':
					$rows[] = [
						'value' => $this->convertToYaml([$key => $before[$key]]),
						'depth' => $depth + 1,
						'change_type' => self::CHANGE_NONE
					];

					break;

				case 'updated':
					$rows[] = [
						'value' => $this->convertToYaml([$key => $before[$key]]),
						'depth' => $depth + 1,
						'change_type' => self::CHANGE_REMOVED
					];
					$rows[] = [
						'value' => $this->convertToYaml([$key => $after[$key]]),
						'depth' => $depth + 1,
						'change_type' => self::CHANGE_ADDED
					];

					break;

				case 'removed':
					$rows[] = [
						'value' => $this->convertToYaml([$key => $before[$key]]),
						'depth' => $depth + 1,
						'change_type' => self::CHANGE_REMOVED
					];

					break;

				case 'added':
					$rows[] = [
						'value' => $this->convertToYaml([$key => $after[$key]]),
						'depth' => $depth + 1,
						'change_type' => self::CHANGE_ADDED
					];

					break;

				case 'updated_array':
					$rows = array_merge($rows, $this->arrayToRows($key, $before[$key], $after[$key], $depth + 1));

					break;
			}
		}

		if ($rows) {
			$rows[0] += ['id' => $id];
		}

		return $rows;
	}

	private function blocksToDiff(array $blocks, int $depth, array $outer_names = [],
			string $outer_change_type = 'updated'): array {
		$change_types = [
			'added' => self::CHANGE_ADDED,
			'removed' => self::CHANGE_REMOVED,
			'updated' => self::CHANGE_NONE
		];

		$rows = [];
		foreach ($blocks as $entity_type => $changes) {
			$changes = self::sortChanges($entity_type, $changes);

			$rows[] = [
				'value' => $entity_type . ':',
				'depth' => $depth,
				'change_type' => $change_types[$outer_change_type]
			];

			foreach ($changes as $change_type => $entities) {
				foreach ($entities as $entity) {
					$before = array_key_exists('before', $entity) ? $entity['before'] : [];
					$after = array_key_exists('after', $entity) ? $entity['after'] : [];
					$object = $before ?: $after;
					unset($entity['before'], $entity['after']);

					$name = $this->nameForToc($entity_type, $object, $outer_names);

					$this->toc[$change_type][$entity_type][] = [
						'name' => $name,
						'id' => $this->id_counter
					];

					$new_rows = $this->objectToRows($before, $after, $depth + 1, $this->id_counter);

					if ($new_rows) {
						$rows = array_merge($rows, $new_rows);

						$this->id_counter++;
					}

					// Process any sub-entities.
					if ($entity) {
						$rows = array_merge($rows, $this->blocksToDiff($entity, $depth + 2,
							[...$outer_names, [$entity_type, $name]], $change_type
						));
					}
				}
			}
		}

		return $rows;
	}

	private static function sortChanges(string $entity_type, array $changes): array {
		$order = match ($entity_type) {
			'pages' => ['updated', 'added', 'removed'],
			default => ['added', 'removed', 'updated']
		};

		$order = array_flip($order);

		uksort($changes, fn (string $key_1, string $key_2): int => $order[$key_1] <=> $order[$key_2]);

		return $changes;
	}

	private function nameForToc(string $entity_type, array $object, array $outer_names = []): string {
		switch ($entity_type) {
			case 'templates':
				return array_key_exists('name', $object) ? $object['name'] : $object['template'];
			case 'host_prototypes':
				return array_key_exists('name', $object) ? $object['name'] : $object['host'];
			case 'pages':
				$dashboard_name = $outer_names[count($outer_names) - 1][1];
				$dashboard_page_name = array_key_exists('name', $object)
					? $object['name']
					: _s('Page %1$d', $object['_index'] + 1);

				return $dashboard_page_name.' ('.$dashboard_name.')';
			case 'widgets':
				$dashboard_page_name = $outer_names[count($outer_names) - 1][1];

				$x = array_key_exists('x', $object) ? $object['x'] : '0';
				$y = array_key_exists('y', $object) ? $object['y'] : '0';

				$widget_name = array_key_exists('name', $object)
					? $object['name']
					: $object['type'];

				$widget_name .= '['.$x.','.$y.']';

				return $widget_name.' ('.$dashboard_page_name.')';
			default:
				return $object['name'];
		}
	}

	private function convertToYaml($object): string {
		$writer = new CYamlExportWriter();

		return $writer->write($object);
	}
}

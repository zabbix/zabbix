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


abstract class CControllerDataTable extends CController {

	protected array $allowed_data_fields = [];
	protected array $validation_rules = [];
	protected ?array $paging = null;

	protected function setValidationRules(array $validation_rules): self {
		$this->validation_rules = $validation_rules;

		return $this;
	}

	protected function addValidationRules(array $validation_rules): self {
		$this->validation_rules = array_merge($this->validation_rules, $validation_rules);

		return $this;
	}

	abstract protected function getData(): array;

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);

		$this->setValidationRules([
			'data_fields' =>		'required|array',
			'options' =>			'array',
			'filter' =>				'array',
			'filter_counters' =>	'in 1',
			'page' =>				'int32',
			'sort_field' =>			'string',
			'sort_order' =>			'string|in '.ZBX_SORT_UP.','.ZBX_SORT_DOWN,
			'export_file' =>		'string'
		]);
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput($this->validation_rules);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => _('Invalid request')
					]
				])])
			);
		}

		return $ret;
	}

	protected function paginate(array &$rows, int $page, string $sort_order): array {
		$num_rows = count($rows);
		$rows_per_page = (int) CWebUser::$data['rows_per_page'];

		$offset_down = 0;
		$limit_exceeded = $num_rows > CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);

		if ($limit_exceeded) {
			$offset_down = $num_rows - CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);
			$num_rows = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);
		}

		$num_pages = max(1, (int) ceil($num_rows / $rows_per_page));
		$page = max(1, min($num_pages, $page));

		$start = ($page - 1) * $rows_per_page;
		$end = min($num_rows, $start + $rows_per_page);
		$offset = $sort_order == ZBX_SORT_DOWN ? $offset_down : 0;

		// Trim given rows for the current page.
		$rows = array_slice($rows, $start + $offset, $end - $start, true);

		return [
			'page' => $page,
			'num_rows' => $num_rows,
			'num_pages' => $num_pages,
			'rows_per_page' => $rows_per_page,
			'limit_exceeded' => $limit_exceeded
		];
	}

	protected function export(string $type): ?string {
		return null;
	}

	protected function doAction(): void {
		$export = $this->getInput('export_file', '');
		if ($export == 'csv') {
			$data = $this->export($export);

			$this->setResponse(new CControllerResponseData([
				'main_block' => json_encode(['export' => $data])
			]));

			return;
		}

		$rows = [];

		try {
			$data = array_merge(['rows' => []], $this->getData());

			foreach ($data['rows'] as $row_index => [$row_config, $row_data]) {
				if (array_key_exists('renderer', $row_config) && array_key_exists('raw_data', $row_config)) {
					$rows[$row_index] = [$row_config, $row_data];

					continue;
				}

				$prepared_row_data = array_map(static fn (string $field) => $row_data[$field] ?? null,
					$data['data_fields']);

				$rows[$row_index] = [$row_config, $prepared_row_data];
			}

			unset($data['rows']);
		} catch (Throwable) {
			$this->setResponse(new CControllerResponseData([
				'main_block' => json_encode([
					'error' => [
						'title' => _('Invalid request')
					]
				])
			]));

			return;
		}

		$paging = $this->paging ?: [
			'num_rows' => count($rows),
			'page' => 1,
			'rows_per_page' => (int) CWebUser::$data['rows_per_page'],
			'limit_exceeded' => false
		];

		$rows = array_map(static fn (array $row) => [$row[0], array_values($row[1])], $rows);

		$data = array_merge($data, ['data' => $rows], $paging);

		$this->setResponse(new CControllerResponseData([
			'main_block' => json_encode($data)
		]));
	}

	protected function getDataFields(): array {
		return array_values(array_intersect($this->getInput('data_fields'), $this->allowed_data_fields));
	}

	protected function getUserConfigs(string $storage_idx): array {
		return array_map(static fn (string $user_config) => json_decode($user_config, true) ?? [],
			CProfile::getArray($storage_idx, []));
	}

	protected function getColumnOptions(array $user_configs, int $tabfilter_index = 0): array {
		return array_merge(...array_column($user_configs[$tabfilter_index]['columns'] ?? [], 'column_options'));
	}
}

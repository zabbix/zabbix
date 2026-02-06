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

	protected array $validation_rules = [];
	protected array $pre_processors = [];
	protected ?array $paging = null;

	protected function setValidationRules(array $validation_rules): self {
		$this->validation_rules = $validation_rules;

		return $this;
	}

	protected function addValidationRules(array $validation_rules): self {
		$this->validation_rules = array_merge($this->validation_rules, $validation_rules);

		return $this;
	}

	protected function setPreProcessors(array $pre_processors): self {
		$this->pre_processors = $pre_processors;

		return $this;
	}

	protected function addPreProcessors(array $pre_processors): self {
		$this->pre_processors = array_merge($this->pre_processors, $pre_processors);

		return $this;
	}

	abstract protected function getData(): array;

	protected function init(): void {
		parent::init();

		$this->disableCsrfValidation();
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);

		$this->setValidationRules([
			'columns' =>			'required|array',
			'filter' =>				'array',
			'filter_counters' =>	'in 1',
			'options' =>			'array',
			'page' =>				'int32',
			'sort_field' =>			'string',
			'sort_order' =>			'string',
			'export_file' =>		'string'
		]);
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput($this->validation_rules);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => _('Invalid request')
				])])
			);
		}

		return $ret;
	}

	protected function paginate(array &$rows, int $page, string $sort_order): array {
		$num_rows = count($rows);
		$rows_per_page = (int) CWebUser::$data['rows_per_page'];

		$offset_up = 0;
		$offset_down = 0;
		$limit_exceeded = ($num_rows > CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT));

		if ($limit_exceeded) {
			$offset_down = $num_rows - CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);
			$num_rows = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);
		}

		$num_pages = max(1, (int) ceil($num_rows / $rows_per_page));
		$page = max(1, min($num_pages, $page));

		$start = ($page - 1) * $rows_per_page;
		$end = min($num_rows, $start + $rows_per_page);
		$offset = ($sort_order == ZBX_SORT_DOWN) ? $offset_down : $offset_up;

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
			$fields = array_flip($data['fields']);

			foreach ($data['rows'] as $row_index => [$row_config, &$row_data]) {
				$prepared_row_data = [];

				if (array_key_exists('renderer', $row_config) && array_key_exists('raw_data', $row_config)) {
					$rows[$row_index] = [$row_config, $row_data];

					continue;
				}

				if ($row_data && $fields) {
					$row_data = array_intersect_key($row_data, $fields);
				}

				foreach ($data['columns'] as $column_config) {
					$column_data = [];

					foreach ($column_config['fields'] as $field) {
						$column_data[] = $row_data[$field] ?? null;
					}

					$pre_processor = $column_config['pre_processor'] ?? null;
					if (array_key_exists($pre_processor, $this->pre_processors)) {
						$column_data = call_user_func($this->pre_processors[$pre_processor], [
							'column_config' => $column_config,
							'column_data' => $column_data,
							'row_index' => $row_index
						]);
					}

					$prepared_row_data[] = $column_data;
				}

				$rows[$row_index] = [$row_config, $prepared_row_data];
			}
		} catch (Throwable $e) {
			$this->setResponse(new CControllerResponseData([
				'main_block' => json_encode([
					'error' => $e->getMessage()
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

		unset($data['fields'], $data['columns'], $data['rows'], $data['meta']);

		$rows = array_map(static fn (array $row) => [$row[0], array_values($row[1])], $rows);

		$data = array_merge($data, ['data' => $rows], $paging);

		$this->setResponse(new CControllerResponseData([
			'main_block' => json_encode($data)
		]));
	}

	protected function extractFields(array $columns): array {
		$visible_columns = array_filter($columns, static fn (array $column_config) => $column_config['visible']);
		$fields = array_merge(...array_column($visible_columns, 'fields'));

		return array_keys(array_flip($fields));
	}
}

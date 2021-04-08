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


/**
 * Class containing methods for operations with configuration.
 */
class CConfiguration extends CApiService {

	public const ACCESS_RULES = [
		'export' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'import' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'importcompare' => ['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	/**
	 * @param array $params
	 *
	 * @return string
	 */
	public function export(array $params) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'format' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => implode(',', [CExportWriterFactory::YAML, CExportWriterFactory::XML, CExportWriterFactory::JSON, CExportWriterFactory::RAW])],
			'prettyprint' => ['type' => API_BOOLEAN, 'default' => false],
			'options' =>	['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
				'groups' =>		['type' => API_IDS],
				'hosts' =>		['type' => API_IDS],
				'images' =>		['type' => API_IDS],
				'maps' =>		['type' => API_IDS],
				'mediaTypes' =>	['type' => API_IDS],
				'templates' =>	['type' => API_IDS],
				'valueMaps' =>	['type' => API_IDS]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $params, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($params['format'] === CExportWriterFactory::XML) {
			$lib_xml = (new CFrontendSetup())->checkPhpLibxml();

			if ($lib_xml['result'] == CFrontendSetup::CHECK_FATAL) {
				self::exception(ZBX_API_ERROR_INTERNAL, $lib_xml['error']);
			}

			$xml_writer = (new CFrontendSetup())->checkPhpXmlWriter();

			if ($xml_writer['result'] == CFrontendSetup::CHECK_FATAL) {
				self::exception(ZBX_API_ERROR_INTERNAL, $xml_writer['error']);
			}
		}

		$export = new CConfigurationExport($params['options']);
		$export->setBuilder(new CConfigurationExportBuilder());
		$writer = CExportWriterFactory::getWriter($params['format']);
		$writer->formatOutput($params['prettyprint']);
		$export->setWriter($writer);

		$export_data = $export->export();

		if ($export_data === false) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		return $export_data;
	}

	/**
	 * Validate input parameters for import() and importcompare() methods.
	 *
	 * @param type $params
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateImport($params): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'format' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => implode(',', [CImportReaderFactory::YAML, CImportReaderFactory::XML, CImportReaderFactory::JSON])],
			'source' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'rules' =>				['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
				'applications' =>		['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN, 'default' => false],
					'deleteMissing' =>		['type' => API_BOOLEAN, 'default' => false]
				]],
				'discoveryRules' =>		['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN, 'default' => false],
					'updateExisting' =>		['type' => API_BOOLEAN, 'default' => false],
					'deleteMissing' =>		['type' => API_BOOLEAN, 'default' => false]
				]],
				'graphs' =>				['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN, 'default' => false],
					'updateExisting' =>		['type' => API_BOOLEAN, 'default' => false],
					'deleteMissing' =>		['type' => API_BOOLEAN, 'default' => false]
				]],
				'groups' =>				['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN, 'default' => false]
				]],
				'hosts' =>				['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN, 'default' => false],
					'updateExisting' =>		['type' => API_BOOLEAN, 'default' => false]
				]],
				'httptests' =>			['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN, 'default' => false],
					'updateExisting' =>		['type' => API_BOOLEAN, 'default' => false],
					'deleteMissing' =>		['type' => API_BOOLEAN, 'default' => false]
				]],
				'images' =>				['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN, 'default' => false],
					'updateExisting' =>		['type' => API_BOOLEAN, 'default' => false]
				]],
				'items' =>				['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN, 'default' => false],
					'updateExisting' =>		['type' => API_BOOLEAN, 'default' => false],
					'deleteMissing' =>		['type' => API_BOOLEAN, 'default' => false]
				]],
				'maps' =>				['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN, 'default' => false],
					'updateExisting' =>		['type' => API_BOOLEAN, 'default' => false]
				]],
				'mediaTypes' =>			['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN, 'default' => false],
					'updateExisting' =>		['type' => API_BOOLEAN, 'default' => false]
				]],
				'templateLinkage' =>	['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN, 'default' => false],
					'deleteMissing' =>		['type' => API_BOOLEAN, 'default' => false]
				]],
				'templates' =>			['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN, 'default' => false],
					'updateExisting' =>		['type' => API_BOOLEAN, 'default' => false]
				]],
				'templateDashboards' =>	['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN, 'default' => false],
					'updateExisting' =>		['type' => API_BOOLEAN, 'default' => false],
					'deleteMissing' =>		['type' => API_BOOLEAN, 'default' => false]
				]],
				'triggers' =>			['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN, 'default' => false],
					'updateExisting' =>		['type' => API_BOOLEAN, 'default' => false],
					'deleteMissing' =>		['type' => API_BOOLEAN, 'default' => false]
				]],
				'valueMaps' =>			['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN, 'default' => false],
					'updateExisting' =>		['type' => API_BOOLEAN, 'default' => false],
					'deleteMissing' =>		['type' => API_BOOLEAN, 'default' => false]
				]]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $params, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (array_key_exists('maps', $params['rules']) && !self::checkAccess(CRoleHelper::ACTIONS_EDIT_MAPS)
				&& ($params['rules']['maps']['createMissing'] || $params['rules']['maps']['updateExisting'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'rules',
				_('no permissions to create and edit maps')
			));
		}

		if ($params['format'] === CImportReaderFactory::XML) {
			$lib_xml = (new CFrontendSetup())->checkPhpLibxml();

			if ($lib_xml['result'] == CFrontendSetup::CHECK_FATAL) {
				self::exception(ZBX_API_ERROR_INTERNAL, $lib_xml['error']);
			}

			$xml_reader = (new CFrontendSetup())->checkPhpXmlReader();

			if ($xml_reader['result'] == CFrontendSetup::CHECK_FATAL) {
				self::exception(ZBX_API_ERROR_INTERNAL, $xml_reader['error']);
			}
		}
	}

	/**
	 * @param array $params
	 *
	 * @return bool
	 */
	public function import($params) {
		$this->validateImport($params);

		$import_reader = CImportReaderFactory::getReader($params['format']);
		$data = $import_reader->read($params['source']);

		$import_validator_factory = new CImportValidatorFactory($params['format']);
		$import_converter_factory = new CImportConverterFactory();

		$validator = new CXmlValidator($import_validator_factory, $params['format']);

		$data = $validator
			->setStrict(true)
			->validate($data, '/');

		foreach (['1.0', '2.0', '3.0', '3.2', '3.4', '4.0', '4.2', '4.4', '5.0', '5.2'] as $version) {
			if ($data['zabbix_export']['version'] !== $version) {
				continue;
			}

			$data = $import_converter_factory
				->getObject($version)
				->convert($data);

			$data = $validator
				// Must not use XML_INDEXED_ARRAY key validaiton for the converted data.
				->setStrict(false)
				->validate($data, '/');
		}

		// Get schema for converters.
		$schema = $import_validator_factory
			->getObject(ZABBIX_EXPORT_VERSION)
			->getSchema();

		// Convert human readable import constants to values Zabbix API can work with.
		$data = (new CConstantImportConverter($schema))->convert($data);

		// Add default values in place of missed tags.
		$data = (new CDefaultImportConverter($schema))->convert($data);

		// Normalize array keys and strings.
		$data = (new CImportDataNormalizer($schema))->normalize($data);

		// Transform converter.
		$data = (new CTransformImportConverter($schema))->convert($data);

		$adapter = new CImportDataAdapter();
		$adapter->load($data);

		$configuration_import = new CConfigurationImport(
			$params['rules'],
			new CImportReferencer(),
			new CImportedObjectContainer()
		);

		return $configuration_import->import($adapter);
	}

	/**
	 * Preview changes that would be done to templates.
	 *
	 * @param array $params  Same params, as for import.
	 *
	 * @return bool
	 */
	public function importcompare($params) {
		$this->validateImport($params);

		if ($params['format'] === CImportReaderFactory::XML) {
			$lib_xml = (new CFrontendSetup())->checkPhpLibxml();

			if ($lib_xml['result'] == CFrontendSetup::CHECK_FATAL) {
				self::exception(ZBX_API_ERROR_INTERNAL, $lib_xml['error']);
			}

			$xml_reader = (new CFrontendSetup())->checkPhpXmlReader();

			if ($xml_reader['result'] == CFrontendSetup::CHECK_FATAL) {
				self::exception(ZBX_API_ERROR_INTERNAL, $xml_reader['error']);
			}
		}

		$import_reader = CImportReaderFactory::getReader($params['format']);
		$data = $import_reader->read($params['source']);

		$import_validator_factory = new CImportValidatorFactory($params['format']);
		$import_converter_factory = new CImportConverterFactory();

		$validator = new CXmlValidator($import_validator_factory, $params['format']);

		$data = $validator
			->setStrict(true)
			->validate($data, '/');

		foreach (['1.0', '2.0', '3.0', '3.2', '3.4', '4.0', '4.2', '4.4', '5.0', '5.2'] as $version) {
			if ($data['zabbix_export']['version'] !== $version) {
				continue;
			}

			$data = $import_converter_factory
				->getObject($version)
				->convert($data);

			$data = $validator
				// Must not use XML_INDEXED_ARRAY key validaiton for the converted data.
				->setStrict(false)
				->validate($data, '/');
		}

		// Get schema for converters.
		$schema = $import_validator_factory
			->getObject(ZABBIX_EXPORT_VERSION)
			->getSchema();

		// TODO VM: remove commented blocks
		// Convert human readable import constants to values Zabbix API can work with.
//		$data = (new CConstantImportConverter($schema))->convert($data);

		// Add default values in place of missed tags.
//		$data = (new CDefaultImportConverter($schema))->convert($data);

		// Normalize array keys and strings.
		$data = (new CImportDataNormalizer($schema))->normalize($data);

		// Transform converter.
		$data = (new CTransformImportConverter($schema))->convert($data);

		$adapter = new CImportDataAdapter();
		$adapter->load($data);

		// TODO VM: (?) Everything till now is almost same as in import(). Need to find nice way to move it to function.

		$import = $adapter->getData();
		$imported_uuids = [];
		foreach (['groups', 'templates'] as $first_level) {
			if (array_key_exists($first_level, $import)) {
				$imported_uuids[$first_level] = array_column($import[$first_level], 'uuid');
			}
		}

		$imported_ids = [];
		foreach ($imported_uuids as $entity => $uuids) {
			switch ($entity) {
				case 'groups':
					$imported_ids['groups'] = API::HostGroup()->get([
						'filter' => [
							'uuid' => $uuids
						],
						'preservekeys' => true
					]);
					$imported_ids['groups'] = array_keys($imported_ids['groups']);

					break;

				case 'templates':
					$imported_ids['templates'] = API::Template()->get([
						'filter' => [
							'uuid' => $uuids
						],
						'preservekeys' => true
					]);
					$imported_ids['templates'] = array_keys($imported_ids['templates']);

					break;

				// TODO VM: (?) In import file, if we have a trigger or graph unrelated to templates in this file, export is not managing it. Do we need an alternative?

				default:
					break;
			}
		}

		// Get current state of templates in same format, as import to compare this data.
		$export = API::Configuration()->export([
			'format' => CExportWriterFactory::RAW,
			'prettyprint' => false,
			'options' => $imported_ids
		]);
		$export = $export['zabbix_export'];


		$uuid_structure = [
			'groups' => [],
			'templates' => [
				'groups' => [],
				'items' => [
					'triggers' => []
				],
				'discovery_rules' => [
					'item_prototypes' => [],
					'trigger_prototypes' => [],
					'graph_prototypes' => [],
					'host_prototypes' => []
				],
				'dashboards' => [],
				'httptests' => [],
				'valuemaps' => []
			],
			'triggers' => [],
			'graphs' => []
		];

		// Leave only template related
		$export = $this->intersectKeys($export, $uuid_structure);
		$import = $this->intersectKeys($import, $uuid_structure);

		$return = $this->compareByStructure($uuid_structure, $export, $import, $params['rules']);

		return $return;
	}

	/**
	 * Similar to array_intersect(), but will not throw notice when working on multidimensional array.
	 *
	 * @param array $array
	 * @param array $keys
	 *
	 * @return array
	 */
	protected function intersectKeys(array $array, array $keys): array {
		// TODO VM: do I really need it?
		$result = [];

		foreach($array as $key => $value) {
			if (array_key_exists($key, $keys)) {
				$result[$key] = $value;
			}
		}

		return $result;
	}

	/**
	 * Create separate comparison for each structured object.
	 * Warning: Recursion.
	 *
	 * @param array $structure
	 * @param array $before
	 * @param array $after
	 * @param array $options
	 *
	 * @return array
	 */
	protected function compareByStructure(array $structure, array $before, array $after, array $options): array {
		$result = [];

		foreach ($structure as $key => $sub_structure) {
			if ((!array_key_exists($key, $before) || !$before[$key])
					&& (!array_key_exists($key, $after) || !$after[$key])) {
				continue;
			}

			// Make sure, $key exists in both arrays.
			$before += [$key => []];
			$after += [$key => []];

			$diff = $this->compareArrayByUuid($before[$key], $after[$key]);

			if (array_key_exists('added', $diff)) {
				foreach ($diff['added'] as &$entity) {
					$entity = $this->compareByStructure($sub_structure, [], $entity, $options);
				}
				unset($entity);
			}

			if (array_key_exists('removed', $diff)) {
				foreach ($diff['removed'] as &$entity) {
					$entity = $this->compareByStructure($sub_structure, $entity, [], $options);
				}
				unset($entity);
			}

			if ($sub_structure && array_key_exists('updated', $diff)) {
				foreach ($diff['updated'] as &$entity) {
					$entity = $this->compareByStructure($sub_structure, $entity['before'], $entity['after'], $options);
				}
				unset($entity);
			}

			$diff = $this->applyOptions($options, $key, $diff);

			unset($before[$key], $after[$key]);

			if ($diff) {
				$result[$key] = $diff;
			}
		}

		$object = [];

		if ($before) {
			$object['before'] = $before;
		}

		if ($after) {
			$object['after'] = $after;
		}

		if($object) {
			// Insert 'before' and/or 'after' at the beginning of array.
			$result = array_merge($object, $result);
		}

		return $result;
	}

	/**
	 * Compare two entities and separate all their keys into added/removed/updated.
	 *
	 * @param array $before
	 * @param array $after
	 *
	 * @return array
	 */
	protected function compareArrayByUuid(array $before, array $after): array {
		$diff = [
			'added' => [],
			'removed' => [],
			'updated' => []
		];

		$before = zbx_toHash($before, 'uuid');
		$after = zbx_toHash($after, 'uuid');

		$before_keys = array_keys($before);
		$after_keys = array_keys($after);

		$same_keys = array_intersect($before_keys, $after_keys);
		$added_keys = array_diff($after_keys, $before_keys);
		$removed_keys = array_diff($before_keys, $after_keys);

		foreach ($added_keys as $key) {
			$diff['added'][] = $after[$key];
		}

		foreach ($removed_keys as $key) {
			$diff['removed'][] = $before[$key];
		}

		foreach ($same_keys as $key) {
			if ($before[$key] != $after[$key]) {
				$diff['updated'][] = [
					'before' => $before[$key],
					'after' => $after[$key]
				];
			}
		}

		foreach (['added', 'removed', 'updated'] as $key) {
			if (!$diff[$key]) {
				unset($diff[$key]);
			}
		}

		return $diff;
	}

	/**
	 * Compare two entities and separate all their keys into added/removed/updated.
	 *
	 * @param array $options     import options
	 * @param array $entity_key  key of entity being processed
	 * @param array $diff        diff for this entity
	 *
	 * @return array
	 */
	protected function applyOptions(array $options, string $entity_key, array $diff): array {
		$option_key_map = [
			'groups' => 'groups',
			'templates' => 'templates',
			'items' => 'items',
			'triggers' => 'triggers',
			'discovery_rules' => 'discoveryRules',
			'item_prototypes' => 'discoveryRules',
			'trigger_prototypes' => 'discoveryRules',
			'graph_prototypes' => 'discoveryRules',
			'host_prototypes' => 'discoveryRules',
			'dashboards' => 'templateDashboards',
			'httptests' => 'httptests',
			'valuemaps' => 'valueMaps',
			'triggers' => 'triggers',
			'graphs' => 'graphs'
		];

		// TODO VM: simple triggers should be moved to general triggers.

		$entity_options = $options[$option_key_map[$entity_key]];
		$stored_changes = [];

		if ($entity_key === 'templates' && array_key_exists('updated', $diff)) {
			for ($key = 0; $key< count($diff['updated']); $key++) {
				$entity = $diff['updated'][$key];
				$has_before_templates = array_key_exists('templates', $entity['before']);
				$has_after_templates = array_key_exists('templates', $entity['after']);

				if (!$has_before_templates && !$has_after_templates) {
					continue;
				}
				elseif ($has_before_templates && !$has_after_templates) {
					$entity['after']['templates'] = [];

					// Make sure, precessed entry is last in both arrays. Otherwise it will break the comparison.
					$before_templates = $entity['before']['templates'];
					unset($entity['before']['templates']);
					$entity['before']['templates'] = $before_templates;
				}
				elseif ($has_after_templates && !$has_before_templates) {
					$entity['before']['templates'] = [];
				}

				if ($entity['before']['templates'] === $entity['after']['templates']) {
					continue;
				}

				if (!$options['templateLinkage']['createMissing'] && !$options['templateLinkage']['deleteMissing']) {
					$entity['after']['templates'] = $entity['before']['templates'];
				}
				elseif ($options['templateLinkage']['createMissing'] && !$options['templateLinkage']['deleteMissing']) {
					$entity['after']['templates'] = $this->afterForInnerCreateMissing($entity['before']['templates'],
							$entity['after']['templates']);
				}
				elseif ($options['templateLinkage']['deleteMissing'] && !$options['templateLinkage']['createMissing']) {
					$entity['after']['templates'] = $this->afterForInnerDeleteMissing($entity['before']['templates'],
							$entity['after']['templates']);
				}

				if ($entity['before'] === $entity['after'] && count($entity) === 2) {
					unset($diff['updated'][$key]);
				}
				else {
					$stored_changes[$key]['templates'] = $entity['after']['templates'];
				}
			}
			unset($entity);

			if (!$diff['updated']) {
				unset($diff['updated']);
			}
		}

		if (!array_key_exists('createMissing', $entity_options) || !$entity_options['createMissing']) {
			unset($diff['added']);
		}

		if (!array_key_exists('deleteMissing', $entity_options) || !$entity_options['deleteMissing']) {
			unset($diff['removed']);
		}

		if ((!array_key_exists('updateExisting', $entity_options) || !$entity_options['updateExisting'])
				&& array_key_exists('updated', $diff)) {
			$new_updated = [];

			foreach ($diff['updated'] as $key => $entity) {
				$has_inner_entities = array_flip(array_keys($entity));
				unset($has_inner_entities['before'], $has_inner_entities['after']);
				$has_inner_entities = count($has_inner_entities) > 0;

				if ($has_inner_entities || array_key_exists($key, $stored_changes)) {
					$entity['after'] = $entity['before'];
					$new_updated[] = $entity;
				}
			}

			if ($new_updated) {
				$diff['updated'] = $new_updated;
			}
			else {
				unset($diff['updated']);
			}
		}

		if ($stored_changes) {
			foreach ($stored_changes as $key => $stored_entry) {
				$entry = $diff['updated'][$key]['after'];

				foreach ($stored_entry as $entry_key => $entry_after_value) {
					if ($entry_after_value !== []) {
						$entry[$entry_key] = $entry_after_value;
					}
				}

				$diff['updated'][$key]['after'] = $entry;
			}
		}

		if (array_key_exists('updated', $diff)) {
			// Reset keys.
			$diff['updated'] = array_values($diff['updated']);

			// TODO VM: (?) it may be a good idea to keep original order of keys, but it will increase complexity.
			// Make sure, key order is same in 'before' and 'after' arrays.
			foreach ($diff['updated'] as &$entity) {
				$order = array_flip(array_keys($entity['before']));
				$order = $this->intersectKeys($order, array_keys($entity['after']));
				$entity['after'] = array_merge($order, $entity['after']);
			}
			unset($entity);
		}

		return $diff;
	}

	/**
	 * Create "after" that contains all entries from "before" and "after" combined.
	 *
	 * @param type $before
	 * @param type $after
	 *
	 * @return type
	 */
	protected function afterForInnerCreateMissing($before, $after) {
		$missing = [];

		foreach ($after as $after_entity) {
			$found = false;

			foreach ($before as $before_entity) {
				if ($before_entity === $after_entity) {
					$found = true;
					break;
				}
			}

			if (!$found) {
				$missing[] = $after_entity;
			}
		}

		return array_merge($before, $missing);
	}

	/**
	 * Create "after" that contains only entries from "after" that were also present in "before".
	 *
	 * @param type $before
	 * @param type $after
	 *
	 * @return type
	 */
	protected function afterForInnerDeleteMissing($before, $after) {
		$new_after = [];

		foreach ($after as $after_entity) {
			$found = false;

			foreach ($before as $before_entity) {
				if ($before_entity === $after_entity) {
					$found = true;
					break;
				}
			}

			if ($found) {
				$new_after[] = $after_entity;
			}
		}

		return $new_after;
	}
}

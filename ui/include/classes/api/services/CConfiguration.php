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
				'templates' =>	['type' => API_IDS]
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
					'createMissing' =>		['type' => API_BOOLEAN, 'default' => false],
					'updateExisting' =>		['type' => API_BOOLEAN, 'default' => false]
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

		foreach (['1.0', '2.0', '3.0', '3.2', '3.4', '4.0', '4.2', '4.4', '5.0', '5.2', '5.4'] as $version) {
			if ($data['zabbix_export']['version'] !== $version) {
				continue;
			}

			$data = $import_converter_factory
				->getObject($version)
				->convert($data);

			$data = $validator
				// Must not use XML_INDEXED_ARRAY key validation for the converted data.
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
	 * @param array $params Same params, as for import.
	 *
	 * @return array
	 *
	 * @throws APIException
	 * @throws Exception
	 */
	public function importcompare(array $params): array {
		$this->validateImport($params);

		$import_reader = CImportReaderFactory::getReader($params['format']);
		$data = $import_reader->read($params['source']);

		$import_validator_factory = new CImportValidatorFactory($params['format']);
		$import_converter_factory = new CImportConverterFactory();

		$validator = new CXmlValidator($import_validator_factory, $params['format']);

		$data = $validator
			->setStrict(true)
			->setPreview(true)
			->validate($data, '/');

		foreach (['1.0', '2.0', '3.0', '3.2', '3.4', '4.0', '4.2', '4.4', '5.0', '5.2', '5.4'] as $version) {
			if ($data['zabbix_export']['version'] !== $version) {
				continue;
			}

			$data = $import_converter_factory
				->getObject($version)
				->convert($data);

			$data = $validator
				// Must not use XML_INDEXED_ARRAY key validation for the converted data.
				->setStrict(false)
				->setPreview(true)
				->validate($data, '/');
		}

		// Get schema for converters.
		$schema = $import_validator_factory
			->getObject(ZABBIX_EXPORT_VERSION)
			->getSchema();

		// Normalize array keys and strings.
		$data = (new CImportDataNormalizer($schema))->normalize($data);

		// Transform converter.
		$data = (new CTransformImportConverter($schema))->convert($data);

		$adapter = new CImportDataAdapter();
		$adapter->load($data);

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
		// Normalize array keys and strings.
		$export = (new CImportDataNormalizer($schema))->normalize($export);
		$export = $export['zabbix_export'];

		$importcompare = new CConfigurationImportcompare($params['rules']);
		return $importcompare->importcompare($export, $import);
	}
}

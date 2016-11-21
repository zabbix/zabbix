<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

	/**
	 * Export configuration data.
	 *
	 * @param array $params
	 *
	 * @return string
	 */
	public function export(array $params) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'format' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => [CExportWriterFactory::XML, CExportWriterFactory::JSON]],
			'options' =>	['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
				'groups' =>		['type' => API_IDS],
				'hosts' =>		['type' => API_IDS],
				'images' =>		['type' => API_IDS],
				'maps' =>		['type' => API_IDS],
				'screens' =>	['type' => API_IDS],
				'templates' =>	['type' => API_IDS],
				'valueMaps' =>	['type' => API_IDS]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $params, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$export = new CConfigurationExport($params['options']);
		$export->setBuilder(new CConfigurationExportBuilder());
		$writer = CExportWriterFactory::getWriter($params['format']);
		$writer->formatOutput(false);
		$export->setWriter($writer);

		return $export->export();
	}

	/**
	 * Import configuration data.
	 *
	 * @param array $params
	 *
	 * @return bool
	 */
	public function import($params) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'format' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => [CImportReaderFactory::XML, CImportReaderFactory::JSON]],
			'source' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'rules' =>				['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
				'applications' =>		['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN],
					'deleteMissing' =>		['type' => API_BOOLEAN]
				]],
				'discoveryRules' =>		['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN],
					'updateExisting' =>		['type' => API_BOOLEAN],
					'deleteMissing' =>		['type' => API_BOOLEAN]
				]],
				'graphs' =>				['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN],
					'updateExisting' =>		['type' => API_BOOLEAN],
					'deleteMissing' =>		['type' => API_BOOLEAN]
				]],
				'groups' =>				['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN]
				]],
				'hosts' =>				['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN],
					'updateExisting' =>		['type' => API_BOOLEAN]
				]],
				'httptests' =>			['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN],
					'updateExisting' =>		['type' => API_BOOLEAN],
					'deleteMissing' =>		['type' => API_BOOLEAN]
				]],
				'images' =>				['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN],
					'updateExisting' =>		['type' => API_BOOLEAN]
				]],
				'items' =>				['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN],
					'updateExisting' =>		['type' => API_BOOLEAN],
					'deleteMissing' =>		['type' => API_BOOLEAN]
				]],
				'maps' =>				['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN],
					'updateExisting' =>		['type' => API_BOOLEAN]
				]],
				'screens' =>			['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN],
					'updateExisting' =>		['type' => API_BOOLEAN]
				]],
				'templateLinkage' =>	['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN]
				]],
				'templates' =>			['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN],
					'updateExisting' =>		['type' => API_BOOLEAN]
				]],
				'templateScreens' =>	['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN],
					'updateExisting' =>		['type' => API_BOOLEAN],
					'deleteMissing' =>		['type' => API_BOOLEAN]
				]],
				'triggers' =>			['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN],
					'updateExisting' =>		['type' => API_BOOLEAN],
					'deleteMissing' =>		['type' => API_BOOLEAN]
				]],
				'valueMaps' =>			['type' => API_OBJECT, 'fields' => [
					'createMissing' =>		['type' => API_BOOLEAN],
					'updateExisting' =>		['type' => API_BOOLEAN]
				]]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $params, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$importReader = CImportReaderFactory::getReader($params['format']);
		$data = $importReader->read($params['source']);

		$data = (new CXmlValidator())->validate($data, $params['format']);

		$importConverterFactory = new CImportConverterFactory();

		$converterChain = new CConverterChain();
		$converterChain->addConverter('1.0', $importConverterFactory->getObject('1.0'));
		$converterChain->addConverter('2.0', $importConverterFactory->getObject('2.0'));
		$converterChain->addConverter('3.0', $importConverterFactory->getObject('3.0'));

		$adapter = new CImportDataAdapter(ZABBIX_EXPORT_VERSION, $converterChain);
		$adapter->load($data);

		$configurationImport = new CConfigurationImport(
			$params['rules'],
			new CImportReferencer(),
			new CImportedObjectContainer()
		);

		return $configurationImport->import($adapter);
	}
}

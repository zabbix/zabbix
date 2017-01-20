<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
	 * $params structure:
	 * array(
	 * 	'options' => array(
	 * 		'hosts' => array with host ids,
	 * 		'templates' => array with templateids,
	 *		 ...
	 * 	),
	 * 	'format' => 'json'|'xml'
	 * )
	 *
	 * @param array $params
	 *
	 * @return string
	 */
	public function export(array $params) {
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
	 * $params structure:
	 * array(
	 * 	'format' => 'json'|'xml'
	 * 	'source' => configuration data in specified format,
	 * 	'rules' => array(
	 * 		'hosts' => array('createMissing' => true, 'updateExisting' => false),
	 * 		'templates' => array('createMissing' => true, 'updateExisting' => true),
	 * 		...
	 * 	)
	 * )
	 *
	 * @param $params
	 *
	 * @return bool
	 */
	public function import($params) {
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

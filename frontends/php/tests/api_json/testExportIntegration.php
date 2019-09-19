<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


require_once dirname(__FILE__).'/../include/sdk/CBaseCase.php';

class testExportIntegration extends CBaseCase {


	public function template() {
		$sdk = new CSDK();

		return [
			[
				'template' => $sdk->templateCreate([
					'host' => 'h1',
					'groups' => [
						['groupid' => $sdk->hostgroupCreate([
							'name' => 'hg1'
						])],
						['groupid' => $sdk->hostgroupCreate([
							'name' => 'hg2'
						])]
					]
				]),
				'unset' => function(&$export) {unset($export['zabbix_export']['templates'][0]['groups']);},
				'assertions' => [
					'expected' => 'fail',
					'error_message' => 'Application error.',
					'error_details' => 'Invalid tag "/zabbix_export/templates/template(1)": the tag "groups" is missing.'
				]
			],
		];
	}

	/**
	 * Performs request, that creates data set.
	 * Requests an export only for data that were just created.
	 * Deletes data that were just created.
	 *
	 * @param CRequest $request
	 * @param CClientError|null $error  Not null if any error happened during any of requests.
	 *
	 * @return array  Json formatted full export.
	 */
	public function createTemplateExportOnly(CRequest &$template, ?ClientError &$error) {
		$sdk = new CSDK();

		$export = $sdk->configurationExport([
			'format' => 'json',
			'options' => [
				'templates' => [&$template]
			]
		]);

		list($result, $error) = $export($this->client);
		$export->undo($this->client);

		return json_decode($result, true);
	}

	/**
	 * Performs configuration imort request.
	 *
	 * @param array $configuration
	 *
	 * @return array  Client response tuple.
	 */
	public function doConfigurationImport(array $configuration) {
		$sdk = new CSDK();

		$request = $sdk->configurationImport([
			'format' => 'json',
			'source' => json_encode($configuration),
			'rules' => [
				'groups' => [
					'createMissing' => true
				],
				'templates' => [
					'createMissing' => true
				]
			]
		]);

		return $request($this->client);
	}

	/**
	 * @dataProvider template
	 *
	 * @param CRequest $template  Request that builds app state, to test export against.
	 * @param callable $unset  Modifications to be applied to configuration object, before import.
	 * @param array $assertions  Various assertions for how "reimport" should behave.
	 */
	public function testTemplateExportOptionAndMandatoryFields(CRequest $template, callable $unset, array $assertions) {
		$export = $this->createTemplateExportOnly($template, $error);
		$unset($export);

		list($result, $error) = $this->doConfigurationImport($export);

		if ($assertions['expected'] == 'fail') {
			$this->assertNotNull($error);

			if (array_key_exists('expected', $assertions)) {
				$this->assertEquals($assertions['error_message'], $error->reason);
			}

			if (array_key_exists('error_message', $assertions)) {
				$this->assertEquals($assertions['error_message'], $error->reason);
			}

			if (array_key_exists('error_details', $assertions)) {
				$this->assertEquals($assertions['error_details'], $error->data['data']);
			}
		}
		else {
			$this->assertNull($error);
			$this->assertTrue($result);
		}
	}
}

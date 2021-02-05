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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @backup applications
 * @backup items
 * @backup hosts
 */
class testTemplateImport extends CAPITest {

	public function testDeleteMissingForLinkedTemplateApplication() {
		$import_tmpl = file_get_contents(dirname(__FILE__).'/xml/testDeleteMissingForLinkedTemplateApplication.xml');
		$import1 = strtr($import_tmpl, ['@application' => 'App X']);
		$import2 = strtr($import_tmpl, ['@application' => 'App Y']);

		$rules = [
			'applications' => [
				'createMissing' => true
			],
			'items' => [
				'updateExisting' => true,
				'createMissing' => true
			],
			'templateLinkage' => [
				'createMissing' => true
			],
			'templates' => [
				'updateExisting' => true,
				'createMissing' => true
			]
		];

		$this->call('configuration.import', [
			'format' => 'xml',
			'source' => $import1,
			'rules' => $rules
		], null);

		$this->assertNotEquals(0, CDBHelper::getCount('SELECT * FROM applications WHERE name='.zbx_dbstr('App X')));

		$rules['applications']['deleteMissing'] = true;
		$this->call('configuration.import', [
			'format' => 'xml',
			'source' => $import2,
			'rules' => $rules
		], null);

		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM applications WHERE name='.zbx_dbstr('App X')));
	}
}

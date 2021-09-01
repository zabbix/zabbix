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

require_once dirname(__FILE__).'/../include/CWebTest.php';

/**
 * @backup services
 */
class testFormMonitoringServices extends CWebTest
{
	public function getServiceFormData() {
		return [
			[
				[
					'fields' => [
						'Name' => 'Server 1'
					],
					'error_message' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
		];
	}

	/**
	 * @dataProvider getServiceFormData
	 */
	public function testFormMonitoringServices_CreateService($data)
	{
		$this->page->login()->open('zabbix.php?action=service.list');

		// Click on Edit mode button
		$this->query('xpath://label[@for="list_mode_1"]')->waitUntilClickable()->one()->click();

		// Check that "Create service" button is displayed
		$this->assertTrue($this->query('button', 'Create service')->one()->isVisible());

		// Open service create form
		$this->query('xpath://button[@class="js-create-service"]')->waitUntilClickable()->one()->click();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asFluidForm()->one()->waitUntilReady();

		// var_dump($form->getLabels()->asText());

		$form->fill($data['fields']);

		sleep(10);
		$form->submit();
		$this->page->waitUntilReady();
	}



	public function getServiceFormFieldsData()
	{
		return [
			[
				'fields' => [
					'Name',
					'Parent services',
					'Status calculation algorithm',
					'Problem tags',
					'Sort order (0->999)',
					'SLA',
					'Service times',
					'Tags',
					'Child services'
				]
			]
		];
	}

	/**
	 * @dataProvider getServiceFormFieldsData
	 */
	public function testFormMonitoringServices_CheckFormLauout($data)
	{
		$this->page->login()->open('zabbix.php?action=service.list');

		// Click on Edit mode button
		$this->query('xpath://label[@for="list_mode_1"]')->waitUntilClickable()->one()->click();

		// Check that "Create service" button is displayed
		$this->assertTrue($this->query('button', 'Create service')->one()->isVisible());

		// Open service create form
		$this->query('xpath://button[@class="js-create-service"]')->waitUntilClickable()->one()->click();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asFluidForm()->one()->waitUntilReady();

		$formFields = $form->getLabels()->asText();

		for($i = 0; $i < 9; $i++)
		{
			$this->assertEquals($data[$i], $formFields[$i]);
		}


	}
}

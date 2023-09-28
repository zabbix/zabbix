<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


require_once dirname(__FILE__) . '/../../include/CWebTest.php';

class testErrorsInFilterMultiselects extends CWebTest {

	public $filter_labels;

	public static function getCheckDialogsData() {
		return [
			[
				[
					'object' => 'Items',
					'check_object_page' => true
				]
			],
			[
				[
					'object' => 'Triggers'
				]
			],
			[
				[
					'object' => 'Graphs'
				]
			],
			[
				[
					'object' => 'Discovery'
				]
			],
			[
				[
					'object' => 'Web'
				]
			]
		];
	}

	public function testMultiselectDialog($data, $url, $context_name) {
		$this->page->login()->open($url);

		if (CTestArrayHelper::get($data, 'check_object_page', false)) {
			$templates_filter_form = $this->query('name:zbx_filter')->asForm()->one();

			$this->openDialogCheckAndClose($templates_filter_form, $this->filter_labels['context_page'][0],
					$this->filter_labels['context_page'][1], $this->filter_labels['context_page'][2]
			);

			// Fill "Template groups" multiselect.
			$templates_filter_form->getField('Template groups')->asMultiselect()
					->setFillMode(CMultiselectElement::MODE_SELECT)->fill('Templates');

			// Check second multiselect, when "Template groups" is filled.
			$this->openDialogCheckAndClose($templates_filter_form, $this->filter_labels['context_page'][0],
					$this->filter_labels['context_page'][1], $this->filter_labels['context_page'][2]
			);

			$templates_filter_form->query('button:Reset')->waitUntilClickable()->one()->click();
		}

		$this->query('class:list-table')->asTable()->waitUntilPresent()->one()->findRow('Name', $context_name)
				->getColumn($data['object'])->query('tag:a')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		// Check second multiselect, when "Template groups" field is empty.
		$object_filter_form = $this->query('name:zbx_filter')->asForm()->one();
		$this->openDialogCheckAndClose($object_filter_form, $this->filter_labels['object_page'][0],
				$this->filter_labels['object_page'][1], $this->filter_labels['object_page'][2]
		);

		// Fill "Template groups" multiselect.
		$object_filter_form->getField('Template groups')->asMultiselect()->setFillMode(CMultiselectElement::MODE_SELECT)
				->fill('Templates');

		// Check second multiselect, when "Template groups" field is filled.
		$this->openDialogCheckAndClose($object_filter_form, $this->filter_labels['object_page'][0],
				$this->filter_labels['object_page'][1], $this->filter_labels['object_page'][2]
		);

		// Check "Value mapping" dialog.
		if ($data['object'] === 'Items') {
			$value_mapping_dialog = $this->checkMultiselectDialog($object_filter_form, 'Value mapping', 'Value mapping');
			$value_mapping_dialog->getFooter()->query('button:Cancel')->waitUntilClickable()->one()->click();
		}

		$object_filter_form->query('button:Reset')->waitUntilClickable()->one()->click();
	}

	protected function openDialogCheckAndClose($form, $field, $title_1, $title_2) {
		$level_1_dialog = $this->checkMultiselectDialog($form, $field, $title_1);
		$level_2_dialog = $this->checkMultiselectDialog($level_1_dialog, $field, $title_2, true);

		$level_2_dialog->getFooter()->query('button:Cancel')->waitUntilClickable()->one()->click();
		$level_1_dialog->getFooter()->query('button:Cancel')->waitUntilClickable()->one()->click();
	}

	protected function checkMultiselectDialog($form, $field, $title, $sub_dialog = false) {
		$field_container = $sub_dialog ? $form : $form->getFieldContainer($field);
		$field_container->query('button:Select')->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$this->assertEquals($title, $dialog->getTitle());
		$this->assertFalse($dialog->query('xpath:.//*[contains(@class, "msg-bad")]')->exists());

		return $dialog;
	}
}

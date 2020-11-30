<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/../../include/CWebTest.php';

/**
 * Base class for "Hosts and Problems filter save" function tests.
 */
class testFormFilter extends CWebTest {

	/**
	 * Check created filter.
	 *
	 * @param array $data  given data provider
	 * @param string $url  link to page with filters
	 */
	public function checkFilters($data, $url) {
		$this->page->login()->open($url)->waitUntilReady();
		$this->createFilter($data);

		if (array_key_exists('error_message', $data)) {
			return;
		}
		else {
			$table = $this->query('class:list-table')->asTable()->waitUntilReady()->one();
			$text = $this->query('xpath://table[@class="list-table"]/tbody/tr/td')->one()->getText();
			$filtered_rows_count = ($text === 'No data found.') ? 0 : $table->getRows()->count();
			$filter_container = $this->query('xpath://ul[@class="ui-sortable-container ui-sortable"]')->asFilterTab()->one();

			// Checking that data exists after saving filter.
			if (array_key_exists('filter_form', $data)) {
				$form = $this->query('id:tabfilter_'.$data['tab_id'])->asForm()->one();
				$form->checkValue($data['filter_form']);
			}

			// Filter default name is Untitled.
			if (!array_key_exists('Name', $data['filter'])) {
				$data['filter']['Name'] = 'Untitled';
			}

			// Checking that name of filter displayed on the tab.
			$this->assertEquals($data['filter']['Name'], $filter_container->getText());

			// Checking that names displayed on the filter tabs same as in drop down list.
			$filters = $filter_container->getTitles();
			$this->assertEquals($filters, $this->getDropdownList());

			// Checking that name displayed in filter properties.
			$filter_container->getProperties();
			$dialog = COverlayDialogElement::find()->asForm()->all()->last()->waitUntilReady();
			$dialog->checkValue(['Name' => $data['filter']['Name']]);
			$this->query('button:Cancel')->one()->click();

			// Checking that hosts/problems amount displayed near name in filter tab.
			if (array_key_exists('Show number of records', $data['filter'])) {
				$this->query('xpath://a[@class="icon-home tabfilter-item-link"]')->one()->click();
				$this->assertEquals($filtered_rows_count, $this->query('xpath://li[@data-target="tabfilter_'.$data['tab_id'].
						'"]/a')->one()->getAttribute('data-counter'));
			}

			// Checking that dropdown/popup tab works.
			$dropdown = $this->query('class:btn-widget-expand')->asPopupButton()->one();
			$dropdown->fill($data['filter']['Name']);
			$this->assertEquals($data['filter']['Name'], $filter_container->getText());
		}
	}

	/**
	 * Change data in filter form
	 *
	 * @param array $data  data added to filter form
	 * @param string $url  link to page where available to save filter
	 */
	public function updateFilterForm($data, $url) {
		$this->page->login()->open($url)->waitUntilReady();
		$this->createFilter($data);

		// Changing filter data.
		$form = $this->query('id:tabfilter_'.$data['tab_id'])->asForm()->one();
		$form->fill(['Host groups' => ['Zabbix servers']]);
		$this->query('name:filter_apply')->one()->click();
		$this->query('xpath://li[@data-target="tabfilter_0"]/a')->one()->click();
		$this->page->waitUntilReady();

		// Checking that filter name became italic.
		$font_style = $this->query('xpath://a[@class="tabfilter-item-link"]')->one()->getCSSValue('font-style');
		$this->assertEquals('italic', $font_style);

		// Updating filter.
		$this->query('xpath://li[@data-target="tabfilter_'.$data['tab_id'].'"]/a')->one()->click();
		$this->query('button:Update')->one()->click();

		// This time needed for filter to update table with results.
		sleep(1);

		// Getting changed host/problem result and then comparing it with displayed result from dropdown.
		$table = $this->query('class:list-table')->asTable()->waitUntilReady()->one();
		$text = $this->query('xpath://table[@class="list-table"]/tbody/tr/td')->one()->getText();
		$result = ($text === 'No data found.') ? 0 : $table->getRows()->count();
		$this->query('xpath://li[@data-target="tabfilter_0"]/a')->one()->click();
		$this->query('xpath://button[@data-action="toggleTabsList"]')->one()->click();
		$this->page->waitUntilReady();
		$this->assertEquals($result, $this->query('xpath://a[@aria-label="'.$data['filter']['Name'].'"]')
				->one()->getAttribute('data-counter'));

		// Checking that hosts/problems amount in filter displayed near name at the tab changed.
		$this->assertEquals($result, $this->query('xpath://li[@data-target="tabfilter_'.$data['tab_id'].'"]/a')
				->one()->getAttribute('data-counter'));
	}

	/**
	 * Update filter properties.
	 *
	 * @param string $data	data added to filter form
	 * @param string $url	link to page where available to save filter
	 */
	public function updateFilterProperties($data, $url) {
		$this->page->login()->open($url)->waitUntilReady();
		$this->createFilter($data);
		$filter_container = $this->query('xpath://ul[@class="ui-sortable-container ui-sortable"]')->asFilterTab()->one();

		// Changing filter name to empty space.
		$filter_container->getProperties();
		$this->page->waitUntilReady();
		$dialog = COverlayDialogElement::find()->asForm()->all()->last()->waitUntilReady();
		$dialog->fill(['Name' => '']);
		$dialog->submit();
		$message = CMessageElement::find()->one();
		$this->assertTrue($message->hasLine('Incorrect value for field "filter_name": cannot be empty.'));

		// Changing filter name and enabling Show number of records
		$dialog->fill(['Name' => 'updated_filter_name', 'Show number of records' => true]);
		$dialog->submit();
		$this->page->waitUntilReady();

		// Checking that filter name changed, and result amount displayed.
		$this->assertEquals('updated_filter_name', $filter_container->getText());
		$this->query('xpath://li[@data-target="tabfilter_0"]/a')->one()->click();
		$this->assertTrue($this->query('xpath://li[@data-target="tabfilter_1"]/a[@data-counter]')->one()->isValid());
	}

	/**
	 * Delete existing filters.
	 *
	 * @param string $url		link to page where available to save filter
	 */
	public function deleteFilter($url) {
		$this->page->login()->open($url)->waitUntilReady();
		$filter_container = $this->query('xpath://ul[@class="ui-sortable-container ui-sortable"]')->asFilterTab()->one();

		$filters = ['Untitled', 'simple_name', '*;%№:?(', 'кирилица', 'duplicated_name', 'duplicated_name'];
		foreach ($filters as $filter) {
			$filter_container->getProperties($filter);
			$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
			$dialog->query('button:Delete')->one()->click();
			$this->page->acceptAlert();
			$this->page->waitUntilReady();
			array_shift($filters);

			// Checking that deleted filter doesn't exists in filters tab list.
			if ($filters !== []) {
				$this->assertEquals($filters, $filter_container->getTitles());
			}
			else {
				$this->assertEquals(null, $filter_container->getTitles());
			}

			// Checking that deleted filter doesn't exists in filters dropdown list.
			$this->assertEquals($filters, $this->getDropdownList());
		}
	}

	/**
	 * Create filter.
	 *
	 * @param array $data  given data provider
	 */
	public function createFilter($data) {

		// Checking if home tab is selected.
		$selector = 'xpath://li[@data-target="tabfilter_0"]';
		if ($this->query($selector)->one()->getAttribute('class') === 'tabfilter-item-label') {
			$this->query($selector.'/a')->one()->click();
		}

		$this->page->waitUntilReady();
		$home_form = $this->query('xpath://div[@id="tabfilter_0"]/form')->asForm()->one();
		if ($this->page->getTitle() === 'Problems') {
			$home_form->query('id:show_timeline_0')->one()->asCheckbox()->fill(false);
		}

		if (array_key_exists('filter_form', $data)) {
			$home_form->fill($data['filter_form']);
		}

		$this->query('button:Save as')->one()->click();
		$dialog = COverlayDialogElement::find()->asForm()->all()->last()->waitUntilReady();
		$dialog->fill($data['filter']);
		$dialog->submit();

		if (array_key_exists('error_message', $data)) {
			$message = CMessageElement::find()->one();
			$this->assertTrue($message->hasLine($data['error_message']));
		}

		$this->page->waitUntilReady();
	}

	// Return filter TEST names from droplist.
	public function getDropdownList() {
		$this->query('xpath://button[@data-action="toggleTabsList"]')->one()->click();
		$dropdown_filters = CPopupMenuElement::find()->waitUntilVisible()->one()->getItems()->asText();
		array_shift($dropdown_filters);
		return $dropdown_filters;
	}
}

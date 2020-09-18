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
 * Base class for "Test item" function tests.
 */
class testFormFilterSave extends CWebTest {
	/**
	 * Creates filter.
	 *
	 * @param array $data  given data provider
	 * @param string $url  link to page where available to save filter
	 */
	public function createFilter($data, $url) {
		$this->page->login()->open($url);
		if ($this->query('xpath://li[@data-target="tabfilter_0"]')->one()->getAttribute('class') == 'tabfilter-item-label') {
			$this->query('xpath://li[@data-target="tabfilter_0"]/a')->one()->click();
		}

		$this->page->waitUntilReady();
		$home_form = $this->query('xpath://div[@id="tabfilter_0"]/form')->asForm()->one();
		$home_form->fill($data['hosts_filter']);
		$this->query('xpath://button[@name="filter_apply"]')->one()->click();
		$this->query('button:Save as')->one()->click();
		$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$filter_form = $dialog->query('name:tabfilter_form')->asForm()->one();
		$filter_form->fill($data['filter']);
		$filter_form->submit();
		$this->page->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->waitUntilReady()->one();
		if ($this->query('xpath://table[@class="list-table"]/tbody/tr/td')->one()->getText() == 'No data found.') {
			$filtered_rows_count = '0';
		} else {
			$filtered_rows_count = $table->getRows()->count();
		}

		// Checking that name of filter displayed on the tab.
		if ($data['filter']['Name'] == ' ') {
			$this->assertEquals('', $this->query('xpath://li[@data-target="tabfilter_'
					.$data['tab_id'].'"]/a[@class="tabfilter-item-link"]')->one()->getText());
		} else {
			$this->assertEquals($data['filter']['Name'], $this->query('xpath://li[@data-target="tabfilter_'
					.$data['tab_id'].'"]/a[@class="tabfilter-item-link"]')->one()->getText());
		}

		// Checking that hosts amount in filter displayed near name at the tab.
		$this->query('xpath://a[@class="icon-home tabfilter-item-link"]')->one()->click();
		if ($data['filter']['Show number of records'] == true) {
			$this->assertEquals($filtered_rows_count, $this->query('xpath://li[@data-target="tabfilter_'.$data['tab_id']
					.'"]/a')->one()->getAttribute('data-counter'));
		}

		// Checking that added name in filter saved and filter is correct.
		$this->query('xpath://li[@data-target="tabfilter_'.$data['tab_id'].'"]/a')->one()->click();
		$new_form = $this->query('name:zbx_filter')->asForm()->one();
		$new_form->checkValue($data['hosts_filter']);
	}

	/**
	 * Change data in filter form
	 *
	 * @param string $url		link to page where available to save filter
	 * @param array $filtering  data added to filter form
	 */
	public function updateFilterForm($url, $filtering) {
		$this->page->login()->open($url);

		// Checking that dropdown/popup tab works.
		$hosts_popup = $this->query('class:btn-widget-expand')->asPopupButton()->one();
		$hosts_popup->fill('Several things');
		$form = $this->query('id:tabfilter_5')->asForm()->one();
		$form->fill($filtering);
		$this->query('button:Update')->one()->click();
		$this->page->waitUntilReady();

		// Getting changed host result, and then comparing it with displayed result from dropdown.
		$table = $this->query('class:list-table')->asTable()->waitUntilReady()->one();
		$this->page->refresh();
		$this->page->waitUntilReady();
		$filtered_rows_count = $table->getRows()->count();
		$this->query('xpath://a[@class="icon-home tabfilter-item-link"]')->one()->click();
		$this->query('xpath://button[@data-action="toggleTabsList"]')->one()->click();
		$this->assertEquals($filtered_rows_count, $this->query('xpath://a[@aria-label="Several things"]')
				->one()->getAttribute('data-counter'));

		// Checking that hosts amount in filter displayed near name at the tab changed.
		$this->assertEquals($filtered_rows_count, $this->query('xpath://li[@data-target="tabfilter_5"]/a')
				->one()->getAttribute('data-counter'));
	}

	/**
	 * Update filter properties.
	 *
	 * @param string $url	link to page where available to save filter
	 */
	public function updateFilterProperties($url) {
		$this->page->login()->open($url);

		// Checking that result amount displayed near filter name.
		$this->query('xpath://li[@data-target="tabfilter_0"]/a')->one()->click();
		$this->assertEquals('simple name', $this->query('xpath://li[@data-target="tabfilter_2"]/a')->one()->getText());
		$this->query('xpath://li[@data-target="tabfilter_2"]/a[@data-counter]')->one()->isPresent();
		$this->query('xpath://li[@data-target="tabfilter_2"]/a')->one()->click();
		$this->query('xpath://li[@data-target="tabfilter_2"]/a[@class="icon-edit"]')->one()->click();

		// Changing filter name and removing Show number of records.
		$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$filter_form = $dialog->query('name:tabfilter_form')->asForm()->one();
		$filter_form->fill(['Name' => 'Changed name', 'Show number of records' => false]);
		$filter_form->submit();

		// Checking that filter name changed, and result amount not displayed anymore.
		$this->assertEquals('Changed name', $this->query('xpath://li[@data-target="tabfilter_2"]/a')->one()->getText());
		$this->query('xpath://li[@data-target="tabfilter_0"]/a')->one()->click();
		$this->query('xpath://li[@data-target="tabfilter_2"]/a[@data-counter]')->one(false)->isPresent();
	}

	/**
	 * Delete existing filter
	 *
	 * @param string $url	link to page where available to save filter
	 */
	public function filterDelete($url) {
		$this->page->login()->open($url);

		// We compare filter names before delete.
		$before_delete = ['Home', '*;%№:?(', 'Changed name', 'Untitled', 'Untitled', 'Several things'];
		$this->query('xpath://button[@data-action="toggleTabsList"]')->one()->click();
		$this->assertEquals($before_delete, CPopupMenuElement::find()->waitUntilVisible()->one()->getItems()->asText());
		$this->query('xpath://li[@data-target="tabfilter_4"]/a')->one()->click();
		$this->query('xpath://li[@data-target="tabfilter_4"]/a[@class="icon-edit"]')->one()->click();

		// In filter properties press Delete button and remove filter. Then compare with droplist.
		$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$dialog->query('button:Delete')->one()->click();
		$after_delete = ['Home', '*;%№:?(', 'Changed name', 'Untitled', 'Several things'];
		$this->query('xpath://button[@data-action="toggleTabsList"]')->one()->click();
		$this->assertEquals($after_delete, CPopupMenuElement::find()->waitUntilVisible()->one()->getItems()->asText());
	}
}

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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/



class testWidgets extends CWebTest {

	/**
	 * Function which checks that only permitted item types are accessible for widgets.
	 *
	 * @param string    $url		url provided which needs to be opened
	 * @param string    $widget		name of widget type
	 *
	 */
	public function checkAvailableItems($url, string $widget) {
		$this->page->login()->open($url)->waitUntilReady();
		$dialog =  CDashboardElement::find()->one()->waitUntilReady()->edit()->addWidget()->asForm();
		$dialog->fill(['Type' => CFormElement::RELOADABLE_FILL($widget)]);
		$class = 'class:multiselect-control';

		if ($widget === 'Top hosts') {
			$dialog->query('id:add')->one()->waitUntilClickable()->click();
			$class = 'xpath://div[@class="table-forms-td-right"]//div[@class="multiselect-control"]';
		}
		elseif ($widget === 'Clock') {
			$dialog->fill(['Time type' => CFormElement::RELOADABLE_FILL('Host time')]);
			$dialog->query('button:Select')->one()->waitUntilClickable()->click();
		}

		$host_item_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$host_item_dialog->query('button:Select')->one()->waitUntilClickable()->click();
		COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$host_item_dialog->query($class)->asMultiselect()->one()->fill('Host for all item value types');
		COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$this->assertTableDataColumn(['Character item', 'Float item', 'Log item',
				'Text item', 'Unsigned item', 'Unsigned_dependent item'],
				'Name', 'xpath://form[@name="itemform"]//table'
		);
	}
}

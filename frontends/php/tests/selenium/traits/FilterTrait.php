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

require_once dirname(__FILE__).'/../../include/CWebTest.php';

/**
 * Trait for filter related tests.
 */
trait FilterTrait {

	/**
	 * Select tag type. Add new tag, select operator, name and value.
	 *
	 * @param string  $type        tag type And/Or, Or
	 * @param array   $tags        tag operator, name and value
	 */
	public function setTags($type, $tags) {
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$table = $form->getField('Tags');

		$table->getRow(0)->query('class:radio-segmented')->asSegmentedRadio()->one()->select($type);

		$last = count($tags) - 1;
		foreach ($tags as $i => $tag) {
			$tag_row = $table->getRow($i + 1);
			if (array_key_exists('name', $tag)) {
				$tag_row->getColumn(0)->query('tag:input')->one()->fill($tag['name']);
			}
			$tag_row->getColumn(1)->query('class:radio-segmented')->asSegmentedRadio()->one()->select($tag['operator']);

			if (array_key_exists('value', $tag)) {
				$tag_row->getColumn(2)->query('tag:input')->one()->fill($tag['value']);
			}

			if ($i !== $last) {
				$table->query('button:Add')->one()->click();
			}
		}

		return $form;
	}
}

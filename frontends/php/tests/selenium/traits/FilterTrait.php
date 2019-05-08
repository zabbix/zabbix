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
	 * Select type of tags. Add new tag, select tag operator, name and value.
	 *
	 * @param string  $type        type of tags And/Or, Or
	 * @param array   $tags        tag operator, name and value
	 *
	 * @return CFormElement
	 */
	public function setTags($type, $tags) {
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$table = $form->getField('Tags')->asMultifieldTable([
			'mapping' => [
				[
					'name' => 'name',
					'selector' => 'xpath:./input',
					'class' => 'CElement'
				],
				[
					'name' => 'operator',
					'selector' => 'class:radio-list-control',
					'class' => 'CSegmentedRadioElement'
				],
				[
					'name' => 'value',
					'selector' => 'xpath:./input',
					'class' => 'CElement'
				]
			]
		]);

		$table->query('id:filter_evaltype')->asSegmentedRadio()->one()->select($type);

		foreach ($tags as $i => $tag) {
			if ($i === 0) {
				$table->updateRow($i, $tag);
			}
			else {
				$table->addRow($tag);
			}
		}

		return $form;
	}
}

<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Base controller for the "Problems" page and the "Problems" asynchronous refresh page.
 */
abstract class CControllerProblem extends CController {

	/**
	 * Validate input of filter inventory fields.
	 *
	 * @return bool
	 */
	protected function validateInventory(): bool {
		if (!$this->hasInput('filter_inventory')) {
			return true;
		}

		$ret = true;
		foreach ($this->getInput('filter_inventory') as $filter_inventory) {
			if (count($filter_inventory) != 2
					|| !array_key_exists('field', $filter_inventory) || !is_string($filter_inventory['field'])
					|| !array_key_exists('value', $filter_inventory) || !is_string($filter_inventory['value'])) {
				$ret = false;
				break;
			}
		}

		return $ret;
	}

	/**
	 * Validate values of filter tags input fields.
	 *
	 * @return bool
	 */
	protected function validateTags(): bool {
		if (!$this->hasInput('filter_tags')) {
			return true;
		}

		$ret = true;
		foreach ($this->getInput('filter_tags') as $filter_tag) {
			if (count($filter_tag) != 3
					|| !array_key_exists('tag', $filter_tag) || !is_string($filter_tag['tag'])
					|| !array_key_exists('value', $filter_tag) || !is_string($filter_tag['value'])
					|| !array_key_exists('operator', $filter_tag) || !is_string($filter_tag['operator'])) {
				$ret = false;
				break;
			}
		}

		return $ret;
	}
}

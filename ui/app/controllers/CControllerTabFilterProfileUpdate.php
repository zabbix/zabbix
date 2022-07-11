<?php declare(strict_types = 0);
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
 * Controller to update tab filter. Handles following events:
 * - select tab;
 * - expand/collapse active tab;
 * - update filter properties;
 * - save tab order.
 */
class CControllerTabFilterProfileUpdate extends CController {

	public static $namespaces = [
		CControllerHost::FILTER_IDX => CControllerHost::FILTER_FIELDS_DEFAULT,
		CControllerProblem::FILTER_IDX => CControllerProblem::FILTER_FIELDS_DEFAULT,
		CControllerLatest::FILTER_IDX => CControllerLatest::FILTER_FIELDS_DEFAULT
	];

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function checkInput() {
		$fields = [
			'idx' =>		'required|string',
			'value_int' =>	'int32',
			'value_str' =>	'string',
			'idx2' =>		'id'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$idx_cunks = explode('.', $this->getInput('idx'));
			$property = array_pop($idx_cunks);
			$idx = implode('.', $idx_cunks);
			$supported = ['selected', 'expanded', 'properties', 'taborder'];

			$ret = (in_array($property, $supported) && array_key_exists($idx, static::$namespaces));

			if ($property === 'selected' || $property === 'expanded') {
				$ret = ($ret && $this->hasInput('value_int'));
			}
			else if ($property === 'properties' || $property === 'taborder') {
				$ret = ($ret && $this->hasInput('value_str'));
			}
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseData(['main_block' => '']));
		}

		return $ret;
	}

	protected function doAction() {
		$data = $this->getInputAll() + [
			'value_int' => 0,
			'value_str' => '',
			'idx2' => 0
		];
		$idx_cunks = explode('.', $this->getInput('idx'));
		$property = array_pop($idx_cunks);
		$idx = implode('.', $idx_cunks);
		$defaults = static::$namespaces[$idx];

		if (array_key_exists('from', $defaults) || array_key_exists('to', $defaults)) {
			$defaults += [
				'from' => 'now-'.CSettingsHelper::get(CSettingsHelper::PERIOD_DEFAULT),
				'to' => 'now'
			];
		}

		$filter = (new CTabFilterProfile($idx, $defaults))->read();

		switch ($property) {
			case 'selected':
				$dynamictabs = count($filter->tabfilters);

				if ($data['value_int'] >= 0 && $data['value_int'] < $dynamictabs) {
					$filter->selected = (int) $data['value_int'];
				}

				break;

			case 'properties':
				$properties = [];
				parse_str($this->getInput('value_str'), $properties);
				$filter->setTabFilter($this->getInput('idx2'), $this->cleanProperties($properties));

				break;

			case 'taborder':
				$filter->sort($this->getInput('value_str'));

				break;

			case 'expanded':
				$filter->expanded = ($data['value_int'] > 0);

				break;
		}

		$filter->update();

		$data += [
			'property' => $property,
			'idx' => $idx
		];
		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}

	/**
	 * Clean fields data removing empty initial elements.
	 *
	 * @param array $properties  Array of submitted fields data.
	 *
	 * @return array
	 */
	protected function cleanProperties(array $properties): array {
		if (array_key_exists('tags', $properties)) {
			$tags = array_filter($properties['tags'], function ($tag) {
				return !($tag['tag'] === '' && $tag['value'] === '');
			});

			if ($tags) {
				$properties['tags'] = array_values($tags);
			}
			else {
				unset($properties['tags']);
			}
		}

		if (array_key_exists('inventory', $properties)) {
			$inventory = array_filter($properties['inventory'], function ($field) {
				return ($field['value'] !== '');
			});

			if ($inventory) {
				$properties['inventory'] = array_values($inventory);
			}
			else {
				unset($properties['inventory']);
			}
		}

		return $properties;
	}
}

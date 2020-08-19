<?php declare(strict_types=1);
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


class CControllerTabFilterProfileUpdate extends CController {

	public static $namespaces = [
		CControllerHost::FILTER_IDX => CControllerHost::FILTER_FIELDS_DEFAULT,
		CControllerProblem::FILTER_IDX => CControllerProblem::FILTER_FIELDS_DEFAULT
	];

	public function init() {
		$this->disableSIDvalidation();
	}

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
				$ret = $ret && $this->hasInput('value_int');
			}
			else if ($property === 'properties' || $property === 'taborder') {
				$ret = $ret && $this->hasInput('value_str');
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
		$filter = (new CTabFilterProfile($idx, static::$namespaces[$idx]))->read();

		switch ($property) {
			case 'selected':
				$dynamictabs = count($filter->tabfilters);

				if ($data['value_int'] >= 0 && $data['value_int'] < $dynamictabs) {
					$filter->selected = (int) $data['value_int'];
					$filter->expanded = true;
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
			$tags = [];

			foreach ($properties['tags'] as $tag) {
				if ($tag['tag'] !== '' && $tag['value'] !== '') {
					$tags[] = $tag;
				}
			}

			$properties['tags'] = $tags;
		}

		return $properties;
	}
}

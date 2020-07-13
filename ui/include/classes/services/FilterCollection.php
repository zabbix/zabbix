<?php declare(strict_types = 1);

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

namespace Services;

use DB;
use CArrayHelper;
use Services\DataProviders\AbstractDataProvider;

class FilterCollection {

	/**
	 * Unique id of collection. Is used as "Idx" when saving collection.
	 */
	protected $collectionid;

	/**
	 * Array of filter collection data providers. Data provider fields:
	 *
	 * profileid    contains profiles.profileid database field value.
	 * active       contains 1 for active data provider, 0 otherwise.
	 * order        contains zero based order of data providers, data provider with order 0 is considered as 'Home'.
	 * type         contains data provider registered type in DataProviderFactory.
	 * fields       contains data provider field values which are different from default values.
	 * label        (optional) data provider visual name, tab name if not set tab is considered as 'Home'.
	 * show_count   (optional) shold the data provider report rows count when rows count for data providers is requested.
	 * from         (optional) custom time range start time, time can be in relative or absolute form.
	 * to           (optional) custom time range end time, time can be in relative or absolute form.
	 */
	public $data_providers = [];

	/**
	 * DataProviderFactory registered data provider identifier.
	 * @var string $default_provider
	 */
	public $default_provider;

	/**
	 * Active data provider instance.
	 *
	 * @var AbstractDataProvider $active_provider
	 */
	public $active_provider;

	/**
	 * Create filter collectioin instance.
	 *
	 * @param int    $userid         Filter collection owner user id.
	 * @param string $collectionid   Unique string id of colelction.
	 */
	public function __construct($userid, string $collectionid) {
		$this->collectionid = $collectionid;
		$this->data_providers = [];
		$data_providers = $this->readProfile($userid);
		$default = [
			'type' => $this->default_provider,
			'title' => '',
			'active' => 1,
			'fields' => [],
			'show_count' => false,
		];

		if (!$data_providers) {
			$data_providers = [$default];
		}

		foreach ($data_providers as $data_provider) {
			$data_provider['instance'] = $this->getDataProviderInstance($data_provider);
			$data_provider['default'] = is_a($data_provider['instance'], AbstractDataProvider::class)
				? $data_provider['instance']->getFieldsDefaults() : [];
			$this->data_providers[] = $data_provider + $default;
		}
	}

	/**
	 * Get data for every filter in collection.
	 *
	 * @return array
	 */
	public function getDataProvidersArray(): array {
		$result = [];

		foreach ($this->data_providers as $data_provider) {
			$instance = $data_provider['instance'];

			$result[] = [
				'active' => $data_provider['active'],
				'type' => $data_provider['type'],
				'label' => $data_provider['label'],
				'fields' => $data_provider['fields'],
				'default' => $data_provider['default'],
				'show_count' => $data_provider['show_count'],
				'data' => $instance ? $data_provider['instance']->getTemplateData() : null,
				'template' => $instance ? $data_provider['instance']->template_file : null
			];
		}

		return $result;
	}

	/**
	 * Set default data provider for filter collection.
	 *
	 * @param string $provider    Registered identifier in DataProviderFactory.
	 */
	public function setDefaultProvider(string $provider) {
		$this->default_provider = $provider;

		return $this;
	}

	public function getDataProviderInstance(array $options) {
		/** @var AbstractDataProvider $instance */
		$id = array_key_exists('profileid', $options) ? $options['profileid'] : 'home';
		$instance = DataProviderFactory::create($options['type'], $id);
		$instance->updateFields($options['fields'] ? $options['fields'] : $instance->getFieldsDefaults());

		return $instance;
	}

	/**
	 * Get active data provider instance. If no active data provider were found default provider will be returned.
	 */
	public function getActiveDataProvider() {
		$instance = null;

		foreach ($this->data_providers as $data_provider) {
			if ($data_provider['active']) {
				$instance = $data_provider['instance'];
				break;
			}

			$data_provider = [];
		}

		return $instance;
	}

	/**
	 * Get data providers input data for filter collection.
	 *
	 * @param int    $userid        Filter collection owner user id.
	 */
	public function readProfile($userid) {
		/**
		 * profiles table fields usage:
		 * idx          contains unique filter collection id.
		 * idx2         contains 1 for active data provider 0 otherwise, data provider to be used when getData is requested.
		 * source       contains data provider unique id for current filter collection.
		 * value_int    contains order of data provider, zero based integer, visual tab order.
		 * value_str    contains json data for every data provider in this filters collection.
		 */
		$data_providers = DB::select('profiles', [
			'output' => ['profileid', 'source', 'value_int', 'value_str', 'idx2'],
			'filter' => [
				'idx' => $this->collectionid,
				'userid' => $userid
			]
		]);
		CArrayHelper::sort($data_providers, ['value_int']);

		foreach ($data_providers as &$data_provider) {
			$json = json_decode($data_provider['value_str'], true);
			unset($data_provider['value_str']);

			if (is_array($json)) {
				// json properties: type, fields, show_count, label, from, to.
				$data_provider = $json + $data_provider;
			}
		}
		unset($data_provider);

		return CArrayHelper::renameObjectsKeys($data_providers, [
			'idx2' 		=> 'active',
			'value_int' => 'order'
		]);
	}

	/**
	 * Update profile settings of desired data provider from filter collection.
	 *
	 * @param int $userid                              Filter collection owner user id.
	 * @param AbstractDataProvider $provider_instance  Data provider instance to update.
	 */
	public function updateProfile($userid, $provider_instance) {
		$fields = $provider_instance->getFieldsModified();

		if (!$fields) {
			return;
		}

		$data_providers = $this->data_providers;
		$data_providers[] = [
			'type' => $this->default_provider,
			'active' => 1,
			'order' => 0,
			'fields' => [],
			'show_count' => false
		];

		foreach ($data_providers as $data_provider) {
			if ($data_provider['profileid'] !== $provider_instance->id) {
				continue;
			}

			break;
		}

		if (!$data_provider) {
			// Such data provider does not exists, ignore.
			return;
		}

		$options = [
			'type' => $provider_instance::PROVIDER_TYPE,
			'fields' => $fields
		] + array_intersect_key($data_provider, [
			'label' => '',
			'show_count' => '',
			'from' => '',
			'to' => '',
		]);

		if (array_key_exists('profileid', $data_provider)) {
			// profiles row already exists, update.
			DB::update('profiles', [
				'where' => [
					'idx' => $this->collectionid,
					'userid' => $userid
				],
				'values' => [
					'value_int' => $data_provider['order'],
					'value_str' => json_encode($options)
				]
			]);
		}
		else {
			// profile row does not exists, create.
			DB::insert('profiles', [[
				'profileid' => get_dbid('profiles', 'profileid'),
				'userid' => $userid,
				'idx' => $this->collectionid,
				'idx2' => 1,
				'value_int' => $data_provider['order'],
				'value_str' => json_encode($options)
			]], false);
		}
	}
}

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

use Exception;
use Services\DataProviders\HostDataProvider;
use Services\DataProviders\AbstractDataProvider;

class DataProviderFactory {

	/**
	 * Mapping of data provider unique type and it FQ class name .
	 */
	static protected $data_providers = [
		HostDataProvider::PROVIDER_TYPE => HostDataProvider::class,
	];

	/**
	 * Register new data provider.
	 *
	 * @param string $class    FQ class name of data provider.
	 * @throws Exception
	 */
	static public function register(string $class) {
		if (!class_exists($class, true)) {
			throw new Exception(sprintf('Data provider class %s is not found.', $class));
		}

		if (!defined($class.'::PROVIDER_TYPE')) {
			throw new Exception(sprintf('Data provider %s PROVIDER_TYPE is not defined.', $class));
		}

		static::$data_providers[$class::PROVIDER_TYPE] = $class;
	}

	/**
	 * Create new instance of data provider by data provider type if it is registered passing $id to constructor.
	 *
	 * @param string $type    Registered data provider type.
	 * @param string $id      Unique id used in filters to distinguish by multiple instances of same data provider.
	 * @throws Exception
	 */
	static public function create(string $type, string $id) {
		if (!array_key_exists($type, static::$data_providers)) {
			throw new Exception(sprintf('Data provider %s is not registered.', $id));
		}

		$object = new static::$data_providers[$type]($id);

		if (!is_a($object, AbstractDataProvider::class)) {
			throw new Exception(sprintf('Data provider %s should extend AbstractDataProvider.',
				get_class($object))
			);
		}

		return $object;
	}
}

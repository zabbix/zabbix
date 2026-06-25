<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


abstract class ApmDb {

	protected static ?CurlHandle $connection = null;

	protected array $settings;

	public function __construct(array $settings) {
		$this->settings = $settings;
	}

	public static function create(array $settings): ApmDbClickHouse {
		return match ($settings['type']) {
			ZBX_DB_CLICKHOUSE => new ApmDbClickHouse($settings)
		};
	}

	protected function getConnection(): CurlHandle {
		if (!self::$connection) {
			self::$connection = $this->connect();
		}

		return self::$connection;
	}

	abstract protected function connect(): CurlHandle;

	abstract public function select(string $sql): array;

	abstract public function test(): bool;
}

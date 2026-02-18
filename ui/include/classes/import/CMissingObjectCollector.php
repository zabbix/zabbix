<?php
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


/**
 * Class for collecting missing referred objects while preparing for the import or doing the import of new data.
 */
class CMissingObjectCollector {

	private array $hosts = [];

	private array $hostgroups = [];

	private array $items = [];

	private array $graphs = [];

	private array $services = [];

	private array $slas = [];

	private array $actions = [];

	private array $mediatypes = [];

	private array $sysmaps = [];

	private array $users = [];

	public function addHost(string $name): void {
		$this->hosts[$name] = true;
	}

	public function addHostGroup(string $name): void {
		$this->hostgroups[$name] = true;
	}

	public function addItem(string $key, string $host): void {
		$this->items[$host][$key] = true;
	}

	public function addGraph(string $name, string $host): void {
		$this->graphs[$host][$name] = true;
	}

	public function addService(string $name): void {
		$this->services[$name] = true;
	}

	public function addSla(string $name): void {
		$this->slas[$name] = true;
	}

	public function addAction(string $name): void {
		$this->actions[$name] = true;
	}

	public function addMediaType(string $name): void {
		$this->mediatypes[$name] = true;
	}

	public function addSysmap(string $name): void {
		$this->sysmaps[$name] = true;
	}

	public function addUser(string $username): void {
		$this->users[$username] = true;
	}

	public function getMissingObjects(): array {
		$missing_objects = [];

		if ($hosts = self::toSortedObjects($this->hosts, ['name'])) {
			$missing_objects['hosts'] = $hosts;
		}

		if ($hostgroups = self::toSortedObjects($this->hostgroups, ['name'])) {
			$missing_objects['hostgroups'] = $hostgroups;
		}

		if ($items = self::toSortedObjects($this->items, ['host', 'key'])) {
			$missing_objects['items'] = $items;
		}

		if ($graphs = self::toSortedObjects($this->graphs, ['host', 'name'])) {
			$missing_objects['graphs'] = $graphs;
		}

		if ($services = self::toSortedObjects($this->services, ['name'])) {
			$missing_objects['services'] = $services;
		}

		if ($slas = self::toSortedObjects($this->slas, ['name'])) {
			$missing_objects['slas'] = $slas;
		}

		if ($actions = self::toSortedObjects($this->actions, ['name'])) {
			$missing_objects['actions'] = $actions;
		}

		if ($mediatypes = self::toSortedObjects($this->mediatypes, ['name'])) {
			$missing_objects['mediatypes'] = $mediatypes;
		}

		if ($sysmaps = self::toSortedObjects($this->sysmaps, ['name'])) {
			$missing_objects['sysmaps'] = $sysmaps;
		}

		if ($users = self::toSortedObjects($this->users, ['username'])) {
			$missing_objects['users'] = $users;
		}

		return $missing_objects;
	}

	protected static function toSortedObjects(array $data, array $fields): array {
		$data = [['_sub' => $data]];

		foreach ($fields as $field) {
			$next_data = [];

			foreach ($data as $datum) {
				$datum_common = $datum;
				unset($datum_common['_sub']);

				foreach ($datum['_sub'] as $sub_field => $sub_data) {
					$next_sub = is_array($sub_data) ? ['_sub' => $sub_data] : [];
					$next_data[] = $datum_common + [$field => $sub_field] + $next_sub;
				}
			}

			$data = $next_data;
		}

		CArrayHelper::sort($data, $fields);

		return $data;
	}
}

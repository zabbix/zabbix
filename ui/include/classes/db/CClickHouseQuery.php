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


class CClickHouseQuery {

	protected array $select = [];
	protected array $from = [];
	protected array $join = [];
	protected array $where = [];
	protected array $group = [];
	protected array $order = [];
	protected ?int $limit = null;
	protected array $param = [];

	protected array $linked_queries = [];

	public function __construct() {}

	public function select(string $select, ?string $alias = null): static {
		$this->select[] = $alias !== null ? $select.' AS `'.$alias.'`' : $select;

		return $this;
	}

	public function from(string $from, ?string $alias = null): static {
		$this->from[] = $from.($alias !== null ? ' AS '.$alias : '');

		return $this;
	}

	public function join(string $join, string $on): static {
		$this->join[] = $join.' ON '.$on;

		return $this;
	}

	public function where(string $where, array $params = []): static {
		$this->where[] = $where;

		$this->params($params);

		return $this;
	}

	public function group(string $group): static {
		$this->group[] = $group;

		return $this;
	}

	public function order(string $order_by, $sort_order = ZBX_SORT_UP): static {
		$this->order[] = $order_by.($sort_order === ZBX_SORT_DOWN ? ' '.ZBX_SORT_DOWN : '');

		return $this;
	}

	public function limit(?int $limit): static {
		$this->limit = $limit;

		return $this;
	}

	public function param(string $name, mixed $value): static {
		$this->param[$name] = $value;

		return $this;
	}

	public function params(array $params): static {
		foreach ($params as $name => $value) {
			$this->param($name, $value);
		}

		return $this;
	}

	public function linkQuery(CClickHouseQuery $query): static {
		$this->linked_queries[] = $query;

		return $this;
	}

	public function getSql(): string {
		return
			'SELECT '.implode(',', $this->select).
			' FROM '.implode(',', $this->from).
			($this->join ? ' '.implode(' ', $this->join) : '').
			($this->where ? ' WHERE '.implode(' AND ', $this->where) : '').
			($this->group ? ' GROUP BY '.implode(',', $this->group) : '').
			($this->order ? ' ORDER BY '.implode(',', $this->order) : '').
			($this->limit !== null ? ' LIMIT '.$this->limit : '');
	}

	public function getParams(): array {
		$params = $this->param;

		foreach ($this->linked_queries as $query) {
			$params += $query->getParams();
		}

		return $params;
	}
}

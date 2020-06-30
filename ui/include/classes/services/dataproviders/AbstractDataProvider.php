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


namespace Services\DataProviders;

abstract class AbstractDataProvider {

	/**
	 * Unique string id, is set by class implementing collection of data providers.
	 */
	public $id;

	/**
	 * Associative array of fields.
	 */
	public $fields = [];

	/**
	 * @param string $id    Unique string, is set by class implementing collection of data providers.
	 */
	public function __construct(string $id) {
		$this->id = $id;
		$this->fields = $this->getFieldsDefaults();
	}

	/**
	 * Get results found by data provider.
	 */
	abstract public function getData(): array;

	/**
	 * Get count of results found by data provider.
	 */
	abstract public function getCount(): int;

	/**
	 * Get fields default values.
	 */
	abstract public function getFieldsDefaults(): array;

	/**
	 * Get data provider input.
	 */
	public function getFields(): array {
		return $this->fields;
	}

	/**
	 * Set data provider input, passed input will be merged with exisitng fields values.
	 *
	 * @param array $input    Array of filter fields for data provider input.
	 */
	public function updateFields(array $input) {
		$this->fields = array_merge($this->fields, $input);

		return $this;
	}

	/**
	 * Get paging object.
	 */
	public function getPaging() {
		return $this->paging;
	}

	/**
	 * Get data provider template file.
	 */
	public function getTemplateFile(): string {
		return $this->template_file;
	}

	/**
	 * Get data provider fields modified by latest updateFields call.
	 */
	public function getFieldsModified(): array {
		$modified = [];

		foreach ($this->getFieldsDefaults() as $key => $value) {
			if ($this->fields[$key] !== $value) {
				$modified[$key] = $this->fields[$key];
			}
		}

		return $modified;
	}
}

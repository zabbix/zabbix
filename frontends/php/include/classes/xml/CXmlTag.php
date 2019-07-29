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


/**
 * Abstract class for tags.
 */
abstract class CXmlTag implements CXmlTagInterface, CExportXmlTagInterface, CImportXmlTagInterface {

	/**
	 * Class tag.
	 *
	 * @var string
	 */
	protected $tag;

	/**
	 * Data key.
	 *
	 * @var string
	 */
	protected $key;

	/**
	 * Tag required.
	 *
	 * @var boolean
	 */
	protected $is_required = false;

	/**
	 * Callback for export method.
	 *
	 * @var callable
	 */
	protected $export_handler;

	/**
	 * Callback for import method.
	 *
	 * @var callable
	 */
	protected $import_handler;

	/**
	 * Class constructor.
	 *
	 * @param string $tag
	 */
	public function __construct($tag) {
		$this->setTag($tag);
	}

	public function setTag($tag) {
		$this->tag = $tag;

		return $this;
	}

	public function getTag() {
		return $this->tag;
	}

	public function setRequired() {
		$this->is_required = true;

		return $this;
	}

	public function isRequired() {
		return $this->is_required;
	}

	public function setKey($key) {
		$this->key = $key;

		return $this;
	}

	public function getKey() {
		return $this->key;
	}

	public function setExportHandler(callable $func) {
		$this->export_handler = $func;

		return $this;
	}

	public function getExportHandler() {
		return $this->export_handler;
	}

	public function setImportHandler(callable $func) {
		$this->import_handler = $func;

		return $this;
	}

	public function getImportHandler() {
		return $this->import_handler;
	}
}

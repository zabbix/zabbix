<?php
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


class CControllerResponseData extends CControllerResponse {

	private $data;
	private $title = null;
	private $file_name = null;

	/**
	 * @var bool $view_enabled  true - send view and layout; false - send layout only.
	 */
	private $view_enabled = true;

	public function __construct($data) {
		$this->data = $data;
	}

	public function getData() {
		return $this->data;
	}

	public function setTitle($title) {
		$this->title = $title;
	}

	public function getTitle() {
		return $this->title;
	}

	public function setFileName($file_name) {
		$this->file_name = $file_name;
	}

	public function getFileName() {
		return $this->file_name;
	}

	/**
	 * Prohibits sending view.
	 */
	public function disableView() {
		$this->view_enabled = false;

		return $this;
	}

	/**
	 * Returns current value of view_enabled variable.
	 *
	 * @return bool
	 */
	public function isViewEnabled() {
		return $this->view_enabled;
	}
}

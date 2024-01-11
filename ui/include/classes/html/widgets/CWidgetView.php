<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


class CWidgetView extends CObject {

	private array $data;

	private array $vars = [];

	public function __construct($data) {
		parent::__construct();

		$this->data = $data;
	}

	public function setVar(string $name, $value): self {
		$this->vars[$name] = $value;

		return $this;
	}

	/**
	 * @throws JsonException
	 */
	public function show($destroy = true): void {
		$output = [];

		if (array_key_exists('name', $this->data)) {
			$output['name'] = $this->data['name'];
		}

		if ($this->items) {
			$output['body'] = implode('', $this->items);
		}

		foreach ($this->vars as $name => $value) {
			$output[$name] = $value;
		}

		if ($messages = get_and_clear_messages()) {
			$output['messages'] = array_column($messages, 'message');
		}

		if (array_key_exists('user', $this->data) && $this->data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$output['debug'] = CProfiler::getInstance()->make()->toString();
		}

		echo json_encode($output, JSON_THROW_ON_ERROR);
	}
}

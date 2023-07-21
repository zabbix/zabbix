<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


class CPatternSelect extends CMultiSelect {

	/**
	 * Search method used for auto-suggestions.
	 */
	const SEARCH_METHOD = 'patternselect.get';

	public function __construct(array $options = []) {
		parent::__construct($options);

		// Reset numeric IDs and use names as unique identifiers.
		$data_params = $this->getAttribute('data-params');
		$params = json_decode($data_params, true);

		if (array_key_exists('data', $params) && $params['data']) {
			foreach ($params['data'] as &$item) {
				$item = [
					'name' => $item,
					'id' => $item
				];
			}
			unset($item);
		}

		$this->setAttribute('data-params', json_encode($params));
	}

	protected function mapOptions(array $options): array {
		$wildcard_allowed = false;

		if (array_key_exists('wildcard_allowed', $options) && $options['wildcard_allowed']) {
			$wildcard_allowed = true;
			unset($options['wildcard_allowed']);
		}

		$options = parent::mapOptions($options);
		$options['popup']['parameters']['patternselect'] = '1';

		if ($wildcard_allowed) {
			$options['objectOptions']['wildcard_allowed'] = true;
		}

		return $options;
	}

	public function setEnabled($enabled) {
		$data_params = $this->getAttribute('data-params');
		$params = json_decode($data_params, true);

		$params['disabled'] = !$enabled;

		$this->setAttribute('data-params', json_encode($params));
		$this->setAttribute('aria-disabled', $enabled ? null : 'true');

		return $this;
	}
}

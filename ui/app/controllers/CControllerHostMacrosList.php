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


class CControllerHostMacrosList extends CController {

	public const DISCOVERY_STATE_AUTOMATIC = 0x1;
	public const DISCOVERY_STATE_CONVERTING = 0x2;
	public const DISCOVERY_STATE_MANUAL = 0x3;

	public const MACRO_TEXTAREA_PARENT = 'macro-textarea-parent';

	/**
	 * @var array  Array of parent host defined macros.
	 */
	protected $parent_macros = [];

	protected function checkInput() {
		$fields = [
			'macros'				=> 'array',
			'show_inherited_macros' => 'required|in 0,1',
			'templateids'			=> 'array_db hosts.hostid',
			'readonly'				=> 'required|in 0,1',
			'parent_hostid'			=> 'id'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		$allow = ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN);

		if ($allow && $this->hasInput('parent_hostid')) {
			$parent_host = API::Host()->get([
				'output' => ['hostid'],
				'hostids' => [$this->getInput('parent_hostid')]
			]);
			$allow = (bool) reset($parent_host);

			if (!$allow) {
				$parent_template = API::Template()->get([
					'output' => ['hostid'],
					'templateids' => [$this->getInput('parent_hostid')]
				]);

				return (bool) reset($parent_template);
			}
		}

		return $allow;
	}

	protected function doAction() {
		$macros = $this->getInput('macros', []);
		$show_inherited_macros = (bool) $this->getInput('show_inherited_macros', 0);
		$readonly = (bool) $this->getInput('readonly', 0);
		$parent_hostid = $this->hasInput('parent_hostid') ? $this->getInput('parent_hostid') : null;

		if ($macros) {
			$macros = cleanInheritedMacros($macros);

			// Remove empty new macro lines.
			$macros = array_filter($macros, function ($macro) {
				$keys = array_flip(['hostmacroid', 'macro', 'value', 'description']);

				return (bool) array_filter(array_intersect_key($macro, $keys));
			});
		}

		if ($show_inherited_macros) {
			$macros = mergeInheritedMacros($macros,
				getInheritedMacros($this->getInput('templateids', []), $parent_hostid)
			);
		}

		$macros = array_values(order_macros($macros, 'macro'));

		if (!$macros && !$readonly) {
			$macro = ['macro' => '', 'value' => '', 'description' => '', 'type' => ZBX_MACRO_TYPE_TEXT];
			if ($show_inherited_macros) {
				$macro['inherited_type'] = ZBX_PROPERTY_OWN;
			}
			$macros[] = $macro;
		}

		foreach ($macros as &$macro) {
			if (!array_key_exists('discovery_state', $macro)) {
				$macro['discovery_state'] = self::DISCOVERY_STATE_MANUAL;
			}

			self::addMacroOriginalValues($macro);
		}
		unset($macro);

		$data = [
			'macros' => $macros,
			'show_inherited_macros' => $show_inherited_macros,
			'readonly' => $readonly,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		if ($parent_hostid !== null) {
			$data['parent_hostid'] = $parent_hostid;
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	/**
	 * Create array of original macro values from input fields.
	 *
	 * @param array  $macro
	 * @param string $macro['original_value']
	 * @param string $macro['original_description']
	 * @param string $macro['original_macro_type']
	 */
	protected static function addMacroOriginalValues(array &$macro) {
		if ($macro['discovery_state'] == self::DISCOVERY_STATE_MANUAL) {
			return;
		}

		$field_keys_map = [
			'original_value' => 'value',
			'original_description' => 'description',
			'original_macro_type' => 'type'
		];

		$macro['original'] = array_intersect_key($macro, $field_keys_map);
		$macro['original'] = CArrayHelper::renameKeys($macro['original'], $field_keys_map);

		foreach (array_keys($field_keys_map) as $key) {
			unset($macro[$key]);
		}
	}
}

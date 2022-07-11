<?php declare(strict_types = 0);
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


class CHostAvailability extends CTag {

	public const LABELS = [
		INTERFACE_TYPE_AGENT => 'ZBX',
		INTERFACE_TYPE_SNMP => 'SNMP',
		INTERFACE_TYPE_IPMI => 'IPMI',
		INTERFACE_TYPE_JMX => 'JMX'
	];

	public const COLORS = [
		INTERFACE_AVAILABLE_UNKNOWN => ZBX_STYLE_STATUS_GREY,
		INTERFACE_AVAILABLE_TRUE => ZBX_STYLE_STATUS_GREEN,
		INTERFACE_AVAILABLE_FALSE => ZBX_STYLE_STATUS_RED,
		INTERFACE_AVAILABLE_MIXED => ZBX_STYLE_STATUS_YELLOW
	];

	protected $type_interfaces = [];

	public function __construct() {
		parent::__construct('div', true);
		$this->addClass(ZBX_STYLE_STATUS_CONTAINER);
	}

	/**
	 * Set host interfaces.
	 *
	 * @param array  $interfaces                 Array of arrays with all host interfaces.
	 * @param int    $interfaces[]['type']       Type of interface, INTERFACE_TYPE_* constant.
	 * @param string $interfaces[]['interface']  Hint table 'Interface' column value.
	 * @param string $interfaces[]['detail']     Hint table 'Interface' column additional details string.
	 * @param int    $interfaces[]['available']  Hint table 'Status' column value, INTERFACE_AVAILABLE_* constant.
	 * @param string $interfaces[]['error']      Hint table 'Error' column value.
	 *
	 * @return CHostAvailability
	 */
	public function setInterfaces(array $interfaces): CHostAvailability {
		$this->type_interfaces = array_fill_keys(array_keys(static::LABELS), []);

		foreach ($interfaces as $interface) {
			$this->type_interfaces[$interface['type']][] = $interface;
		}

		return $this;
	}

	/**
	 * Get host interfaces hint table HTML object.
	 *
	 * @param array $interfaces  Array of arrays with interfaces.
	 *
	 * @return CTableInfo
	 */
	protected function getInterfaceHint(array $interfaces): CTableInfo {
		$hint_table = (new CTableInfo())
			->setHeader([_('Interface'), _('Status'), _('Error')])
			->addStyle('max-width: 640px');
		$status = [
			INTERFACE_AVAILABLE_UNKNOWN => _('Unknown'),
			INTERFACE_AVAILABLE_TRUE => _('Available'),
			INTERFACE_AVAILABLE_FALSE => _('Not available')
		];

		foreach ($interfaces as $interface) {
			$interface_tag = new CDiv($interface['interface']);

			if ($interface['description']) {
				$interface_tag->addItem((new CDiv($interface['description']))->addClass(ZBX_STYLE_GREY));
			}

			$hint_table->addRow([
				$interface_tag,
				(new CSpan($status[$interface['available']]))
					->addClass(static::COLORS[$interface['available']])
					->addClass(ZBX_STYLE_NOWRAP),
				(new CDiv($interface['error']))->addClass(ZBX_STYLE_RED)
			]);
		}

		return $hint_table;
	}

	public function toString($destroy = true) {
		foreach ($this->type_interfaces as $type => $interfaces) {
			if (!$interfaces || !array_key_exists($type, static::LABELS)) {
				continue;
			}

			CArrayHelper::sort($interfaces, ['interface']);
			$available = array_column($interfaces, 'available');
			$status = in_array(INTERFACE_AVAILABLE_UNKNOWN, $available)
				? INTERFACE_AVAILABLE_UNKNOWN
				: INTERFACE_AVAILABLE_TRUE;

			if (in_array(INTERFACE_AVAILABLE_FALSE, $available)) {
				$status = (in_array(INTERFACE_AVAILABLE_UNKNOWN, $available)
						|| in_array(INTERFACE_AVAILABLE_TRUE, $available))
					? INTERFACE_AVAILABLE_MIXED
					: INTERFACE_AVAILABLE_FALSE;
			}

			$this->addItem((new CSpan(static::LABELS[$type]))
				->addClass(static::COLORS[$status])
				->setHint($this->getInterfaceHint($interfaces))
			);
		}

		return parent::toString($destroy);
	}
}

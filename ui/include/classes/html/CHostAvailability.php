<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


class CHostAvailability extends CTag {

	public const TYPES = [INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX,
		INTERFACE_TYPE_AGENT_ACTIVE
	];

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

	protected array $type_interfaces = [];

	protected bool $has_passive_checks = true;

	public function __construct() {
		parent::__construct('div', true);
		$this->addClass(ZBX_STYLE_STATUS_CONTAINER);
	}

	/**
	 * Set host interfaces.
	 *
	 * @param array  $interfaces
	 * @param string $availability_status
	 *
	 * @return CHostAvailability
	 */
	public function setInterfaces(array $interfaces): CHostAvailability {
		$this->type_interfaces = array_fill_keys(static::TYPES, []);

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

		CArrayHelper::sort($interfaces, ['interface']);

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

	/**
	 * @param bool $value
	 *
	 * @return CHostAvailability
	 */
	public function enablePassiveChecks(bool $value = true): CHostAvailability {
		$this->has_passive_checks = $value;

		return $this;
	}

	public function toString($destroy = true) {
		foreach ($this->type_interfaces as $type => $interfaces) {
			if ($type == INTERFACE_TYPE_AGENT) {
				$interfaces = array_merge($interfaces, $this->type_interfaces[INTERFACE_TYPE_AGENT_ACTIVE]);
			}

			if (!$interfaces || !array_key_exists($type, static::LABELS)) {
				continue;
			}

			$status = $type == INTERFACE_TYPE_AGENT && !$this->has_passive_checks
					&& $this->type_interfaces[INTERFACE_TYPE_AGENT_ACTIVE]
				? getInterfaceAvailabilityStatus($this->type_interfaces[INTERFACE_TYPE_AGENT_ACTIVE])
				: getInterfaceAvailabilityStatus($interfaces);

			$this->addItem(
				(new CSpan(static::LABELS[$type]))
					->addClass(static::COLORS[$status])
					->setHint($this->getInterfaceHint($interfaces))
			);
		}

		return parent::toString($destroy);
	}
}

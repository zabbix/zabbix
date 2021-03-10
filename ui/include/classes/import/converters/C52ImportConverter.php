<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * Converter for converting import data from 5.2 to 5.4.
 */
class C52ImportConverter extends CConverter {

	/**
	 * Converter used to convert trigger expressions from 5.2 to 5.4 syntax.
	 *
	 * @var C30TriggerConverter
	 */
	protected $trigger_expression_converter;

	public function __construct() {
		$this->trigger_expression_converter = new C30TriggerConverter();
	}

	/**
	 * Convert import data from 5.2 to 5.4 version.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function convert($data): array {
		$data['zabbix_export']['version'] = '5.4';

		if (array_key_exists('hosts', $data['zabbix_export'])) {
			$data['zabbix_export']['hosts'] = $this->convertHosts($data['zabbix_export']['hosts']);
		}

		if (array_key_exists('templates', $data['zabbix_export'])) {
			$data['zabbix_export']['templates'] = $this->convertTemplates($data['zabbix_export']['templates']);
		}

		if (array_key_exists('triggers', $data['zabbix_export'])) {
			foreach ($data['zabbix_export']['triggers'] as &$trigger) {
				$trigger = $this->convertTrigger($trigger);
			}
			unset($trigger);
		}

		return $data;
	}

	/**
	 * Convert hosts.
	 *
	 * @param array $hosts
	 *
	 * @return array
	 */
	private function convertHosts(array $hosts): array {
		$tls_fields = array_flip(['tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject', 'tls_psk_identity',
			'tls_psk'
		]);

		foreach ($hosts as &$host) {
			$host = array_diff_key($host, $tls_fields);

			if (array_key_exists('items', $host)) {
				foreach ($host['items'] as &$item) {
					if (array_key_exists('triggers', $item)) {
						foreach ($item['triggers'] as &$trigger) {
							$trigger = $this->convertTrigger($trigger, $host['host'], $item['key']);
						}
						unset($trigger);
					}
				}
				unset($item);
			}
		}
		unset($host);

		return $hosts;
	}

	/**
	 * Convert templates.
	 *
	 * @param array $templates
	 *
	 * @return array
	 */
	private function convertTemplates(array $templates): array {
		foreach ($templates as &$template) {
			if (array_key_exists('items', $template)) {
				foreach ($template['items'] as &$item) {
					if (array_key_exists('triggers', $item)) {
						foreach ($item['triggers'] as &$trigger) {
							$trigger = $this->convertTrigger($trigger, $template['host'], $item['key']);
						}
						unset($trigger);
					}
				}
				unset($item);
			}
		}
		unset($template);

		return $templates;
	}

	/**
	 * Convert trigger expression to new syntax.
	 *
	 * @param array  $trigger
	 * @param string $host     (optional)
	 * @param string $item     (optional)
	 *
	 * @return array
	 */
	private function convertTrigger(array $trigger, ?string $host = null, ?string $item = null): array {
		foreach (['expression', 'recovery_expression'] as $source) {
			if (array_key_exists($source, $trigger)) {
				$trigger[$source] = $this->trigger_expression_converter->convert(array_filter([
					'expression' => $trigger[$source],
					'host' => $host,
					'item' => $item
				]));
			}
		}

		return $trigger;
	}
}

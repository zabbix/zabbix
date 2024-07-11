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


class CApiPskHelper {

	/**
	 * Check tls_psk_identity have same tls_psk value across all hosts, proxies and autoregistration.
	 *
	 * @param array $psk_pairs
	 *
	 * @throws APIException
	 */
	public static function checkPskIndentityPskPairs(array $psk_pairs) {
		$psk_pair_index = [];

		foreach ($psk_pairs as $i => $psk_pair) {
			if (array_key_exists($psk_pair['tls_psk_identity'], $psk_pair_index)
					&& $psk_pairs[$psk_pair_index[$psk_pair['tls_psk_identity']]]['tls_psk'] !== $psk_pair['tls_psk']) {
				throw new APIException(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'/'.($i + 1).'/tls_psk', _('another value of tls_psk exists for same tls_psk_identity'))
				);
			}

			$psk_pair_index[$psk_pair['tls_psk_identity']] = $i;
		}

		$check_psk_identities = array_keys($psk_pair_index);
		$autoreg = DBfetch(DBselect(
			'SELECT tls_psk_identity,tls_psk FROM config_autoreg_tls'.
			' WHERE '.dbConditionString('tls_psk_identity', $check_psk_identities)
		));

		if ($autoreg) {
			$i = $psk_pair_index[$autoreg['tls_psk_identity']];

			if ($psk_pairs[$i]['tls_psk'] !== $autoreg['tls_psk']) {
				throw new APIException(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'/'.($i + 1).'/tls_psk', _('another value of tls_psk exists for same tls_psk_identity'))
				);
			}
		}

		$exclude_hostids = array_unique(array_column($psk_pairs, 'hostid'));
		$cursor = DBselect(
			'SELECT tls_psk_identity,tls_psk FROM hosts'.
			' WHERE '.dbConditionId('hostid', $exclude_hostids, true).
				' AND '.dbConditionString('tls_psk_identity', $check_psk_identities)
		);

		while ($db_row = DBfetch($cursor)) {
			$i = $psk_pair_index[$db_row['tls_psk_identity']];

			if ($psk_pairs[$i]['tls_psk'] !== $db_row['tls_psk']) {
				throw new APIException(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'/'.($i + 1).'/tls_psk', _('another value of tls_psk exists for same tls_psk_identity'))
				);
			}
		}
	}
}

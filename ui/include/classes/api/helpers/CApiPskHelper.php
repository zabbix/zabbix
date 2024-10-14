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

	public static function checkPskOfIdentitiesAmongGivenPairs(array $psk_pairs): void {
		$tls_psk_by_identity = [];

		foreach ($psk_pairs as $i => $psk_pair) {
			if (array_key_exists($psk_pair['tls_psk_identity'], $tls_psk_by_identity)
					&& $tls_psk_by_identity[$psk_pair['tls_psk_identity']] !== $psk_pair['tls_psk']) {
				throw new APIException(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/'.($i + 1).'/tls_psk',
					_('another tls_psk value is already associated with given tls_psk_identity')
				));
			}

			$tls_psk_by_identity[$psk_pair['tls_psk_identity']] = $psk_pair['tls_psk'];
		}
	}

	public static function checkPskOfIdentitiesInAutoregistration(array $psk_pairs): void {
		$object_indexes = [];
		$psk_conditions = [];

		foreach ($psk_pairs as $i => $psk_pair) {
			if (!array_key_exists($psk_pair['tls_psk_identity'], $object_indexes)) {
				$object_indexes[$psk_pair['tls_psk_identity']] = $i;
				$psk_conditions[] = dbConditionString('tls_psk_identity', [$psk_pair['tls_psk_identity']]).
					' AND '.dbConditionString('tls_psk', [$psk_pair['tls_psk']], true);
			}
		}

		$row = DBfetch(DBselect(
			'SELECT tls_psk_identity'.
			' FROM config_autoreg_tls'.
			' WHERE ('.implode(') OR (', $psk_conditions).')'
		));

		if ($row) {
			$i = $object_indexes[$row['tls_psk_identity']];

			throw new APIException(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
				'/'.($i + 1).'/tls_psk', _('another tls_psk value is already associated with given tls_psk_identity')
			));
		}
	}

	public static function checkPskOfIdentityInAutoregistration(array $psk_pair): void {
		$row = DBfetch(DBselect(
			'SELECT NULL'.
			' FROM config_autoreg_tls'.
			' WHERE '.dbConditionString('tls_psk_identity', [$psk_pair['tls_psk_identity']]).
				' AND '.dbConditionString('tls_psk', [$psk_pair['tls_psk']], true),
			1
		));

		if ($row) {
			throw new APIException(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/tls_psk',
				_('another tls_psk value is already associated with given tls_psk_identity')
			));
		}
	}

	public static function checkPskOfIdentitiesAmongHostsAndProxies(array $psk_pairs, array $hostids = null): void {
		$object_indexes = [];
		$psk_conditions = [];

		foreach ($psk_pairs as $i => $psk_pair) {
			if (!array_key_exists($psk_pair['tls_psk_identity'], $object_indexes)) {
				$object_indexes[$psk_pair['tls_psk_identity']] = $i;
				$psk_conditions[] = dbConditionString('tls_psk_identity', [$psk_pair['tls_psk_identity']]).
					' AND '.dbConditionString('tls_psk', [$psk_pair['tls_psk']], true);
			}
		}

		$conditions = $hostids === null
			? '('.implode(') OR (', $psk_conditions).')'
			: '(('.implode(') OR (', $psk_conditions).'))'.
				' AND '.dbConditionId('hostid', $hostids, true);

		$row = DBfetch(DBselect(
			'SELECT tls_psk_identity'.
			' FROM hosts'.
			' WHERE '.$conditions,
			1
		));

		if ($row) {
			$i = $object_indexes[$row['tls_psk_identity']];

			throw new APIException(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
				'/'.($i + 1).'/tls_psk', _('another tls_psk value is already associated with given tls_psk_identity')
			));
		}
	}

	public static function checkPskOfIdentityAmongHostsAndProxies(array $psk_pair): void {
		$row = DBfetch(DBselect(
			'SELECT NULL'.
			' FROM hosts'.
			' WHERE '.dbConditionString('tls_psk_identity', [$psk_pair['tls_psk_identity']]).
				' AND '.dbConditionString('tls_psk', [$psk_pair['tls_psk']], true),
			1
		));

		if ($row) {
			throw new APIException(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/tls_psk',
				_('another tls_psk value is already associated with given tls_psk_identity')
			));
		}
	}
}

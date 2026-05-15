<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CProxyHelper {

	/**
	 * Resolves proxy data.
	 *
	 * @param int $proxyid
	 * @return array
	 */
	public static function resolveProxyOption(int $proxyid): array {
		if ($proxyid === 0) {
			return [];
		}

		$proxies = API::Proxy()->get([
			'output' => ['proxyid', 'name'],
			'proxyids' => $proxyid
		]);

		if ($proxies) {
			$proxy = $proxies[0];

			return [
				'id' => $proxy['proxyid'],
				'name' => $proxy['name'],
				'inaccessible' => false
			];
		}

		return [
			'id' => $proxyid,
			'name' => _('Inaccessible proxy'),
			'inaccessible' => true
		];
	}
}

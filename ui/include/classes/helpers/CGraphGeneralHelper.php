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


class CGraphGeneralHelper {

	/**
	 * @param array  $src_graphs
	 * @param array  $dst_options
	 * @param array  $src_options
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	protected static function getDestinationItems(array $src_graphs, array $dst_options, array $src_options): array {
		$dst_hostids = reset($dst_options);
		$src_item_graphs = [];

		foreach ($src_graphs as $src_graph) {
			if ($src_graph['ymin_itemid'] != 0) {
				$src_item_graphs[$src_graph['ymin_itemid']][$src_graph['graphid']] = true;
			}

			if ($src_graph['ymax_itemid'] != 0) {
				$src_item_graphs[$src_graph['ymax_itemid']][$src_graph['graphid']] = true;
			}

			foreach ($src_graph['gitems'] as $gitem) {
				$src_item_graphs[$gitem['itemid']][$src_graph['graphid']] = true;
			}
		}

		$src_items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'key_'],
			'webitems' => true,
			'itemids' => array_keys($src_item_graphs)
		]);

		if (array_key_exists('hostids', $src_options) && $src_options['hostids'] != 0) {
			foreach ($src_items as $i => $src_item) {
				if (bccomp($src_item['hostid'], $src_options['hostids']) != 0) {
					unset($src_items[$i]);
				}
			}
		}

		if (!$src_items) {
			return [];
		}

		$dst_items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'key_'],
			'webitems' => true,
			'filter' => ['key_' => array_unique(array_column($src_items, 'key_'))]
		] + $dst_options);

		$_dst_itemids = [];

		foreach ($dst_items as $dst_item) {
			$_dst_itemids[$dst_item['key_']][$dst_item['hostid']] = $dst_item['itemid'];
		}

		$dst_itemids = [];

		foreach ($src_items as $src_item) {
			foreach ($dst_hostids as $dst_hostid) {
				if (!array_key_exists($src_item['key_'], $_dst_itemids)
						|| !array_key_exists($dst_hostid, $_dst_itemids[$src_item['key_']])) {
					$src_graph = $src_graphs[key($src_item_graphs[$src_item['itemid']])];

					if (array_key_exists('flags', $src_graph)) {
						$error = array_key_exists('hostids', $dst_options)
							? _('Cannot copy graph "%1$s", because the item with key "%2$s" does not exist on the host "%3$s".')
							: _('Cannot copy graph "%1$s", because the item with key "%2$s" does not exist on the template "%3$s".');
					}
					else {
						$error = array_key_exists('hostids', $dst_options)
							? _('Cannot copy graph prototype "%1$s", because the item with key "%2$s" does not exist on the host "%3$s".')
							: _('Cannot copy graph prototype "%1$s", because the item with key "%2$s" does not exist on the template "%3$s".');
					}

					$dst_hosts = array_key_exists('hostids', $dst_options)
						? API::Host()->get([
							'output' => ['host'],
							'hostids' => $dst_hostid
						])
						: API::Template()->get([
							'output' => ['host'],
							'templateids' => $dst_hostid
						]);

					error(sprintf($error, $src_graph['name'], $src_item['key_'], $dst_hosts[0]['host']));

					throw new Exception();
				}

				$dst_itemids[$src_item['itemid']][$dst_hostid] = $_dst_itemids[$src_item['key_']][$dst_hostid];
			}
		}

		return $dst_itemids;
	}
}

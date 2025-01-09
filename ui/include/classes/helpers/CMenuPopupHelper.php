<?php
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


class CMenuPopupHelper {

	/**
	 * Prepare data for dashboard popup menu.
	 *
	 * @param string|null $dashboardid
	 * @param bool        $editable
	 * @param bool        $has_related_reports
	 * @param bool        $can_edit_dashboards
	 * @param bool        $can_view_reports
	 * @param bool        $can_create_reports
	 *
	 * @return array
	 */
	public static function getDashboard(?string $dashboardid, bool $editable, bool $has_related_reports,
			bool $can_edit_dashboards, bool $can_view_reports, bool $can_create_reports): array {
		return [
			'type' => 'dashboard',
			'data' => [
				'dashboardid' => $dashboardid,
				'editable' => $editable,
				'has_related_reports' => $has_related_reports,
				'can_edit_dashboards' => $can_edit_dashboards,
				'can_view_reports' => $can_view_reports,
				'can_create_reports' => $can_create_reports,
				'csrf_token' => CCsrfTokenHelper::get('dashboard')
			]
		];
	}

	/**
	 * Prepare data for item history menu popup.
	 *
	 * @param string $itemid
	 *
	 * @return array
	 */
	public static function getHistory($itemid) {
		return [
			'type' => 'history',
			'data' => [
				'itemid' => $itemid
			]
		];
	}

	/**
	 * Prepare data for Ajax host menu popup.
	 *
	 * @param string $hostid
	 * @param bool   $has_goto     Show "Go to" block in popup.
	 *
	 * @return array
	 */
	public static function getHost($hostid, $has_goto = true) {
		$data = [
			'type' => 'host',
			'data' => [
				'hostid' => $hostid
			]
		];

		if ($has_goto === false) {
			$data['data']['has_goto'] = '0';
		}

		return $data;
	}

	/**
	 * Prepare data for Ajax map element menu popup.
	 *
	 * @param string $sysmapid                     Map ID.
	 * @param array  $selement                     Map element data (ID, type, URLs, etc...).
	 * @param string $selement['selementid_orig']  Map element ID.
	 * @param string $selement['unique_id']        Map element unique ID.
	 * @param int    $severity_min                 Minimum severity.
	 * @param string $hostid                       Host ID.
	 *
	 * @return array
	 */
	public static function getMapElement($sysmapid, $selement, $severity_min, $hostid) {
		$data = [
			'type' => 'map_element',
			'data' => [
				'sysmapid' => $sysmapid,
				'selementid' => $selement['selementid_orig']
			]
		];

		if (array_key_exists('unique_id', $selement)) {
			$data['data']['unique_id'] = $selement['unique_id'];
		}

		if ($severity_min != TRIGGER_SEVERITY_NOT_CLASSIFIED) {
			$data['data']['severity_min'] = $severity_min;
		}
		if ($hostid != 0) {
			$data['data']['hostid'] = $hostid;
		}

		return $data;
	}

	/**
	 * Prepare data for Ajax trigger menu popup.
	 *
	 * @param array  $data
	 *        string $data['triggerid']                 Trigger ID.
	 *        string $data['backurl']                   URL from where the menu popup was called.
	 *        string $data['eventid']                   (optional) Mandatory for "Update problem", "Mark as cause"
	 *                                                  and "Mark selected as symptoms" context menus.
	 *        bool   $data['show_update_problem']       (optional) Whether to show "Update problem".
	 *        bool   $data['show_rank_change_cause']    (optional) Whether to show "Mark as cause".
	 *        bool   $data['show_rank_change_symptom']  (optional) Whether to show "Mark selected as symptoms".
	 *
	 * @return array
	 */
	public static function getTrigger(array $data): array {
		$menu = [
			'type' => 'trigger',
			'data' => [
				'triggerid' => $data['triggerid'],
				'backurl' => $data['backurl']
			]
		];

		if (array_key_exists('eventid', $data) && $data['eventid'] != 0) {
			$menu['data']['eventid'] = $data['eventid'];

			foreach (['show_update_problem', 'show_rank_change_cause', 'show_rank_change_symptom'] as $option) {
				if (array_key_exists($option, $data)) {
					$menu['data'][$option] = (int) $data[$option];
				}
			}
		}

		return $menu;
	}

	/**
	 * Prepare data for trigger macro menu popup.
	 *
	 * @return array
	 */
	public static function getTriggerMacro() {
		return [
			'type' => 'trigger_macro'
		];
	}

	/**
	 * Prepare data for item latest data popup menu.
	 *
	 * @param array  $data
	 * @param string $data['itemid']
	 * @param string $data['backurl']
	 * @param string $data['context']
	 *
	 * @return array
	 */
	public static function getItem(array $data): array {
		return [
			'type' => 'item',
			'data' => [
				'itemid' => $data['itemid'],
				'backurl' => $data['backurl']
			],
			'context' => $data['context']
		];
	}

	/**
	 * Prepare data for item prototype configuration popup menu.
	 *
	 * @param array  $data
	 * @param string $data['itemid']
	 * @param string $data['backurl']
	 * @param string $data['context']
	 *
	 * @return array
	 */
	public static function getItemPrototype(array $data): array {
		return [
			'type' => 'item_prototype',
			'data' => [
				'itemid' => $data['itemid'],
				'backurl' => $data['backurl']
			],
			'context' => $data['context']
		];
	}

	/**
	 * Prepare data for discovery rule configuration popup menu.
	 *
	 * @param string $druleid
	 *
	 * @return array
	 */
	public static function getDRule(string $druleid): array {
		return [
			'type' => 'drule',
			'data' => [
				'druleid' => $druleid
			]
		];
	}
}

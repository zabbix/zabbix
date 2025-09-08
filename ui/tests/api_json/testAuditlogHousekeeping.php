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


require_once dirname(__FILE__).'/common/testAuditlogCommon.php';

/**
 * @backup config
 */
class testAuditlogHousekeeping extends testAuditlogCommon {

	public function testAuditlogHousekeeping_Update() {
		$this->call('housekeeping.update', [
			'hk_events_mode' => '1',
			'hk_events_trigger' => '200d',
			'hk_events_service' => '2d',
			'hk_events_internal' => '2d',
			'hk_events_discovery' => '2d',
			'hk_events_autoreg' => '2d',
			'hk_services_mode' => 1,
			'hk_services' => '300d',
			'hk_audit_mode' => 1,
			'hk_audit' => '200d',
			'hk_sessions_mode' => 1,
			'hk_sessions' => '200d',
			'hk_history_mode' => 1,
			'hk_history_global' => 1,
			'hk_history' => '69d',
			'hk_trends_mode' => 1,
			'hk_trends_global' => 1,
			'hk_trends' => '200d',
			'compression_status' => 1,
			'compress_older' => 788400000
		]);

		$updated = json_encode([
			'housekeeping.hk_events_trigger' => ['update', '200d', '365d'],
			'housekeeping.hk_events_service' => ['update', '2d', '1d'],
			'housekeeping.hk_events_internal' => ['update', '2d', '1d'],
			'housekeeping.hk_events_discovery' => ['update', '2d', '1d'],
			'housekeeping.hk_events_autoreg' => ['update', '2d', '1d'],
			'housekeeping.hk_services' => ['update', '300d', '365d'],
			'housekeeping.hk_audit' => ['update', '200d', '31d'],
			'housekeeping.hk_sessions' => ['update', '200d', '365d'],
			'housekeeping.hk_history_global' => ['update', '1', '0'],
			'housekeeping.hk_history' => ['update', '69d', '31d'],
			'housekeeping.hk_trends_global' => ['update', '1', '0'],
			'housekeeping.hk_trends' => ['update', '200d', '365d'],
			'housekeeping.compression_status' => ['update', '1', '0'],
			'housekeeping.compress_older' => ['update', '788400000', '7d']
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, 1);
	}
}

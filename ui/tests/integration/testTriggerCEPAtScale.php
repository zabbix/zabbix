<?php
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

require_once dirname(__FILE__).'/testTriggerCEP.php';

/**
 * Re-runs the whole testTriggerCEP suite with the load knobs cranked up to the recommended
 * stress values (and the server-restart and services test variants enabled), so CEP is actually
 * exercised under heavy load rather than at the tiny CI-friendly defaults. This is slow and meant
 * to be run on demand, not in the fast CI pass. The parent reads all of these knobs via late
 * static binding (static::), so overriding the constants here is enough to redirect its behavior.
 *
 * @required-components server
 * @suite-components-reuse true
 * @onAfter clearData
 * @hosts test
 */
class testTriggerCEPAtScale extends testTriggerCEP {
	const LLD_DISCOVERY_COUNT = 500;	// discovered items/triggers per rule; use at least 4000 to stress CEP
	const LOG_EVENT_COUNT = 10000;		// log values pushed at the single-trigger stream; use at least 10000
	const RECOVERY_CYCLES_COUNT = 2000;	// PROBLEM/recovery cycles in the rapid burst; use at least 1000
	const MAINTENANCE_COUNT = 40;		// number of maintenances to create; change to any number
	const MAINTENANCE_COUNT_EXTRA = 10;
	// One window per id, so this is how many windows of the same rule the close window flavours keep open at
	// once - enough of them to be spread over the CEP worker processes instead of a handful.
	const CEP_CLOSE_WINDOW_SERVICE_COUNT = 100;
	const SKIP_RESTART_TESTS = true;

	// Larger scale needs longer to settle; override the parent's reduced default back up.
	const WAIT_ITERATIONS = 60;
	const WAIT_ITERATIONS_LONGER = 120;
}

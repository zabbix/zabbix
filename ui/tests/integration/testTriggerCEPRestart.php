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
 * Re-runs the whole testTriggerCEP suite with tiny load knobs but with the server-restart and
 * services test variants enabled, so the *Restart and *WithServices scenarios that the parent
 * skips are actually exercised (at small scale). The parent reads all of these knobs via late
 * static binding (static::), so overriding the constants here is enough to redirect its behavior.
 *
 * @required-components server
 * @suite-components-reuse true
 * @onAfter clearData
 * @hosts test
 */
class testTriggerCEPRestart extends testTriggerCEP {
	const LLD_DISCOVERY_COUNT = 10;		// discovered items/triggers per rule; use at least 4000 to stress CEP
	const LOG_EVENT_COUNT = 10;			// log values pushed at the single-trigger stream; use at least 10000
	const RECOVERY_CYCLES_COUNT = 20;	// PROBLEM/recovery cycles in the rapid burst; use at least 1000
	const MAINTENANCE_COUNT = 40;		// number of maintenances to create; change to any number
	const MAINTENANCE_COUNT_EXTRA = 10;
	const SKIP_RESTART_TESTS = false;

	// Larger scale needs longer to settle; override the parent's reduced default back up.
	const WAIT_ITERATIONS = 60;
	const WAIT_ITERATIONS_LONGER = 120;
}

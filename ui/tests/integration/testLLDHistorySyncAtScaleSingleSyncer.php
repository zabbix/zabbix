<?php declare(strict_types = 1);
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

require_once dirname(__FILE__).'/testLLDHistorySyncAtScale.php';

/**
 * Re-runs the testLLDHistorySyncAtScale suite with StartDBSyncers=1 to exercise
 * the same scenarios under a single history syncer.
 *
 * @required-components server
 * @suite-components-reuse true
 * @configurationDataProvider configurationProvider
 * @onAfter clearData
 */
class testLLDHistorySyncAtScaleSingleSyncer extends testLLDHistorySyncAtScale {

	public function configurationProvider() {
		$config = parent::configurationProvider();
		$config[self::COMPONENT_SERVER]['StartDBSyncers'] = '1';

		return $config;
	}
}

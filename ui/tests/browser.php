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


include __DIR__."/bootstrap.php";
include __DIR__."/include/web/CPage.php";

class CBrowserStats extends CPage {
	public function getBrowserInfo() {
		$capabilities = $this->driver->getCapabilities();

		return [
			"browser" => $capabilities->getBrowserName(),
			"version" => $capabilities->getVersion()
		];
	}
}

$browser_stats = new CBrowserStats();
$info = $browser_stats->getBrowserInfo();
echo "***********************************************************\n".
"Frontend URL: ".PHPUNIT_URL."\n".
"Browser:      ".$info["browser"]."\n".
"Version:      ".$info["version"]."\n".
"PHP version:  ".phpversion()."\n".
"***********************************************************\n";
$browser_stats->destroy();

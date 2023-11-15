<?php

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

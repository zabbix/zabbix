<?php

namespace Zabbix\Test;

use Zabbix\Test\APIGateway\APIGatewayInterface;
use Zabbix\Test\APIGateway\FileAPIGateway;

class BaseAPITestCase extends \PHPUnit_Framework_TestCase {

	/**
	 * @return APIGatewayInterface
	 */
	protected function getGateway() {
		$gateway = new FileAPIGateway();
		$gateway->configure(array(
			'entry_point' => ''
		));
	}

}

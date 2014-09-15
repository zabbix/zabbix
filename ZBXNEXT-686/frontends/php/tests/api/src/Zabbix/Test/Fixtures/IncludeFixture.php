<?php

namespace Zabbix\Test\Fixtures;

use Symfony\Component\Yaml\Yaml;

class IncludeFixture extends Fixture {

	/**
	 * @var FixtureLoader
	 */
	protected $fixtureLoader;

	public function __construct(FixtureLoader $fixtureLoader) {
		$this->fixtureLoader = $fixtureLoader;
	}

	public function load(array $params) {
		if (!isset($params['params'])) {
			$params['params'] = array();
		}

		$file = $params['file'];
		$path = __DIR__ . '/../../../../tests/fixtures/'.$file.'.yml';

		if (!is_readable($path)) {
			throw new \Exception(sprintf('Can not find fixture file "%s" (expected location "%s")', $file, $path));
		}

		$fixtureFile = Yaml::parse(file_get_contents($path));

		$fixtures = $this->fixtureLoader->load($fixtureFile['fixtures'], $params['params']);

		$return = (isset($fixtureFile['return'])) ? $fixtureFile['return'] : array();

		return $this->fixtureLoader->resolveMacros($return, $fixtures, $params);
	}

}

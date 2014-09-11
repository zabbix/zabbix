<?php

namespace Zabbix\Test\Fixtures;


class FixtureLoader {

	const TYPE_INCLUDE = 'include';
	const TYPE_API = 'api';
	const TYPE_DATA = 'data';

	/**
	 * @var FixtureFactory
	 */
	protected $fixtureFactory;

	public function __construct(FixtureFactory $fixtureFactory) {
		$fixtureFactory->setFixtureLoader($this);

		$this->fixtureFactory = $fixtureFactory;
	}

	public function load(array $fixtures, array $params = array()) {
		foreach ($fixtures as $name => $fixtureConf) {
			if (!isset($fixtureConf['type'])) {
				throw $this->getException($name, '"type" parameter missing');
			}
			if (!isset($fixtureConf['params'])) {
				throw $this->getException($name, '"params" parameter missing');
			}

			try {
				$fixture = $this->fixtureFactory->getFixture($fixtureConf['type']);
				$fixture->load($fixtureConf['params']);
			}
			catch (\Exception $e) {
				throw $this->getException($name, $e->getMessage());
			}
		}
	}

	protected function getException($fixtureName, $message) {
		return new \Exception(sprintf('Error loading fixture "%1$s": %2$s', $fixtureName, $message));
	}

}

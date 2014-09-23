<?php

namespace Zabbix\Test\Fixtures;


class FixtureFactory {

	const TYPE_INCLUDE = 'include';
	const TYPE_API = 'api';
	const TYPE_DATA = 'data';
	const TYPE_UPDATE = 'update';

	/**
	 * @var FixtureLoader
	 */
	protected $fixtureLoader;

	/**
	 * @var \CApiWrapper
	 */
	protected $apiWrapper;

	public function __construct(\CApiWrapper $apiWrapper) {
		$this->apiWrapper = $apiWrapper;
	}

	public function setFixtureLoader(FixtureLoader $fixtureLoader) {
		$this->fixtureLoader = $fixtureLoader;
	}

	public function getFixture($type) {
		switch ($type) {
			case self::TYPE_INCLUDE:
				return new IncludeFixture($this->fixtureLoader);

				break;
			case self::TYPE_API:
				return new ApiFixture($this->apiWrapper);

				break;
			case self::TYPE_DATA:
				return new DataFixture();

				break;
			case self::TYPE_UPDATE:
				return new UpdateFixture();

				break;
		}

		throw new \Exception(sprintf('Incorrect fixture type "%1$s"', $type));
	}

}

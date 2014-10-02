<?php

class CFixtureFactory {

	const TYPE_INCLUDE = 'include';
	const TYPE_API = 'api';
	const TYPE_DATA = 'data';
	const TYPE_UPDATE = 'update';

	/**
	 * @var CFixtureLoader
	 */
	protected $fixtureLoader;

	/**
	 * @var CApiWrapper
	 */
	protected $apiWrapper;

	public function __construct(CApiWrapper $apiWrapper) {
		$this->apiWrapper = $apiWrapper;
	}

	public function setFixtureLoader(CFixtureLoader $fixtureLoader) {
		$this->fixtureLoader = $fixtureLoader;
	}

	public function getFixture($type) {
		switch ($type) {
			case self::TYPE_INCLUDE:
				return new CIncludeFixture($this->fixtureLoader);

				break;
			case self::TYPE_API:
				return new CApiFixture($this->apiWrapper);

				break;
			case self::TYPE_DATA:
				return new CDataFixture();

				break;
			case self::TYPE_UPDATE:
				return new CUpdateFixture();

				break;
		}

		throw new Exception(sprintf('Incorrect fixture type "%1$s"', $type));
	}

}

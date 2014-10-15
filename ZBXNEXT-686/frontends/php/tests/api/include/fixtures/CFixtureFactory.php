<?php

/**
 * A class for getting objects for loading fixtures.
 */
class CFixtureFactory {

	const TYPE_INCLUDE = 'include';
	const TYPE_API = 'api';
	const TYPE_DATA = 'data';
	const TYPE_UPDATE = 'update';

	/**
	 * Object to use for including fixtures.
	 *
	 * @var CFixtureLoader
	 */
	protected $fixtureLoader;

	/**
	 * Object to use for API requests.
	 *
	 * @var CApiWrapper
	 */
	protected $apiWrapper;

	/**
	 * Directory where the fixture files are located.
	 *
	 * @var string
	 */
	protected $fileDir;

	/**
	 * @param string $fileDir			directory where the fixture files are located
	 * @param CApiWrapper $apiWrapper	object to use for API requests
	 */
	public function __construct($fileDir, CApiWrapper $apiWrapper) {
		$this->fileDir = $fileDir;
		$this->apiWrapper = $apiWrapper;
	}

	/**
	 * @param CFixtureLoader $fixtureLoader
	 */
	public function setFixtureLoader(CFixtureLoader $fixtureLoader) {
		$this->fixtureLoader = $fixtureLoader;
	}

	/**
	 * Return an object for loading a fixture of the specified type.
	 *
	 * @param int $type
	 *
	 * @return CFixture
	 *
	 * @throws InvalidArgumentException	if the fixture type is incorrect
	 */
	public function getFixture($type) {
		switch ($type) {
			case self::TYPE_INCLUDE:
				return new CIncludeFixture($this->fileDir, $this->fixtureLoader);

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

		throw new InvalidArgumentException(sprintf('Incorrect fixture type "%1$s"', $type));
	}

}

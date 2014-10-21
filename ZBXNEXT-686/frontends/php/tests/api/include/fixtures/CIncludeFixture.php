<?php

class CIncludeFixture extends CFixture {

	/**
	 * Object for loading fixtures.
	 *
	 * @var CFixtureLoader
	 */
	protected $fixtureLoader;

	/**
	 * Directory where the fixture files are located.
	 *
	 * @var string
	 */
	protected $fileDir;

	/**
	 * @param string $fileDir					directory where the fixture files are located
	 * @param CFixtureLoader $fixtureLoader		object for loading fixtures
	 */
	public function __construct($fileDir, CFixtureLoader $fixtureLoader) {
		$this->fileDir = $fileDir;
		$this->fixtureLoader = $fixtureLoader;
	}

	/**
	 * Load a fixture that includes a separate fixture file.
	 *
	 * Supported parameters:
	 * - file 	- name of the fixture file
	 * - params	- parameters passed to the file
	 */
	public function load(array $params) {
		$this->checkMissingParams($params, array('file'));

		if (!isset($params['params'])) {
			$params['params'] = array();
		}

		$path = $this->fileDir.'/'.$params['file'].'.yml';

		if (!is_readable($path)) {
			throw new InvalidArgumentException(
				sprintf('Can not find fixture file "%s" (expected location "%s")', $params['file'], $path)
			);
		}

		try {
			$fixtureFile = yaml_parse_file($path);
		}
		catch (Exception $e) {
			throw new InvalidArgumentException(
				sprintf('Cannot parse fixture file: %1$s', $e->getMessage())
			);
		}

		$fixtures = (isset($fixtureFile['fixtures'])) ? $fixtureFile['fixtures'] : array();
		$fixtures = $this->fixtureLoader->load($fixtures, $params['params']);

		$return = (isset($fixtureFile['return'])) ? $fixtureFile['return'] : array();

		return $this->fixtureLoader->resolveMacros($return, $fixtures, $params);
	}

}

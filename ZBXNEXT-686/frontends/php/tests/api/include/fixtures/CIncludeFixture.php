<?php

class CIncludeFixture extends CFixture {

	/**
	 * @var CFixtureLoader
	 */
	protected $fixtureLoader;

	/**
	 * Directory where the fixture files are located.
	 *
	 * @var string
	 */
	protected $fileDir;

	public function __construct($fileDir, CFixtureLoader $fixtureLoader) {
		$this->fileDir = $fileDir;
		$this->fixtureLoader = $fixtureLoader;
	}

	public function load(array $params) {
		if (!isset($params['params'])) {
			$params['params'] = array();
		}

		$path = $this->fileDir.'/'.$params['file'].'.yml';

		if (!is_readable($path)) {
			throw new Exception(sprintf('Can not find fixture file "%s" (expected location "%s")', $file, $path));
		}

		$fixtureFile = yaml_parse_file($path);

		$fixtures = $this->fixtureLoader->load($fixtureFile['fixtures'], $params['params']);

		$return = (isset($fixtureFile['return'])) ? $fixtureFile['return'] : array();

		return $this->fixtureLoader->resolveMacros($return, $fixtures, $params);
	}

}

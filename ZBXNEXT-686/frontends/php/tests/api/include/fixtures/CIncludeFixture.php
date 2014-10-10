<?php

class CIncludeFixture extends CFixture {

	/**
	 * @var CFixtureLoader
	 */
	protected $fixtureLoader;

	public function __construct(CFixtureLoader $fixtureLoader) {
		$this->fixtureLoader = $fixtureLoader;
	}

	public function load(array $params) {
		if (!isset($params['params'])) {
			$params['params'] = array();
		}

		$file = $params['file'];

		// TODO: pass the path as a parameter
		$path = __DIR__ . '/../../tests/fixtures/'.$file.'.yml';

		if (!is_readable($path)) {
			throw new Exception(sprintf('Can not find fixture file "%s" (expected location "%s")', $file, $path));
		}

		$fixtureFile = yaml_parse_file($path);

		$fixtures = $this->fixtureLoader->load($fixtureFile['fixtures'], $params['params']);

		$return = (isset($fixtureFile['return'])) ? $fixtureFile['return'] : array();

		return $this->fixtureLoader->resolveMacros($return, $fixtures, $params);
	}

}

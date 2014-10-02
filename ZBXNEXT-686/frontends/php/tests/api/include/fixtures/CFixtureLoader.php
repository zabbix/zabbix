<?php

class CFixtureLoader {

	const TYPE_INCLUDE = 'include';
	const TYPE_API = 'api';
	const TYPE_DATA = 'data';

	/**
	 * @var CFixtureFactory
	 */
	protected $fixtureFactory;

	/**
	 * @var CArrayMacroResolver
	 */
	protected $macroResolver;

	public function __construct(CFixtureFactory $fixtureFactory, CArrayMacroResolver $macroResolver) {
		$this->macroResolver = $macroResolver;

		$fixtureFactory->setFixtureLoader($this);

		$this->fixtureFactory = $fixtureFactory;
	}

	public function load(array $fixtures, array $params = array()) {
		$fixtures = $this->expandShortenedSyntax($fixtures);

		foreach ($fixtures as $name => $fixtureConf) {
			if (!isset($fixtureConf['type'])) {
				throw $this->getException($name, '"type" parameter missing');
			}
			if (!isset($fixtureConf['params'])) {
				throw $this->getException($name, '"params" parameter missing');
			}

			$fixtureConf['params'] = $this->resolveMacros($fixtureConf['params'], $fixtures, $params);

			try {
				$fixture = $this->fixtureFactory->getFixture($fixtureConf['type']);
				$result = $fixture->load($fixtureConf['params']);

				$fixtures[$name]['result'] = $result;
			}
			catch (Exception $e) {
				throw $this->getException($name, $e->getMessage());
			}
		}

		return $fixtures;
	}

	protected function expandShortenedSyntax(array $fixtures) {
		// expand the short include syntax
		foreach ($fixtures as $name => &$fixture) {
			if (!is_array($fixture) || !array_key_exists('type', $fixture) && !array_key_exists('params', $fixture)) {
				$fixture = array(
					'type' => CFixtureLoader::TYPE_INCLUDE,
					'params' => array(
						'file' => $name,
						'params' => $fixture
					)
				);
			}
		}
		unset($fixture);

		return $fixtures;
	}

	protected function getException($fixtureName, $message) {
		return new Exception(sprintf('Error loading fixture "%1$s": %2$s', $fixtureName, $message));
	}

	public function resolveMacros(array $array, array $fixtures, array $params) {
		return $this->macroResolver->resolve($array, array(
			'params' => $params,
			'fixtures' => $fixtures
		));
	}


}

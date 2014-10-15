<?php

class CFixtureLoader {

	/**
	 * Factory for creating fixture objects.
	 *
	 * @var CFixtureFactory
	 */
	protected $fixtureFactory;

	/**
	 * Object for resolving array macros in fixtures.
	 *
	 * @var CArrayMacroResolver
	 */
	protected $macroResolver;

	/**
	 * @param CFixtureFactory $fixtureFactory		factory for creating fixture objects
	 * @param CArrayMacroResolver $macroResolver	object for resolving array macros in fixtures
	 */
	public function __construct(CFixtureFactory $fixtureFactory, CArrayMacroResolver $macroResolver) {
		$this->macroResolver = $macroResolver;

		$fixtureFactory->setFixtureLoader($this);

		$this->fixtureFactory = $fixtureFactory;
	}

	/**
	 * Load the given fixtures.
	 *
	 * Supported fixture parameters:
	 * - type	- fixture type
	 * - params	- fixture parameters, depend on fixture type
	 *
	 * @param array $fixtures
	 * @param array $params		array of parameters that will be used to resolve array macros before the fixtures are loaded
	 *
	 * @return array	fixtures array with the macros resolved
	 *
	 * @throws InvalidArgumentException		if a fixture cannot be loaded due to misconfiguration
	 * @throws UnexpectedValueException		if a fixture cannot be loaded due to external reasons
	 */
	public function load(array $fixtures, array $params = array()) {
		$fixtures = $this->expandShortenedSyntax($fixtures);

		foreach ($fixtures as $name => $fixtureConf) {
			if (!isset($fixtureConf['type'])) {
				throw new InvalidArgumentException($this->getExceptionMessage($name, '"type" parameter missing'));
			}
			if (!isset($fixtureConf['params'])) {
				throw new InvalidArgumentException($this->getExceptionMessage($name, '"params" parameter missing'));
			}

			$fixtureConf['params'] = $this->resolveMacros($fixtureConf['params'], $fixtures, $params);

			try {
				$fixture = $this->fixtureFactory->getFixture($fixtureConf['type']);
				$result = $fixture->load($fixtureConf['params']);

				$fixtures[$name]['result'] = $result;
			}
			catch (InvalidArgumentException $e) {
				throw new InvalidArgumentException($this->getExceptionMessage($name, $e->getMessage()), null, $e);
			}
			catch (UnexpectedValueException $e) {
				throw new UnexpectedValueException($this->getExceptionMessage($name, $e->getMessage()), null, $e);
			}
		}

		return $fixtures;
	}

	/**
	 * Converts the short include fixture syntax into the full.
	 *
	 * @param array $fixtures
	 *
	 * @return array
	 */
	protected function expandShortenedSyntax(array $fixtures) {
		// expand the short include syntax
		foreach ($fixtures as $name => &$fixture) {
			if (!is_array($fixture) || !array_key_exists('type', $fixture) && !array_key_exists('params', $fixture)) {
				$fixture = array(
					'type' => CFixtureFactory::TYPE_INCLUDE,
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

	/**
	 * Get an exception error message.
	 *
	 * @param string $fixtureName
	 * @param string $message
	 *
	 * @return string
	 */
	protected function getExceptionMessage($fixtureName, $message) {
		return sprintf('Error loading fixture "%1$s": %2$s', $fixtureName, $message);
	}

	/**
	 * Resolves array macros used in $array using the data from $fixtures and $params.
	 *
	 * @param array $array
	 * @param array $fixtures
	 * @param array $params
	 *
	 * @return array
	 */
	public function resolveMacros(array $array, array $fixtures, array $params) {
		return $this->macroResolver->resolve($array, array(
			'params' => $params,
			'fixtures' => $fixtures
		));
	}


}

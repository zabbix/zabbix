<?php

class CXmlTagHostPrototype extends CXmlTagHost
{
	public function __construct(array $schema = [])
	{
		parent::__construct();

		$schema += [];

		$this->schema += $schema;

		unset($this->schema['discovery_rule']);
	}
}

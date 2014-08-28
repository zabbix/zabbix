<?php

namespace Zabbix\Test;

use Symfony\Component\Yaml\Yaml;

class TestDatabase {

	protected $pdo;

	public function __construct(\PDO $pdo) {
		$this->pdo = $pdo;
	}

	public function clear() {
		try {
			$this->pdo->beginTransaction();

			// TODO: clear the database in the correct order
			foreach ($this->getTablesToClear() as $tableName) {
				$this->pdo->query('DELETE FROM '.$tableName);
			}

			$this->pdo->commit();
		}
		catch (\Exception $e) {
			$this->pdo->rollBack();

			throw $e;
		}
	}

	protected function getTablesToClear() {
		// TODO: implement a better ordering algorithm
		$tables = array();
		foreach (\DB::getSchema() as $tableName => $tableData) {
			if (in_array($tableName, array('dbversion', 'items'))) {
				continue;
			}

			$tables[] = $tableName;
		}

		// clear the items table first to avoid integrity check errors
		array_unshift($tables, 'items');

		return $tables;
	}

	public function loadFixtures(array $files, $loaded = array()) {
		try {
			$this->pdo->beginTransaction();

			foreach ($files as $file) {
				$this->loadFixture($file);
			}

			$this->pdo->commit();
		}
		catch (\Exception $e) {
			$this->pdo->rollBack();

			throw $e;
		}
	}

	protected function loadFixture($file, array $loaded = array()) {
		if (in_array($file, $loaded)) {
			return;
		}

		$path = __DIR__ . '/../../../tests/fixtures/'.$file.'.yml';

		if (!is_readable($path)) {
			throw new \Exception(sprintf('Can not find fixture file "%s" (expected location "%s")', $file, $path));
		}

		$fixtures = Yaml::parse(file_get_contents($path));

		// todo: validate here

		foreach ($fixtures as $suite => $data) {
			foreach ($data['require'] as $fixture) {
				$this->loadFixture($fixture, $loaded);
			}

			foreach ($data['rows'] as $table => $rows) {
				foreach ($rows as $objectName => $fields) {
					$query = 'INSERT INTO '.$table.' (';
					$query .= implode(', ', array_keys($fields));
					$query .= ') VALUES (';
					$query .= implode(', ', array_map(function ($value) {
						return ':'.$value;
					}, array_keys($fields)));
					$query .= ')';

					$query = $this->pdo->prepare($query);
					$query->execute($fields);
				}
			}

			$loaded[] = $file;
		}
	}

}

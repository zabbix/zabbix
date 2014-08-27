<?php

namespace Zabbix\Test;

use Symfony\Component\Yaml\Yaml;

class TestDatabase {

	protected $pdo;

	public function __construct(\PDO $pdo) {
		$this->pdo = $pdo;
	}

	public function clear() {
		foreach (\DB::getSchema() as $tableName => $tableData) {
			if ($tableName === 'dbversion') {
				continue;
			}

			$this->pdo->query('DELETE FROM '.$tableName);
		}
	}

	public function loadFixtures($file, $loaded = array()) {
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
				$this->loadDatabaseFixtures($fixture, $loaded);
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

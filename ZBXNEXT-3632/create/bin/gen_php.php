<?php

define('DEFAULT_SRC_FILE', dirname(__FILE__).'/../src/schema.tmpl');
define('DEFAULT_DEST_FILE', dirname(__FILE__).'/../src/schema.inc.php');

function parse_schema($path) {
	$schema = [];
	$lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

	foreach ($lines as $line) {
		$str = explode('|', $line, 2);
		$part = trim($str[0]);
		$rest_line = isset($str[1]) ? $str[1] : '';

		switch (trim($part)) {
			case 'TABLE':
				$str = explode('|', $rest_line);
				$table = trim($str[0]);
				$key = trim($str[1]);
				$schema[$table] = ['key' => $key, 'fields' => []];
				break;
			case 'FIELD':
				$str = explode('|', $rest_line);

				$field = trim($str[0]);
				$type = trim($str[1]);
				$default = trim($str[2]);
				$null = trim($str[3]);
				$ref_table = isset($str[6]) ? trim($str[6]) : null;
				$ref_field = isset($str[7]) ? trim($str[7]) : null;

				preg_match('/(?<type>[a-z_]+)(?:\((?<length>[0-9]+)\))?/', $type, $type_data);
				switch ($type_data['type']) {
					case 't_integer':
					case 't_nanosec':
					case 't_time':
						$type = 'DB::FIELD_TYPE_INT';
						$length = 10;
						break;
					case 't_id':
						$type = 'DB::FIELD_TYPE_ID';
						$length = 20;
						break;
					case 't_bigint':
					case 't_serial':
						$type = 'DB::FIELD_TYPE_UINT';
						$length = 20;
						break;
					case 't_double':
						$type = 'DB::FIELD_TYPE_FLOAT';
						$length = 16;
						break;
					case 't_varchar':
					case 't_char':
						$type = 'DB::FIELD_TYPE_CHAR';
						$length = $type_data['length'];
						break;
					case 't_blob':
					case 't_text':
					case 't_longtext':
					case 't_shorttext':
						$type = 'DB::FIELD_TYPE_TEXT';
						$length = false;
						break;
					case 't_image':
						$type = 'DB::FIELD_TYPE_BLOB';
						$length = 2048;
						break;
				}

				$data = [
					'null' => ($null == 'NULL' ? 'true' : 'false'),
					'type' => $type
				];

				if ($length) {
					$data['length'] = $length;
				}

				if (!empty($default)) {
					$data['default'] = $default;
				}

				if ($ref_table) {
					$data['ref_table'] = "'".$ref_table."'";
					$data['ref_field'] = "'".(!empty($ref_field) ? $ref_field : $field)."'";
				}

				$schema[$table]['fields'][$field] = $data;

				break;
		}
	}

	$str = "<?php\n";

	$str .= 'return ['."\n";
	foreach ($schema as $table => $data) {
		$str .= "\t'$table' => [\n";
		$str .= "\t\t'key' => '{$data['key']}',\n";
		$str .= "\t\t'fields' => [\n";
		foreach ($data['fields'] as $field => $fieldata) {
			$str .= "\t\t\t'$field' => [\n";
			foreach ($fieldata as $name => $val) {
				$str .= "\t\t\t\t'$name' => $val,\n";
			}
			$str .= "\t\t\t],\n";
		}
		$str .= "\t\t],\n";
		$str .= "\t],\n";
	}
	$str .= "];\n";

	return $str;
}

	$path_src = isset($argv[1]) ? $argv[1] : DEFAULT_SRC_FILE;
	if (!is_file($path_src)) {
		fwrite(STDERR, 'File does not exist: "'.$path_src.'"'."\n");
		exit(1);
	}
	$schema_text = parse_schema($path_src);
	fwrite(STDOUT, 'File parsed: "'.$path_src.'"'."\n");

	$path_dest = isset($argv[2]) ? $argv[2] : DEFAULT_DEST_FILE;
	$result = file_put_contents($path_dest, $schema_text);
	if ($result) {
		fwrite(STDOUT, 'File written: "'.$path_dest.'"'."\n");
		exit(0);
	}
	else {
		fwrite(STDERR, 'Cannot write file: "'.$path_dest.'"'."\n");
		exit(1);
	}

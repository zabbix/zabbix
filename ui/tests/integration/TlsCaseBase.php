<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

require_once 'vendor/autoload.php';

use PHPUnit\Framework\TestCase;

class CZabbixServerApi {
	public function __construct(
		public CZabbixServerClient $client,
		public string $sid = ''
	) {}

	public function testItem(array $data = []): array {
		$this->client->send(json_encode([
			'request' => 'item.test',
			'data' => $data,
			'sid' => $this->sid
		]));

		$response = $this->client->recv();
		if (!$response) {
			return [];
		}

		return json_decode($response, true);
	}
}

class CShellExec {
	private static string $server_bin = '';
	private static string $agent_bin = '';

	public static array $resources = [
		'server_pid' => null,
		'server_stdout' => null,
		'agent_stdout' => null,
		'server_stderr' => null,
		'agent_stderr' => null,
		'server_logs' => null,
		'agent_logs' => null
	];

	public static function agentBin(string $path = ''): string {
		if ($path) {
			if (!is_file($path) || !is_readable($path) || !is_executable($path)) {
				throw new RuntimeException(sprintf("[Bin error]: %s.", $path));
			}
			self::$agent_bin = $path;
		}
		return  self::$agent_bin;
	}

	public static function serverBin(string $path = ''): string {
		if ($path) {
			if (!is_file($path) || !is_readable($path) || !is_executable($path)) {
				throw new RuntimeException(sprintf("[Bin error]: %s.", $path));
			}
			self::$server_bin = $path;
		}
		return self::$server_bin;
	}

	private static function config(array $config): string {
		$config = implode(PHP_EOL, array_map(static fn (string $key) => "$key={$config[$key]}", array_keys($config)));
		$temp_config = tempnam('/tmp', 'server_config_');
		file_put_contents($temp_config, $config);
		return $temp_config;
	}

	public static function logs(): string {
		$result = [];

		foreach (self::$resources as $key => $resource) {
			if (is_resource($resource)) {
				$result[] = "Logs '$key':\n";
				$result[] = stream_get_contents($resource);
			}
		}

		return implode(PHP_EOL, $result);
	}

	public static function serverTick(): array {
		$events = [];

		if ($resource = self::$resources['server_logs']) {
			$data = stream_get_contents($resource);
			foreach (explode(PHP_EOL, $data) as $line) {
				$errors = [
					'/cannot initialize runtime control service/',
					'/Zabbix Server stopped/',
					'/\[Z3001\] connection to database .* failed:/'
				];
				foreach ($errors as $error) {
					if (1 == preg_match($error, $line)) {
						$events[] = 'error';
					}
				}
				$ready = '/server #0 started \[main process\]/';
				if (1 == preg_match($ready, $line)) {
					$events[] = 'ready';
				}
			}
		}
		if ($resource = self::$resources['server_stderr']) {
			$data = stream_get_contents($resource);
			if ($data) {
				throw new RuntimeException(sprintf("[Server stderr]:\n %s.", $data));
			}
		}
		if ($resource = self::$resources['server_stdout']) {
			$data = stream_get_contents($resource);
		}

		return $events;
	}

	public static function agentTick(): array {
		$events = [];

		if ($resource = self::$resources['agent_logs']) {
			$data = stream_get_contents($resource);
			foreach (explode(PHP_EOL, $data) as $line) {
				$errors = [];
				foreach ($errors as $error) {
					if (1 == preg_match($error, $line)) {
						$events[] = 'error';
					}
				}
				$ready = '/Zabbix Agent2 hostname:/';
				if (1 == preg_match($ready, $line)) {
					$events[] = 'ready';
				}
			}
		}
		if ($resource = self::$resources['agent_stderr']) {
			$data = stream_get_contents($resource);
			if ($data) {
				throw new RuntimeException(sprintf("[Agent stderr]:\n %s.", $data));
			}
		}
		if ($resource = self::$resources['agent_stdout']) {
			$data = stream_get_contents($resource);
		}

		return $events;
	}

	public static function agent(array $config) {
		$temp_log = tempnam('/tmp', 'agent_log_');
		$temp_pid = tempnam('/tmp', 'agent_pid_');
		$temp_stderr = tempnam('/tmp', 'agent_stderr_');
		$temp_stdout = tempnam('/tmp', 'agent_stdout_');

		$config['Server'] = '127.0.0.1,0.0.0.0';
		$config['LogFile'] = $temp_log;
		$config['PidFile'] = $temp_pid;

		if (!self::agentBin()) {
			throw new RuntimeException('No agent bin is set.');
		}

		$config = self::config($config);
		$cmd = implode(' ', array_map(
			static fn ($arg) => escapeshellarg($arg),
			[self::agentBin(), '--foreground', '--config', $config])
		);
		$cmd .= " > $temp_stdout 2> $temp_stderr &";
		exec($cmd, $output, $status);

		if ($status != 0) {
			throw new RuntimeException(sprintf("[Server exit (%s) stderr]:\n %s.", $status, $output));
		}

		self::$resources['agent_logs'] = fopen($temp_log, 'r');
		self::$resources['agent_stdout'] = fopen($temp_stdout, 'r');
		self::$resources['agent_stderr'] = fopen($temp_stderr, 'r');

		$ready = false;
		$exited = false;
		$timed = time();
		while (!$ready && !$exited && !($timed + 10 < time())) {
			sleep(1);
			foreach (CShellExec::agentTick() as $event) {
				switch ($event) {
					case 'error':
						$exited = true;
					break;
					case 'ready':
						$ready = true;
					break;
				}
			}
		}
		return [$ready, $exited, $timed];
	}

	public static function server(array $config) {
		$temp_log = tempnam('/tmp', 'server_log_');
		$temp_pid = tempnam('/tmp', 'server_pid_');
		$temp_stderr = tempnam('/tmp', 'server_stderr_');
		$temp_stdout = tempnam('/tmp', 'server_stdout_');

		$config['LogFile'] = $temp_log;
		$config['PidFile'] = $temp_pid;

		if (!self::serverBin()) {
			throw new RuntimeException('No server bin is set.');
		}

		$config = self::config($config);
		$cmd = implode(' ', array_map(
			static fn ($arg) => escapeshellarg($arg),
			[self::serverBin(), '--foreground', '--config', $config])
		);
		$cmd .= " > $temp_stdout 2> $temp_stderr &";
		exec($cmd, $output, $status);

		if ($status != 0) {
			throw new RuntimeException(sprintf("[Server exit (%s) stderr]:\n %s.", $status, $output));
		}

		self::$resources['server_logs'] = fopen($temp_log, 'r');
		self::$resources['server_stdout'] = fopen($temp_stdout, 'r');
		self::$resources['server_stderr'] = fopen($temp_stderr, 'r');

		$ready = false;
		$exited = false;
		$timed = time();
		while (!$ready && !$exited && !($timed + 10 < time())) {
			sleep(1);
			foreach (CShellExec::serverTick() as $event) {
				switch ($event) {
					case 'error':
						$exited = true;
					break;
					case 'ready':
						$ready = true;
					break;
				}
			}
		}
		return [$ready, $exited, $timed];
	}
}

class CZabbixServerClient {
	const HEADER = "ZBXD\1";

	public function __construct(
		public string $address,
		public string $ca_file,
		public string $key_file,
		public string $crt_file,
	) {}

	private static function pack(string $data, &$strlen): string {
		$result = self::HEADER.pack('V', strlen($data))."\x00\x00\x00\x00".$data;
		$strlen = strlen($result);

		return $result;
	}

	public function recv(): string {
		$socket = self::connection($this->address, $this->ca_file, $this->key_file, $this->crt_file);
		$result = stream_get_contents($socket);

		if (!$result) {
			return $result;
		}

		$expected_len = unpack('Vlen', substr($result, strlen(self::HEADER), 4))['len'];

		return substr($result, -$expected_len);
	}

	public function send(string $data): int {
		$socket = self::connection($this->address, $this->ca_file, $this->key_file, $this->crt_file);
		$result = fwrite($socket, self::pack($data, $strlen));

		if ($result === false) {
			return 1;
		}
		if ($result != $strlen) {
			return 2;
		}

		return 0;
	}

	public static function connection(string $address, string $ca_file, string $key_file, string $crt_file) {
		static $socket = null;

		if ($socket === null) {
			$context = stream_context_create([
				'ssl' => [
					'cafile' => $ca_file,
					'local_pk' => $key_file,
					'local_cert' => $crt_file,
					'capture_peer_cert' => true,
					'verify_peer_name' => false
				]
			]);

			['host' => $host, 'port' => $port] = parse_url($address);
			$address = 'tls://'.$host.':'.$port;
			$socket = @stream_socket_client($address, $error_code, context: $context);

			if (!$socket) {
				throw new RuntimeException("sockopen error");
			}
		}

		return $socket;
	}
}

class TlsCaseBase extends TestCase {
	function setUp(): void {
		if ($procs = strtr((string) `pgrep zabbix`, PHP_EOL, ' ')) {
			`kill -9 $procs`;
		}
		parent::setUp();
	}

	public static function serverApi(string $address, string $ca_file, string $key_file, string $crt_file, string $sid): CZabbixServerApi {
		$client = new CZabbixServerClient($address, $ca_file, $key_file, $crt_file);

		return new CZabbixServerApi($client, $sid);
	}

	public static function sid(string $address, string $username, string $password): string {
		$data = [
			'jsonrpc' => '2.0',
			'method' => 'user.login',
			'params' => [
				'username' => $username,
				'password' => $password
			],
			'id' => 1
		];

		['host' => $host, 'port' => $port] = parse_url($address);
		$url = "http://$host:$port/api_jsonrpc.php";
		$ch = curl_init($url);

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json-rpc'
			],
			CURLOPT_POSTFIELDS => json_encode($data)
		]);

		$sid = json_decode(curl_exec($ch))->result;

		return $sid;
	}

	public static function generateCerts(string $ca_subject, string $server_subject, string $agent_subject) {
		$working_dir = '/tmp/tmp_cert';

		if (is_dir($working_dir)) {
			`rm -rf $working_dir`;
		}
		`mkdir -p $working_dir`;

		$ca_key = "$working_dir/ca.key";
		$ca_crt = "$working_dir/ca.crt";

		$agent_key = "$working_dir/agent.key";
		$agent_crt = "$working_dir/agent.crt";
		$agent_csr = "$working_dir/agent.csr";

		$server_key = "$working_dir/server.key";
		$server_crt = "$working_dir/server.crt";
		$server_csr = "$working_dir/server.csr";

		`
			set -e
			cd $working_dir

			# Generating CA private key...
			openssl genrsa -out "$ca_key" 4096
			# Generating CA certificate...
			openssl req -new -x509 -days "3650" -key "$ca_key" -out "$ca_crt" -subj "$ca_subject"

			# Generating server private key...
			openssl genrsa -out "$server_key" 2048
			# Generating server CSR...
			openssl req -new -key "$server_key" -out "$server_csr" -subj "$server_subject"
			# Signing server CSR with CA...
			openssl x509 -req -days "365" -in "$server_csr" -CA "$ca_crt" -CAkey "$ca_key" -CAcreateserial -sha256 -out "$server_crt"
			# Verifying signed certificate...
			openssl verify -CAfile "$ca_crt" "$server_crt"

			# Generating agent private key...
			openssl genrsa -out "$agent_key" 2048
			# Generating agent CSR...
			openssl req -new -key "$agent_key" -out "$agent_csr" -subj "$agent_subject"
			# Signing agent CSR with CA...
			openssl x509 -req -days "365" -in "$agent_csr" -CA "$ca_crt" -CAkey "$ca_key" -CAcreateserial -sha256 -out "$agent_crt"
			# Verifying signed certificate...
			openssl verify -CAfile "$ca_crt" "$agent_crt"

			chmod 666 ./*
		`;

		return [
			'ca' => [$ca_key, $ca_crt],
			'server' => [$server_key, $server_crt],
			'agent' => [$agent_key, $agent_crt]
		];
	}
}

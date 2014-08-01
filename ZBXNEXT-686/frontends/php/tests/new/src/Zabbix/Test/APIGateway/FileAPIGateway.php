<?php

namespace Zabbix\Test\APIGateway;

use Zabbix\Test\APITestRequest;
use Zabbix\Test\APITestResponse;

class FileAPIGateway extends BaseAPIGateway {

	/**
	 * API Gateway endpoint (file)
	 *
	 * @var string
	 */
	protected $endpoint;

	/**
	 * {@inheritdoc}
	 */
	public function execute(APITestRequest $request)
	{
		if ($request->isSecure()) {
			$request->setToken($this->authorize());
		}

		$this->setStreamWrapper($request->getBody());

		$_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';

		ob_start();
		require $this->endpoint;
		$contents = ob_get_contents();
		ob_end_clean();

		$json = json_decode($contents, true);

		if (null === $json || !is_array($json)) {
			throw new \Exception(sprintf('JSON returned by API call is not decodable ("%s" given)', $contents));
		}

		$this->restoreStreamWrapper();

		if (isset($json['result']) && !isset($json['error'])) {
			return APITestResponse::createTestResponse($json['result'], $json['id']);
		}
		elseif (isset($json['error']) && !isset($json['result'])) {
			return APITestResponse::createTestException($json['error']['message'],
				$json['error']['data'],
				$json['error']['code'],
				$json['id']
			);
		}
		else {
			throw new \Exception(
				sprintf('Wow. The API response does not look like both response and error, "%s" given', $contents)
			);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function configure(array $params, array $testConfig)
	{
		$keys = array_keys($params);

		if ($keys != array() && $keys != array('endpoint')) {
			throw new \Exception('Only "endpoint" is accepted as parameter');
		}

		$path = __DIR__.'/../../../../../../api_jsonrpc.php';

		$this->endpoint = isset($params['endpoint']) ? $params['endpoint'] :
			realpath($path);

		if (!is_readable($this->endpoint)) {
			throw new \Exception(sprintf(
				'Current JSON API endpoint file is not readable (best guess was "%s")', $path
			));
		}

		parent::configure($params, $testConfig);
	}

	protected function restoreStreamWrapper() {
		stream_wrapper_restore('php');
	}

	protected function setStreamWrapper($contents) {
		stream_wrapper_unregister('php');
		stream_wrapper_register('php', 'Zabbix\Test\Util\InputStreamWrapper');

		file_put_contents('php://input', $contents);
	}

}

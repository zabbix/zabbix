<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
// @author: Artem Suharev <aly@zabbix.com>

class CJSONrpc{
	const VERSION = '2.0';

	public $json;

	private $multicall = false;
	private $error = false;
	private $response = array();
	private $error_list;
	private $zbx2json_errors;

	public function __construct(){
		$this->json = new CJSON();

		$this->initErrors();
	}

	public function process($json_call){
		$json_decoded = $this->json->decode($json_call, true);
		if(!$json_decoded){
			$this->json_error(null, '-32700', null, null, true);
			return;
		}

		if(!isset($json_decoded['jsonrpc']))
			$this->multicall = true;
		else
			$json_decoded = array($json_decoded);

		foreach($json_decoded as $call){
			if(!isset($call['id'])) $call['id'] = null;  // Notification

			if($this->validate($call)){
				$params = isset($call['params']) ? $call['params'] : null;
				$auth = isset($call['auth']) ? $call['auth'] : null;

				$result = czbxrpc::call($call['method'], $params, $auth);

				if(isset($result['result'])){
					// Notifications MUST NOT be answered
					if($call['id'] !== null){
						$formed_resp = array(
							'jsonrpc' => self::VERSION,
							'result' => $result,
							'id' => $call['id']
						);

						if($this->multicall)
							$this->response[] = $formed_resp;
						else
							$this->response = $formed_resp;
					}
				}
				else{
					$result['data'] = isset($result['data']) ? $result['data'] : null;
					$result['debug'] = isset($result['debug']) ? $result['debug'] : null;
					$errno = $this->zbx2json_errors[$result['error']];

					$this->json_error($call['id'], $errno, $result['data'], $result['debug']);
				}
			}
		}
	}

	public function validate($call){
		if(!isset($call['jsonrpc'])){
			$this->json_error($call['id'], '-32600', 'JSON-rpc version is not specified.', null, true);
			return false;
		}

		if($call['jsonrpc'] != self::VERSION){
			$this->json_error($call['id'], '-32600', 'Expacting JSON-rpc version 2.0, '.$call['jsonrpc'].' is given.', null, true);
			return false;
		}

		if(!isset($call['method'])){
			$this->json_error($call['id'], '-32600', 'JSON-rpc method is not defined.');
			return false;
		}

		if(isset($call['params']) && !is_array($call['params'])){
			$this->json_error($call['id'], '-32602', 'JSON-rpc params is not an Array.');
			return false;
		}

	return true;
	}

	public function result($encoded=true){
		if(!$encoded) return $this->response;
		else return $this->json->encode($this->response);
	}

	public function is_error(){
		return $this->error;
	}

// NOT Public methods
//------------------------------------------------------------------------------

	private function json_error($id, $errno, $data=null, $debug=null, $force_err=false){
// Notifications MUST NOT be answered, but error MUST be generated on JSON parse error
		if(is_null($id) && !$force_err) return;

		$this->error = true;

		if(!isset($this->error_list[$errno])){
			$data = 'JSON-rpc error generation failed. No such error: '.$errno;
			$errno = '-32400';
		}

		$error = $this->error_list[$errno];

		if(!is_null($data))
			$error['data'] = $data;
		if(!is_null($debug))
			$error['debug'] = $debug;


		$formed_error = array(
			'jsonrpc' => self::VERSION,
			'error' => $error,
			'id' => $id
		);

		if($this->multicall)
			$this->response[] = $formed_error;
		else
			$this->response = $formed_error;
	}

	private function initErrors(){
		$this->error_list = array(
			'-32700' => array(
					'code' => -32700,
					'message' =>'Parse error',
					'data' => 'Invalid JSON. An error occurred on the server while parsing the JSON text.'),
			'-32600' => array(
					'code' => -32600,
					'message' =>'Invalid Request.',
					'data' => 'The received JSON is not a valid JSON-RPC Request.'),
			'-32601' => array(
					'code' => -32601,
					'message' =>'Method not found.',
					'data' => 'The requested remote-procedure does not exist / is not available'),
			'-32602' => array(
					'code' => -32602,
					'message' =>'Invalid params.',
					'data' => 'Invalid method parameters.'),
			'-32603' => array(
					'code' => -32603,
					'message' =>'Internal error.',
					'data' => 'Internal JSON-RPC error.'),
			'-32500' => array(
					'code' => -32500,
					'message' =>'Application error.',
					'data' => 'No details'),
			'-32400' => array(
					'code' => -32400,
					'message' =>'System error.',
					'data' => 'No details'),
			'-32300' => array(
					'code' => -32300,
					'message' =>'Transport error.',
					'data' => 'No details'));

		$this->zbx2json_errors = array(
			ZBX_API_ERROR_NO_METHOD => '-32601',
			ZBX_API_ERROR_PARAMETERS => '-32602',
			ZBX_API_ERROR_NO_AUTH => '-32602',
			ZBX_API_ERROR_PERMISSIONS => '-32500',
			ZBX_API_ERROR_INTERNAL => '-32500',
		);
	}
}
?>

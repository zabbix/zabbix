<?php
namespace LLNW\Zabbix;

class Hostlist {
    public function get($name, $output = 'json') {
        $this->_getpull($name, $output, 'hostlist.get');
    }

    public function pull($name, $output = 'json') {
        $this->_getpull($name, $output, 'hostlist.pull');
    }

    private function _getpull($list_name, $output, $method) {
        global $logger;

        $list_name = (isset($list_name) && $list_name != '') ? addslashes($list_name) : '';
        $output    = (isset($output) && $output == 'raw') ? 'raw' : 'json';

        if ($list_name == '') {
            $logger->log("Error: no hostlist name supplied in request");
            sendErrorResponse('235','Invalid params','no hostlist name provided');
        }

        $list_dir = '/tmp/';

        if (!is_readable($list_dir.$list_name)) {
            $logger->log("Error: hostlist file $list_dir$list_name does not exist");
            sendErrorResponse('151','Internal error','hostlist file does not exist');
        }

        $hostlist = file($list_dir.$list_name);

        // removes the newlines from each item in array
        $hostlist = array_map('trim', $hostlist);

        $resp['result'] = 'success';
        $resp['hosts'] = $hostlist;

        // method 'pull' truncates the list.
        if ($method == 'hostlist.pull') {
            if (!is_writable($list_dir.$list_name)) {
                $logger->log("Error: hostlist file $list_dir$list_name cannot be truncated");
            }
            else {
                $fh = fopen($list_dir.$list_name, 'w');
                fclose($fh);
            }
        }

        if ($output == 'json') {
            sendResponse($resp);
        }
        else {
            foreach ($hostlist as $host) {
                print $host."\n";
            }
            exit;
        }
    }
}
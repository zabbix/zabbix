<?php
namespace LLNW\Zabbix;

class Proxymap
{
    protected $static_proxies;

    public function __construct()
    {
        // TODO: Move to static constant
        $this->static_proxies['PHX01'] = 'zabbix-proxy01.phx2.llnw.net';
        $this->static_proxies['PHX02'] = 'zabbix-proxy02.phx2.llnw.net';
        $this->static_proxies['PHXEXT01'] = 'zabbix-proxy-ext01.phx2.llnw.net';
        $this->static_proxies['PHXEXT02'] = 'zabbix-proxy-ext02.phx2.llnw.net';
        $this->static_proxies['PHXINT01'] = 'zabbix-proxy-int01.phx2.llnw.net';
        $this->static_proxies['PHXINT02'] = 'zabbix-proxy-int02.phx2.llnw.net';
        $this->static_proxies['PHXSEC01'] = 'zabbix-proxy-sec01.phx4.llnw.net';
        $this->static_proxies['PHXSEC02'] = 'zabbix-proxy-sec02.phx4.llnw.net';
    }

    public function get($hostnames, $selectAll, $output)
    {
        $hostnames = (is_array($hostnames) && count($hostnames) > 0) ? $hostnames : '';
        $all      = (isset($selectAll)) ? 1 : '';
        $output   = (isset($output) && $output == 'csv') ? 'csv' : 'json';

        if ($hostnames == '' && $all == '') {
            dbug("Error: no hostnames or selectAll param supplied in request");
            sendErrorResponse('235', 'Invalid params', 'no hostname or selectAll param provided');
        }

        if ($all == 1) {
            $host_hash = getHosts();
        } else {
            $hostids = array();
            foreach ($hostnames as $host) {
                $hostid = getHostId($host);
                array_push($hostids, $hostid);
            }
            $host_hash = getHosts($hostids);
        }

        $resp = array();

        $proxies = getProxies();

        $p_cnt = count($proxies);
        $proxies[$p_cnt]['host'] = 'MainServer';
        $proxies[$p_cnt]['proxyid'] = 0;

        foreach ($proxies as $a) {

            $proxy_name = $a['host'];
            $proxy_id   = $a['proxyid'];

            if (isset($this->static_proxies[$proxy_name])) {
                $proxy_fqdn = $this->static_proxies[$proxy_name];
            } elseif (preg_match('/(\D{3})(\d{2})/', $proxy_name, $m)) {
                $site = strtolower($m[1]);
                $num  = $m[2];
                // HACK: Override phx2 hostname
                $site = ($site == 'phx' ? 'phx2' : $site);
                $proxy_fqdn = 'zabbix-proxy'.$num.'.'.$site.'.llnw.net';
            } else {
                $proxy_fqdn = 'zabbix-mainha.phx2.llnw.net';
            }

            $proxy_hash[$proxy_id] = $proxy_fqdn;

        }

        $c=0;
        foreach ($host_hash as $a => $b) {
            $host = $host_hash[$a]['host'];
            $host_proxyid = $host_hash[$a]['proxy_hostid'];
            $resp['result'][$c]['host'] = $host;
            $resp['result'][$c]['proxy'] = $proxy_hash[$host_proxyid];
            ++$c;
        }

        if ($output == 'json') {
            sendResponse($resp);
        } else {
            foreach ($resp['result'] as $c) {
                print $c['host'].','.$c['proxy']."\n";
            }
            exit;
        }
    }
}

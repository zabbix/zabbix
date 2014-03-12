<?php

namespace LLNW\Zabbix;

class Circuit
{
    public function get($host, $regx)
    {
        $host = addslashes($host);
        $regx = addslashes($regx);

        $q = "SELECT * FROM interface_cache
              WHERE ifAdminStatus = 1
              AND device = '$host'
              AND ifDescr LIKE '%Ethernet%'
              AND ifAlias REGEXP '$regx'";

        $res = $cdb->get_results($q);

        $data = array();

        $c=0;
        foreach ($res as $a) {
            $data['data'][$c]['{#HOST}'] = $host;
            $data['data'][$c]['{#IFDESCR}'] = $a->ifDescr;
            $data['data'][$c]['{#IFALIAS}'] = $a->ifAlias;
            $data['data'][$c]['{#IFINDEX}'] = $a->ifIndex;
            ++$c;
        }

        $json = json_encode($data);

        print $json;
    }
}

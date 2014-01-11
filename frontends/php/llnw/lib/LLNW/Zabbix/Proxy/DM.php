<?php
namespace LLNW\Zabbix;

class DM
{
    public function info()
    {
        // Original: proxies.php:229
        $data = array();

        $sortfield = getPageSortField('host');

        $data['proxies'] = API::Proxy()->get(array(
            'editable' => true,
            'selectHosts' => API_OUTPUT_EXTEND,
            'output' => API_OUTPUT_EXTEND,
            'sortfield' => $sortfield,
        ));
        $data['proxies'] = zbx_toHash($data['proxies'], 'proxyid');
        $proxyids = array_keys($data['proxies']);

        order_result($data['proxies'], $sortfield, getPageSortOrder());

        if (function_exists('DBcondition')) {
                $dbcondition = ' AND '.DBcondition('h.proxy_hostid', $proxyids);
        } else {
                $dbcondition = ' AND '.dbConditionInt('h.proxy_hostid', $proxyids);
        }
        // calculate performance
        $dbPerformance = DBselect(
            'SELECT h.proxy_hostid,SUM(1.0/i.delay) AS qps'.
            ' FROM items i,hosts h'.
            ' WHERE i.status='.ITEM_STATUS_ACTIVE.
            ' AND i.hostid=h.hostid '.
            ' AND h.status='.HOST_STATUS_MONITORED.
            ' AND i.delay<>0'.
            $dbcondition.
            ' GROUP BY h.proxy_hostid'
        );
        while ($performance = DBfetch($dbPerformance)) {
            $data['proxies'][$performance['proxy_hostid']]['perf'] = round($performance['qps'], 2);
        }

        // get items
        $items = API::Item()->get(array(
            'groupCount' => 1,
            'countOutput' => 1,
            'proxyids' => $proxyids,
            'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)),
            'webitems' => 1,
            'monitored' => 1
        ));
        foreach ($items as $item) {
            if (!isset($data['proxies'][$item['proxy_hostid']]['item_count'])) {
                $data['proxies'][$item['proxy_hostid']]['item_count'] = 0;
            }
            $data['proxies'][$item['proxy_hostid']]['item_count'] += $item['rowscount'];
        }

        return $data;
    }
}

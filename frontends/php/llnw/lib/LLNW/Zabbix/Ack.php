<?php

namespace LLNW\Zabbix;

class Ack
{
    public function get($eventids)
    {
        global $db, $logger;
        // HACK: SQL injection!
        $sql_where = (isset($eventids) && $eventids != '') ? 'WHERE eventid IN ('.$eventids.')' : '';
        $q = "SELECT * FROM acknowledges $sql_where ORDER BY clock ASC";

        $logger->log("get.ack query: $q");

        return $db->get_results($q);
    }

    public function add($userid, $triggerid, $message = '')
    {
        global $db, $logger;

        // TODO: Act on behalf of userid, lookup trigger eventid
        //  (trigger.get, selectLastEvent) -> event.acknowledge
        // objectid in the events table have to use triggerid because checkboxes
        //  are trigger ids.

        $clock   = strtotime('now'); //$_POST['clock'];
        $message = urldecode($message);
        $message = addslashes($message);

        $logger->log("received triggerid: $triggerid");

        $q = "SELECT eventid FROM events ".
                "WHERE objectid=$triggerid AND value=1 ".
                "ORDER BY clock DESC LIMIT 1";

        $eventid = $db->get_var($q);
        $logger->log("got eventid from events table: $eventid");

        if (isset($eventid) && $eventid > 0) {

            $ackid = $db->get_var("SELECT nextid FROM ids WHERE table_name='acknowledges' LIMIT 1");
            $logger->log("got nextid: $ackid");
            if (isset($ackid) && $ackid > 0) {
                $next_id = $ackid + 1;
                $logger->log("adding $next_id to ids table");
                $db->get_var("UPDATE ids SET nextid=$next_id WHERE table_name='acknowledges'");

                $q = "UPDATE events SET acknowledged=1 WHERE eventid=$eventid";
                $db->query($q);
                $logger->log($q);

                $q = "INSERT INTO acknowledges SET acknowledgeid='$ackid'," .
                     " userid='$userid', eventid='$eventid', clock='$clock'," .
                     " message='$message'";
                $db->query($q);
                $logger->log($q);

                return 'success';
            }
        }

        return 'error';
    }
}

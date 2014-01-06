<?php
namespace LLNW\Zabbix;

class Squelch
{
    public function add($hostname, $username, $reason, $comment, $start, $end)
    {
        global $ldb, $logger;

        $hostname_param = $hostname;
        unset($hostname);

        // accept a start and end timestamp. if start time is omitted, use now();
        // if end time is also omitted, use a default of 1 hour.
        // only accept hostnames. we'll convert hostnames to hostids in the cron script that processes squelches.
        // also accept a username, reason (short text), and comment (longer text).

        $start_ts = (isset($start) && $start != '') ? addslashes($start) : strtotime("now");
        $end_ts   = (isset($end) && $end != '') ? addslashes($end) : strtotime("+1 hour");

        $start = date('Y-m-d H:i:s', $start_ts);
        $end   = date('Y-m-d H:i:s', $end_ts);

        $username = addslashes($username);
        $reason   = addslashes($reason);
        $comment  = addslashes($comment);

        if ($username == '') {
            error_log("Error: no username set to apply squelch!");
            sendErrorResponse('235','Invalid params','no username provided');
        }

        // validate hostname entries
        foreach ($hostname_param as $key=>$hostname) {
            $hostname = addslashes($hostname);

            error_log("received squelch for $hostname start: $start end: $end");

            if ($hostname == '') {
                error_log("Error: no hostname set to apply squelch!");
                sendErrorResponse('234','Invalid params','invalid hostname or hostname missing');
            }
        }

        // see if a squelch already exists for given host.
        // if so, and existing clear time is less then new clear time then update it.
        // if not, insert a new squelch entry.

        // maintain 2 tables: squelch = just hostid, start and end times.
        // squelch_log = entries for each submittion - hostid,start,end plus - username,reason,comment,action (add or clear)


        foreach ($hostname_param as $key=>$hostname) {
            $hostname = addslashes($hostname);

            $q = "SELECT * FROM squelch WHERE hostname = '$hostname'";
            $e = $ldb->get_row($q);

            if (isset($e) && $e->id > 0) {

                $id = $e->id;

                $existing_start_ts = strtotime($e->start);
                $existing_end_ts   = strtotime($e->end);

                $sql = "UPDATE squelch SET ";

                // if new start time is earlier than existing starttime:
                if ($start_ts < $existing_start_ts) {
                    // need to udpate start timea
                    $su=1;
                    $sql .= "start = '$start'";
                }
                // if existing end time is earlier than the new end time:
                if ($existing_end_ts < $end_ts) {
                    // need to update end time
                    $eu=1;
                    $sql .= (isset($su)) ? ", end = '$end'" : "end = '$end'"; // adds the comma if we're appending.
                }

                $sql .= ", updated_by = '$username', updated_date=now()";
                $sql .= " WHERE id = $id";

                if (isset($su) || isset($eu)) {
                    $logger->log($sql);
                    $ldb->query($sql);
                }
            } else {
                $sql = "INSERT INTO squelch SET ".
                        "hostname = '$hostname', ".
                        "start = '$start', ".
                        "end = '$end', ".
                        "created_by = '$username', ".
                        "created_date = now(), ".
                        "updated_date = '0000-00-00 00:00:00'";

                $logger->log($sql);
                $ldb->query($sql);

                $id = $ldb->insert_id;
            }

            // log the details of this squelch request

            $sql = "INSERT INTO squelch_log SET ".
                    "squelch_id = $id, ".
                    "hostname = '$hostname', ".
                    "start = '$start', ".
                    "end = '$end', ".
                    "reason = '$reason', ".
                    "comment = '$comment', ".
                    "created_by = '$username', ".
                    "created_date = now(), ".
                    "updated_date = '0000-00-00 00:00:00'";

            $logger->log($sql);
            $ldb->query($sql);

            $resp['result']['hostnames'][$key] = $hostname;
        }
        sendResponse($resp);
    }

    public function clear()
    {
        // run the possibility of clearing someone elses squelch that may have been submitted for a different reason.
        // but not going to worry about that now.
        // be sure to log these clears as a separate entry in the squlech_log so we can track potential issues.
        // this separate table may help with logic to avoid clearing other squelches?
    }
}

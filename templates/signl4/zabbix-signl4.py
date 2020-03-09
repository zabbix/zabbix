#!/usr/bin/env python3
# -*- coding: utf-8 -*-

# Usage:
# Copy the script here: usr/lib/zabbix/alertscripts/zabbix-signl4.py
# Give execution rights to the script
# python3 zabbix-signl4.py teamsecret subject message
# Example: python3 zabbix-signl4.py 'xxxxxxxx' 'New Alert from Zabbix' 'Alert message.'

import requests
import json
import argparse
import os
import sys
import time

# Settings
enable_log = True
log_file = "/var/log/zabbix/signl4.log"
api_url = "https://connect.signl4.com/webhook/"


# Logging
def log(msg):
    timestamp = time.strftime('%Y-%m-%d %H:%M:%S')
    msg = "[%s] %s" % (timestamp, msg)

    # To stdout
    print(msg)

    # Log file
    if enable_log:
        try:
            lf = open(log_file, 'a')
            lf.write("%s\n" % (msg))

        except (OSError) as exc:
            print("Error while writing log file: %s" % str(exc))
            return False
        
        lf.close()    

    return True


def send_alert(password, message):

    resp = requests.post(api_url + password, params=None, data=json.dumps(message))

    if resp.status_code == 200:
        result = resp.text.split("\t")
        if result.find('eventId') == -1:
            sys.stdout.write(result)
            return 0
        
    sys.stderr.write(resp.text)
    return 2


# Arguments
parser = argparse.ArgumentParser(description='Send alert to SIGNL4 team.')
parser.add_argument('teamsecret', help='SIGNL4 team secret.')
parser.add_argument('subject', help='Subject line.')
parser.add_argument('message', help='Message text.')

# Arguments
args = parser.parse_args()
teamsecret = args.teamsecret
subject = args.subject
message = args.message

# Alert data (JSON)
data = {
    'Title': subject,
    'Message': message
}

# Send alert
send_alert(teamsecret, data)

# Success
log("SIGNL4 alert sent to team [%s]" % (teamsecret))
sys.exit(0)

#!/usr/bin/env python3
#
# Copyright (C) 2001-2026 Zabbix SIA
#
# This program is free software: you can redistribute it and/or modify it under the terms of
# the GNU Affero General Public License as published by the Free Software Foundation, version 3.
#

import argparse
import json
import os
import signal
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer


class BridgeAdapterMock(BaseHTTPRequestHandler):
    server_version = "ZabbixBridgeAdapterMock/1.0"

    def do_POST(self):
        length = int(self.headers.get("Content-Length", "0"))
        raw_body = self.rfile.read(length).decode("utf-8")

        try:
            body = json.loads(raw_body)
        except json.JSONDecodeError:
            body = None

        self.server.write_request(body, raw_body)

        response = self.server.build_response(body)
        encoded = json.dumps(response, separators=(",", ":")).encode("utf-8")

        self.send_response(self.server.status_code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(encoded)))
        self.end_headers()
        self.wfile.write(encoded)

    def log_message(self, fmt, *args):
        return


class AdapterServer(ThreadingHTTPServer):
    def __init__(self, address, log_file, status_code, notify_error):
        super().__init__(address, BridgeAdapterMock)
        self.log_file = log_file
        self.status_code = status_code
        self.notify_error = notify_error

    def write_request(self, body, raw_body):
        record = {
            "method": body.get("method") if isinstance(body, dict) else None,
            "body": body,
            "raw_body": raw_body
        }

        with open(self.log_file, "a", encoding="utf-8") as log:
            log.write(json.dumps(record, separators=(",", ":")) + "\n")

    def build_response(self, body):
        request_id = body.get("id") if isinstance(body, dict) else None
        method = body.get("method") if isinstance(body, dict) else None

        if method == "device.notify" and self.notify_error:
            return {
                "jsonrpc": "2.0",
                "id": request_id,
                "error": {
                    "code": "bridge.adapter.error",
                    "message": "Mock bridge-adapter rejected notification",
                    "data": "device.notify mock failure"
                }
            }

        if method == "device.init":
            return {
                "jsonrpc": "2.0",
                "id": request_id,
                "result": {
                    "enrollment_token": "mock-mobile-enrollment-token",
                    "adapter_enc_key": {
                        "kty": "EC",
                        "use": "enc",
                        "alg": "ES256",
                        "kid": "afd196fd-64f4-4a9d-989f-c61f8c5d5f31",
                        "crv": "P-256",
                        "x": "OV6uOpawdTTSC6QsLQSv9tCGVDyt3u0ZpdVCD95vogY",
                        "y": "bnJ86Qyj0HDCrzNo2GOyvOTJA9lCiUEmLUXLcwLZeOQ"
                    },
                    "enroll_url": "enroll.zabbixmobile.com"
                }
            }

        return {
            "jsonrpc": "2.0",
            "id": request_id,
            "result": {}
        }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", type=int, required=True)
    parser.add_argument("--log-file", required=True)
    parser.add_argument("--pid-file", required=True)
    parser.add_argument("--status-code", type=int, default=200)
    parser.add_argument("--notify-error", action="store_true")
    args = parser.parse_args()

    server = AdapterServer((args.host, args.port), args.log_file, args.status_code, args.notify_error)

    open(args.log_file, "a", encoding="utf-8").close()

    with open(args.pid_file, "w", encoding="utf-8") as pid:
        pid.write(str(os.getpid()))

    def shutdown(signum, frame):
        threading.Thread(target=server.shutdown, daemon=True).start()

    signal.signal(signal.SIGTERM, shutdown)
    signal.signal(signal.SIGINT, shutdown)

    try:
        server.serve_forever()
    finally:
        server.server_close()
        if os.path.exists(args.pid_file):
            os.unlink(args.pid_file)


if __name__ == "__main__":
    main()

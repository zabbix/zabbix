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
import ssl
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

        if self.server.malformed_json:
            encoded = b"{not valid json"
        else:
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
    def __init__(self, address, log_file, status_code, notify_error, init_error, init_error_detail,
            offboard_error_detail, malformed_json, missing_jsonrpc, invalid_jsonrpc_version, oversized_response,
            incomplete_error, init_no_result, init_incomplete_result, ssl_context=None):
        super().__init__(address, BridgeAdapterMock)
        self.log_file = log_file
        self.status_code = status_code
        self.notify_error = notify_error
        self.init_error = init_error
        self.init_error_detail = init_error_detail
        self.offboard_error_detail = offboard_error_detail
        self.malformed_json = malformed_json
        self.missing_jsonrpc = missing_jsonrpc
        self.invalid_jsonrpc_version = invalid_jsonrpc_version
        self.oversized_response = oversized_response
        self.incomplete_error = incomplete_error
        self.init_no_result = init_no_result
        self.init_incomplete_result = init_incomplete_result
        self.ssl_context = ssl_context

    def get_request(self):
        sock, addr = self.socket.accept()

        if self.ssl_context is None:
            return sock, addr

        return self.ssl_context.wrap_socket(sock, server_side=True), addr

    def write_request(self, body, raw_body):
        record = {
            "method": body.get("method") if isinstance(body, dict) else None,
            "body": body,
            "raw_body": raw_body
        }

        with open(self.log_file, "a", encoding="utf-8") as log:
            log.write(json.dumps(record, separators=(",", ":")) + "\n")

    def build_response(self, body):
        response = self._build_response_body(body)

        if self.missing_jsonrpc:
            response.pop("jsonrpc", None)
        elif self.invalid_jsonrpc_version:
            response["jsonrpc"] = "1.0"

        if self.oversized_response:
            result = response.setdefault("result", {})

            if isinstance(result, dict):
                result["padding"] = "x" * 4096

        return response

    def _build_response_body(self, body):
        request_id = body.get("id") if isinstance(body, dict) else None
        method = body.get("method") if isinstance(body, dict) else None

        if self.incomplete_error:
            return {
                "jsonrpc": "2.0",
                "id": request_id,
                "error": {
                    "code": "bridge.adapter.error"
                }
            }

        if method == "device.init" and self.init_no_result:
            return {
                "jsonrpc": "2.0",
                "id": request_id
            }

        if method == "device.init" and self.init_incomplete_result:
            return {
                "jsonrpc": "2.0",
                "id": request_id,
                "result": {
                    "enrollment_token": "mock-mobile-enrollment-token"
                }
            }

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

        if method == "device.init" and self.init_error:
            return {
                "jsonrpc": "2.0",
                "id": request_id,
                "error": {
                    "code": "bridge.adapter.error",
                    "message": "Mock bridge-adapter rejected init",
                    "data": "device.init mock failure"
                }
            }

        if method == "device.init" and self.init_error_detail:
            return {
                "jsonrpc": "2.0",
                "id": request_id,
                "error": {
                    "code": "bridge.adapter.error",
                    "message": "Mock bridge-adapter rejected init",
                    "data": {
                        "details": [
                            {
                                "@type": "bridge_jsonrpc.ErrorInfo",
                                "reason": "DEVICE_LIMIT_EXCEEDED",
                                "domain": "bridge.device"
                            }
                        ]
                    }
                }
            }

        if method == "device.deactivate" and self.offboard_error_detail:
            return {
                "jsonrpc": "2.0",
                "id": request_id,
                "error": {
                    "code": "bridge.adapter.error",
                    "message": "Mock bridge-adapter rejected offboard",
                    "data": {
                        "details": [
                            {
                                "@type": "bridge_jsonrpc.ErrorInfo",
                                "reason": "DEVICE_NOT_FOUND",
                                "domain": "bridge.device"
                            }
                        ]
                    }
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
                    "bridge_url": "enroll.zabbixmobile.com"
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
    parser.add_argument("--init-error", action="store_true")
    parser.add_argument("--init-error-detail", action="store_true")
    parser.add_argument("--offboard-error-detail", action="store_true")
    parser.add_argument("--malformed-json", action="store_true")
    parser.add_argument("--missing-jsonrpc", action="store_true")
    parser.add_argument("--invalid-jsonrpc-version", action="store_true")
    parser.add_argument("--oversized-response", action="store_true")
    parser.add_argument("--incomplete-error", action="store_true")
    parser.add_argument("--init-no-result", action="store_true")
    parser.add_argument("--init-incomplete-result", action="store_true")
    parser.add_argument("--tls", action="store_true")
    parser.add_argument("--mtls", action="store_true")
    parser.add_argument("--cert")
    parser.add_argument("--key")
    parser.add_argument("--ca")
    args = parser.parse_args()

    if args.mtls and not args.tls:
        parser.error("--mtls requires --tls")

    if args.tls and (not args.cert or not args.key):
        parser.error("--tls requires --cert and --key")

    ssl_context = None

    if args.tls:
        ssl_context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
        ssl_context.load_cert_chain(certfile=args.cert, keyfile=args.key)

        if args.mtls:
            if not args.ca:
                parser.error("--mtls requires --ca")

            ssl_context.verify_mode = ssl.CERT_REQUIRED
            ssl_context.load_verify_locations(cafile=args.ca)

    server = AdapterServer((args.host, args.port), args.log_file, args.status_code, args.notify_error,
                           args.init_error, args.init_error_detail, args.offboard_error_detail,
                           args.malformed_json, args.missing_jsonrpc, args.invalid_jsonrpc_version,
                           args.oversized_response, args.incomplete_error, args.init_no_result,
                           args.init_incomplete_result, ssl_context)

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

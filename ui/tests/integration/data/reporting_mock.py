#!/usr/bin/env python3

import argparse
import http.server
import os
import signal
import socketserver
import threading


PDF = b"%PDF-1.4\n%%EOF\n"


class ThreadingTCPServer(socketserver.ThreadingMixIn, socketserver.TCPServer):
    allow_reuse_address = True
    daemon_threads = True


class ThreadingHTTPServer(http.server.ThreadingHTTPServer):
    allow_reuse_address = True
    daemon_threads = True


class ReportHandler(http.server.BaseHTTPRequestHandler):
    def do_POST(self):
        content_length = int(self.headers.get("Content-Length", "0"))
        self.rfile.read(content_length)

        self.server.log("REPORT")
        self.send_response(200)
        self.send_header("Content-Type", "application/pdf")
        self.send_header("Content-Length", str(len(PDF)))
        self.end_headers()
        self.wfile.write(PDF)

    def log_message(self, _format, *_args):
        pass


class SMTPHandler(socketserver.StreamRequestHandler):
    def handle(self):
        self.wfile.write(b"220 reporting-mock ESMTP\r\n")

        data_mode = False

        while True:
            line = self.rfile.readline()
            if not line:
                return

            command = line.decode("utf-8", errors="replace").rstrip("\r\n")

            if data_mode:
                if command == ".":
                    data_mode = False
                    self.wfile.write(b"250 2.0.0 message accepted\r\n")
                continue

            verb = command.split(" ", 1)[0].upper()

            if verb in ("EHLO", "HELO"):
                self.wfile.write(b"250-reporting-mock\r\n250 SIZE 10485760\r\n")
            elif verb == "MAIL":
                self.wfile.write(b"250 2.1.0 sender accepted\r\n")
            elif verb == "RCPT":
                self.server.log(command)
                self.wfile.write(b"250 2.1.5 recipient accepted\r\n")
            elif verb == "DATA":
                data_mode = True
                self.wfile.write(b"354 End data with <CR><LF>.<CR><LF>\r\n")
            elif verb == "RSET":
                self.wfile.write(b"250 2.0.0 reset\r\n")
            elif verb == "NOOP":
                self.wfile.write(b"250 2.0.0 ok\r\n")
            elif verb == "QUIT":
                self.wfile.write(b"221 2.0.0 bye\r\n")
                return
            else:
                self.wfile.write(b"502 5.5.1 command not implemented\r\n")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--host", required=True)
    parser.add_argument("--http-port", required=True, type=int)
    parser.add_argument("--smtp-port", required=True, type=int)
    parser.add_argument("--log-file", required=True)
    parser.add_argument("--pid-file", required=True)
    args = parser.parse_args()

    log_lock = threading.Lock()

    def log(message):
        with log_lock:
            with open(args.log_file, "a", encoding="utf-8") as log_file:
                log_file.write(message + "\n")

    http_server = ThreadingHTTPServer((args.host, args.http_port), ReportHandler)
    smtp_server = ThreadingTCPServer((args.host, args.smtp_port), SMTPHandler)
    http_server.log = log
    smtp_server.log = log

    stop_event = threading.Event()

    def stop(_signum, _frame):
        stop_event.set()

    signal.signal(signal.SIGTERM, stop)
    signal.signal(signal.SIGINT, stop)

    with open(args.pid_file, "w", encoding="utf-8") as pid_file:
        pid_file.write(str(os.getpid()))

    http_thread = threading.Thread(target=http_server.serve_forever, daemon=True)
    smtp_thread = threading.Thread(target=smtp_server.serve_forever, daemon=True)
    http_thread.start()
    smtp_thread.start()
    log("READY")

    try:
        stop_event.wait()
    finally:
        http_server.shutdown()
        smtp_server.shutdown()
        http_server.server_close()
        smtp_server.server_close()

        if os.path.exists(args.pid_file):
            os.unlink(args.pid_file)


if __name__ == "__main__":
    main()

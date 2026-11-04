#!/usr/bin/env python3
#
# Copyright (C) 2001-2026 Zabbix SIA
#
# This program is free software: you can redistribute it and/or modify it under the terms of
# the GNU Affero General Public License as published by the Free Software Foundation, version 3.
#

import argparse
import os
import signal
import socket
import threading

IAC = 0xFF
DO = 0xFD
SGA = 0x03

# Command text (as sent verbatim in the item "params" field) -> (echo the command back, output bytes
# to send before the next prompt, whether the prompt is followed by a trailing space), or None to send
# no reply at all and wait for the next line.
#
# "short output cmd" is deliberately longer than any data the mock ever sends back for it (the mock
# sends only the bare prompt byte, no echo, no output) - this is the "short Telnet command output"
# condition fixed by DEV-5055: the real reply is shorter than the text zbx_telnet_execute() tries to
# strip from the front of the buffer (either the echoed command itself, or the "\n"/"$ " fragments).
#
# The "multiline command N" trio is the multi-line variant of the same DEV-5055 fix: the first two
# lines get no reply at all, so the only bytes the client ever reads are the bare "$ " sent after the
# third line, which is what makes zbx_telnet_execute() call telnet_rm_echo() a second time on an
# already-zero offset.
COMMANDS = {
    b"short output cmd": (False, b"", False),
    b"echo test output": (True, b"hello world\r\n", True),
    b"eol test": (True,
        b"CRLF-line\r\n"
        b"LFCR-line\n\r"
        b"CR-line\r"
        b"CRNUL-line\r\x00"
        b"\r\n",
        True),
    b"multiline command 1": None,
    b"multiline command 2": None,
    b"multiline command 3": (False, b"", True)
}
DEFAULT_COMMAND = (True, b"", True)


class ClientHandler(threading.Thread):
    def __init__(self, conn, args):
        super().__init__(daemon=True)
        self.conn = conn
        self.args = args
        self._buf = b""

    def run(self):
        try:
            self.handle()
        except OSError:
            pass
        finally:
            try:
                self.conn.close()
            except OSError:
                pass

    def recv_until(self, terminator=b"\r\n"):
        # A multi-line command's lines usually all arrive in the same recv(), so any bytes past the
        # first terminator have to be kept for the next call instead of being dropped with it.
        while terminator not in self._buf:
            chunk = self.conn.recv(4096)

            if not chunk:
                return None

            self._buf += chunk

        line, self._buf = self._buf.split(terminator, 1)

        return line

    def handle(self):
        # Ask the client to suppress go-ahead; the reply (IAC WILL/WON'T SGA) is consumed transparently
        # by telnet_read() on the client side and is never visible in the lines read below. This just
        # exercises the option-negotiation branch of telnet_read().
        self.conn.sendall(bytes([IAC, DO, SGA]))

        if self.args.no_login_prompt:
            return

        self.conn.sendall(b"login: ")

        if self.recv_until() is None:
            return

        if self.args.no_password_prompt:
            return

        self.conn.sendall(b"Password: ")

        if self.recv_until() is None:
            return

        if self.args.no_shell_prompt:
            self.conn.sendall(b"Permission denied\r\n")
            return

        prompt_char = self.args.prompt_char.encode()

        self.conn.sendall(b"Last login: today\r\n" + prompt_char + b" ")

        while True:
            command = self.recv_until()

            if command is None:
                return

            entry = COMMANDS.get(command, DEFAULT_COMMAND)

            if entry is None:
                continue

            echo, output, trailing_space = entry

            reply = b""

            if echo:
                reply += command + b"\r\n"

            reply += output
            reply += prompt_char

            if trailing_space:
                reply += b" "

            self.conn.sendall(reply)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", type=int, required=True)
    parser.add_argument("--log-file", required=True)
    parser.add_argument("--pid-file", required=True)
    parser.add_argument("--prompt-char", default="$")
    parser.add_argument("--no-login-prompt", action="store_true")
    parser.add_argument("--no-password-prompt", action="store_true")
    parser.add_argument("--no-shell-prompt", action="store_true")
    args = parser.parse_args()

    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    server.bind((args.host, args.port))
    server.listen(5)
    server.settimeout(0.5)

    open(args.log_file, "a", encoding="utf-8").close()

    with open(args.pid_file, "w", encoding="utf-8") as pid:
        pid.write(str(os.getpid()))

    stop_event = threading.Event()

    def shutdown(signum, frame):
        stop_event.set()

    signal.signal(signal.SIGTERM, shutdown)
    signal.signal(signal.SIGINT, shutdown)

    try:
        while not stop_event.is_set():
            try:
                conn, _addr = server.accept()
            except socket.timeout:
                continue

            ClientHandler(conn, args).start()
    finally:
        server.close()

        if os.path.exists(args.pid_file):
            os.unlink(args.pid_file)


if __name__ == "__main__":
    main()

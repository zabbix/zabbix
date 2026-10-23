# Ping popup browser regression checks

Run from the repository root with Node.js, Playwright and an installed Google Chrome:

```sh
node tests/ping/browser.cjs
```

The test renders the production JavaScript against both Zabbix stylesheets. It checks successful responses, partial loss, no responses, DNS errors, execution failures and unrecognized output; host/IP display; text escaping; responsive width; Ping-only progress; reduced motion; and removal of animations when the popup is removed.

The test uses synthetic command output and a stub for `PopUp`. It does not execute a command, authenticate a user, or validate a complete current-master Zabbix deployment.

Presentation currently recognizes non-webhook scripts named `Ping` or `ICMP Ping` (including menu paths) and English Linux/macOS ping summaries. Other output is reported as unrecognized. The animation indicates a pending request, not live packet telemetry. Existing script execution permissions and CSRF validation remain authoritative.

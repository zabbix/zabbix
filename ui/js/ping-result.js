/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/* Friendly presentation for the Ping script. Never insert command output as HTML. */
(() => {
 const root = document.getElementById('ping-result');
 if (!root) return;
 const style = document.createElement('style');
 style.textContent = `
  .overlay-dialogue.modal-popup:has(#ping-result) { box-sizing: border-box; min-width: 0; width: min(480px, calc(100vw - 24px)); }
  #ping-result { white-space: normal; overflow-wrap: anywhere; }
  #ping-result .form-field { min-width: 0; }
  @media (max-width: 400px) { #ping-result .form-grid { grid-template-columns: 1fr; row-gap: 6px; } #ping-result .form-field { margin-bottom: 8px; } }
 `;
 root.append(style);
 const raw = root.dataset.output || '';
 const summary = raw.match(/(\d+)\s+packets transmitted,\s*(\d+)\s+(?:packets )?received[^\n]*?([\d.,]+)%\s*packet loss/i);
 const timing = raw.match(/(?:rtt|round-trip)[^=\n]*=\s*([\d.,]+)\/([\d.,]+)\/([\d.,]+)(?:\/[\d.,]+)?\s*ms/i);
 const target = raw.match(/^PING\s+([^\s(]+)(?:\s+\(([^)]+)\))?/m);
 let state = 'unknown', title = t("Unrecognized ping result"), description = t("The check finished without a recognized result. Retry ping or contact your administrator.");
 let sent, received, loss;
 if (root.dataset.success !== '1') {
  state = 'error'; title = t("Cannot execute ping");
  description = t("The server could not execute ping. This does not mean the host is unavailable. Retry or contact your administrator.");
 }
 else if (summary) {
  sent = Number(summary[1]); received = Number(summary[2]); loss = Number(summary[3].replace(',', '.'));
  if (sent > 0 && received <= sent && loss >= 0 && loss <= 100) {
   state = received === 0 ? 'down' : received < sent ? 'warning' : 'up';
   title = {up: t("Host responds"), warning: t("Some packets were lost"), down: t("No ping response")}[state];
   description = {up: t("All transmitted packets received a response."), warning: t("Some transmitted packets did not receive a response."), down: t("The host did not respond. Check connectivity and whether ICMP is blocked.")}[state];
  }
 }
 else if (/unknown host|name or service not known|temporary failure in name resolution|cannot resolve/i.test(raw)) {
  state = 'error'; title = t("Cannot resolve host address"); description = t("Check the host name and DNS settings.");
 }
 root.dataset.state = state;
 const message = document.createElement('output');
 message.className = state === 'up' ? 'msg-good' : ['warning', 'unknown'].includes(state) ? 'msg-warning' : 'msg-bad';
 const heading = document.createElement('span');
 heading.textContent = title;
 const details = document.createElement('div');
 details.className = 'msg-details';
 details.textContent = description;
 message.append(heading, details);
 root.append(message);

 const fields = document.createElement('div');
 fields.className = 'form-grid';
 const addRow = (label, value) => {
  const caption = document.createElement('label');
  caption.textContent = label;
  const field = document.createElement('div');
  field.className = 'form-field';
  const text = document.createElement('span');
  text.className = 'wordbreak';
  text.textContent = value;
  field.append(text);
  fields.append(caption, field);
 };
 addRow(t("Host"), root.dataset.host || t("Not specified"));
 const address = target ? (target[2] || target[1]) : '';
 // Only label literal addresses as IP; a DNS name alone is not a resolved IP.
 const ip = /^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/.test(address) || address.includes(':') ? address : '';
 addRow(ip ? t("IP address") : t("Configured IP address"), ip || root.dataset.ip || t("Not specified"));
 if (['up', 'warning', 'down'].includes(state)) {
  addRow(t("Responses received"), `${received} / ${sent}`);
  addRow(t("Packet loss"), `${loss} %`);
  if (received > 0 && timing) addRow(t("Average response time"), `${timing[2]} ms`);
 }
 root.append(fields);
 root.removeAttribute('data-output');
})();

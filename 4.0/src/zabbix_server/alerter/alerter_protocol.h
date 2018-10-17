/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_ALERTER_PROTOCOL_H
#define ZABBIX_ALERTER_PROTOCOL_H

#include "common.h"

#define ZBX_IPC_SERVICE_ALERTER	"alerter"

/* alerter -> manager */
#define ZBX_IPC_ALERTER_REGISTER	1000
#define ZBX_IPC_ALERTER_RESULT		1001


/* manager -> alerter */
#define ZBX_IPC_ALERTER_EMAIL		1100
#define ZBX_IPC_ALERTER_JABBER		1101
#define ZBX_IPC_ALERTER_SMS		1102
#define ZBX_IPC_ALERTER_EZTEXTING	1103
#define ZBX_IPC_ALERTER_EXEC		1104


zbx_uint32_t	zbx_alerter_serialize_result(unsigned char **data, int errcode, const char *errmsg);
void	zbx_alerter_deserialize_result(const unsigned char *data, int *errcode, char **errmsg);

zbx_uint32_t	zbx_alerter_serialize_email(unsigned char **data, zbx_uint64_t alertid, const char *sendto,
		const char *subject, const char *message, const char *smtp_server, unsigned short smtp_port,
		const char *smtp_helo, const char *smtp_email, unsigned char smtp_security,
		unsigned char smtp_verify_peer, unsigned char smtp_verify_host, unsigned char smtp_authentication,
		const char *username, const char *password);

void	zbx_alerter_deserialize_email(const unsigned char *data, zbx_uint64_t *alertid, char **sendto, char **subject,
		char **message, char **smtp_server, unsigned short *smtp_port, char **smtp_helo, char **smtp_email,
		unsigned char *smtp_security, unsigned char *smtp_verify_peer, unsigned char *smtp_verify_host,
		unsigned char *smtp_authentication, char **username, char **password);

zbx_uint32_t	zbx_alerter_serialize_jabber(unsigned char **data, zbx_uint64_t alertid,  const char *sendto,
		const char *subject, const char *message, const char *username, const char *password);

void	zbx_alerter_deserialize_jabber(const unsigned char *data, zbx_uint64_t *alertid, char **sendto, char **subject,
		char **message, char **username, char **password);

zbx_uint32_t	zbx_alerter_serialize_sms(unsigned char **data, zbx_uint64_t alertid,  const char *sendto,
		const char *message, const char *gsm_modem);

void	zbx_alerter_deserialize_sms(const unsigned char *data, zbx_uint64_t *alertid, char **sendto, char **message,
		char **gsm_modem);

zbx_uint32_t	zbx_alerter_serialize_eztexting(unsigned char **data, zbx_uint64_t alertid,  const char *sendto,
		const char *message, const char *username, const char *password, const char *exec_path);

void	zbx_alerter_deserialize_eztexting(const unsigned char *data, zbx_uint64_t *alertid, char **sendto,
		char **message, char **username, char **password, char **exec_path);

zbx_uint32_t	zbx_alerter_serialize_exec(unsigned char **data, zbx_uint64_t alertid, const char *command);

void	zbx_alerter_deserialize_exec(const unsigned char *data, zbx_uint64_t *alertid, char **command);

#endif

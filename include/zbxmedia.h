/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#ifndef ZABBIX_ZBXMEDIA_H
#define ZABBIX_ZBXMEDIA_H

#include "zbxsysinc.h"	/* using "config.h" would be better, but it causes warnings when compiled with Net-SNMP */

#define ZBX_MEDIA_MESSAGE_FORMAT_TEXT	0
#define ZBX_MEDIA_MESSAGE_FORMAT_HTML	1
#define ZBX_MEDIA_MESSAGE_FORMAT_MULTI	2	/* multipart/mixed message with pre-formatted message body */

/* SMTP authentication options */
#define SMTP_AUTHENTICATION_NONE		0
#define SMTP_AUTHENTICATION_NORMAL_PASSWORD	1

typedef struct
{
	char		*addr;
	char		*disp_name;
}
zbx_mailaddr_t;

int	send_email(const char *smtp_server, unsigned short smtp_port, const char *smtp_helo, const char *smtp_email,
		const char *mailto, const char *inreplyto, const char *mailsubject, const char *mailbody,
		unsigned char smtp_security, unsigned char smtp_verify_peer, unsigned char smtp_verify_host,
		unsigned char smtp_authentication, const char *username, const char *password,
		unsigned char message_format, int timeout, const char *config_source_ip,
		const char *config_ssl_ca_location, char **error);
int	send_sms(const char *device, const char *number, const char *message, char *error, int max_error_len);

char	*zbx_email_make_body(const char *message, unsigned char message_format,  const char *attachment_name,
		const char *attachment_type, const char *attachment, size_t attachment_size);

#endif

/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#ifndef ZABBIX_ZBXMEDIA_H
#define ZABBIX_ZBXMEDIA_H

#include "config.h"

extern char	*CONFIG_SOURCE_IP;

int	send_email(const char *smtp_server, const char *smtp_helo, const char *smtp_email, const char *mailto,
		char *mailsubject, char *mailbody, char *error, int max_error_len);
int	send_ez_texting(const char *username, const char *password, const char *sendto,
		const char *subject, const char *message, char *error, int max_error_len);
#ifdef HAVE_JABBER
int	send_jabber(const char *username, const char *passwd, const char *sendto,
		const char *subject, const char *message, char *error, int max_error_len);
#endif
int	send_sms(const char *device, const char *number, const char *message, char *error, int max_error_len);

#endif

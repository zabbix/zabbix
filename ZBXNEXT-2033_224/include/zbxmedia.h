/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#ifndef ZABBIX_ZBXMEDIA_H
#define ZABBIX_ZBXMEDIA_H

#include "sysinc.h" /* using "config.h" would be better, but it causes warnings when compiled with Net-SNMP */
#include "zbxalgo.h"

extern char	*CONFIG_SOURCE_IP;

struct DB_ALERT;
struct DB_MEDIATYPE;

int	send_email(const char *smtp_server, const char *smtp_helo, const char *smtp_email, const char *mailto,
		const char *mailsubject, const char *mailbody, char *error, size_t max_error_len);
int	send_ez_texting(const char *username, const char *password, const char *sendto,
		const char *message, const char *limit, char *error, int max_error_len);
#ifdef HAVE_JABBER
int	send_jabber(const char *username, const char *password, const char *sendto,
		const char *subject, const char *message, char *error, int max_error_len);
#endif
int	send_sms(const char *device, const char *number, const char *message, char *error, int max_error_len);

typedef struct
{
	/* the source event id */
	zbx_uint64_t	eventid;
	/* the associated ticketid (Remedy incident number) */
	char		*ticketid;
	/* the ticket status */
	char		*status;
	/* contains error message or NULL otherwise */
	char		*error;
	/* the assignee */
	char		*assignee;
	/* 1 if a new ticket was created, 0 otherwise, set only for zbx_remedy_acknowledge_events() request */
	int		is_new;
	/* the ticket creation time, set only for zbx_remedy_query_events() request */
	int		clock;
}
zbx_ticket_t;

typedef struct
{
	/* the event id */
	zbx_uint64_t	eventid;
	/* the acknowledgment message subject */
	char		*subject;
	/* the acknowledgment message contents */
	char		*message;
}
zbx_acknowledge_t;

void zbx_free_ticket(zbx_ticket_t *ticket);
void zbx_free_acknowledge(zbx_acknowledge_t *ticket);

int	zbx_remedy_process_alert(const struct DB_ALERT *alert, const struct DB_MEDIATYPE *mediatype, char **error);
int	zbx_remedy_query_events(zbx_vector_uint64_t *eventids, zbx_vector_ptr_t *tickets, char **error);
int	zbx_remedy_acknowledge_events(zbx_uint64_t userid, zbx_vector_ptr_t *acknowledges, zbx_vector_ptr_t *tickets,
		char **error);

#endif

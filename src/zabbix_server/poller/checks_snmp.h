/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_CHECKS_SNMP_H
#define ZABBIX_CHECKS_SNMP_H

#include "config.h"
#include "zbxcacheconfig.h"
#include "zbxasyncpoller.h"

#ifdef HAVE_NETSNMP

#define ZBX_SNMP_STR_HEX	1
#define ZBX_SNMP_STR_STRING	2
#define ZBX_SNMP_STR_OID	3
#define ZBX_SNMP_STR_BITS	4
#define ZBX_SNMP_STR_ASCII	5
#define ZBX_SNMP_STR_UNDEFINED	255


#define SNMP_NO_DEBUGGING		/* disabling debugging messages from Net-SNMP library */
#include <net-snmp/net-snmp-config.h>
#include <net-snmp/net-snmp-includes.h>

typedef void*	zbx_snmp_sess_t;

typedef struct
{
	oid	root_oid[MAX_OID_LEN];
	size_t	root_oid_len;
	char	*str_oid;
}
zbx_snmp_oid_t;

ZBX_PTR_VECTOR_DECL(snmp_oid, zbx_snmp_oid_t *)

typedef struct
{
	int			reqid;
	int			sock;
	int			pdu_type;
	zbx_snmp_oid_t		p_oid;
	oid			name[MAX_OID_LEN];
	size_t			name_length;
	int			running;
	int			vars_num;
	void			*arg;
	char			*error;
	netsnmp_large_fd_set	fdset;
}
zbx_bulkwalk_context_t;

ZBX_PTR_VECTOR_DECL(bulkwalk_context, zbx_bulkwalk_context_t*)

typedef struct
{
	void				*arg;
	void				*arg_action;
	zbx_dc_tem_context_t		item;
	zbx_snmp_sess_t			ssp;
	int				snmp_max_repetitions;
	char				*results;
	size_t				results_alloc;
	size_t				results_offset;
	zbx_vector_bulkwalk_context_t	bulkwalk_contexts;
	int				i;
}
zbx_snmp_context_t;

void	zbx_init_library_mt_snmp(void);
void	zbx_shutdown_library_mt_snmp(void);
int	get_value_snmp(const zbx_dc_item_t *item, AGENT_RESULT *result, unsigned char poller_type, int config_timeout,
		const char *config_source_ip);
void	get_values_snmp(const zbx_dc_item_t *items, AGENT_RESULT *results, int *errcodes, int num,
		unsigned char poller_type, int config_timeout, const char *config_source_ip);
void	zbx_clear_cache_snmp(unsigned char process_type, int process_num);
zbx_snmp_sess_t	zbx_snmp_open_session(const zbx_dc_item_t *item, char *error, size_t max_error_len,
		int config_timeout, const char *config_source_ip);
int	zbx_async_check_snmp(zbx_snmp_sess_t ssp, zbx_dc_item_t *item, AGENT_RESULT *result,
		zbx_async_task_clear_cb_t clear_cb, void *arg, void *arg_action, struct event_base *base,
		int config_timeout, const char *config_source_ip);
void	zbx_async_check_snmp_clean(zbx_snmp_context_t *snmp_context);
#endif

#endif

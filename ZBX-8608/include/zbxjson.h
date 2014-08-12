/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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

#ifndef ZABBIX_ZJSON_H
#define ZABBIX_ZJSON_H

#include <stdarg.h>

#define ZBX_PROTO_TAG_CLOCK		"clock"
#define ZBX_PROTO_TAG_DATA		"data"
#define ZBX_PROTO_TAG_REGEXP		"regexp"
#define	ZBX_PROTO_TAG_DELAY		"delay"
#define	ZBX_PROTO_TAG_DRULE		"drule"
#define	ZBX_PROTO_TAG_DCHECK		"dcheck"
#define ZBX_PROTO_TAG_HOST		"host"
#define ZBX_PROTO_TAG_INFO		"info"
#define ZBX_PROTO_TAG_IP		"ip"
#define ZBX_PROTO_TAG_KEY		"key"
#define ZBX_PROTO_TAG_KEY_ORIG		"key_orig"
#define ZBX_PROTO_TAG_LOGLASTSIZE	"lastlogsize"
#define ZBX_PROTO_TAG_MTIME		"mtime"
#define ZBX_PROTO_TAG_LOGTIMESTAMP	"timestamp"
#define ZBX_PROTO_TAG_LOGSOURCE		"source"
#define ZBX_PROTO_TAG_LOGSEVERITY	"severity"
#define ZBX_PROTO_TAG_LOGEVENTID	"eventid"
#define ZBX_PROTO_TAG_PORT		"port"
#define ZBX_PROTO_TAG_PROXY		"proxy"
#define ZBX_PROTO_TAG_REQUEST		"request"
#define ZBX_PROTO_TAG_RESPONSE		"response"
#define ZBX_PROTO_TAG_STATUS		"status"
#define ZBX_PROTO_TAG_TYPE		"type"
#define ZBX_PROTO_TAG_VALUE		"value"
#define ZBX_PROTO_TAG_SCRIPTID		"scriptid"
#define ZBX_PROTO_TAG_HOSTID		"hostid"
#define ZBX_PROTO_TAG_NODEID		"nodeid"
#define ZBX_PROTO_TAG_AVAILABLE		"available"
#define ZBX_PROTO_TAG_SNMP_AVAILABLE	"snmp_available"
#define ZBX_PROTO_TAG_IPMI_AVAILABLE	"ipmi_available"
#define ZBX_PROTO_TAG_ERROR		"error"
#define ZBX_PROTO_TAG_SNMP_ERROR	"snmp_error"
#define ZBX_PROTO_TAG_IPMI_ERROR	"ipmi_error"

#define ZBX_PROTO_VALUE_FAILED		"failed"
#define ZBX_PROTO_VALUE_SUCCESS		"success"

#define	ZBX_PROTO_VALUE_GET_ACTIVE_CHECKS	"active checks"
#define	ZBX_PROTO_VALUE_PROXY_CONFIG		"proxy config"
#define	ZBX_PROTO_VALUE_PROXY_HEARTBEAT		"proxy heartbeat"
#define ZBX_PROTO_VALUE_DISCOVERY_DATA		"discovery data"
#define ZBX_PROTO_VALUE_HOST_AVAILABILITY	"host availability"
#define ZBX_PROTO_VALUE_HISTORY_DATA		"history data"
#define ZBX_PROTO_VALUE_AUTO_REGISTRATION_DATA	"auto registration"
#define	ZBX_PROTO_VALUE_SENDER_DATA		"sender data"
#define	ZBX_PROTO_VALUE_AGENT_DATA		"agent data"
#define ZBX_PROTO_VALUE_COMMAND			"command"

typedef enum
{
	ZBX_JSON_TYPE_UNKNOWN = 0,
	ZBX_JSON_TYPE_STRING,
	ZBX_JSON_TYPE_INT,
	ZBX_JSON_TYPE_ARRAY,
	ZBX_JSON_TYPE_OBJECT,
	ZBX_JSON_TYPE_NULL
}
zbx_json_type_t;

typedef enum
{
	ZBX_JSON_EMPTY = 0,
	ZBX_JSON_COMMA
}
zbx_json_status_t;

#define ZBX_JSON_STAT_BUF_LEN 4096

struct zbx_json
{
	char			*buffer;
	char			buf_stat[ZBX_JSON_STAT_BUF_LEN];
	size_t			buffer_allocated;
	size_t			buffer_offset;
	size_t			buffer_size;
	zbx_json_status_t	status;
	int			level;
};

struct zbx_json_parse
{
	const char		*start;
	const char		*end;
};

const char	*zbx_json_strerror();

void	zbx_json_init(struct zbx_json *j, size_t allocate);
void	zbx_json_clean(struct zbx_json *j);
void	zbx_json_free(struct zbx_json *j);
void	zbx_json_addobject(struct zbx_json *j, const char *name);
void	zbx_json_addarray(struct zbx_json *j, const char *name);
void	zbx_json_addstring(struct zbx_json *j, const char *name, const char *string, zbx_json_type_t type);
void	zbx_json_adduint64(struct zbx_json *j, const char *name, zbx_uint64_t value);
int	zbx_json_close(struct zbx_json *j);

int		zbx_json_open(char *buffer, struct zbx_json_parse *jp);
const char	*zbx_json_decodevalue(const char *p, char *string, size_t len);
const char	*zbx_json_next(struct zbx_json_parse *jp, const char *p);
const char	*zbx_json_next_value(struct zbx_json_parse *jp, const char *p, char *string, size_t len);
const char	*zbx_json_pair_next(struct zbx_json_parse *jp, const char *p, char *name, size_t len);
const char	*zbx_json_pair_by_name(struct zbx_json_parse *jp, const char *name);
int		zbx_json_value_by_name(struct zbx_json_parse *jp, const char *name, char *string, size_t len);
int		zbx_json_brackets_open(const char *p, struct zbx_json_parse *jp);
int		zbx_json_brackets_by_name(struct zbx_json_parse *jp, const char *name, struct zbx_json_parse *out);
int		zbx_json_object_is_empty(struct zbx_json_parse *jp);
int		zbx_json_count(struct zbx_json_parse *jp);

#endif /* ZABBIX_ZJSON_H */

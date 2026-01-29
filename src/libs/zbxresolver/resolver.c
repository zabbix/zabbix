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
#include "config.h"
#include "zbxcommon.h"

#ifdef HAVE_ARES_QUERY_CACHE
#define ZBX_SOCKET_STRERROR_LEN	512
#include "zbxresolver.h"
#include "zbxtypes.h"
#include "zbxstr.h"

static ZBX_THREAD_LOCAL zbx_channel_t	*zbx_channel;
static int				zbx_ares_init_done;

void	zbx_ares_library_init(void)
{
	int	status;

	if (1 == zbx_ares_init_done)
		return;

	zbx_ares_init_done = 1;

	if (ARES_SUCCESS != (status = ares_library_init(ARES_LIB_INIT_ALL)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialise c-ares library: %s", ares_strerror(status));
		exit(EXIT_FAILURE);
		return;
	}
}

static void	ares_event_thread_init(void)
{
	zbx_ares_library_init();

	if (NULL != zbx_channel)
		return;

	struct ares_options	options = {0};
	int			optmask = ARES_OPT_EVENT_THREAD|ARES_OPT_QUERY_CACHE, status;

	options.evsys = ARES_EVSYS_DEFAULT;
	options.qcache_max_ttl = SEC_PER_HOUR;

	if (0 == (status = ares_threadsafety()))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialise c-ares library: %s", ares_strerror(status));
		return;
	}

	if (ARES_SUCCESS != (status = ares_init_options(&zbx_channel, &options, optmask)))
		zabbix_log(LOG_LEVEL_ERR, "cannot set c-ares library options: %s", ares_strerror(status));
}

typedef struct
{
	struct ares_addrinfo	**ares_ai;
	char			zbx_socket_strerror_message[ZBX_SOCKET_STRERROR_LEN];
}
zbx_ai_context_t;

static void	ares_addrinfo_cb(void *arg, int err, int timeouts, struct ares_addrinfo *ai)
{
	zbx_ai_context_t *ai_context = (zbx_ai_context_t *)arg;

	ZBX_UNUSED(timeouts);

	if (ARES_SUCCESS != err)
	{
		*ai_context->ares_ai = NULL;

		zbx_snprintf(ai_context->zbx_socket_strerror_message, sizeof(ai_context->zbx_socket_strerror_message),
				"cannot resolve DNS name: %s", ares_strerror(err));
		return;
	}

	*ai_context->ares_ai = ai;
}

int	zbx_ares_getaddrinfo(const char *ip, const char *service, int ai_flags, int ai_family, int ai_socktype,
		struct ares_addrinfo **ares_ai, char *error, int max_error_len)
{
	int				err;
	struct ares_addrinfo_hints	ares_hints = {0};
	zbx_ai_context_t		ai_context = {.ares_ai = ares_ai};

	ares_event_thread_init();

	if (NULL == zbx_channel)
	{
		zbx_strlcpy(error, "could not initialize ares event thread", max_error_len);
		return -1;
	}

	if (AI_NUMERICHOST == ai_flags)
		ai_flags = ARES_AI_NUMERICHOST;

	ares_hints.ai_flags = ai_flags;
	ares_hints.ai_family = ai_family;
	ares_hints.ai_socktype = ai_socktype;

	ares_getaddrinfo(zbx_channel, ip, service, &ares_hints, ares_addrinfo_cb, &ai_context);
	/* small timeouts can make ares hang on some versions, give it time to timeout by itself */
	if (ARES_SUCCESS != (err = ares_queue_wait_empty(zbx_channel, SEC_PER_MIN * 10 * 1000)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot receive DNS answer: %s", ares_strerror(err));

		ares_cancel(zbx_channel);

		if (NULL != *ai_context.ares_ai)
		{
			ares_freeaddrinfo(*ai_context.ares_ai);
			*ai_context.ares_ai = NULL;
		}
	}

	if (NULL != *ai_context.ares_ai)
		return 0;

	zbx_strlcpy(error, ai_context.zbx_socket_strerror_message, max_error_len);

	zabbix_log(LOG_LEVEL_DEBUG, "%s",  ai_context.zbx_socket_strerror_message);

	return -1;
}
typedef struct
{
	char	*host;
	size_t	hostlen;
	int	status;
	char	zbx_socket_strerror_message[ZBX_SOCKET_STRERROR_LEN];
}
zbx_name_info_t;

static void	ares_nameinfo_cb(void *arg, int err, int timeouts, char *node, char *service)
{
	zbx_name_info_t	*name_info = (zbx_name_info_t *)arg;

	ZBX_UNUSED(service);
	ZBX_UNUSED(timeouts);

	if (ARES_SUCCESS != err)
	{
		zbx_snprintf(name_info->zbx_socket_strerror_message, sizeof(name_info->zbx_socket_strerror_message),
				"cannot reverse resolve DNS name: %s", ares_strerror(err));
		return;
	}

	name_info->status = SUCCEED;
	zbx_strlcpy(name_info->host, ZBX_NULL2EMPTY_STR(node), name_info->hostlen);

}

int	zbx_ares_getnameinfo(const struct sockaddr *sa, ares_socklen_t salen, char *host, size_t hostlen,
		char *error, int max_error_len)
{
	zbx_name_info_t	name_info = {.host = host, .hostlen = hostlen, .status = FAIL};
	int		err;

	ares_event_thread_init();

	if (NULL == zbx_channel)
	{
		zbx_strlcpy(error, "could not initialize ares event thread", max_error_len);
		return -1;
	}

	ares_getnameinfo(zbx_channel, sa, salen, ARES_NI_NAMEREQD, ares_nameinfo_cb, &name_info);

	if (ARES_SUCCESS != (err = ares_queue_wait_empty(zbx_channel, SEC_PER_MIN * 10 * 1000)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot receive DNS answer: %s", ares_strerror(err));

		ares_cancel(zbx_channel);
	}

	if (SUCCEED == name_info.status)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "resolved host '%s'", host);
		return 0;
	}

	zbx_strlcpy(error, name_info.zbx_socket_strerror_message, max_error_len);
	zabbix_log(LOG_LEVEL_DEBUG, "%s",  name_info.zbx_socket_strerror_message);

	return -1;
}

#endif

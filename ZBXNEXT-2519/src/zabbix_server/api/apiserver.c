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

#include "common.h"

#include "comms.h"
#include "log.h"
#include "zbxjson.h"
#include "zbxself.h"
#include "db.h"

#include "daemon.h"
#include "api.h"
#include "objects/mediatype.h"

extern unsigned char	process_type, daemon_type;
extern int		server_num, process_num;

#define ZBX_APISERVER_REQUEST_LINE		"POST /api_jsonrpc.php HTTP/1."
#define ZBX_APISERVER_REQUEST_LINE_SIZE		(sizeof(ZBX_APISERVER_REQUEST_LINE) - 1)

static const char	*skip_field_spaces(const char *in)
{
	while (' ' == *in)
		in++;

	return in;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_user                                                 *
 *                                                                            *
 * Purpose: finds the user calling API method by session id passed in jSON    *
 *          request                                                           *
 *                                                                            *
 * Parameters: jp     - [IN] the json rpc request                             *
 *             user   - [OUT] the caller user                                 *
 *             error  - [OUT] the error message                               *
 *                                                                            *
 * Return value: SUCCESS - the user session was found                         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_get_user(const struct zbx_json_parse *jp, zbx_api_user_t *user, char **error)
{
	int		ret = FAIL;
	char		*auth = NULL, *auth_esc = NULL;
	size_t		auth_alloc = 0;
	DB_RESULT	result;
	DB_ROW		row;

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, ZBX_API_RESULT_TAG_AUTH, &auth, &auth_alloc))
	{
		*error = zbx_strdup(*error, "Cannot read session id");
		goto out;
	}

	auth_esc = DBdyn_escape_string(auth);
	zbx_free(auth);

	result = DBselect("select u.userid,u.type from users u,sessions s where s.sessionid='%s' and u.userid=s.userid"
			" and s.status=%d", auth_esc, ZBX_SESSION_ACTIVE);

	/* TODO: should API timeout inactive sessions ? */
	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(user->id, row[0]);
		user->type = atoi(row[1]);
		ret = SUCCEED;
	}
	else
		*error = zbx_strdup(*error, "Invalid session id");

	DBfree_result(result);
out:
	zbx_free(auth_esc);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_call_method                                              *
 *                                                                            *
 * Purpose: calls API method                                                  *
 *                                                                            *
 * Parameters: jp     - [IN] the json rpc request                             *
 *             user   - [IN] the caller user                                  *
 *             result - [IN/OUT] the JSON response                            *
 *             error  - [OUT] the error message                               *
 *                                                                            *
 * Return value: SUCCESS - the request was processed successfully and         *
 *                         result (or failure) was written in JSON response   *
 *               FAIL    - failed to call method                              *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_call_method(const struct zbx_json_parse *jp, const zbx_api_user_t *user,
		struct zbx_json *result, char **error)
{
	const char	*__function_name = "zbx_api_call_method";
	int		ret = SUCCEED;
	char		*method = NULL;
	size_t		method_alloc = 0;


	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, ZBX_API_RESULT_TAG_METHOD, &method, &method_alloc))
	{
		*error = zbx_strdup(*error, "Cannot read method name");
		ret = FAIL;
		goto out;
	}

	/* TODO: speed up method resolving with hashset ? */
	if (0 == strcmp(method, "mediatype.get"))
	{
		zbx_api_mediatype_get(user, jp, result);
	}
	else if (0 == strcmp(method, "mediatype.create"))
	{
		zbx_api_mediatype_create(user, jp, result);
	}
	else if (0 == strcmp(method, "mediatype.delete"))
	{
		zbx_api_mediatype_delete(user, jp, result);
	}
	else if (0 == strcmp(method, "mediatype.update"))
	{
		zbx_api_mediatype_update(user, jp, result);
	}
	else
	{
		*error = zbx_dsprintf(*error, "Unsupported API method \"%s\"", method);
		ret = FAIL;
	}
	/* TODO: should error/success results be logged ? */
out:
	zbx_free(method);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_process_request_body                                     *
 *                                                                            *
 * Purpose: processes HTTP request body containing json rpc request           *
 *                                                                            *
 * Parameters: sock - [OUT] the connection socket                             *
 *             data - [IN]  the request data                                  *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCESS - the request was processed successfully and         *
 *                         JSON response was sent                             *
 *               FAIL    - bad request, no response was sent                  *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_process_jsonrpc(zbx_sock_t *sock, const char *data)
{
	const char		*__function_name = "zbx_api_process_jsonrpc";
	char			*header, *error = NULL, *id = NULL;
	struct zbx_json_parse	jp;
	struct zbx_json		result;
	size_t			id_alloc = 0;
	zbx_api_user_t		user;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): %s", __function_name, data);

	if (SUCCEED != zbx_json_open(data, &jp))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid JSON data: %s", data);
		goto out;
	}

	if (SUCCEED != zbx_json_value_by_name_dyn(&jp, ZBX_API_RESULT_TAG_ID, &id, &id_alloc))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot read request id");
		goto out;
	}

	zbx_api_json_init(&result, id);

	if (SUCCEED != zbx_api_get_user(&jp, &user, &error) ||
			SUCCEED != zbx_api_call_method(&jp, &user, &result, &error))
	{
		zbx_api_json_add_error(&result, NULL, error);
	}

	zbx_json_close(&result);

	header = zbx_dsprintf(NULL, "HTTP/1.0 200 OK\r\nContent-Length: %d\r\nConnection: close\r\n"
			"Content-Type: application/json\r\n\r\n", strlen(result.buffer));

	zbx_tcp_send_raw(sock, header);
	zbx_tcp_send_raw(sock, result.buffer);

	zbx_free(header);
	zbx_json_free(&result);

	ret = SUCCEED;
out:
	zbx_free(id);
	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_process_request                                          *
 *                                                                            *
 * Purpose: processes HTTP request                                            *
 *                                                                            *
 * Parameters: sock - [IN/OUT] the connection socket                          *
 *                                                                            *
 ******************************************************************************/
static void	zbx_api_process_request(zbx_sock_t *sock)
{
	const char	*__function_name = "zbx_api_process_request";
	const char	*line;
	size_t		content_length = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == (line = zbx_tcp_recv_line(sock)))
	{
		zbx_tcp_send_raw(sock, "HTTP/1.0 400 Bad Request\r\n");
		zabbix_log(LOG_LEVEL_WARNING, "cannot read post request");
		goto out;
	}

	if (0 != strncmp(line, ZBX_APISERVER_REQUEST_LINE, sizeof(ZBX_APISERVER_REQUEST_LINE_SIZE)) ||
			(line[ZBX_APISERVER_REQUEST_LINE_SIZE] != '0' && line[ZBX_APISERVER_REQUEST_LINE_SIZE] != '1'))
	{
		zbx_tcp_send_raw(sock, "HTTP/1.0 400 Bad Request\r\n");
		zabbix_log(LOG_LEVEL_WARNING, "invalid request line \"%s\"", line);
		goto out;
	}

	for (line = zbx_tcp_recv_line(sock); '\0' != *line; line = zbx_tcp_recv_line(sock))
	{
		if (0 == zbx_strncasecmp(line, "Content-Length:", 15))
		{
			line = skip_field_spaces(line + 15);

			if (SUCCEED != is_uint31(line, &content_length))
			{
				zbx_tcp_send_raw(sock, "HTTP/1.0 400 Bad Request\r\n");
				zabbix_log(LOG_LEVEL_WARNING, "invalid Content-Length value \"%s\"", line);
				return;
			}

			continue;
		}

		if (0 == zbx_strncasecmp(line, "Content-Type:", 13))
		{
			line = skip_field_spaces(line + 13);

			if (0 != strncmp(line, "application/json", 17))
			{
				zbx_tcp_send_raw(sock, "HTTP/1.0 415 Unsupported Media Type\r\n");
				zabbix_log(LOG_LEVEL_WARNING, "invalid Content-Type value \"%s\"", line);
				return;
			}

			continue;
		}
	}

	if (0 == content_length)
	{
		zbx_tcp_send_raw(sock, "HTTP/1.0 411 Length Required\r\n");
		zabbix_log(LOG_LEVEL_WARNING, "no Content-Length value specified");
		goto out;
	}

	if (content_length == sock->read_bytes - (unsigned)(sock->next_line - sock->buffer))
	{
		/* trivial case - the expected data was sent in one line */
		if (SUCCEED != zbx_api_process_jsonrpc(sock, sock->next_line))
			zbx_tcp_send_raw(sock, "HTTP/1.0 400 Bad Request\r\n");
	}
	else if (content_length < sock->read_bytes - (unsigned)(sock->next_line - sock->buffer))
	{
		/* we already have received more data than expected, throw error  */
		zbx_tcp_send_raw(sock, "HTTP/1.0 400 Bad Request\r\n");
		zabbix_log(LOG_LEVEL_WARNING, "received more data than expected");
		goto out;
	}
	else
	{
		/* data is coming on multiple lines - copy the leftover data from buffer, */
		/* read the rest of data and copy it to the buffer                        */
		char	*data = NULL;
		size_t	data_alloc = 0, data_offset = 0;

		zbx_strcpy_alloc(&data, &data_alloc, &data_offset, sock->next_line);

		if (SUCCEED != zbx_tcp_recv(sock))
		{
			zbx_tcp_send_raw(sock, "HTTP/1.0 400 Bad Request\r\n");
			zabbix_log(LOG_LEVEL_WARNING, "cannot receive full request body");
			goto clean;
		}

		if (data_offset + sock->read_bytes != content_length)
		{
			zbx_tcp_send_raw(sock, "HTTP/1.0 400 Bad Request\r\n");
			zabbix_log(LOG_LEVEL_WARNING, "received data size does not match the content length");
			goto clean;
		}

		zbx_strcpy_alloc(&data, &data_alloc, &data_offset, sock->buffer);

		if (SUCCEED != zbx_api_process_jsonrpc(sock, data))
			zbx_tcp_send_raw(sock, "HTTP/1.0 400 Bad Request\r\n");

clean:
		zbx_free(data);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

ZBX_THREAD_ENTRY(apiserver_thread, args)
{
	double		sec = 0.0;
	zbx_sock_t	s;
	char		*error = NULL;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_daemon_type_string(daemon_type),
			server_num, get_process_type_string(process_type), process_num);

	memcpy(&s, (zbx_sock_t *)((zbx_thread_args_t *)args)->args, sizeof(zbx_sock_t));

	if (SUCCEED != zbx_api_init(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "API initialization failure: %s\n", error);
		zbx_free(error);

		exit(EXIT_FAILURE);
	}

	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_setproctitle("%s #%d [processed data in " ZBX_FS_DBL " sec, waiting for connection]",
				get_process_type_string(process_type), process_num, sec);

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);

		if (SUCCEED == zbx_tcp_accept(&s))
		{
			update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

			zbx_setproctitle("%s #%d [processing data]", get_process_type_string(process_type),
					process_num);

			sec = zbx_time();
			zbx_api_process_request(&s);
			sec = zbx_time() - sec;

			zbx_tcp_unaccept(&s);
		}
		else if (EINTR != zbx_sock_last_error())
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to accept an incoming connection: %s",
					zbx_tcp_strerror());
		}
	}
}

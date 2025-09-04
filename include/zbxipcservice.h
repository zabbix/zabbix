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

#ifndef ZABBIX_ZBXIPCSERVICE_H
#define ZABBIX_ZBXIPCSERVICE_H

#include "zbxalgo.h"
#include "zbxtime.h"

#define ZBX_IPC_PATH_MAX	sizeof(((struct sockaddr_un *)0)->sun_path)

#define ZBX_IPC_SOCKET_BUFFER_SIZE	4096

#define ZBX_IPC_RECV_IMMEDIATE	0
#define ZBX_IPC_RECV_WAIT	1
#define ZBX_IPC_RECV_TIMEOUT	2

#define ZBX_IPC_WAIT_FOREVER	-1

typedef struct
{
	/* the message code */
	zbx_uint32_t	code;

	/* the data size */
	zbx_uint32_t	size;

	/* the data */
	unsigned char	*data;
}
zbx_ipc_message_t;

/* Messaging socket, providing blocking connections to IPC service. */
/* The IPC socket api is used for simple write/read operations.     */
typedef struct
{
	/* socket descriptor */
	int		fd;

	/* incoming data buffer */
	unsigned char	rx_buffer[ZBX_IPC_SOCKET_BUFFER_SIZE];
	zbx_uint32_t	rx_buffer_bytes;
	zbx_uint32_t	rx_buffer_offset;
}
zbx_ipc_socket_t;

typedef struct zbx_ipc_client zbx_ipc_client_t;

ZBX_PTR_VECTOR_DECL(ipc_client_ptr, zbx_ipc_client_t *)

/* IPC service */
typedef struct
{
	/* the listening socket descriptor */
	int				fd;

	struct event_base		*ev;
	struct event			*ev_listener;
	struct event			*ev_timer;
	struct event			*ev_alert;

	/* the unix socket path */
	char				*path;

	/* the connected clients */
	zbx_vector_ipc_client_ptr_t	clients;

	/* the clients with messages */
	zbx_queue_ptr_t			clients_recv;
}
zbx_ipc_service_t;

typedef struct
{
	zbx_ipc_client_t	*client;

	struct event_base	*ev;
	struct event		*ev_timer;

	unsigned char		state;
}
zbx_ipc_async_socket_t;

int	zbx_ipc_service_init_env(const char *path, char **error);
void	zbx_ipc_service_free_env(void);
int	zbx_ipc_service_start(zbx_ipc_service_t *service, const char *service_name, char **error);
int	zbx_ipc_service_recv(zbx_ipc_service_t *service, const zbx_timespec_t *timeout, zbx_ipc_client_t **client,
		zbx_ipc_message_t **message);
void	zbx_ipc_service_alert(zbx_ipc_service_t *service);
void	zbx_ipc_service_close(zbx_ipc_service_t *service);

int	zbx_ipc_client_send(zbx_ipc_client_t *client, zbx_uint32_t code, const unsigned char *data, zbx_uint32_t size);
void	zbx_ipc_client_close(zbx_ipc_client_t *client);
int	zbx_ipc_client_get_fd(zbx_ipc_client_t *client);

void			zbx_ipc_client_addref(zbx_ipc_client_t *client);
void			zbx_ipc_client_release(zbx_ipc_client_t *client);
int			zbx_ipc_client_connected(zbx_ipc_client_t *client);
zbx_uint64_t		zbx_ipc_client_id(const zbx_ipc_client_t *client);
zbx_ipc_client_t	*zbx_ipc_client_by_id(const zbx_ipc_service_t *service, zbx_uint64_t id);
void	zbx_ipc_client_set_userdata(zbx_ipc_client_t *client, void *userdata);
void	*zbx_ipc_client_get_userdata(zbx_ipc_client_t *client);

int	zbx_ipc_socket_open(zbx_ipc_socket_t *csocket, const char *service_name, int timeout, char **error);
void	zbx_ipc_socket_close(zbx_ipc_socket_t *csocket);
int	zbx_ipc_socket_write(zbx_ipc_socket_t *csocket, zbx_uint32_t code, const unsigned char *data,
		zbx_uint32_t size);
int	zbx_ipc_socket_read(zbx_ipc_socket_t *csocket, zbx_ipc_message_t *message);
int	zbx_ipc_socket_connected(const zbx_ipc_socket_t *csocket);

int	zbx_ipc_async_socket_open(zbx_ipc_async_socket_t *asocket, const char *service_name, int timeout, char **error);
void	zbx_ipc_async_socket_close(zbx_ipc_async_socket_t *asocket);
int	zbx_ipc_async_socket_send(zbx_ipc_async_socket_t *asocket, zbx_uint32_t code, const unsigned char *data,
		zbx_uint32_t size);
int	zbx_ipc_async_socket_recv(zbx_ipc_async_socket_t *asocket, int timeout, zbx_ipc_message_t **message);
int	zbx_ipc_async_socket_flush(zbx_ipc_async_socket_t *asocket, int timeout);
int	zbx_ipc_async_socket_check_unsent(zbx_ipc_async_socket_t *asocket);
int	zbx_ipc_async_socket_connected(zbx_ipc_async_socket_t *asocket);
int	zbx_ipc_async_exchange(const char *service_name, zbx_uint32_t code, int timeout, const unsigned char *data,
		zbx_uint32_t size, unsigned char **out, char **error);

void	zbx_ipc_message_free(zbx_ipc_message_t *message);
void	zbx_ipc_message_clean(zbx_ipc_message_t *message);
void	zbx_ipc_message_init(zbx_ipc_message_t *message);
void	zbx_ipc_message_format(const zbx_ipc_message_t *message, char **data);
#ifdef HAVE_OPENIPMI
void	zbx_ipc_message_copy(zbx_ipc_message_t *dst, const zbx_ipc_message_t *src);
#endif

void	zbx_init_library_ipcservice(unsigned char program_type);

#endif

#ifndef ZABBIX_ZBXIPCSERVICE_H
#define ZABBIX_ZBXIPCSERVICE_H

#include "common.h"
#include "zbxalgo.h"

#define ZBX_IPC_SOCKET_BUFFER_SIZE	4096

#define ZBX_IPC_MESSAGE_CODE	0
#define ZBX_IPC_MESSAGE_SIZE	1

typedef struct
{
	/* the message header containing code and data */
	zbx_uint32_t	header[2];
	/* the data */
	unsigned char	*data;
}
zbx_ipc_message_t;

/* messaging socket */
typedef struct
{
	/* socket descriptor */
	int		fd;
}
zbx_ipc_socket_t;

typedef struct zbx_ipc_client zbx_ipc_client_t;

/* IPC service */
typedef struct
{
	/* the listening socket descriptor */
	int			fd;

	struct event_base	*ev;
	struct event		*ev_listener;

	/* the unix socket path */
	char			*path;

	/* the connected clients */
	zbx_vector_ptr_t	clients;

	/* the clients with messages */
	zbx_queue_ptr_t		clients_recv;

	/* the client to be removed during next recv() call */
	zbx_ipc_client_t	*client_remove;
}
zbx_ipc_service_t;

int	zbx_ipc_service_init_env(const char *path, char **error);
void	zbx_ipc_service_free_env();
int	zbx_ipc_service_start(zbx_ipc_service_t *service, const char *service_name, char **error);
void	zbx_ipc_service_recv(zbx_ipc_service_t *service, zbx_ipc_client_t **client, zbx_ipc_message_t **message);
void	zbx_ipc_service_close(zbx_ipc_service_t *service);

int	zbx_ipc_client_send(zbx_ipc_client_t *client, zbx_uint32_t code, const char *data, zbx_uint32_t size);
void	zbx_ipc_client_close(zbx_ipc_client_t *client);

int	zbx_ipc_socket_open(zbx_ipc_socket_t *csocket, const char *service_name, int timeout, char **error);
void	zbx_ipc_socket_close(zbx_ipc_socket_t *csocket);
int	zbx_ipc_socket_write(zbx_ipc_socket_t *csocket, zbx_uint32_t code, const char *data, zbx_uint32_t size);
int	zbx_ipc_socket_read(zbx_ipc_socket_t *csocket, zbx_ipc_message_t *message);

void	zbx_ipc_message_free(zbx_ipc_message_t *message);
void	zbx_ipc_message_clean(zbx_ipc_message_t *message);

#endif


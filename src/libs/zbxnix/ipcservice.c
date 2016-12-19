#include <errno.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/un.h>
#include <sys/ioctl.h>
#include <event.h>

#include "common.h"
#include "zbxtypes.h"
#include "zbxalgo.h"
#include "log.h"

#include "zbxipcservice.h"


#define ZBX_IPC_PATH_MAX	sizeof(((struct sockaddr_un *)0)->sun_path)

#define ZBX_IPC_DATA_DUMP_SIZE		128

static char	ipc_path[ZBX_IPC_PATH_MAX] = {0};
static size_t	ipc_path_root_len = 0;

#define ZBX_IPC_CLIENT_STATE_NONE	0
#define ZBX_IPC_CLIENT_STATE_QUEUED	1

struct zbx_ipc_client
{
	zbx_ipc_socket_t	csocket;
	zbx_ipc_service_t	*service;

	zbx_ipc_message_t	*rx_message;
	zbx_uint32_t		rx_bytes;
	zbx_queue_ptr_t		rx_queue;
	struct event		*rx_event;

	zbx_ipc_message_t	*tx_message;
	zbx_uint32_t		tx_bytes;
	zbx_queue_ptr_t		tx_queue;
	struct event		*tx_event;

	zbx_uint64_t		id;
	unsigned char		state;
};

/*
 * Private API
 */
#define ZBX_IPC_CLIENT_STATE_PENDING	0
#define ZBX_IPC_CLIENT_STATE_QUEUED	1

#define ZBX_IPC_HEADER_SIZE	(int)(sizeof(zbx_uint32_t) * 2)

#if !defined(_EVENT_NUMERIC_VERSION) || _EVENT_NUMERIC_VERSION < 0x2000000
typedef int evutil_socket_t;

static struct event	*event_new(struct event_base *ev, evutil_socket_t fd, short what,
		void(*cb_func)(int, short, void *), void *cb_arg)
{
	struct event	*event;

	event = zbx_malloc(NULL, sizeof(struct event));
	event_set(event, fd, what, cb_func, cb_arg);
	event_base_set(ev, event);

	return event;
}

static void	event_free(struct event *event)
{
	event_del(event);
	zbx_free(event);
}

#endif

static void	ipc_client_read_event_cb(evutil_socket_t fd, short what, void *arg);
static void	ipc_client_write_event_cb(evutil_socket_t fd, short what, void *arg);

static const char	*ipc_get_path()
{
	ipc_path[ipc_path_root_len] = '\0';

	return ipc_path;
}

#define ZBX_IPC_SOCKET_PREFIX	"/zabbix_"
#define ZBX_IPC_SOCKET_SUFFIX	".sock"

/******************************************************************************
 *                                                                            *
 * Function: ipc_make_path                                                    *
 *                                                                            *
 * Purpose: makes socket path from the service name                           *
 *                                                                            *
 * Parameters: service - [IN] the service name                                *
 *                                                                            *
 * Return value: The created path or NULL if the path exceeds unix domain     *
 *               socket path maximum length                                   *
 *                                                                            *
 ******************************************************************************/
static const char	*ipc_make_path(const char *service)
{
	int	path_len, offset;

	path_len = strlen(service);

	if (ZBX_IPC_PATH_MAX < ipc_path_root_len + path_len + 1 + ZBX_CONST_STRLEN(ZBX_IPC_SOCKET_PREFIX) +
			ZBX_CONST_STRLEN(ZBX_IPC_SOCKET_SUFFIX))
	{
		return NULL;
	}

	offset = ipc_path_root_len;
	memcpy(ipc_path + ipc_path_root_len , ZBX_IPC_SOCKET_PREFIX, ZBX_CONST_STRLEN(ZBX_IPC_SOCKET_PREFIX));
	offset += ZBX_CONST_STRLEN(ZBX_IPC_SOCKET_PREFIX);
	memcpy(ipc_path + offset, service, path_len);
	offset += path_len;
	memcpy(ipc_path + offset, ZBX_IPC_SOCKET_SUFFIX, ZBX_CONST_STRLEN(ZBX_IPC_SOCKET_SUFFIX) + 1);

	return ipc_path;
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_client_free_event                                            *
 *                                                                            *
 * Purpose: frees client's libevent event                                     *
 *                                                                            *
 * Parameters: client - [IN]                                                  *
 *                                                                            *
 ******************************************************************************/
static void	ipc_client_free_events(zbx_ipc_client_t *client)
{
	if (NULL != client->rx_event)
	{
		event_free(client->rx_event);
		client->rx_event = NULL;
	}

	if (NULL != client->tx_event)
	{
		event_free(client->tx_event);
		client->tx_event = NULL;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_client_free                                                  *
 *                                                                            *
 * Purpose: frees IPC service client                                          *
 *                                                                            *
 * Parameters: client - [IN] the client to free                               *
 *                                                                            *
 ******************************************************************************/
static void	ipc_client_free(zbx_ipc_client_t *client)
{
	zbx_ipc_message_t	*message;

	zbx_ipc_socket_close(&client->csocket);

	while (NULL != (message = zbx_queue_ptr_pop(&client->rx_queue)))
		zbx_ipc_message_free(message);

	zbx_queue_ptr_destroy(&client->rx_queue);
	zbx_ipc_message_free(client->rx_message);

	while (NULL != (message = zbx_queue_ptr_pop(&client->tx_queue)))
		zbx_ipc_message_free(message);

	zbx_queue_ptr_destroy(&client->tx_queue);
	zbx_ipc_message_free(client->tx_message);

	ipc_client_free_events(client);

	zbx_free(client);
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_write                                                        *
 *                                                                            *
 * Purpose: writes data to a socket                                           *
 *                                                                            *
 * Parameters: fd        - [IN] the socket file descriptor                    *
 *             data      - [IN] the data                                      *
 *             size      - [IN] the data size                                 *
 *             size_sent - [IN] the actual size written to socket             *
 *                                                                            *
 * Return value: SUCCEED - the data was successfully written                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	ipc_write(int fd, const char *data, zbx_uint32_t size, zbx_uint32_t *size_sent)
{
	zbx_uint32_t	offset = 0;
	int		n, ret = SUCCEED;
	static zbx_uint64_t	write_num = 0;

	while (offset != size)
	{
		n = write(fd, data + offset, size - offset);

		if (-1 == n)
		{
			if (EINTR == errno)
				continue;

			if (EWOULDBLOCK == errno || EAGAIN == errno)
				break;

			zabbix_log(LOG_LEVEL_WARNING, "cannot write to IPC socket: %s", strerror(errno));
			ret = FAIL;
			break;
		}
		write_num += n;
		offset += n;
	}

	*size_sent = offset;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_socket_write                                                 *
 *                                                                            *
 * Purpose: writes IPC message to socket                                      *
 *                                                                            *
 * Parameters: socket  - [IN] the IPC socket                                  *
 *             code    - [IN] the message code                                *
 *             data    - [IN] the data                                        *
 *             size    - [IN] the data size                                   *
 *             tx_size - [IN] the actual size written to socket               *
 *                                                                            *
 * Return value: SUCCEED - the data was successfully written                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	ipc_socket_write_message(zbx_ipc_socket_t *csocket, zbx_uint32_t code, const char *data, zbx_uint32_t size,
		zbx_uint32_t *tx_size)
{
	int		ret;
	zbx_uint32_t	size_data, buffer[ZBX_IPC_SOCKET_BUFFER_SIZE / sizeof(zbx_uint32_t)];


	buffer[0] = code;
	buffer[1] = size;

	if (ZBX_IPC_SOCKET_BUFFER_SIZE - ZBX_IPC_HEADER_SIZE >= size)
	{
		memcpy(buffer + 2, data, size);
		return ipc_write(csocket->fd, (char *)buffer, size + ZBX_IPC_HEADER_SIZE, tx_size);
	}

	if (FAIL == ipc_write(csocket->fd, (char *)buffer, ZBX_IPC_HEADER_SIZE, tx_size))
		return FAIL;

	ret = ipc_write(csocket->fd, data, size, &size_data);
	*tx_size += size_data;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_client_push_rx_message                                       *
 *                                                                            *
 * Purpose: adds message to received messages queue                           *
 *                                                                            *
 * Parameters: client - [IN] the client to read                               *
 *                                                                            *
 ******************************************************************************/
static void	ipc_client_push_rx_message(zbx_ipc_client_t *client)
{
	zbx_queue_ptr_push(&client->rx_queue, client->rx_message);
	client->rx_message = NULL;
	client->rx_bytes = 0;
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_client_parse_buffer                                          *
 *                                                                            *
 * Purpose: parses data into IPC messages                                     *
 *                                                                            *
 * Parameters: client - [IN] the client                                       *
 *             buffer - [IN] the buffer to parse                              *
 *             size   - [IN] the number of bytes to parse                     *
 *                                                                            *
 ******************************************************************************/
static void	ipc_client_parse_buffer(zbx_ipc_client_t *client, const unsigned char *buffer, zbx_uint32_t size)
{
	zbx_uint32_t	offset = 0, copy_size, data_size, data_offset;

	while (offset < size)
	{
		if (NULL == client->rx_message)
			client->rx_message = zbx_malloc(NULL, sizeof(zbx_ipc_message_t));

		if (ZBX_IPC_HEADER_SIZE > client->rx_bytes)
		{
			copy_size = MIN(ZBX_IPC_HEADER_SIZE - client->rx_bytes, size - offset);
			data_offset = client->rx_bytes;

			memcpy((char *)client->rx_message->header + data_offset, buffer + offset, copy_size);

			client->rx_bytes += copy_size;
			offset += copy_size;

			if (ZBX_IPC_HEADER_SIZE > client->rx_bytes)
				return;

			data_size = client->rx_message->header[ZBX_IPC_MESSAGE_SIZE];

			if (0 == data_size)
			{
				client->rx_message->data = NULL;
				ipc_client_push_rx_message(client);
				continue;
			}

			client->rx_message->data = zbx_malloc(NULL, data_size);
		}

		data_size = client->rx_message->header[ZBX_IPC_MESSAGE_SIZE];
		data_offset = client->rx_bytes - ZBX_IPC_HEADER_SIZE;
		copy_size = MIN(data_size - data_offset, size - offset);

		memcpy(client->rx_message->data + data_offset, buffer + offset, copy_size);

		client->rx_bytes += copy_size;
		offset += copy_size;

		if (client->rx_bytes != data_size + ZBX_IPC_HEADER_SIZE)
			break;

		ipc_client_push_rx_message(client);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_client_read                                                  *
 *                                                                            *
 * Purpose: reads data from IPC service client                                *
 *                                                                            *
 * Parameters: client - [IN] the client to read                               *
 *                                                                            *
 * Return value:  FAIL - read error/connection was closed                     *
 *                                                                            *
 * Comments: This function reads data from socket, parses it and adds         *
 *           parsed messages to received messages queue.                      *
 *                                                                            *
 ******************************************************************************/
static int	ipc_client_read(zbx_ipc_client_t *client)
{
	int		n;
	zbx_uint32_t	data_size, data_offset, read_size = ZBX_IPC_SOCKET_BUFFER_SIZE;
	unsigned char	buffer[ZBX_IPC_SOCKET_BUFFER_SIZE], *pbuffer = buffer;

	for (;;)
	{
		if (ZBX_IPC_HEADER_SIZE < client->rx_bytes)
		{
			data_size = client->rx_message->header[ZBX_IPC_MESSAGE_SIZE];
			data_offset = client->rx_bytes - ZBX_IPC_HEADER_SIZE;

			/* long messages read directly into message buffer */
			if (ZBX_IPC_SOCKET_BUFFER_SIZE * 0.75 < data_size - data_offset)
			{
				pbuffer = client->rx_message->data + data_offset;
				read_size = data_size - data_offset;
			}
		}

		if (-1 == (n = read(client->csocket.fd, pbuffer, read_size)))
		{
			if (EWOULDBLOCK == errno || EAGAIN == errno)
				break;
		}

		if (0 >= n)
		{
			zbx_free(client->rx_message);
			client->rx_bytes = 0;
			return FAIL;
		}

		if (pbuffer != buffer)
		{
			client->rx_bytes += n;

			if (client->rx_bytes == data_size + ZBX_IPC_HEADER_SIZE)
				ipc_client_push_rx_message(client);

			pbuffer = buffer;
			read_size = ZBX_IPC_SOCKET_BUFFER_SIZE;

			continue;
		}

		ipc_client_parse_buffer(client, buffer, n);
	}

	return SUCCEED;
}
/******************************************************************************
 *                                                                            *
 * Function: ipc_client_write                                                 *
 *                                                                            *
 * Purpose: writes queued data to IPC service client                          *
 *                                                                            *
 * Parameters: client - [IN] the client                                       *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 ******************************************************************************/
static int	ipc_client_write(zbx_ipc_client_t *client)
{
	int		n;
	zbx_uint32_t	data_offset, data_size;

	if (ZBX_IPC_HEADER_SIZE > client->tx_bytes)
	{
		int	offset = client->tx_bytes;

		if (0 > (n = write(client->csocket.fd, (char *)client->tx_message->header + offset,
				ZBX_IPC_HEADER_SIZE - offset)))
		{
			return FAIL;
		}

		client->tx_bytes += n;

		if (ZBX_IPC_HEADER_SIZE > client->tx_bytes)
			return SUCCEED;
	}

	data_size = client->tx_message->header[ZBX_IPC_MESSAGE_SIZE];
	data_offset = client->tx_bytes - ZBX_IPC_HEADER_SIZE;

	while (data_offset < data_size)
	{
		if (0 > (n = write(client->csocket.fd, client->tx_message->data + data_offset,
				data_size - data_offset)))
		{
			return (EWOULDBLOCK == errno || EAGAIN == errno ? SUCCEED : FAIL);
		}

		data_offset += n;
		client->tx_bytes += n;
	}

	if (client->tx_bytes - ZBX_IPC_HEADER_SIZE == data_size)
	{
		zbx_ipc_message_free(client->tx_message);
		client->tx_message = zbx_queue_ptr_pop(&client->tx_queue);
		client->tx_bytes = 0;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_service_pop_client                                           *
 *                                                                            *
 * Purpose: gets the next client with messages/closed socket from recv queue  *
 *                                                                            *
 * Parameters: service - [IN] the IPC service                                 *
 *                                                                            *
 * Return value: The client with messages/closed socket                       *
 *                                                                            *
 ******************************************************************************/
static zbx_ipc_client_t	*ipc_service_pop_client(zbx_ipc_service_t *service)
{
	zbx_ipc_client_t	*client;

	if (0 == zbx_queue_ptr_values_num(&service->clients_recv))
		return NULL;

	client = (zbx_ipc_client_t *)zbx_queue_ptr_pop(&service->clients_recv);
	client->state = ZBX_IPC_CLIENT_STATE_NONE;

	return client;
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_service_push_client                                          *
 *                                                                            *
 * Purpose: pushes client to the recv queue if needed                         *
 *                                                                            *
 * Parameters: service - [IN] the IPC service                                 *
 *             client  - [IN] the IPC client                                  *
 *                                                                            *
 * Comments: The client is pushed to the recv queue if it isn't already there *
 *           and there is messages to return or the client connection was     *
 *           closed.                                                          *
 *                                                                            *
 ******************************************************************************/
static void	ipc_service_push_client(zbx_ipc_service_t *service, zbx_ipc_client_t *client)
{
	if (ZBX_IPC_CLIENT_STATE_QUEUED == client->state)
		return;

	if (0 == zbx_queue_ptr_values_num(&client->rx_queue) && NULL != client->rx_event)
		return;

	client->state = ZBX_IPC_CLIENT_STATE_QUEUED;
	zbx_queue_ptr_push(&service->clients_recv, client);
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_service_add_client                                           *
 *                                                                            *
 * Purpose: adds a new IPC service client                                     *
 *                                                                            *
 * Parameters: service - [IN] the IPC service                                 *
 *             fd      - [IN] the client socket descriptor                    *
 *                                                                            *
 ******************************************************************************/
static void	ipc_service_add_client(zbx_ipc_service_t *service, int fd)
{
	const char		*__function_name = "ipc_service_add_client";
	zbx_ipc_client_t	*client;
	static zbx_uint64_t	next_clientid = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	client = (zbx_ipc_client_t *)zbx_malloc(NULL, sizeof(zbx_ipc_client_t));
	memset(client, 0, sizeof(zbx_ipc_client_t));

	fcntl(fd, F_SETFL, fcntl(fd, F_GETFL, 0) | O_NONBLOCK);
	client->csocket.fd = fd;
	client->id = next_clientid++;
	client->state = ZBX_IPC_CLIENT_STATE_NONE;

	zbx_queue_ptr_create(&client->rx_queue);
	zbx_queue_ptr_create(&client->tx_queue);

	client->service = service;
	client->rx_event = event_new(service->ev, fd, EV_READ | EV_PERSIST, ipc_client_read_event_cb, (void *)client);
	client->tx_event = event_new(service->ev, fd, EV_WRITE | EV_PERSIST, ipc_client_write_event_cb, (void *)client);
	event_add(client->rx_event, NULL);

	zbx_vector_ptr_append(&service->clients, client);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() clientid:%d", __function_name, client->id);
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_service_remove_client                                        *
 *                                                                            *
 * Purpose: adds a new IPC service client                                     *
 *                                                                            *
 * Parameters: service - [IN] the IPC service                                 *
 *             client  - [IN] the client to remove                            *
 *                                                                            *
 ******************************************************************************/
static void	ipc_service_remove_client(zbx_ipc_service_t *service, zbx_ipc_client_t *client)
{
	const char	*__function_name = "ipc_service_remove_client";
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() clientid:%d", __function_name, client->id);

	zbx_ipc_socket_close(&client->csocket);

	for (i = 0; i < service->clients.values_num; i++)
	{
		if (service->clients.values[i] == client)
			zbx_vector_ptr_remove_noorder(&service->clients, i);
	}

	zbx_queue_ptr_remove_value(&service->clients_recv, client);

	ipc_client_free(client);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_client_read_event_cb                                         *
 *                                                                            *
 * Purpose: client read event libevent callback                               *
 *                                                                            *
 ******************************************************************************/
static void	ipc_client_read_event_cb(evutil_socket_t fd, short what, void *arg)
{
	zbx_ipc_client_t	*client = (zbx_ipc_client_t *)arg;

	if (SUCCEED != ipc_client_read(client))
		ipc_client_free_events(client);

	/*
	if (SUCCEED != ipc_client_read(client))
		ipc_client_free_events(client);
		*/

	ipc_service_push_client(client->service, client);
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_client_write_event_cb                                        *
 *                                                                            *
 * Purpose: client write event libevent callback                              *
 *                                                                            *
 ******************************************************************************/
static void	ipc_client_write_event_cb(evutil_socket_t fd, short what, void *arg)
{
	zbx_ipc_client_t	*client = (zbx_ipc_client_t *)arg;

	if (SUCCEED != ipc_client_write(client))
		ipc_client_free_events(client);

	if (NULL == client->tx_message)
		event_del(client->tx_event);
}
/******************************************************************************
 *                                                                            *
 * Function: ipc_service_accept                                               *
 *                                                                            *
 * Purpose: accepts a new client connection                                   *
 *                                                                            *
 * Parameters: service - [IN] the IPC service                                 *
 *                                                                            *
 ******************************************************************************/
static int	ipc_service_accept(zbx_ipc_service_t *service)
{
	const char	*__function_name = "ipc_service_accept";
	int		fd, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (-1 == (fd = accept(service->fd, NULL, NULL)))
		goto out;

	ipc_service_add_client(service, fd);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_message_create                                               *
 *                                                                            *
 * Purpose: creates IPC message                                               *
 *                                                                            *
 * Parameters: code    - [IN] the message code                                *
 *             data    - [IN] the data                                        *
 *             size    - [IN] the data size                                   *
 *                                                                            *
 * Return value: The created message.                                         *
 *                                                                            *
 ******************************************************************************/
static zbx_ipc_message_t	*ipc_message_create(zbx_uint32_t code, const char *data, zbx_uint32_t size)
{
	zbx_ipc_message_t	*message;

	message = (zbx_ipc_message_t *)zbx_malloc(NULL, sizeof(zbx_ipc_message_t));

	message->header[ZBX_IPC_MESSAGE_CODE] = code;
	message->header[ZBX_IPC_MESSAGE_SIZE] = size;

	if (0 != size)
	{
		message->data = zbx_malloc(NULL, size);
		memcpy(message->data, data, size);
	}
	else
		message->data = NULL;

	return message;
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_message_format                                               *
 *                                                                            *
 * Purpose: formats message to readable format for debug messages             *
 *                                                                            *
 * Parameters: service - [IN] the IPC service                                 *
 *                                                                            *
 ******************************************************************************/
static void	ipc_message_format(zbx_ipc_message_t *message, char **data)
{
	size_t		data_alloc = ZBX_IPC_DATA_DUMP_SIZE * 4 + 32, data_offset = 0;
	zbx_uint32_t	i, data_num = message->header[ZBX_IPC_MESSAGE_SIZE];

	if (NULL == message)
		return;

	if (ZBX_IPC_DATA_DUMP_SIZE < data_num)
		data_num = ZBX_IPC_DATA_DUMP_SIZE;

	*data = zbx_malloc(*data, data_alloc);
	zbx_snprintf_alloc(data, &data_alloc, &data_offset, "code:%u size:%u data:",
			message->header[ZBX_IPC_MESSAGE_CODE], message->header[ZBX_IPC_MESSAGE_SIZE]);

	for (i = 0; i < data_num; i++)
	{
		if (0 != i)
		{
			zbx_strcpy_alloc(data, &data_alloc, &data_offset, (0 == (i & 7) ? " | " : " "));
		}

		zbx_snprintf_alloc(data, &data_alloc, &data_offset, "%02x", (int)message->data[i]);
	}

	(*data)[data_offset] = '\0';
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_service_event_log                                            *
 *                                                                            *
 * Purpose: libevent logging callback                                         *
 *                                                                            *
 ******************************************************************************/
static void ipc_service_event_log_cb(int severity, const char *msg)
{
	int	loglevel;

	switch (severity)
	{
		case _EVENT_LOG_DEBUG:
			loglevel = LOG_LEVEL_TRACE;
			break;
		case _EVENT_LOG_MSG:
			loglevel = LOG_LEVEL_DEBUG;
			break;
		case _EVENT_LOG_WARN:
			loglevel = LOG_LEVEL_WARNING;
			break;
		case _EVENT_LOG_ERR:
			loglevel = LOG_LEVEL_DEBUG;
			break;
		default:
			loglevel = LOG_LEVEL_DEBUG;
			break;
	}

	zabbix_log(loglevel, "IPC service: %s", msg);
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_service_init_libevent                                        *
 *                                                                            *
 * Purpose: initialize libevent library                                       *
 *                                                                            *
 ******************************************************************************/
static void	ipc_service_init_libevent()
{
	event_set_log_callback(ipc_service_event_log_cb);
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_service_free_libevent                                        *
 *                                                                            *
 * Purpose: uninitialize libevent library                                     *
 *                                                                            *
 ******************************************************************************/
static void	ipc_service_free_libevent()
{
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_service_client_connected_cb                                  *
 *                                                                            *
 * Purpose: libevent listener callback                                        *
 *                                                                            *
 ******************************************************************************/
void	ipc_service_client_connected_cb(evutil_socket_t fd, short what, void *arg)
{
	zbx_ipc_service_t	*service = (zbx_ipc_service_t *)arg;

	ipc_service_accept(service);
}

/*
 * Public client API
 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_ipc_socket_open                                              *
 *                                                                            *
 * Purpose: opens socket to an IPC service listening on the specified path    *
 *                                                                            *
 * Parameters: csocket      - [OUT] the IPC socket to the service             *
 *             service_name - [IN] the IPC service name                       *
 *             timeout      - [IN] the connection timeout                     *
 *             error        - [OUT] the error message                         *
 *                                                                            *
 * Return value: SUCCEED - the socket was successfully opened                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_ipc_socket_open(zbx_ipc_socket_t *csocket, const char *service_name, int timeout, char **error)
{
	const char		*__function_name = "zbx_ipc_socket_open";
	struct sockaddr_un	addr;
	time_t			start;
	struct timespec		ts = {0, 100000000};
	const char		*socket_path;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == (socket_path = ipc_make_path(service_name)))
	{
		*error = zbx_dsprintf(*error, "Invalid service name \"%s\".", service_name);
		goto out;
	}

	if (-1 == (csocket->fd = socket(AF_UNIX, SOCK_STREAM, 0)))
	{
		*error = zbx_dsprintf(*error, "Cannot create client socket: %s.", zbx_strerror(errno));
		goto out;
	}

	memset(&addr, 0, sizeof(addr));
	addr.sun_family = AF_UNIX;
	memcpy(addr.sun_path, socket_path, sizeof(addr.sun_path));

	start = time(NULL);

	while (0 != connect(csocket->fd, (struct sockaddr*)&addr, sizeof(addr)))
	{
		if (time(NULL) - start > timeout)
		{
			*error = zbx_dsprintf(*error, "Cannot connect to service \"%s\": %s.", service_name,
					zbx_strerror(errno));
			goto out;
		}

		nanosleep(&ts, NULL);
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ipc_socket_close                                             *
 *                                                                            *
 * Purpose: closes socket to an IPC service                                   *
 *                                                                            *
 * Parameters: csocket - [IN/OUT] the IPC socket to close                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_ipc_socket_close(zbx_ipc_socket_t *csocket)
{
	close(csocket->fd);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ipc_socket_write                                             *
 *                                                                            *
 * Purpose: writes a message to IPC service                                   *
 *                                                                            *
 * Parameters: csocket - [IN] an opened IPC socket to the servic              *
 *             code    - [IN] the message code                                *
 *             data    - [IN] the data                                        *
 *             size    - [IN] the data size                                   *
 *                                                                            *
 * Return value: SUCCEED - the message was successfully written               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_ipc_socket_write(zbx_ipc_socket_t *csocket, zbx_uint32_t code, const char *data, zbx_uint32_t size)
{
	const char	*__function_name = "zbx_ipc_socket_write";
	int		ret;
	zbx_uint32_t	size_sent;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED == ipc_socket_write_message(csocket, code, data, size, &size_sent) &&
			size_sent == size + ZBX_IPC_HEADER_SIZE)
	{
		ret = SUCCEED;
	}
	else
		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ipc_socket_read                                              *
 *                                                                            *
 * Purpose: reads a message from IPC service                                  *
 *                                                                            *
 * Parameters: csocket - [IN] an opened IPC socket to the service             *
 *             message - [OUT] the received message                           *
 *                                                                            *
 * Return value: SUCCEED - the message was successfully received              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_ipc_socket_read(zbx_ipc_socket_t *csocket, zbx_ipc_message_t *message)
{
	const char	*__function_name = "zbx_ipc_socket_read";
	int		n, ret = FAIL;
	zbx_uint32_t	data_offset = 0, data_size;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (ZBX_IPC_HEADER_SIZE > data_offset)
	{
		n = read(csocket->fd, (char *)message->header + data_offset, ZBX_IPC_HEADER_SIZE - data_offset);

		if (0 == n)
			goto out;

		if (-1 == n)
		{
			if (EINTR == errno)
				continue;

			goto out;
		}

		data_offset += n;
	}

	data_offset = 0;
	data_size = message->header[ZBX_IPC_MESSAGE_SIZE];
	message->data = zbx_malloc(message->data, data_size);

	while (data_size > data_offset)
	{
		n = read(csocket->fd, message->data + data_offset, data_size - data_offset);

		if (0 == n)
			goto out;

		if (-1 == n)
		{
			if (EINTR == errno)
				continue;

			goto out;
		}

		data_offset += n;
	}

	if (SUCCEED == zabbix_check_log_level(LOG_LEVEL_TRACE))
	{
		char		*data = NULL;

		ipc_message_format(message, &data);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() %s", __function_name, data);

		zbx_free(data);
	}

	ret = SUCCEED;
out:

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ipc_message_free                                             *
 *                                                                            *
 * Purpose: frees the resources allocated to store IPC message data           *
 *                                                                            *
 * Parameters: message - [IN] the message to free                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_ipc_message_free(zbx_ipc_message_t *message)
{
	if (NULL != message)
	{
		zbx_free(message->data);
		zbx_free(message);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ipc_message_clean                                            *
 *                                                                            *
 * Purpose: frees the resources allocated to store IPC message data           *
 *                                                                            *
 * Parameters: message - [IN] the message to clean                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_ipc_message_clean(zbx_ipc_message_t *message)
{
	zbx_free(message->data);
}

/*
 * Public service API
 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_ipc_service_init_env                                         *
 *                                                                            *
 * Purpose: initializes IPC service environment                               *
 *                                                                            *
 * Parameters: path    - [IN] the service root path                           *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - the environment was initialized successfully.      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_ipc_service_init_env(const char *path, char **error)
{
	const char	*__function_name = "zbx_ipc_service_init_env";
	struct stat	fs;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() path:%s", __function_name, path);

	if (0 != ipc_path_root_len)
	{
		*error = zbx_dsprintf(*error, "The IPC service environment has been already initialized with"
				" root directory at \"%s\".", ipc_get_path());
		goto out;
	}

	if (0 != stat(path, &fs))
	{
		*error = zbx_dsprintf(*error, "Failed to stat the specified path \"%s\": %s.", path,
				zbx_strerror(errno));
		goto out;
	}

	if (0 == S_ISDIR(fs.st_mode))
	{
		*error = zbx_dsprintf(*error, "The specified path \"%s\" is not a directory.", path);
		goto out;
	}

	if (0 != access(path, W_OK | R_OK))
	{
		/* TODO: create service directory if necessary */
		*error = zbx_dsprintf(*error, "Cannot access path \"%s\": %s.", path, zbx_strerror(errno));
		goto out;
	}

	ipc_path_root_len = strlen(path);
	if (ZBX_IPC_PATH_MAX < ipc_path_root_len + 3)
	{
		*error = zbx_dsprintf(*error, "The IPC root path \"%s\" is too long.", path);
		goto out;
	}

	memcpy(ipc_path, path, ipc_path_root_len + 1);

	while (1 < ipc_path_root_len && '/' == ipc_path[ipc_path_root_len - 1])
		ipc_path[--ipc_path_root_len] = '\0';

	ipc_service_init_libevent();

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ipc_service_free_env                                         *
 *                                                                            *
 * Purpose: frees IPC service environment                                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_ipc_service_free_env()
{
	ipc_service_free_libevent();
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ipc_service_start                                            *
 *                                                                            *
 * Purpose: starts IPC service on the specified path                          *
 *                                                                            *
 * Parameters: service      - [IN/OUT] the IPC service                        *
 *             service_name - [IN] the unix domain socket path                *
 *             error        - [OUT] the error message                         *
 *                                                                            *
 * Return value: SUCCEED - the service was initialized successfully.          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_ipc_service_start(zbx_ipc_service_t *service, const char *service_name, char **error)
{
	const char		*__function_name = "zbx_ipc_service_start";
	struct sockaddr_un	addr;
	const char		*socket_path;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() service:%s", __function_name, service_name);

	if (NULL == (socket_path = ipc_make_path(service_name)))
	{
		*error = zbx_dsprintf(*error, "Invalid service name \"%s\".", service_name);
		goto out;
	}

	if (0 == access(socket_path, W_OK))
		unlink(socket_path);

	if (-1 == (service->fd = socket(AF_UNIX, SOCK_STREAM, 0)))
	{
		*error = zbx_dsprintf(*error, "Cannot create socket: %s.", zbx_strerror(errno));
		goto out;
	}

	memset(&addr, 0, sizeof(addr));
	addr.sun_family = AF_UNIX;

	memcpy(addr.sun_path, socket_path, sizeof(addr.sun_path));
	if (0 != bind(service->fd, (struct sockaddr*)&addr, sizeof(addr)))
	{
		*error = zbx_dsprintf(*error, "Cannot bind socket to \"%s\": %s.", socket_path, zbx_strerror(errno));
		goto out;
	}

	if (0 != listen(service->fd, SOMAXCONN))
	{
		*error = zbx_dsprintf(*error, "Cannot listen socket: %s.", zbx_strerror(errno));
		goto out;
	}

	service->path = zbx_strdup(NULL, service_name);
	zbx_vector_ptr_create(&service->clients);
	zbx_queue_ptr_create(&service->clients_recv);

	service->ev = event_base_new();
	service->ev_listener = event_new(service->ev, service->fd, EV_READ | EV_PERSIST,
			ipc_service_client_connected_cb, service);
	event_add(service->ev_listener, NULL);

	service->client_remove = NULL;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ipc_service_close                                            *
 *                                                                            *
 * Purpose: closes IPC service and frees the resources allocated by it        *
 *                                                                            *
 * Parameters: service - [IN/OUT] the IPC service                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_ipc_service_close(zbx_ipc_service_t *service)
{
	const char	*__function_name = "zbx_ipc_service_close";
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() path:%s", __function_name, service->path);

	close(service->fd);

	for (i = 0; i < service->clients.values_num; i++)
		ipc_client_free(service->clients.values[i]);

	zbx_free(service->path);

	zbx_vector_ptr_destroy(&service->clients);
	zbx_queue_ptr_destroy(&service->clients_recv);

	event_free(service->ev_listener);
	event_base_free(service->ev);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ipc_service_recv                                             *
 *                                                                            *
 * Purpose: receives ipc message from a connected client                      *
 *                                                                            *
 * Parameters: service - [IN] the IPC service                                 *
 *             csocket - [IN] the client socket. Used to send response back   *
 *                            to the client. NULL if there are no new         *
 *                            messages.                                       *
 *             message - [IN] the received message or NULL if the client      *
 *                            connection was closed.                          *
 *                            The message must be freed by caller with        *
 *                            ipc_message_free() function.                    *
 *                                                                            *
 *****************************************************************************/
void	zbx_ipc_service_recv(zbx_ipc_service_t *service, zbx_ipc_client_t **client, zbx_ipc_message_t **message)
{
	const char	*__function_name = "zbx_ipc_service_recv";

	int			ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL != service->client_remove)
	{
		ipc_service_remove_client(service, service->client_remove);
		service->client_remove = NULL;
	}

	event_base_loop(service->ev, EVLOOP_NONBLOCK);

	if (NULL != (*client = ipc_service_pop_client(service)))
	{
		if (NULL != (*message = zbx_queue_ptr_pop(&(*client)->rx_queue)))
		{
			if (SUCCEED == zabbix_check_log_level(LOG_LEVEL_TRACE))
			{
				char		*data = NULL;

				ipc_message_format(*message, &data);
				zabbix_log(LOG_LEVEL_DEBUG, "%s() %s", __function_name, data);

				zbx_free(data);
			}
		}
		else
			service->client_remove = *client;

		ipc_service_push_client(service, *client);
	}
	else
	{
		*client = NULL;
		*message = NULL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ipc_client_send                                              *
 *                                                                            *
 * Purpose: Sends IPC message to client                                       *
 *                                                                            *
 * Parameters: client - [IN] the IPC client                                   *
 *             code   - [IN] the message code                                 *
 *             data   - [IN] the data                                         *
 *             size   - [IN] the data size                                    *
 *                                                                            *
 *****************************************************************************/
int	zbx_ipc_client_send(zbx_ipc_client_t *client, zbx_uint32_t code, const char *data, zbx_uint32_t size)
{
	zbx_uint32_t		tx_size = 0;
	zbx_ipc_message_t	*message;

	if (NULL != client->tx_message)
	{
		message = ipc_message_create(code, data, size);
		zbx_queue_ptr_push(&client->tx_queue, message);
		return SUCCEED;
	}

	if (FAIL == ipc_socket_write_message(&client->csocket, code, data, size, &tx_size))
		return FAIL;

	if (tx_size == ZBX_IPC_HEADER_SIZE + size)
		return SUCCEED;

	client->tx_message = ipc_message_create(code, data, size);
	client->tx_bytes = tx_size;
	event_add(client->tx_event, NULL);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ipc_service_close_socket                                     *
 *                                                                            *
 * Purpose: closes client socket and frees resources allocated for client     *
 *                                                                            *
 * Parameters: service - [IN] the IPC service                                 *
 *             csocket - [IN] the client socket.                              *
 *                                                                            *
 *****************************************************************************/
void	zbx_ipc_client_close(zbx_ipc_client_t *client)
{
	const char	*__function_name = "zbx_ipc_client_close";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ipc_service_remove_client(client->service, client);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

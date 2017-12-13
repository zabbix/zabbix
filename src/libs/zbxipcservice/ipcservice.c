#include "common.h"

#ifdef HAVE_IPCSERVICE

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

extern unsigned char	program_type;

struct zbx_ipc_client
{
	zbx_ipc_socket_t	csocket;
	zbx_ipc_service_t	*service;

	zbx_uint32_t		rx_header[2];
	unsigned char		*rx_data;
	zbx_uint32_t		rx_bytes;
	zbx_queue_ptr_t		rx_queue;
	struct event		*rx_event;

	zbx_uint32_t		tx_header[2];
	unsigned char		*tx_data;
	zbx_uint32_t		tx_bytes;
	zbx_queue_ptr_t		tx_queue;
	struct event		*tx_event;

	zbx_uint64_t		id;
	unsigned char		state;

	zbx_uint32_t		refcount;
};

/*
 * Private API
 */

#define ZBX_IPC_HEADER_SIZE	(int)(sizeof(zbx_uint32_t) * 2)

#define ZBX_IPC_MESSAGE_CODE	0
#define ZBX_IPC_MESSAGE_SIZE	1

#if !defined(LIBEVENT_VERSION_NUMBER) || LIBEVENT_VERSION_NUMBER < 0x2000000
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

static const char	*ipc_get_path(void)
{
	ipc_path[ipc_path_root_len] = '\0';

	return ipc_path;
}

#define ZBX_IPC_SOCKET_PREFIX	"/zabbix_"
#define ZBX_IPC_SOCKET_SUFFIX	".sock"

#define ZBX_IPC_CLASS_PREFIX_NONE	""
#define ZBX_IPC_CLASS_PREFIX_SERVER	"server_"
#define ZBX_IPC_CLASS_PREFIX_PROXY	"proxy_"
#define ZBX_IPC_CLASS_PREFIX_AGENT	"agent_"

/******************************************************************************
 *                                                                            *
 * Function: ipc_make_path                                                    *
 *                                                                            *
 * Purpose: makes socket path from the service name                           *
 *                                                                            *
 * Parameters: service_name - [IN] the service name                           *
 *             error        - [OUT] the error message                         *
 *                                                                            *
 * Return value: The created path or NULL if the path exceeds unix domain     *
 *               socket path maximum length                                   *
 *                                                                            *
 ******************************************************************************/
static const char	*ipc_make_path(const char *service_name, char **error)
{
	const char	*prefix;
	size_t		path_len, offset, prefix_len;

	path_len = strlen(service_name);

	switch (program_type)
	{
		case ZBX_PROGRAM_TYPE_SERVER:
			prefix = ZBX_IPC_CLASS_PREFIX_SERVER;
			prefix_len = ZBX_CONST_STRLEN(ZBX_IPC_CLASS_PREFIX_SERVER);
			break;
		case ZBX_PROGRAM_TYPE_PROXY_ACTIVE:
		case ZBX_PROGRAM_TYPE_PROXY_PASSIVE:
			prefix = ZBX_IPC_CLASS_PREFIX_PROXY;
			prefix_len = ZBX_CONST_STRLEN(ZBX_IPC_CLASS_PREFIX_PROXY);
			break;
		case ZBX_PROGRAM_TYPE_AGENTD:
			prefix = ZBX_IPC_CLASS_PREFIX_AGENT;
			prefix_len = ZBX_CONST_STRLEN(ZBX_IPC_CLASS_PREFIX_AGENT);
			break;
		default:
			prefix = ZBX_IPC_CLASS_PREFIX_NONE;
			prefix_len = ZBX_CONST_STRLEN(ZBX_IPC_CLASS_PREFIX_NONE);
			break;
	}

	if (ZBX_IPC_PATH_MAX < ipc_path_root_len + path_len + 1 + ZBX_CONST_STRLEN(ZBX_IPC_SOCKET_PREFIX) +
			ZBX_CONST_STRLEN(ZBX_IPC_SOCKET_SUFFIX) + prefix_len)
	{
		*error = zbx_dsprintf(*error,
				"Socket path \"%s%s%s%s%s\" exceeds maximum length of unix domain socket path.",
				ipc_path, ZBX_IPC_SOCKET_PREFIX, prefix, service_name, ZBX_IPC_SOCKET_SUFFIX);
		return NULL;
	}

	offset = ipc_path_root_len;
	memcpy(ipc_path + offset , ZBX_IPC_SOCKET_PREFIX, ZBX_CONST_STRLEN(ZBX_IPC_SOCKET_PREFIX));
	offset += ZBX_CONST_STRLEN(ZBX_IPC_SOCKET_PREFIX);
	memcpy(ipc_path + offset, prefix, prefix_len);
	offset += prefix_len;
	memcpy(ipc_path + offset, service_name, path_len);
	offset += path_len;
	memcpy(ipc_path + offset, ZBX_IPC_SOCKET_SUFFIX, ZBX_CONST_STRLEN(ZBX_IPC_SOCKET_SUFFIX) + 1);

	return ipc_path;
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_write_data                                                   *
 *                                                                            *
 * Purpose: writes data to a socket                                           *
 *                                                                            *
 * Parameters: fd        - [IN] the socket file descriptor                    *
 *             data      - [IN] the data                                      *
 *             size      - [IN] the data size                                 *
 *             size_sent - [IN] the actual size written to socket             *
 *                                                                            *
 * Return value: SUCCEED - no socket errors were detected. Either the data or *
 *                         a part of it was written to socket or a write to   *
 *                         non-blocking socket would block                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	ipc_write_data(int fd, const unsigned char *data, zbx_uint32_t size, zbx_uint32_t *size_sent)
{
	zbx_uint32_t	offset = 0;
	int		n, ret = SUCCEED;

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
		offset += n;
	}

	*size_sent = offset;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_read_data                                                    *
 *                                                                            *
 * Purpose: reads data from a socket                                          *
 *                                                                            *
 * Parameters: fd        - [IN] the socket file descriptor                    *
 *             data      - [IN] the data                                      *
 *             size      - [IN] the data size                                 *
 *             size_sent - [IN] the actual size read from socket              *
 *                                                                            *
 * Return value: SUCCEED - the data was successfully read                     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: When reading data from non-blocking sockets SUCCEED will be      *
 *           returned also if there were no more data to read.                *
 *                                                                            *
 ******************************************************************************/
static int	ipc_read_data(int fd, unsigned char *buffer, zbx_uint32_t size, zbx_uint32_t *read_size)
{
	int	n;

	*read_size = 0;

	while (-1 == (n = read(fd, buffer + *read_size, size - *read_size)))
	{
		if (EINTR == errno)
			continue;

		if (EWOULDBLOCK == errno || EAGAIN == errno)
			return SUCCEED;

		return FAIL;
	}

	if (0 == n)
		return FAIL;

	*read_size += n;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_read_data_full                                               *
 *                                                                            *
 * Purpose: reads data from a socket until the requested data has been read   *
 *                                                                            *
 * Parameters: fd        - [IN] the socket file descriptor                    *
 *             buffer    - [IN] the data                                      *
 *             size      - [IN] the data size                                 *
 *             read_size - [IN] the actual size read from socket              *
 *                                                                            *
 * Return value: SUCCEED - the data was successfully read                     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: When reading data from non-blocking sockets this function will   *
 *           return SUCCEED if there are no data to read, even if not all of  *
 *           the requested data has been read.                                *
 *                                                                            *
 ******************************************************************************/
static int	ipc_read_data_full(int fd, unsigned char *buffer, zbx_uint32_t size, zbx_uint32_t *read_size)
{
	int		ret = FAIL;
	zbx_uint32_t	offset = 0, chunk_size;

	*read_size = 0;

	while (offset < size)
	{
		if (FAIL == ipc_read_data(fd, buffer + offset, size - offset, &chunk_size))
			goto out;

		if (0 == chunk_size)
			break;

		offset += chunk_size;
	}

	ret = SUCCEED;
out:
	*read_size = offset;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_socket_write_message                                         *
 *                                                                            *
 * Purpose: writes IPC message to socket                                      *
 *                                                                            *
 * Parameters: csocket - [IN] the IPC socket                                  *
 *             code    - [IN] the message code                                *
 *             data    - [IN] the data                                        *
 *             size    - [IN] the data size                                   *
 *             tx_size - [IN] the actual size written to socket               *
 *                                                                            *
 * Return value: SUCCEED - no socket errors were detected. Either the data or *
 *                         a part of it was written to socket or a write to   *
 *                         non-blocking socket would block                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: When using non-blocking sockets the tx_size parameter must be    *
 *           checked in addition to return value to tell if the message was   *
 *           sent successfully.                                               *
 *                                                                            *
 ******************************************************************************/
static int	ipc_socket_write_message(zbx_ipc_socket_t *csocket, zbx_uint32_t code, const unsigned char *data,
		zbx_uint32_t size, zbx_uint32_t *tx_size)
{
	int		ret;
	zbx_uint32_t	size_data, buffer[ZBX_IPC_SOCKET_BUFFER_SIZE / sizeof(zbx_uint32_t)];

	buffer[0] = code;
	buffer[1] = size;

	if (ZBX_IPC_SOCKET_BUFFER_SIZE - ZBX_IPC_HEADER_SIZE >= size)
	{
		if (0 != size)
			memcpy(buffer + 2, data, size);

		return ipc_write_data(csocket->fd, (unsigned char *)buffer, size + ZBX_IPC_HEADER_SIZE, tx_size);
	}

	if (FAIL == ipc_write_data(csocket->fd, (unsigned char *)buffer, ZBX_IPC_HEADER_SIZE, tx_size))
		return FAIL;

	/* in the case of non-blocking sockets only a part of the header might be sent */
	if (ZBX_IPC_HEADER_SIZE != *tx_size)
		return SUCCEED;

	ret = ipc_write_data(csocket->fd, data, size, &size_data);
	*tx_size += size_data;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_read_buffer                                                  *
 *                                                                            *
 * Purpose: reads message header and data from buffer                         *
 *                                                                            *
 * Parameters: header      - [IN/OUT] the message header                      *
 *             data        - [OUT] the message data                           *
 *             rx_bytes    - [IN] the number of bytes stored in message       *
 *                                (including header)                          *
 *             buffer      - [IN] the buffer to parse                         *
 *             size        - [IN] the number of bytes to parse                *
 *             read_size   - [OUT] the number of bytes read                   *
 *                                                                            *
 * Return value: SUCCEED - message was successfully parsed                    *
 *               FAIL - not enough data                                       *
 *                                                                            *
 ******************************************************************************/
static int	ipc_read_buffer(zbx_uint32_t *header, unsigned char **data, zbx_uint32_t rx_bytes,
		const unsigned char *buffer, zbx_uint32_t size, zbx_uint32_t *read_size)
{
	zbx_uint32_t	copy_size, data_size, data_offset;

	*read_size = 0;

	if (ZBX_IPC_HEADER_SIZE > rx_bytes)
	{
		copy_size = MIN(ZBX_IPC_HEADER_SIZE - rx_bytes, size);
		memcpy((char *)header + rx_bytes, buffer, copy_size);
		*read_size += copy_size;

		if (ZBX_IPC_HEADER_SIZE > rx_bytes + copy_size)
			return FAIL;

		data_size = header[ZBX_IPC_MESSAGE_SIZE];

		if (0 == data_size)
		{
			*data = NULL;
			return SUCCEED;
		}

		*data = zbx_malloc(NULL, data_size);
		data_offset = 0;
	}
	else
	{
		data_size = header[ZBX_IPC_MESSAGE_SIZE];
		data_offset = rx_bytes - ZBX_IPC_HEADER_SIZE;
	}

	copy_size = MIN(data_size - data_offset, size - *read_size);
	memcpy(*data + data_offset, buffer + *read_size, copy_size);
	*read_size += copy_size;

	return (rx_bytes + *read_size == data_size + ZBX_IPC_HEADER_SIZE ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_message_is_completed                                         *
 *                                                                            *
 * Purpose: checks if IPC message has been completed                          *
 *                                                                            *
 * Parameters: header   - [IN] the message header                             *
 *             rx_bytes - [IN] the number of bytes set in message             *
 *                             (including header)                             *
 *                                                                            *
 * Return value:  SUCCEED - message has been completed                        *
 *                FAIL - otherwise                                            *
 *                                                                            *
 ******************************************************************************/
static int	ipc_message_is_completed(const zbx_uint32_t *header, zbx_uint32_t rx_bytes)
{
	if (ZBX_IPC_HEADER_SIZE > rx_bytes)
		return FAIL;

	if (header[ZBX_IPC_MESSAGE_SIZE] + ZBX_IPC_HEADER_SIZE != rx_bytes)
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_socket_read_message                                          *
 *                                                                            *
 * Purpose: reads IPC message from buffered client socket                     *
 *                                                                            *
 * Parameters: csocket  - [IN] the source socket                              *
 *             header   - [OUT] the header of the message                     *
 *             data     - [OUT] the data of the message                       *
 *             rx_bytes - [IN/OUT] the total message size read (including     *
 *                                 header                                     *
 *                                                                            *
 * Return value:  SUCCEED - data was read successfully, check rx_bytes to     *
 *                          determine if the message was completed.           *
 *                FAIL - failed to read message (socket error or connection   *
 *                       was closed).                                         *
 *                                                                            *
 ******************************************************************************/
static int	ipc_socket_read_message(zbx_ipc_socket_t *csocket, zbx_uint32_t *header, unsigned char **data,
		zbx_uint32_t *rx_bytes)
{
	zbx_uint32_t	data_size, offset, read_size = 0;
	int		ret = FAIL;

	/* try to read message from socket buffer */
	if (csocket->rx_buffer_bytes > csocket->rx_buffer_offset)
	{
		ret = ipc_read_buffer(header, data, *rx_bytes, csocket->rx_buffer + csocket->rx_buffer_offset,
				csocket->rx_buffer_bytes - csocket->rx_buffer_offset, &read_size);

		csocket->rx_buffer_offset += read_size;
		*rx_bytes += read_size;

		if (SUCCEED == ret)
			goto out;
	}

	/* not enough data in socket buffer, try to read more until message is completed or no data to read */
	while (SUCCEED != ret)
	{
		csocket->rx_buffer_offset = 0;
		csocket->rx_buffer_bytes = 0;

		if (ZBX_IPC_HEADER_SIZE < *rx_bytes)
		{
			offset = *rx_bytes - ZBX_IPC_HEADER_SIZE;
			data_size = header[ZBX_IPC_MESSAGE_SIZE] - offset;

			/* long messages will be read directly into message buffer */
			if (ZBX_IPC_SOCKET_BUFFER_SIZE * 0.75 < data_size)
			{
				ret = ipc_read_data_full(csocket->fd, *data + offset, data_size, &read_size);
				*rx_bytes += read_size;
				goto out;
			}
		}

		if (FAIL == ipc_read_data(csocket->fd, csocket->rx_buffer, ZBX_IPC_SOCKET_BUFFER_SIZE, &read_size))
			goto out;

		/* it's possible that nothing will be read on non-blocking sockets, return success */
		if (0 == read_size)
		{
			ret = SUCCEED;
			goto out;
		}

		csocket->rx_buffer_bytes = read_size;

		ret = ipc_read_buffer(header, data, *rx_bytes, csocket->rx_buffer, csocket->rx_buffer_bytes,
				&read_size);

		csocket->rx_buffer_offset += read_size;
		*rx_bytes += read_size;
	}
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_client_free_event                                            *
 *                                                                            *
 * Purpose: frees client's libevent event                                     *
 *                                                                            *
 * Parameters: client - [IN] the client                                       *
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
	zbx_free(client->rx_data);

	while (NULL != (message = zbx_queue_ptr_pop(&client->tx_queue)))
		zbx_ipc_message_free(message);

	zbx_queue_ptr_destroy(&client->tx_queue);
	zbx_free(client->tx_data);

	ipc_client_free_events(client);

	zbx_free(client);
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
	zbx_ipc_message_t	*message;

	message = (zbx_ipc_message_t *)zbx_malloc(NULL, sizeof(zbx_ipc_message_t));
	message->code = client->rx_header[ZBX_IPC_MESSAGE_CODE];
	message->size = client->rx_header[ZBX_IPC_MESSAGE_SIZE];
	message->data = client->rx_data;
	zbx_queue_ptr_push(&client->rx_queue, message);

	client->rx_data = NULL;
	client->rx_bytes = 0;
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_client_pop_tx_message                                        *
 *                                                                            *
 * Purpose: prepares to send the next message in send queue                   *
 *                                                                            *
 * Parameters: client - [IN] the client                                       *
 *                                                                            *
 ******************************************************************************/
static void	ipc_client_pop_tx_message(zbx_ipc_client_t *client)
{
	zbx_ipc_message_t	*message;

	zbx_free(client->tx_data);
	client->tx_bytes = 0;

	if (NULL == (message = zbx_queue_ptr_pop(&client->tx_queue)))
		return;

	client->tx_bytes = ZBX_IPC_HEADER_SIZE + message->size;
	client->tx_header[ZBX_IPC_MESSAGE_CODE] = message->code;
	client->tx_header[ZBX_IPC_MESSAGE_SIZE] = message->size;
	client->tx_data = message->data;
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
	int	rc;

	do
	{
		if (FAIL == ipc_socket_read_message(&client->csocket, client->rx_header, &client->rx_data,
				&client->rx_bytes))
		{
			zbx_free(client->rx_data);
			client->rx_bytes = 0;
			return FAIL;
		}

		if (SUCCEED == (rc = ipc_message_is_completed(client->rx_header, client->rx_bytes)))
			ipc_client_push_rx_message(client);
	}

	while (SUCCEED == rc);

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
 * Return value: SUCCEED - the data was sent successfully                     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	ipc_client_write(zbx_ipc_client_t *client)
{
	zbx_uint32_t	data_size, write_size;

	data_size = client->tx_header[ZBX_IPC_MESSAGE_SIZE];

	if (data_size < client->tx_bytes)
	{
		zbx_uint32_t	size, offset;

		size = client->tx_bytes - data_size;
		offset = ZBX_IPC_HEADER_SIZE - size;

		if (SUCCEED != ipc_write_data(client->csocket.fd, (unsigned char *)client->tx_header + offset, size,
				&write_size))
		{
			return FAIL;
		}

		client->tx_bytes -= write_size;

		if (data_size < client->tx_bytes)
			return SUCCEED;
	}

	while (0 < client->tx_bytes)
	{
		if (SUCCEED != ipc_write_data(client->csocket.fd, client->tx_data + data_size - client->tx_bytes,
				client->tx_bytes, &write_size))
		{
			return FAIL;
		}

		if (0 == write_size)
			return SUCCEED;

		client->tx_bytes -= write_size;
	}

	if (0 == client->tx_bytes)
		ipc_client_pop_tx_message(client);

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

	if (NULL != (client = (zbx_ipc_client_t *)zbx_queue_ptr_pop(&service->clients_recv)))
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
	static zbx_uint64_t	next_clientid = 1;
	zbx_ipc_client_t	*client;
	int			flags;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	client = (zbx_ipc_client_t *)zbx_malloc(NULL, sizeof(zbx_ipc_client_t));
	memset(client, 0, sizeof(zbx_ipc_client_t));

	if (-1 == (flags = fcntl(fd, F_GETFL, 0)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot get IPC client socket flags");
		exit(EXIT_FAILURE);
	}

	if (-1 == fcntl(fd, F_SETFL, flags | O_NONBLOCK))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot set non-blocking mode for IPC client socket");
		exit(EXIT_FAILURE);
	}

	client->csocket.fd = fd;
	client->csocket.rx_buffer_bytes = 0;
	client->csocket.rx_buffer_offset = 0;
	client->id = next_clientid++;
	client->state = ZBX_IPC_CLIENT_STATE_NONE;
	client->refcount = 1;

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
 * Purpose: removes IPC service client                                        *
 *                                                                            *
 * Parameters: service - [IN] the IPC service                                 *
 *             client  - [IN] the client to remove                            *
 *                                                                            *
 ******************************************************************************/
static void	ipc_service_remove_client(zbx_ipc_service_t *service, zbx_ipc_client_t *client)
{
	int		i;

	for (i = 0; i < service->clients.values_num; i++)
	{
		if (service->clients.values[i] == client)
			zbx_vector_ptr_remove_noorder(&service->clients, i);
	}
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

	ZBX_UNUSED(fd);
	ZBX_UNUSED(what);

	if (SUCCEED != ipc_client_read(client))
	{
		ipc_client_free_events(client);
		ipc_service_remove_client(client->service, client);
	}

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

	ZBX_UNUSED(fd);
	ZBX_UNUSED(what);

	if (SUCCEED != ipc_client_write(client))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send data to IPC client");
		ipc_client_free_events(client);
		zbx_ipc_client_close(client);
		return;
	}

	if (0 == client->tx_bytes)
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
static void	ipc_service_accept(zbx_ipc_service_t *service)
{
	const char	*__function_name = "ipc_service_accept";
	int		fd;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (-1 == (fd = accept(service->fd, NULL, NULL)))
	{
		if (EINTR != errno)
		{
			/* If there is unaccepted connection libevent will call registered callback function over and */
			/* over again. It is better to exit straight away and cause all other processes to stop. */
			zabbix_log(LOG_LEVEL_CRIT, "cannot accept incoming IPC connection: %s", zbx_strerror(errno));
			exit(EXIT_FAILURE);
		}
	}

	ipc_service_add_client(service, fd);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
static zbx_ipc_message_t	*ipc_message_create(zbx_uint32_t code, const unsigned char *data, zbx_uint32_t size)
{
	zbx_ipc_message_t	*message;

	message = (zbx_ipc_message_t *)zbx_malloc(NULL, sizeof(zbx_ipc_message_t));

	message->code = code;
	message->size = size;

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
static void	ipc_service_init_libevent(void)
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
static void	ipc_service_free_libevent(void)
{
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_service_client_connected_cb                                  *
 *                                                                            *
 * Purpose: libevent listener callback                                        *
 *                                                                            *
 ******************************************************************************/
static void	ipc_service_client_connected_cb(evutil_socket_t fd, short what, void *arg)
{
	zbx_ipc_service_t	*service = (zbx_ipc_service_t *)arg;

	ZBX_UNUSED(fd);
	ZBX_UNUSED(what);

	ipc_service_accept(service);
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_service_timer_cb                                             *
 *                                                                            *
 * Purpose: timer callback                                                    *
 *                                                                            *
 ******************************************************************************/
static void	ipc_service_timer_cb(evutil_socket_t fd, short what, void *arg)
{
	ZBX_UNUSED(fd);
	ZBX_UNUSED(what);
	ZBX_UNUSED(arg);
}

/******************************************************************************
 *                                                                            *
 * Function: ipc_check_running_service                                        *
 *                                                                            *
 * Purpose: checks if an IPC service is already running                       *
 *                                                                            *
 * Parameters: service_name - [IN]                                            *
 *                                                                            *
 ******************************************************************************/
static int	ipc_check_running_service(const char *service_name)
{
	zbx_ipc_socket_t	csocket;
	int			ret;
	char			*error = NULL;

	if (SUCCEED == (ret = zbx_ipc_socket_open(&csocket, service_name, 0, &error)))
		zbx_ipc_socket_close(&csocket);
	else
		zbx_free(error);

	return ret;
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

	if (NULL == (socket_path = ipc_make_path(service_name, error)))
		goto out;

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

	csocket->rx_buffer_bytes = 0;
	csocket->rx_buffer_offset = 0;

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
	const char	*__function_name = "zbx_ipc_socket_close";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	close(csocket->fd);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
int	zbx_ipc_socket_write(zbx_ipc_socket_t *csocket, zbx_uint32_t code, const unsigned char *data, zbx_uint32_t size)
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
 * Comments: If this function succeeds the message must be cleaned/freed by   *
 *           the caller.                                                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_ipc_socket_read(zbx_ipc_socket_t *csocket, zbx_ipc_message_t *message)
{
	const char	*__function_name = "zbx_ipc_socket_read";
	int		ret = FAIL;
	zbx_uint32_t	rx_bytes = 0, header[2];
	unsigned char	*data = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != ipc_socket_read_message(csocket, header, &data, &rx_bytes))
		goto out;

	if (SUCCEED != ipc_message_is_completed(header, rx_bytes))
	{
		zbx_free(data);
		goto out;
	}

	message->code = header[ZBX_IPC_MESSAGE_CODE];
	message->size = header[ZBX_IPC_MESSAGE_SIZE];
	message->data = data;

	if (SUCCEED == zabbix_check_log_level(LOG_LEVEL_TRACE))
	{
		char	*msg = NULL;

		zbx_ipc_message_format(message, &msg);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() %s", __function_name, msg);

		zbx_free(msg);
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

/******************************************************************************
 *                                                                            *
 * Function: zbx_ipc_message_init                                             *
 *                                                                            *
 * Purpose: initializes IPC message                                           *
 *                                                                            *
 * Parameters: message - [IN] the message to initialize                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_ipc_message_init(zbx_ipc_message_t *message)
{
	memset(message, 0, sizeof(zbx_ipc_message_t));
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ipc_message_format                                           *
 *                                                                            *
 * Purpose: formats message to readable format for debug messages             *
 *                                                                            *
 * Parameters: message - [IN] the message                                     *
 *             data    - [OUT] the formatted message                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_ipc_message_format(const zbx_ipc_message_t *message, char **data)
{
	size_t		data_alloc = ZBX_IPC_DATA_DUMP_SIZE * 4 + 32, data_offset = 0;
	zbx_uint32_t	i, data_num;

	if (NULL == message)
		return;

	data_num = message->size;

	if (ZBX_IPC_DATA_DUMP_SIZE < data_num)
		data_num = ZBX_IPC_DATA_DUMP_SIZE;

	*data = zbx_malloc(*data, data_alloc);
	zbx_snprintf_alloc(data, &data_alloc, &data_offset, "code:%u size:%u data:", message->code, message->size);

	for (i = 0; i < data_num; i++)
	{
		if (0 != i)
			zbx_strcpy_alloc(data, &data_alloc, &data_offset, (0 == (i & 7) ? " | " : " "));

		zbx_snprintf_alloc(data, &data_alloc, &data_offset, "%02x", (int)message->data[i]);
	}

	(*data)[data_offset] = '\0';
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ipc_message_copy                                             *
 *                                                                            *
 * Purpose: copies ipc message                                                *
 *                                                                            *
 * Parameters: dst - [IN] the destination message                             *
 *             src - [IN] the source message                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_ipc_message_copy(zbx_ipc_message_t *dst, const zbx_ipc_message_t *src)
{
	dst->code = src->code;
	dst->size = src->size;
	dst->data = zbx_malloc(NULL, src->size);
	memcpy(dst->data, src->data, src->size);
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
void	zbx_ipc_service_free_env(void)
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
	mode_t			mode;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() service:%s", __function_name, service_name);

	mode = umask(077);

	if (NULL == (socket_path = ipc_make_path(service_name, error)))
		goto out;

	if (0 == access(socket_path, F_OK))
	{
		if (0 != access(socket_path, W_OK))
		{
			*error = zbx_dsprintf(*error, "The file \"%s\" is used by another process.", socket_path);
			goto out;
		}

		if (SUCCEED == ipc_check_running_service(service_name))
		{
			*error = zbx_dsprintf(*error, "\"%s\" service is already running.", service_name);
			goto out;
		}

		unlink(socket_path);
	}

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

	service->ev_timer = event_new(service->ev, -1, 0, ipc_service_timer_cb, service);

	ret = SUCCEED;
out:
	umask(mode);

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

	event_free(service->ev_timer);
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
 *             timeout - [IN] the timeout                                     *
 *             client  - [OUT] the client that sent the message or            *
 *                             NULL if there are no messages and the          *
 *                             specified timeout passed.                      *
 *                             The client must be released by caller with     *
 *                             zbx_ipc_client_release() function.             *
 *             message - [OUT] the received message or NULL if the client     *
 *                             connection was closed.                         *
 *                             The message must be freed by caller with       *
 *                             ipc_message_free() function.                   *
 *                                                                            *
 * Return value: ZBX_IPC_RECV_IMMEDIATE - returned immediately without        *
 *                                        waiting for socket events           *
 *                                        (pending events are processed)      *
 *               ZBX_IPC_RECV_WAIT      - returned after receiving socket     *
 *                                        event                               *
 *               ZBX_IPC_RECV_TIMEOUT   - returned after timeout expired      *
 *                                                                            *
 ******************************************************************************/
int	zbx_ipc_service_recv(zbx_ipc_service_t *service, int timeout, zbx_ipc_client_t **client,
		zbx_ipc_message_t **message)
{
	const char	*__function_name = "zbx_ipc_service_recv";

	int		ret, flags;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() timeout:%d", __function_name, timeout);

	if (timeout != 0 && SUCCEED == zbx_queue_ptr_empty(&service->clients_recv))
	{
		struct timeval	tv = {timeout, 0};

		evtimer_add(service->ev_timer, &tv);
		flags = EVLOOP_ONCE;
	}
	else
		flags = EVLOOP_NONBLOCK;

	event_base_loop(service->ev, flags);

	if (NULL != (*client = ipc_service_pop_client(service)))
	{
		if (NULL != (*message = zbx_queue_ptr_pop(&(*client)->rx_queue)))
		{
			if (SUCCEED == zabbix_check_log_level(LOG_LEVEL_TRACE))
			{
				char	*data = NULL;

				zbx_ipc_message_format(*message, &data);
				zabbix_log(LOG_LEVEL_DEBUG, "%s() %s", __function_name, data);

				zbx_free(data);
			}

			ipc_service_push_client(service, *client);
			zbx_ipc_client_addref(*client);
		}

		ret = (EVLOOP_NONBLOCK == flags ? ZBX_IPC_RECV_IMMEDIATE : ZBX_IPC_RECV_WAIT);
	}
	else
	{	ret = ZBX_IPC_RECV_TIMEOUT;
		*client = NULL;
		*message = NULL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, ret);

	return ret;
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
 * Comments: If data can't be written directly to socket (buffer full) then   *
 *           the message is queued and sent during zbx_ipc_service_recv()     *
 *           messaging loop whenever socket becomes ready.                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_ipc_client_send(zbx_ipc_client_t *client, zbx_uint32_t code, const unsigned char *data, zbx_uint32_t size)
{
	const char		*__function_name = "zbx_ipc_client_send";
	zbx_uint32_t		tx_size = 0;
	zbx_ipc_message_t	*message;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() clientid:" ZBX_FS_UI64, __function_name, client->id);

	if (0 != client->tx_bytes)
	{
		message = ipc_message_create(code, data, size);
		zbx_queue_ptr_push(&client->tx_queue, message);
		ret = SUCCEED;
		goto out;
	}

	if (FAIL == ipc_socket_write_message(&client->csocket, code, data, size, &tx_size))
		goto out;

	if (tx_size != ZBX_IPC_HEADER_SIZE + size)
	{
		client->tx_header[ZBX_IPC_MESSAGE_CODE] = code;
		client->tx_header[ZBX_IPC_MESSAGE_SIZE] = size;
		client->tx_data = zbx_malloc(NULL, size);
		memcpy(client->tx_data, data, size);
		client->tx_bytes = ZBX_IPC_HEADER_SIZE + size - tx_size;
		event_add(client->tx_event, NULL);
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ipc_client_close                                             *
 *                                                                            *
 * Purpose: closes client socket and frees resources allocated for client     *
 *                                                                            *
 * Parameters: csocket - [IN] the client socket.                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_ipc_client_close(zbx_ipc_client_t *client)
{
	const char	*__function_name = "zbx_ipc_client_close";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_ipc_socket_close(&client->csocket);
	ipc_service_remove_client(client->service, client);
	zbx_queue_ptr_remove_value(&client->service->clients_recv, client);
	zbx_ipc_client_release(client);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

void	zbx_ipc_client_addref(zbx_ipc_client_t *client)
{
	client->refcount++;
}

void	zbx_ipc_client_release(zbx_ipc_client_t *client)
{
	if (0 == --client->refcount)
		ipc_client_free(client);
}

int	zbx_ipc_client_connected(zbx_ipc_client_t *client)
{
	return (NULL == client->rx_event ? FAIL : SUCCEED);
}

#endif

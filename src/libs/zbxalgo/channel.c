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

#include "zbxalgo.h"
#include "zbxcommon.h"
#include "zbxtime.h"

/******************************************************************************
 *                                                                            *
 * Purpose: grow channel capacity by 1.5x factor                              *
 *                                                                            *
 * Parameters: chan - [IN/OUT] channel to resize                              *
 *                                                                            *
 ******************************************************************************/
static void	chan_reserve(zbx_channel_t *chan, int msg_num)
{
#define GROWTH_FACTOR	1.5

	int	old_capacity = chan->capacity;

	while (chan->capacity < chan->msg_num + msg_num)
		chan->capacity = (int)((chan->capacity + 1) * GROWTH_FACTOR);

	chan->msgs = zbx_realloc(chan->msgs, (size_t)(chan->msg_size * chan->capacity));

	if (0 != chan->msg_num && chan->head >= chan->tail)
	{
		int		move_num = old_capacity - chan->head;
		size_t		move_size = (size_t)(move_num * chan->msg_size);
		unsigned char	*src = chan->msgs + (chan->head * chan->msg_size);
		unsigned char	*dst = chan->msgs + ((chan->capacity - move_num) * chan->msg_size);

		memmove(dst, src, move_size);
		chan->head = chan->capacity - move_num;
	}

#undef GROWTH_FACTOR
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize a dynamic message channel for inter-thread             *
 *          communication                                                     *
 *                                                                            *
 * Parameters: chan             - [IN/OUT] channel structure to initialize    *
 *             msg_size         - [IN] size of each message in bytes          *
 *             initial_capacity - [IN] initial number of message slots,       *
 *                                     grows automatically when full          *
 *                                                                            *
 ******************************************************************************/
void	zbx_chan_init(zbx_channel_t *chan, int msg_size, int initial_capacity)
{
	memset(chan, 0, sizeof(zbx_channel_t));

	if (0 != pthread_mutex_init(&chan->lock, NULL))
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("failed to initialize channel mutex");
		exit(EXIT_FAILURE);
	}

	if (0 != pthread_cond_init(&chan->wait_cond, NULL))
	{
		pthread_mutex_destroy(&chan->lock);
		THIS_SHOULD_NEVER_HAPPEN_MSG("failed to initialize channel conditional variable");
		exit(EXIT_FAILURE);
	}

	chan->msg_size = msg_size;
	if (0 != initial_capacity)
	{
		chan->msgs = zbx_malloc(NULL, (size_t)(msg_size * initial_capacity));
		chan->capacity = initial_capacity;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy channel and free all associated resources                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_chan_destroy(zbx_channel_t *chan)
{
	pthread_cond_destroy(&chan->wait_cond);
	pthread_mutex_destroy(&chan->lock);

	zbx_free(chan->msgs);
}

/******************************************************************************
 *                                                                            *
 * Purpose: send a message to the channel                                     *
 *                                                                            *
 * Parameters: chan - [IN/OUT] channel to send message to                     *
 *             msg  - [IN] pointer to message data to copy                    *
 *                                                                            *
 * Comments: automatically grows capacity if full, wakes one waiting receiver *
 *                                                                            *
 ******************************************************************************/
void	zbx_chan_send(zbx_channel_t *chan, const void *msg)
{
	pthread_mutex_lock(&chan->lock);

	chan_reserve(chan, 1);
	memcpy(chan->msgs + chan->tail * chan->msg_size, msg, (size_t)chan->msg_size);

	if (++chan->tail >= chan->capacity)
		chan->tail = 0;

	chan->msg_num++;

	pthread_cond_signal(&chan->wait_cond);
	pthread_mutex_unlock(&chan->lock);
}

void	zbx_chan_send_batch(zbx_channel_t *chan, const void *msg, int msg_num)
{
	const unsigned char	*src = msg;

	pthread_mutex_lock(&chan->lock);

	chan_reserve(chan, msg_num);

	int	tail_num = chan->capacity - chan->tail;

	if (msg_num <= tail_num)
	{
		memcpy(chan->msgs + chan->tail * chan->msg_size, src, (size_t)(msg_num * chan->msg_size));
		chan->tail += msg_num;
		if (chan->tail == chan->capacity)
			chan->tail = 0;
	}
	else
	{
		size_t	size1 = (size_t)(tail_num * chan->msg_size);
		size_t	size2 = (size_t)(msg_num - tail_num) * (size_t)chan->msg_size;

		memcpy(chan->msgs + chan->tail * chan->msg_size, src, size1);
		memcpy(chan->msgs, src + size1, size2);

		chan->tail = msg_num - tail_num;
	}

	chan->msg_num += msg_num;

	pthread_cond_signal(&chan->wait_cond);
	pthread_mutex_unlock(&chan->lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: receive multiple messages from the channel without waiting        *
 *                                                                            *
 * Parameters: chan    - [IN/OUT] channel to receive messages from            *
 *             msgs    - [OUT] buffer to copy messages to                     *
 *             msg_num - [IN] maximum number of messages to receive           *
 *                                                                            *
 * Return value: number of messages received (0 if empty)                     *
 *                                                                            *
 * Comments: Does not wait for messages, returns immediately with available   *
 *           messages up to msg_num.                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_chan_recv_batch(zbx_channel_t *chan, void *msgs, int msg_num)
{
	int		recv_num = 0;
	unsigned char	*dst;

	pthread_mutex_lock(&chan->lock);

	if (0 == chan->msg_num)
		goto out;

	recv_num = MIN(chan->msg_num, msg_num);

	dst = (unsigned char *)msgs;

	for (int i = 0; i < recv_num; i++)
	{
		unsigned char	*src = chan->msgs + (chan->head * chan->msg_size);

		memcpy(dst, src, (size_t)chan->msg_size);
		dst += chan->msg_size;
		if (++chan->head >= chan->capacity)
			chan->head = 0;
	}

	chan->msg_num -= recv_num;
out:
	pthread_mutex_unlock(&chan->lock);

	return recv_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: receive a message from the channel with timeout                   *
 *                                                                            *
 * Parameters: chan       - [IN/OUT] channel to receive message from          *
 *             message    - [OUT] buffer to copy message to                   *
 *             timeout_ms - [IN] maximum time to wait in milliseconds         *
 *                                                                            *
 * Return value: number of messages received (0 on timeout, FAIL on error)    *
 *                                                                            *
 * Comments: Waits up to timeout_ms for a message to become available.        *
 *                                                                            *
 ******************************************************************************/
int	zbx_chan_recv_timeout(zbx_channel_t *chan, void *message, int timeout_ms)
{
	pthread_mutex_lock(&chan->lock);

	if (0 != chan->msg_num)
		goto out;

	if (0 == timeout_ms)
	{
		while (0 == chan->msg_num)
			pthread_cond_wait(&chan->wait_cond, &chan->lock);
	}
	else
	{
		struct timespec	timeout;
		zbx_timespec_t	ts;

		zbx_timespec(&ts);

		timeout.tv_sec = ts.sec + timeout_ms / 1000;
		timeout.tv_nsec = ts.ns + (timeout_ms % 1000) * 1000000;

		if (timeout.tv_nsec >= 1000000000)
		{
			timeout.tv_sec++;
			timeout.tv_nsec -= 1000000000;
		}

		while (0 == chan->msg_num)
		{
			int	ret = pthread_cond_timedwait(&chan->wait_cond, &chan->lock, &timeout);

			if (ETIMEDOUT == ret)
			{
				pthread_mutex_unlock(&chan->lock);
				return 0;
			}
			else if (0 != ret)
			{
				pthread_mutex_unlock(&chan->lock);
				return FAIL;
			}
		}
	}
out:
	memcpy(message, chan->msgs + (size_t)(chan->head * chan->msg_size), (size_t)chan->msg_size);

	if (++chan->head >= chan->capacity)
		chan->head = 0;

	chan->msg_num--;

	pthread_mutex_unlock(&chan->lock);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the number of messages currently in the channel               *
 *                                                                            *
 * Return value: number of messages in the channel                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_chan_msg_num(zbx_channel_t *chan)
{
	int	msg_num;

	pthread_mutex_lock(&chan->lock);
	msg_num = chan->msg_num;
	pthread_mutex_unlock(&chan->lock);

	return msg_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the current capacity of the channel                           *
 *                                                                            *
 * Return value: number of message slots currently allocated                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_chan_capacity(zbx_channel_t *chan)
{
	int	capacity;

	pthread_mutex_lock(&chan->lock);
	capacity = chan->capacity;
	pthread_mutex_unlock(&chan->lock);

	return capacity;
}

/******************************************************************************
 *                                                                            *
 * Purpose: shrink channel capacity to reduce memory usage                    *
 *                                                                            *
 * Parameters: chan         - [IN/OUT] channel to compact                     *
 *             min_capacity - [IN] minimum capacity to maintain               *
 *                                                                            *
 * Comments: Shrinks capacity to the maximum of current message count and     *
 *           min_capacity. Linearizes the ring buffer. Frees the buffer if    *
 *           min_capacity is 0 and channel is empty.                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_chan_compact(zbx_channel_t *chan, int min_capacity)
{
	unsigned char	*msgs;

	pthread_mutex_lock(&chan->lock);

	int new_capacity = MAX(chan->msg_num, min_capacity);

	if (new_capacity >= chan->capacity)
		goto out;

	if (0 == new_capacity)
	{
		zbx_free(chan->msgs);
		chan->capacity = 0;
		chan->head = 0;
		chan->tail = 0;

		goto out;
	}

	msgs = zbx_malloc(NULL, (size_t)(chan->msg_size * new_capacity));

	if (0 != chan->msg_num)
	{
		if (chan->head < chan->tail)
		{
			memcpy(msgs, chan->msgs + (size_t)(chan->head * chan->msg_size),
					(size_t)(chan->msg_num * chan->msg_size));
		}
		else
		{
			int	head_num = chan->capacity - chan->head;
			size_t	head_size = (size_t)(head_num * chan->msg_size);

			memcpy(msgs, chan->msgs + (size_t)(chan->head * chan->msg_size), head_size);
			memcpy(msgs + head_size, chan->msgs, (size_t)(chan->tail * chan->msg_size));
		}
	}

	zbx_free(chan->msgs);
	chan->msgs = msgs;
	chan->capacity = new_capacity;
	chan->head = 0;
	chan->tail = (chan->msg_num == new_capacity ? 0 : chan->msg_num);
out:
	pthread_mutex_unlock(&chan->lock);
}



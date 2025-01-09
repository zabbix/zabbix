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

package external

import (
	"encoding/json"
	"net"
	"sync"
	"sync/atomic"
	"time"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin/comms"
)

var (
	// ErrBrokerClosed is returned when a request is made on a closed broker.
	ErrBrokerClosed = errs.New("broker closed")
	// ErrBrokerTimeout is returned when a request sent by broker does not
	// receive a response within timeout.
	ErrBrokerTimeout = errs.New("broker timeout")
)

// pluginBroker handles communication with a single plugin.
type pluginBroker struct {
	timeout   time.Duration
	conn      net.Conn
	requestID atomic.Uint32

	// map of requestID to requests awaiting response from plugin
	requests      map[uint32]*requestWithResponse
	requestsMutex sync.Mutex

	// channel to handle agent to plugin requests
	// can have only *request and *requestWithResponse types
	tx chan any

	logr log.Logger
}

// describes a one directional (agent to plugin) request. err chan is closed
// when request is sent out.
type request struct {
	id  uint32
	in  any
	err chan error
}

// requestWithResponse describes a request that expects a response from the plugin.
// out, err chan are closed when response or error is received.
type requestWithResponse struct {
	id      uint32
	in      any
	out     chan []byte
	err     chan error
	timeout time.Duration
}

func newBroker(
	conn net.Conn, timeout time.Duration, logr log.Logger,
) *pluginBroker {
	pb := &pluginBroker{
		timeout:  timeout,
		conn:     conn,
		requests: make(map[uint32]*requestWithResponse),
		tx:       make(chan any),
		logr:     logr,
	}

	go pb.reader()
	go pb.writer()

	return pb
}

func (b *pluginBroker) reader() {
	for {
		meta, data, err := comms.Read(b.conn)
		if err != nil {
			if isErrConnectionClosed(err) {
				b.logr.Tracef("closed connection to plugin")

				return
			}

			b.logr.Errf("failed to read response: %s", err.Error())

			continue
		}

		go func(meta comms.Common, data []byte) {
			if meta.Id == 0 {
				// incoming plugin log requests from plugin.
				resp := &comms.LogRequest{}

				err := json.Unmarshal(data, resp)
				if err != nil {
					b.logr.Errf(
						"failed to read log message from plugins: %s",
						err.Error(),
					)

					return
				}

				b.handleLog(resp)

				return
			}

			// response, forward data to the corresponding channel
			b.requestsMutex.Lock()
			defer b.requestsMutex.Unlock()

			req, ok := b.requests[meta.Id]
			if !ok {
				return
			}

			req.out <- data
			close(req.out)
			close(req.err)
			delete(b.requests, meta.Id)
		}(meta, data)
	}
}

func (b *pluginBroker) writer() {
	for r := range b.tx {
		go func(r any) {
			switch r := r.(type) {
			case *request:
				defer close(r.err)

				err := comms.Write(b.conn, r.in)
				if err != nil {
					r.err <- errs.Wrap(err, "failed to write request body")
				}

			case *requestWithResponse:
				b.requestsMutex.Lock()
				b.requests[r.id] = r
				b.requestsMutex.Unlock()

				go func(id uint32) {
					time.Sleep(r.timeout)

					b.requestsMutex.Lock()
					defer b.requestsMutex.Unlock()

					req, ok := b.requests[id]
					if !ok {
						return
					}

					req.err <- errs.Wrapf(
						ErrBrokerTimeout,
						"timeout %s reached while waiting for response from plugin",
						r.timeout.String(),
					)
					close(req.out)
					close(req.err)
					delete(b.requests, id)
				}(r.id)

				err := comms.Write(b.conn, r.in)
				if err != nil {
					b.requestsMutex.Lock()
					defer b.requestsMutex.Unlock()

					// check if request is still awaiting response (or timeout)
					req, ok := b.requests[r.id]
					if !ok {
						b.logr.Errf(
							"stopped waiting for response from plugin, "+
								"before write error could be handled: %s",
							err.Error(),
						)

						return
					}

					req.err <- errs.Wrap(err, "failed to write request body")
					close(req.out)
					close(req.err)
					delete(b.requests, r.id)
				}

			default:
				panic(errs.Errorf("unsupported request type %T", r))
			}
		}(r)
	}
}

// close closes the broker and all associated resources.
func (b *pluginBroker) close() {
	// not 100% sure if there won't be any requests after close (might panic).
	close(b.tx) // close writer

	err := b.conn.Close() // close reader
	if err != nil {
		b.logr.Errf("failed to close connection to plugin: %s", err.Error())
	}

	b.requestsMutex.Lock()
	defer b.requestsMutex.Unlock()

	for id, req := range b.requests {
		req.err <- ErrBrokerClosed
		close(req.out)
		close(req.err)
		delete(b.requests, id)
	}
}

// DoWithResponse sends a request to the plugin and blocks until a response is received.
// Data must be a pointer.
func (b *pluginBroker) DoWithResponse(data any, timeout time.Duration) ([]byte, error) {
	id := b.requestID.Add(1)

	data, err := setID(data, id)
	if err != nil {
		return nil, errs.Wrap(err, "failed to set request id")
	}

	r := &requestWithResponse{
		id:      id,
		in:      data,
		out:     make(chan []byte),
		err:     make(chan error),
		timeout: timeout,
	}

	b.tx <- r

	select {
	case data := <-r.out:
		return data, nil
	case err := <-r.err:
		return nil, err
	}
}

// Do sends a request to the plugin and blocks until the request data has been written.
// Data must be a pointer.
func (b *pluginBroker) Do(data any) error {
	id := b.requestID.Add(1)

	data, err := setID(data, id)
	if err != nil {
		return errs.Wrap(err, "failed to set request id")
	}

	r := &request{
		id:  id,
		in:  data,
		err: make(chan error),
	}

	b.tx <- r

	err = <-r.err
	if err != nil {
		return err
	}

	return nil
}

// DoWithResponseAs same as pluginBroker.DoWithResponse but unmarshals the
// response into T.
func DoWithResponseAs[T any](broker *pluginBroker, data any, timeout time.Duration) (*T, error) {
	dataBytes, err := broker.DoWithResponse(data, timeout)
	if err != nil {
		return nil, err
	}

	var resp T

	err = json.Unmarshal(dataBytes, &resp)
	if err != nil {
		return nil, errs.Wrap(err, "failed to unmarsal response")
	}

	return &resp, nil
}

func (b *pluginBroker) handleLog(l *comms.LogRequest) {
	switch l.Severity {
	case log.Info:
		b.logr.Infof(l.Message)
	case log.Crit:
		b.logr.Critf(l.Message)
	case log.Err:
		b.logr.Errf(l.Message)
	case log.Warning:
		b.logr.Warningf(l.Message)
	case log.Debug:
		b.logr.Debugf(l.Message)
	case log.Trace:
		b.logr.Tracef(l.Message)
	}
}

func setID(data any, id uint32) (any, error) {
	switch v := data.(type) {
	case *comms.ExportRequest:
		v.Id = id
	case *comms.RegisterRequest:
		v.Id = id
	case *comms.ValidateRequest:
		v.Id = id
	case *comms.TerminateRequest:
		v.Id = id
	case *comms.ConfigureRequest:
		v.Id = id
	case *comms.StartRequest:
		v.Id = id
	default:
		return nil, errs.Errorf("unsupported request type %T", data)
	}

	return data, nil
}

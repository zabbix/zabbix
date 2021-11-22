package external

import (
	"encoding/json"
	"errors"
	"net"
	"time"

	"zabbix.com/pkg/log"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/shared"
)

const queSize = 100

type pluginBroker struct {
	socket   string
	timeout  time.Duration
	conn     net.Conn
	requests map[uint32]chan interface{}

	errx chan error
	log  chan interface{}
	// channel to handle agent->plugin requests
	tx chan *request
	// channel to handle plugin->agent requests/responses
	rx chan *request
}

type RequestWrapper struct {
	shared.Common
	shared.LogRequest
}

type request struct {
	id   uint32
	data interface{}
	out  chan interface{}
}

// p.socket = fmt.Sprintf("%s%d", socketBasePath, time.Now().UnixNano())

func New(conn net.Conn, timeout time.Duration, socket string) *pluginBroker {
	broker := pluginBroker{
		socket:   socket,
		timeout:  timeout,
		conn:     conn,
		requests: make(map[uint32]chan interface{}),
		errx:     make(chan error, queSize),
		log:      make(chan interface{}),
		tx:       make(chan *request, queSize),
		rx:       make(chan *request, queSize),
	}

	return &broker
}

func (b *pluginBroker) handleConnection() {
	for {
		t, data, err := shared.Read(b.conn)
		if err != nil {
			return
		}

		var id uint32
		var resp interface{}

		switch t {
		case shared.RegisterResponseType:
			var reg shared.RegisterResponse
			err := json.Unmarshal(data, &reg)
			if err != nil {
				panic(err)
			}

			id = reg.Id
			resp = reg

		case shared.LogRequestType:
			var log shared.LogRequest
			err := json.Unmarshal(data, &log)
			if err != nil {
				panic(err)
			}

			// plugin notifications don't have responses, so use 0 id
			resp = log

		case shared.ValidateResponseType:
			var valid shared.ValidateResponse
			err := json.Unmarshal(data, &valid)
			if err != nil {
				panic(err)
			}

			id = valid.Id
			resp = valid
		case shared.ExportResponseType:
			var export shared.ExportResponse
			err := json.Unmarshal(data, &export)
			if err != nil {
				panic(err)
			}

			id = export.Id
			resp = export
		}

		b.rx <- &request{id: id, data: resp}
	}
}

func (b *pluginBroker) timeoutRequest(id uint32) {
	<-time.After(b.timeout)
	b.tx <- &request{id: id}
}

func (b *pluginBroker) runBackground() {
	var lastid uint32

	for {
		select {
		case r := <-b.rx:
			if r.id == 0 {
				// incoming plugin request, current only LogRequest
				b.log <- r.data
			} else {
				// response, forward data to the corresponding channel
				if o, ok := b.requests[r.id]; ok {
					o <- r.data
					close(o)
					delete(b.requests, r.id)
				}
			}

		case r := <-b.tx:
			if r.data == nil {
				if r.id == 0 {
					// stop request has null contents
					b.conn.Close()

					return
				}

				// timeout has id + nil data
				if o, ok := b.requests[r.id]; ok {
					o <- errors.New("timeout occurred")
					close(o)
					delete(b.requests, r.id)
				}
			} else {
				lastid++
				switch v := r.data.(type) {
				case *shared.ExportRequest:
					v.Id = lastid
				case *shared.RegisterRequest:
					v.Id = lastid
				case *shared.ValidateRequest:
					v.Id = lastid
				case *shared.TerminateRequest:
					v.Id = lastid
				case *shared.ConfigureRequest:
					v.Id = lastid
				}

				b.requests[lastid] = r.out
				go b.timeoutRequest(lastid)
				err := shared.Write(b.conn, r.data)
				if err != nil {
					panic(err)
				}
			}
		}
	}
}

func (b *pluginBroker) handleLogs() {
	for u := range b.log {
		switch v := u.(type) {
		case shared.LogRequest:
			handleLog(v)
		default:
			log.Errf("Failed to log message from external plugins, unknown request type.")
		}
	}
}

func handleLog(l shared.LogRequest) {
	switch l.Severity {
	case log.Info:
		log.Infof(l.Message)
	case log.Crit:
		log.Critf(l.Message)
	case log.Err:
		log.Errf(l.Message)
	case log.Warning:
		log.Warningf(l.Message)
	case log.Debug:
		log.Debugf(l.Message)
	case log.Trace:
		log.Tracef(l.Message)
	}
}

func (b *pluginBroker) run() {
	go b.handleLogs()
	go b.handleConnection()
	go b.runBackground()
}

func (b *pluginBroker) stop() {
	r := request{data: nil}
	b.tx <- &r
}

func (b *pluginBroker) export(key string, params []string) (*shared.ExportResponse, error) {
	data := shared.ExportRequest{
		Common: shared.Common{
			Type: shared.ExportRequestType,
		},
		Key:    key,
		Params: params,
	}

	r := request{
		data: &data,
		out:  make(chan interface{}),
	}

	b.tx <- &r
	u := <-r.out

	switch v := u.(type) {
	case shared.ExportResponse:
		return &v, nil
	case error:
		return nil, v
	}

	return nil, errors.New("unknown response")
}

func (b *pluginBroker) register() (*shared.RegisterResponse, error) {
	r := request{
		data: &shared.RegisterRequest{
			Common: shared.Common{
				Type: shared.RegisterRequestType,
			},
			Version: shared.Version,
		},
		out: make(chan interface{}),
	}

	b.tx <- &r
	u := <-r.out

	switch v := u.(type) {
	case shared.RegisterResponse:
		return &v, nil
	case error:
		return nil, v
	}

	return nil, errors.New("unknown response")
}

func (b *pluginBroker) configure(globalOptions *plugin.GlobalOptions, privateOptions interface{}) {
	r := request{
		data: &shared.ConfigureRequest{
			Common: shared.Common{
				Type: shared.ConfigureRequestType,
			},
			GlobalOptions:  globalOptions,
			PrivateOptions: privateOptions,
		},
	}

	b.tx <- &r
}

func (b *pluginBroker) validate(privateOptions interface{}) (*shared.ValidateResponse, error) {
	r := request{
		data: &shared.ValidateRequest{
			Common: shared.Common{
				Type: shared.ValidateRequestType,
			},
			PrivateOptions: privateOptions,
		},
		out: make(chan interface{}),
	}

	b.tx <- &r
	u := <-r.out

	switch v := u.(type) {
	case shared.ValidateResponse:
		return &v, nil
	case error:
		return nil, v
	}

	return nil, errors.New("unknown response")
}

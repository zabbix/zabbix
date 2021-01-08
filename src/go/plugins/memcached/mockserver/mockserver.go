package mockserver

import (
	"context"
	"fmt"
	"io"
	"log"
	"net"

	"github.com/dustin/gomemcached"
	"github.com/dustin/gomemcached/server"
)

const (
	Noop = 0x0a
	Stat = 0x10
)

const (
	Success        = 0x00
	UnknownCommand = 0x81
)

const debug = false

func printDebugf(format string, v ...interface{}) {
	if debug {
		log.Printf(format, v...)
	}
}

type MCRequest = gomemcached.MCRequest
type MCResponse = gomemcached.MCResponse

type HandlerFunc func(req *MCRequest, w io.Writer) *MCResponse

type MockServer struct {
	handlers map[gomemcached.CommandCode]HandlerFunc
	listener net.Listener
	ctx      context.Context
	port     int
	stop     context.CancelFunc
}

func (s *MockServer) RegisterHandler(code uint8, fn HandlerFunc) {
	s.handlers[gomemcached.CommandCode(code)] = fn
}

type chanReq struct {
	req *MCRequest
	res chan *MCResponse
	w   io.Writer
}

func notFound(_ *MCRequest) *MCResponse {
	return &MCResponse{
		Status: gomemcached.UNKNOWN_COMMAND,
	}
}

type reqHandler struct {
	ch chan chanReq
}

func (rh *reqHandler) HandleMessage(w io.Writer, req *MCRequest) *MCResponse {
	cr := chanReq{
		req,
		make(chan *MCResponse),
		w,
	}

	rh.ch <- cr

	return <-cr.res
}

func (s *MockServer) handle(req *MCRequest, w io.Writer) (rv *MCResponse) {
	if h, ok := s.handlers[req.Opcode]; ok {
		rv = h(req, w)
	} else {
		return notFound(req)
	}

	return rv
}

func (s *MockServer) dispatch(input chan chanReq) {
	// TODO: stop goroutine
	for {
		req := <-input
		printDebugf("Got a request: %s", req.req)
		req.res <- s.handle(req.req, req.w)
	}
}

func handleIO(conn net.Conn, rh memcached.RequestHandler) {
	// Explicitly ignoring errors since they all result in the
	// client getting hung up on and many are common.
	_ = memcached.HandleIO(conn, rh)
}

func (s *MockServer) ListenAndServe() {
	var err error

	s.listener, err = net.Listen("tcp", s.GetAddr())
	if err != nil {
		panic(err)
	}

	reqChannel := make(chan chanReq)

	go s.dispatch(reqChannel)
	rh := &reqHandler{reqChannel}

	printDebugf("Listening on %s", s.listener.Addr())

	for {
		conn, err := s.listener.Accept()
		select {
		case <-s.ctx.Done():
			printDebugf("Server stopped")
			return
		default:
			if err != nil {
				printDebugf("Error accepting from %s", s.listener)
			} else {
				printDebugf("Got a connection from %v", conn.RemoteAddr())
				go handleIO(conn, rh)
			}
		}
	}
}

func (s *MockServer) Stop() {
	// TODO: finish client requests and close connections
	if s.listener != nil {
		s.stop()
		_ = s.listener.Close()
	}
}

func (s *MockServer) GetAddr() string {
	return fmt.Sprintf("localhost:%d", s.port)
}

func getFreePort() (int, error) {
	addr, err := net.ResolveTCPAddr("tcp", "localhost:0")
	if err != nil {
		return 0, err
	}

	ls, err := net.ListenTCP("tcp", addr)
	if err != nil {
		return 0, err
	}

	_ = ls.Close()

	return ls.Addr().(*net.TCPAddr).Port, nil
}

func NewMockServer() (srv *MockServer, err error) {
	ctx, cancel := context.WithCancel(context.Background())

	srv = &MockServer{
		handlers: make(map[gomemcached.CommandCode]HandlerFunc),
		ctx:      ctx,
		stop:     cancel,
	}

	srv.port, err = getFreePort()
	if err != nil {
		return nil, err
	}

	return srv, nil
}

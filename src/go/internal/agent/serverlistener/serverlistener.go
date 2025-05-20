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

package serverlistener

import (
	"errors"
	"fmt"
	"net"
	"os"
	"strings"
	"sync/atomic"
	"time"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/internal/agent/scheduler"
	"golang.zabbix.com/agent2/internal/monitor"
	"golang.zabbix.com/agent2/pkg/zbxcomms"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/zbxnet"
)

// ServerListener handles passive check requests on dedicated port.
type ServerListener struct {
	listenerID int
	listener   *zbxcomms.Listener
	scheduler  scheduler.Scheduler
	options    *agent.AgentOptions
	bindIP     string
	lastErr    string
	// Is accessed by Stop() and run() at the same time.
	stopped atomic.Bool
}

func (sl *ServerListener) handleError(err error) error {
	var netErr net.Error

	if !errors.As(err, &netErr) {
		log.Errf("failed to accept an incoming connection: %s", err.Error())

		return nil
	}

	if netErr.Timeout() {
		log.Debugf("failed to accept an incoming connection: %s", err.Error())

		return nil
	}

	if sl.stopped.Load() {
		return err
	}

	log.Errf("failed to accept an incoming connection: %s", err.Error())

	var se *os.SyscallError

	if !errors.As(err, &se) {
		return nil
	}

	/* sleep to avoid high CPU usage on surprising temporary errors */
	if sl.lastErr == se.Err.Error() {
		time.Sleep(time.Second)
	}

	sl.lastErr = se.Err.Error()

	return nil
}

func (sl *ServerListener) run(allowedPeers *zbxnet.AllowedPeers) {
	defer log.PanicHook()

	log.Debugf("[%d] starting listener for '%s:%d'", sl.listenerID, sl.bindIP, sl.options.ListenPort)

	for {
		conn, err := sl.listener.Accept(
			time.Second*time.Duration(sl.options.Timeout),
			zbxcomms.TimeoutModeShift,
		)
		if err != nil {
			if sl.handleError(err) != nil {
				break
			}

			continue
		}

		remoteIP := conn.RemoteIP()

		if !isAllowedConnection(remoteIP, allowedPeers) {
			err = conn.Close()
			if err != nil {
				log.Warningf(
					"failed to close connection to rejected host %q",
					remoteIP,
				)
			}

			log.Warningf(
				"connection from %q rejected, allowed hosts: %q",
				remoteIP,
				sl.options.Server,
			)

			continue
		}

		go handleConnection(sl.scheduler, conn)
	}

	log.Debugf("listener has been stopped")
	monitor.Unregister(monitor.Input)
}

func isAllowedConnection(remoteIP string, allowedPeers *zbxnet.AllowedPeers) bool {
	parsedIP := net.ParseIP(remoteIP)

	return allowedPeers.CheckPeer(parsedIP)
}

// New sets up and returns new server listener instance to handle passive checks.
func New(listenerID int, sched scheduler.Scheduler, bindIP string, options *agent.AgentOptions) *ServerListener {
	return &ServerListener{
		listenerID: listenerID,
		scheduler:  sched,
		bindIP:     bindIP,
		options:    options,
	}
}

// Start sets up and launches listener for passive check handling.
func (sl *ServerListener) Start() error {
	tlsConfig, err := agent.GetTLSConfig(sl.options)
	if err != nil {
		return errs.Wrap(err, "failed getting tls config")
	}

	allowedPeers, err := zbxnet.GetAllowedPeers(sl.options.Server)
	if err != nil {
		return errs.Wrap(err, "failed getting allowed peers")
	}

	address := fmt.Sprintf("[%s]:%d", sl.bindIP, sl.options.ListenPort)

	sl.listener, err = zbxcomms.Listen(address, tlsConfig)
	if err != nil {
		return errs.Wrap(err, "failed getting listener")
	}

	monitor.Register(monitor.Input)

	go sl.run(allowedPeers)

	return nil
}

// Stop terminates listener for passive check handling.
func (sl *ServerListener) Stop() {
	sl.stopped.Store(true)

	if sl.listener != nil {
		err := sl.listener.Close()
		if err != nil {
			log.Errf("passive check listener fail: %s", err.Error())
		}
	}
}

// ParseListenIP validate ListenIP value.
func ParseListenIP(options *agent.AgentOptions) ([]string, error) {
	lips := getListLocalIP()

	return parseListenIP(options, lips)
}

func parseListenIP(options *agent.AgentOptions, lips []net.IP) ([]string, error) {
	if options.ListenIP == "" || options.ListenIP == "0.0.0.0" {
		return []string{"0.0.0.0"}, nil
	}

	opts := strings.Split(options.ListenIP, ",")
	ips := make([]string, 0, len(opts))

	for _, o := range opts {
		addr := strings.Trim(o, " \t")

		err := validateLocalIP(addr, lips)
		if err != nil {
			return nil, err
		}

		ips = append(ips, addr)
	}

	return ips, nil
}

func validateLocalIP(addr string, lips []net.IP) error {
	ip := net.ParseIP(addr)
	if ip == nil {
		return errs.Errorf("incorrect value of ListenIP: %q", addr)
	}

	if ip.IsLoopback() || len(lips) == 0 {
		return nil
	}

	for _, lip := range lips {
		if lip.Equal(ip) {
			return nil
		}
	}

	return errs.Errorf("value of ListenIP not present on the host: %q", addr)
}

func getListLocalIP() []net.IP {
	var ips []net.IP

	ifaces, err := net.Interfaces()
	if err != nil {
		return ips
	}

	for _, i := range ifaces {
		addrs, err := i.Addrs()
		if err != nil {
			return ips
		}

		for _, addr := range addrs {
			var ip net.IP
			switch v := addr.(type) {
			case *net.IPNet:
				ip = v.IP
			case *net.IPAddr:
				ip = v.IP
			}

			ips = append(ips, ip)
		}
	}

	return ips
}

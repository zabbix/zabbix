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
	"encoding/json"
	"errors"
	"fmt"
	"net"
	"os"
	"strings"
	"time"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/internal/agent/scheduler"
	"golang.zabbix.com/agent2/internal/monitor"
	"golang.zabbix.com/agent2/pkg/tls"
	"golang.zabbix.com/agent2/pkg/zbxcomms"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/zbxnet"
)

type ServerListener struct {
	listenerID   int
	listener     *zbxcomms.Listener
	scheduler    scheduler.Scheduler
	options      *agent.AgentOptions
	tlsConfig    *tls.Config
	allowedPeers *zbxnet.AllowedPeers
	bindIP       string
	last_err     string
	stopped      bool
}

func (c *ServerListener) handleError(err error) error {
	var netErr net.Error

	if !errors.As(err, &netErr) {
		log.Errf("failed to accept an incoming connection: %s", err.Error())

		return nil
	}

	if netErr.Timeout() {
		log.Debugf("failed to accept an incoming connection: %s", err.Error())

		return nil
	}

	if c.stopped {
		return err
	}

	log.Errf("failed to accept an incoming connection: %s", err.Error())

	var se *os.SyscallError

	if !errors.As(err, &se) {
		return nil
	}

	/* sleep to avoid high CPU usage on surprising temporary errors */
	if c.last_err == se.Err.Error() {
		time.Sleep(time.Second)
	}
	c.last_err = se.Err.Error()

	return nil
}

func (sl *ServerListener) run() {
	defer log.PanicHook()

	log.Debugf("[%d] starting listener for '%s:%d'", sl.listenerID, sl.bindIP, sl.options.ListenPort)

	for {

		// 1. open connection
		// 2. read task from connection
		// 3. parse taks
		// 4. give task to scheduler
		// 5. send result to connection

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

		go func() {

			// Check if IP is whitelisted
			remoteIP := conn.RemoteIP()
			parsedIP := net.ParseIP(remoteIP)
			if !sl.allowedPeers.CheckPeer(parsedIP) {
				_ = conn.Close()

				log.Warningf(
					"failed to accept an incoming connection: connection from %q rejected, allowed hosts: %q",
					remoteIP,
					sl.options.Server,
				)

				return
			}

			// Receive passive check request
			rawRequest, err := conn.Read()
			if err != nil {
				_ = conn.Close()

				log.Warningf(
					"failed to process an incoming connection from %s: %s",
					remoteIP,
					err.Error(),
				)
			}

			log.Debugf(
				"received passive check request: '%s' from '%s'",
				string(rawRequest),
				remoteIP,
			)

			if json.Valid(rawRequest) {
				key = []byte(rawRequest)
			}

			key, timeout, err := parsePassiveCheckJSONRequest(rawRequest)
			// handleCheck(sl.scheduler, conn, data)
		}()
	}

	log.Debugf("listener has been stopped")
	monitor.Unregister(monitor.Input)

}

func New(listenerID int, s scheduler.Scheduler, bindIP string, options *agent.AgentOptions) (sl *ServerListener) {
	sl = &ServerListener{listenerID: listenerID, scheduler: s, bindIP: bindIP, options: options}
	return
}

func (sl *ServerListener) Start() (err error) {
	if sl.tlsConfig, err = agent.GetTLSConfig(sl.options); err != nil {
		return
	}
	if sl.allowedPeers, err = zbxnet.GetAllowedPeers(sl.options.Server); err != nil {
		return
	}
	if sl.listener, err = zbxcomms.Listen(fmt.Sprintf("[%s]:%d", sl.bindIP, sl.options.ListenPort), sl.tlsConfig); err != nil {
		return
	}
	monitor.Register(monitor.Input)
	go sl.run()
	return
}

func (sl *ServerListener) Stop() {
	sl.stopped = true
	if sl.listener != nil {
		sl.listener.Close()
	}
}

// ParseListenIP validate ListenIP value
func ParseListenIP(options *agent.AgentOptions) (ips []string, err error) {
	if 0 == len(options.ListenIP) || options.ListenIP == "0.0.0.0" {
		return []string{"0.0.0.0"}, nil
	}
	lips := getListLocalIP()
	opts := strings.Split(options.ListenIP, ",")
	for _, o := range opts {
		addr := strings.Trim(o, " \t")
		if err = validateLocalIP(addr, lips); nil != err {
			return nil, err
		}
		ips = append(ips, addr)
	}
	return ips, nil
}

func validateLocalIP(addr string, lips *[]net.IP) (err error) {
	if ip := net.ParseIP(addr); nil != ip {
		if ip.IsLoopback() || 0 == len(*lips) {
			return nil
		}
		for _, lip := range *lips {
			if lip.Equal(ip) {
				return nil
			}
		}
	} else {
		return fmt.Errorf("incorrect value of ListenIP: \"%s\"", addr)
	}
	return fmt.Errorf("value of ListenIP not present on the host: \"%s\"", addr)
}

func getListLocalIP() *[]net.IP {
	var ips []net.IP

	ifaces, err := net.Interfaces()
	if nil != err {
		return &ips
	}

	for _, i := range ifaces {
		addrs, err := i.Addrs()
		if nil != err {
			return &ips
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

	return &ips
}

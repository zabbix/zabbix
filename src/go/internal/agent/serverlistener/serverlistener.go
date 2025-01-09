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

func (sl *ServerListener) processConnection(conn *zbxcomms.Connection) (err error) {
	defer func() {
		if err != nil {
			conn.Close()
		}
	}()

	var data []byte
	if data, err = conn.Read(); err != nil {
		return
	}

	log.Debugf("received passive check request: '%s' from '%s'", string(data), conn.RemoteIP())

	response := passiveCheck{conn: &passiveConnection{conn: conn}, scheduler: sl.scheduler}
	go response.handleCheck(data)

	return nil
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
		conn, err := sl.listener.Accept(time.Second*time.Duration(sl.options.Timeout),
			zbxcomms.TimeoutModeShift)

		if err == nil {
			if !sl.allowedPeers.CheckPeer(net.ParseIP(conn.RemoteIP())) {
				conn.Close()
				log.Warningf("failed to accept an incoming connection: connection from \"%s\" rejected, allowed hosts: \"%s\"",
					conn.RemoteIP(), sl.options.Server)
			} else if err := sl.processConnection(conn); err != nil {
				log.Warningf("failed to process an incoming connection from %s: %s", conn.RemoteIP(), err.Error())
			}
		} else {
			if err != nil {
				if sl.handleError(err) == nil {
					continue
				}

				break
			}
		}
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

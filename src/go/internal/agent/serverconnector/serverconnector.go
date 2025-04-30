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

package serverconnector

import (
	"crypto/md5"
	"encoding/binary"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"net"
	"reflect"
	"strings"
	"time"
	"unicode/utf8"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/internal/agent/resultcache"
	"golang.zabbix.com/agent2/internal/agent/scheduler"
	"golang.zabbix.com/agent2/internal/monitor"
	"golang.zabbix.com/agent2/pkg/glexpr"
	"golang.zabbix.com/agent2/pkg/tls"
	"golang.zabbix.com/agent2/pkg/version"
	"golang.zabbix.com/agent2/pkg/zbxcomms"
	"golang.zabbix.com/sdk/log"
)

const defaultAgentPort = 10050

type Connector struct {
	clientID                   uint64
	input                      chan interface{}
	address                    zbxcomms.AddressSet
	hostname                   string
	session                    string
	configRevision             uint64
	localAddr                  net.Addr
	lastActiveCheckErrors      []error
	lastActiveHbErrors         []error
	firstActiveChecksRefreshed bool
	firstActiveChecksLog       bool
	resultCache                resultcache.ResultCache
	taskManager                scheduler.Scheduler
	options                    *agent.AgentOptions
	tlsConfig                  *tls.Config
}

type activeChecksRequest struct {
	Request        string `json:"request"`
	Host           string `json:"host"`
	Version        string `json:"version"`
	Variant        int    `json:"variant"`
	Session        string `json:"session"`
	ConfigRevision uint64 `json:"config_revision"`
	HostMetadata   string `json:"host_metadata,omitempty"`
	HostInterface  string `json:"interface,omitempty"`
	ListenIP       string `json:"ip,omitempty"`
	ListenPort     int    `json:"port,omitempty"`
}

type activeChecksResponse struct {
	Response       string                 `json:"response"`
	Info           string                 `json:"info"`
	ConfigRevision uint64                 `json:"config_revision,omitempty"`
	Data           []*scheduler.Request   `json:"data"`
	Commands       []*agent.RemoteCommand `json:"commands"`
	Expressions    []*glexpr.Expression   `json:"regexp"`
	HistoryUpload  string                 `json:"upload"`
}

type agentDataResponse struct {
	Response      string `json:"response"`
	Info          string `json:"info"`
	HistoryUpload string `json:"upload"`
}

type heartbeatMessage struct {
	Request            string `json:"request"`
	Host               string `json:"host"`
	HeartbeatFrequency int    `json:"heartbeat_freq"`
	Version            string `json:"version"`
	Variant            int    `json:"variant"`
}

//nolint:gocognit,gocyclo,cyclop,maintidx
func (c *Connector) refreshActiveChecks() bool {
	var err error

	a := activeChecksRequest{
		Request:        "active checks",
		Host:           c.hostname,
		Version:        version.Long(),
		Variant:        agent.Variant,
		Session:        c.session,
		ConfigRevision: c.configRevision,
	}

	log.Debugf("[%d] In refreshActiveChecks() from %s", c.clientID, c.address)
	defer log.Debugf("[%d] End of refreshActiveChecks() from %s", c.clientID, c.address)

	if a.HostInterface, err = processConfigItem(c.taskManager, time.Duration(c.options.Timeout)*time.Second,
		"HostInterface", c.options.HostInterface, c.options.HostInterfaceItem, agent.HostInterfaceLen,
		agent.LocalChecksClientID); err != nil {
		log.Errf("cannot get host interface: %s", err)

		return false
	}

	if a.HostMetadata, err = processConfigItem(c.taskManager, time.Duration(c.options.Timeout)*time.Second, "HostMetadata",
		c.options.HostMetadata, c.options.HostMetadataItem, agent.HostMetadataLen, agent.LocalChecksClientID); err != nil {
		log.Errf("cannot get host metadata: %s", err)

		return false
	}

	if len(c.options.ListenIP) > 0 {
		if i := strings.IndexByte(c.options.ListenIP, ','); i != -1 {
			a.ListenIP = c.options.ListenIP[:i]
		} else {
			a.ListenIP = c.options.ListenIP
		}
	}

	if c.options.ListenPort != defaultAgentPort {
		a.ListenPort = c.options.ListenPort
	}

	request, err := json.Marshal(&a)
	if err != nil {
		log.Errf("[%d] cannot create active checks request to [%s]: %s", c.clientID, c.address.Get(), err)

		return false
	}

	data, errs, errRead := zbxcomms.ExchangeWithRedirect(c.address, &c.localAddr,
		time.Second*time.Duration(c.options.Timeout), time.Second*time.Duration(c.options.Timeout), request,
		c.tlsConfig)

	if errs != nil {
		// server is unaware if configuration is actually delivered and saves session
		if errRead != nil {
			c.configRevision = 0
		}

		if !reflect.DeepEqual(errs, c.lastActiveCheckErrors) {
			for i := 0; i < len(errs); i++ {
				log.Warningf("[%d] %s", c.clientID, errs[i])
			}
			log.Warningf("[%d] active check configuration update from host [%s] started to fail", c.clientID,
				c.hostname)
			c.lastActiveCheckErrors = errs
		}

		return false
	}

	if c.lastActiveCheckErrors != nil {
		log.Warningf("[%d] active check configuration update from [%s] is working again", c.clientID, c.address.Get())
		c.lastActiveCheckErrors = nil
	}

	var response activeChecksResponse

	parseSuccess := false

	defer func() {
		if !parseSuccess {
			c.address.Next()
		}
	}()

	err = json.Unmarshal(data, &response)
	if err != nil {
		log.Errf("[%d] cannot parse list of active checks from [%s]: %s", c.clientID, c.address.Get(), err)

		return false
	}

	now := time.Now()

	if response.Response != "success" {
		if !c.firstActiveChecksLog {
			if response.Info != "" {
				log.Errf("[%d] no active checks on server [%s]: %s", c.clientID, c.address.Get(),
					response.Info)
			} else {
				log.Errf("[%d] no active checks on server [%s]", c.clientID, c.address.Get())
			}

			log.Errf("[%d] active checks on server started to fail", c.clientID)
			c.firstActiveChecksLog = true
		}
		c.taskManager.UpdateTasks(c.clientID, c.resultCache.(resultcache.Writer), c.firstActiveChecksRefreshed,
			[]*glexpr.Expression{}, []*scheduler.Request{}, now)
		c.firstActiveChecksRefreshed = true

		return false
	} else if c.firstActiveChecksLog {
		log.Errf("[%d] active checks on server are active again", c.clientID)
		c.firstActiveChecksLog = false
	}

	if response.HistoryUpload == "disabled" {
		c.resultCache.EnableUpload(false)
	} else {
		c.resultCache.EnableUpload(true)
	}

	if response.Commands != nil {
		c.taskManager.UpdateCommands(c.clientID, c.resultCache.(resultcache.Writer), response.Commands, now)
	}

	if response.Data == nil {
		if c.configRevision == 0 {
			log.Errf("[%d] cannot parse list of active checks from [%s]: data array is missing", c.clientID,
				c.address.Get())
		} else {
			parseSuccess = true
		}

		return false
	}

	c.configRevision = response.ConfigRevision

	for i := 0; i < len(response.Data); i++ {

		if len(response.Data[i].Key) == 0 {
			if response.Data[i].Itemid == 0 {
				log.Errf("[%d] cannot parse list of active checks from [%s]: key is missing",
					c.clientID, c.address.Get())

				return false
			}

			log.Errf("[%d] cannot parse list of active checks from [%s]: key is missing for itemid '%d'",
				c.clientID, c.address.Get(), response.Data[i].Itemid)

			return false
		}

		if response.Data[i].Itemid == 0 {
			log.Errf("[%d] cannot parse list of active checks from [%s]: itemid is missing for key '%s'",
				c.clientID, c.address.Get(), response.Data[i].Key)

			return false
		}

		if len(response.Data[i].Delay) == 0 {
			log.Errf("[%d] cannot parse list of active checks from [%s]: delay is missing for itemid '%d'",
				c.clientID, c.address.Get(), response.Data[i].Itemid)

			return false
		}

		if response.Data[i].LastLogsize == nil {
			log.Errf("[%d] cannot parse list of active checks from [%s]: lastlogsize is missing for itemid '%d'",
				c.clientID, c.address.Get(), response.Data[i].Itemid)

			return false
		}

		if response.Data[i].Mtime == nil {
			log.Errf("[%d] cannot parse list of active checks from [%s]: mtime is missing for itemid '%d'",
				c.clientID, c.address.Get(), response.Data[i].Itemid)

			return false
		}
	}

	for i := 0; i < len(response.Expressions); i++ {
		if len(response.Expressions[i].Name) == 0 {
			log.Errf(`[%d] cannot parse list of active checks from [%s]: cannot retrieve value of tag "name"`,
				c.clientID, c.address.Get())

			return false
		}

		if len(response.Expressions[i].Body) == 0 {
			log.Errf(`[%d] cannot parse list of active checks from [%s]: cannot retrieve value of tag "expression"`,
				c.clientID, c.address.Get())

			return false
		}

		if response.Expressions[i].Type == nil {
			log.Errf(`[%d] cannot parse list of active checks from [%s]: cannot retrieve value of tag "expression_type"`,
				c.clientID, c.address.Get())

			return false
		}

		if response.Expressions[i].Delimiter == nil {
			log.Errf(`[%d] cannot parse list of active checks from [%s]: cannot retrieve value of tag "exp_delimiter"`,
				c.clientID, c.address.Get())

			return false
		}

		if len(*response.Expressions[i].Delimiter) > 1 {
			log.Errf(`[%d] cannot parse list of active checks from [%s]: invalid tag "exp_delimiter" value "%s"`,
				c.clientID, c.address.Get(), *response.Expressions[i].Delimiter)

			return false
		}

		if response.Expressions[i].Mode == nil {
			log.Errf(`[%d] cannot parse list of active checks from [%s]: cannot retrieve value of tag "case_sensitive"`,
				c.clientID, c.address.Get())

			return false
		}
	}

	c.taskManager.UpdateTasks(c.clientID, c.resultCache.(resultcache.Writer), c.firstActiveChecksRefreshed,
		response.Expressions, response.Data, now)

	parseSuccess = true
	c.firstActiveChecksRefreshed = true

	return true
}

func (c *Connector) sendHeartbeatMsg() {
	var err error

	h := heartbeatMessage{
		Request:            "active check heartbeat",
		HeartbeatFrequency: c.options.HeartbeatFrequency,
		Host:               c.hostname,
		Version:            version.Long(),
		Variant:            agent.Variant,
	}

	log.Debugf("[%d] In sendHeartbeatMsg() from %s", c.clientID, c.address)
	defer log.Debugf("[%d] End of sendHeartBeatMsg() from %s", c.clientID, c.address)

	request, err := json.Marshal(&h)
	if err != nil {
		log.Errf("[%d] cannot create heartbeat message to [%s]: %s", c.clientID, c.address.Get(), err)
		return
	}

	_, errs, _ := zbxcomms.ExchangeWithRedirect(c.address, &c.localAddr,
		time.Second*time.Duration(c.options.Timeout), time.Second*time.Duration(c.options.Timeout), request,
		c.tlsConfig, true)

	if errs != nil {
		if !reflect.DeepEqual(errs, c.lastActiveHbErrors) {
			for i := 0; i < len(errs); i++ {
				log.Warningf("[%d] %s", c.clientID, errs[i])
			}
			log.Warningf("[%d] sending of heartbeat message for [%s] started to fail", c.clientID,
				c.hostname)
			c.lastActiveHbErrors = errs
		}
		return
	}

	if c.lastActiveHbErrors != nil {
		log.Warningf("[%d] sending of heartbeat message to [%s] is working again", c.clientID, c.address.Get())
		c.lastActiveHbErrors = nil
	}
}

func (c *Connector) run() {
	var nextRefresh, lastFlush, lastHeartbeat int64

	defer log.PanicHook()
	log.Debugf("[%d] starting server connector for %s", c.clientID, c.address)

	time.Sleep(time.Duration(1e9 - time.Now().Nanosecond()))
	ticker := time.NewTicker(time.Second)
run:
	for {
		select {
		case <-ticker.C:
			now := time.Now().Unix()

			if (now - lastFlush) >= int64(c.options.BufferSend) {
				c.resultCache.Upload(nil)
				lastFlush = now
			}
			if now >= nextRefresh {
				var ret = c.refreshActiveChecks()

				nextRefresh = time.Now().Unix()
				if !ret {
					nextRefresh += 60
				} else {
					nextRefresh += int64(c.options.RefreshActiveChecks)
				}
			}
			if c.options.HeartbeatFrequency > 0 {
				if (now - lastHeartbeat) >= int64(c.options.HeartbeatFrequency) {
					c.sendHeartbeatMsg()
					lastHeartbeat = time.Now().Unix()
				}
			}
		case u := <-c.input:
			if u == nil {
				break run
			}
			switch v := u.(type) {
			case *agent.AgentOptions:
				c.updateOptions(v)
				// TODO: when runtime configuration reload is implemented the result cache active
				// connection properties must be updated too
			}
		}
	}
	log.Debugf("[%d] server connector has been stopped", c.clientID)
	monitor.Unregister(monitor.Input)
}

func (c *Connector) updateOptions(options *agent.AgentOptions) {
	c.options = options
	c.localAddr = &net.TCPAddr{IP: net.ParseIP(agent.Options.SourceIP), Port: 0}
}

func newToken() string {
	h := md5.New()
	_ = binary.Write(h, binary.LittleEndian, time.Now().UnixNano())

	return hex.EncodeToString(h.Sum(nil))
}

func New(taskManager scheduler.Scheduler, addresses []string, hostname string,
	options *agent.AgentOptions) (connector *Connector, err error) {
	address := zbxcomms.NewAddressPool(addresses)

	c := &Connector{
		taskManager: taskManager,
		address:     address,
		hostname:    hostname,
		input:       make(chan interface{}, 10),
		clientID:    agent.NewClientID(),
		session:     newToken(),
	}

	c.updateOptions(options)
	if c.tlsConfig, err = agent.GetTLSConfig(c.options); err != nil {
		return
	}

	ac := &activeConnection{
		address:   address,
		hostname:  hostname,
		localAddr: c.localAddr,
		tlsConfig: c.tlsConfig,
		timeout:   options.Timeout,
		session:   c.session,
	}
	c.resultCache = resultcache.New(&agent.Options, c.clientID, ac)

	return c, nil
}

func (c *Connector) Start() {
	c.resultCache.Start()
	monitor.Register(monitor.Input)
	go c.run()
}

func (c *Connector) StopConnector() {
	c.input <- nil
}

func (c *Connector) StopCache() {
	c.resultCache.Stop()
}

func (c *Connector) UpdateOptions() {
	c.input <- &agent.Options
}

func processConfigItem(taskManager scheduler.Scheduler, timeout time.Duration, name, value, item string, length int, clientID uint64) (string, error) {
	if len(item) > 0 {
		if len(value) > 0 {
			log.Warningf("both \"%s\" and \"%sItem\" configuration parameter defined, using \"%s\"", name, name, name)
			return value, nil
		}

		var err error
		var taskResult *string
		taskResult, err = taskManager.PerformTask(item, timeout, clientID)
		if err != nil {
			return "", err
		} else if taskResult == nil {
			return "", fmt.Errorf("no values was received")
		}

		value = *taskResult

		if !utf8.ValidString(value) {
			return "", fmt.Errorf("value is not a UTF-8 string")
		}

		if utf8.RuneCountInString(value) > length {
			log.Warningf("the returned value of \"%s\" item specified by \"%sItem\" configuration parameter"+
				" is too long, using first %d characters", item, name, length)

			return agent.CutAfterN(value, length), nil
		}
	}

	return value, nil
}

func (c *Connector) ClientID() uint64 {
	return c.clientID
}

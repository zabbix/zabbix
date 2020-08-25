/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**

** Eclipse Distribution License - v 1.0
**
** Copyright (c) 2007, Eclipse Foundation, Inc. and its licensors.
**
** All rights reserved.
**
** Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
**
**   * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
**   * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
**   * Neither the name of the Eclipse Foundation, Inc. nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
**
** THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
** COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
** CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
**/

package mqtt

import (
	"crypto/tls"
	"encoding/json"
	"fmt"
	"net/url"
	"strings"
	"sync"

	mqtt "github.com/eclipse/paho.mqtt.golang"
	"zabbix.com/pkg/itemutil"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/watch"
	"zabbix.com/pkg/web"
)

type mqttClient struct {
	client mqtt.Client
	broker string
	subs   map[string]*mqttSub
	opts   *mqtt.ClientOptions
	subMux *sync.RWMutex
}

type mqttSub struct {
	broker     string
	topic      string
	wildCard   bool
	subscribed bool
}

type Plugin struct {
	plugin.Base
	manager     *watch.Manager
	mqttClients map[string]*mqttClient
	clientMux   *sync.RWMutex
}

var impl Plugin

func (p *Plugin) createOptions(clientid, username, password, broker string) *mqtt.ClientOptions {
	opts := mqtt.NewClientOptions().AddBroker(broker).SetClientID(clientid).SetCleanSession(true)
	if username != "" {
		opts.SetUsername(username)
		if password != "" {
			opts.SetPassword(password)
		}
	}
	opts.SetTLSConfig(&tls.Config{InsecureSkipVerify: true, ClientAuth: tls.NoClientCert})
	opts.OnConnectionLost = func(client mqtt.Client, reason error) {
		impl.Errf("Connection lost to %s, reason: %s", broker, reason.Error())
	}
	opts.OnConnect = func(client mqtt.Client) {
		//Will show the query password and username in log
		impl.Infof("Connected to %s", broker)
		impl.clientMux.RLock()
		mc, found := p.mqttClients[broker]
		impl.clientMux.RUnlock()
		if found {
			if mc.broker == broker {
				mc.subMux.RLock()
				for _, ms := range mc.subs {
					if ms.subscribed {
						err := ms.subscribe(mc)
						if err != nil {
							impl.Errf("Failed subscribing to %s after connecting to %s\n", ms.topic, broker)
						}
					}
				}
				mc.subMux.RUnlock()
			}
		}
	}

	return opts
}

func newClient(broker string, options *mqtt.ClientOptions) (mqtt.Client, error) {
	c := mqtt.NewClient(options)
	token := c.Connect()
	if token.Wait() && token.Error() != nil {
		return nil, token.Error()
	}

	return c, nil
}

func (ms *mqttSub) subscribeCallback(client mqtt.Client, msg mqtt.Message) {
	impl.manager.Lock()
	resp[msg.Topic()] = string(msg.Payload())
	impl.Tracef("Received publication from %s: [%s] %s", ms.broker, msg.Topic(),
		string(msg.Payload()))
	impl.manager.Notify(ms, msg)
	impl.manager.Unlock()
}

func (ms *mqttSub) subscribe(mc *mqttClient) error {
	impl.Debugf("Subscribing to %s", ms.broker)
	token := mc.client.Subscribe(
		ms.topic, 0, ms.subscribeCallback)

	if token.Wait() && token.Error() != nil {
		return error(token.Error())
	}

	ms.subscribed = true
	impl.Debugf("Subscribed to %s", ms.broker)

	return nil
}

//Watch MQTT plugin
func (p *Plugin) Watch(requests []*plugin.Request, ctx plugin.ContextProvider) {
	impl.manager.Lock()
	impl.clientMux.Lock()
	for broker, mc := range impl.mqttClients {
		if mc != nil && mc.client != nil && !mc.client.IsConnected() {
			delete(impl.mqttClients, broker)
		}
	}
	impl.clientMux.Unlock()
	impl.manager.Update(ctx.ClientID(), ctx.Output(), requests)
	impl.manager.Unlock()
}

var resp = make(map[string]string)

func (ms *mqttSub) Initialize() (err error) {
	impl.clientMux.RLock()
	mc, ok := impl.mqttClients[ms.broker]
	impl.clientMux.RUnlock()
	if !ok {
		return fmt.Errorf("client missing for broker %s", ms.broker)

	}

	if mc.client == nil {
		impl.Debugf("Creating client for %s", ms.broker)
		mc.client, err = newClient(ms.broker, mc.opts)
		if err != nil {
			return err
		}

	}

	return ms.subscribe(mc)
}

func (ms *mqttSub) Release() {
	impl.clientMux.RLock()
	mc, ok := impl.mqttClients[ms.broker]
	impl.clientMux.RUnlock()
	if !ok || mc == nil || mc.client == nil {
		impl.Errf("Client not found during release for broker %s\n", ms.broker)
	}

	impl.Debugf("Unsubscribing topic from %s", ms.topic)
	token := mc.client.Unsubscribe(ms.topic)
	if token.Wait() && token.Error() != nil {
		impl.Errf("Failed to unsubscribe from %s:%s", ms.topic, token.Error())
	}

	mc.subMux.Lock()
	defer mc.subMux.Unlock()
	delete(mc.subs, ms.topic)

	impl.Debugf("Unsubscribed from %s", ms.topic)
	if len(mc.subs) == 0 {
		impl.Debugf("Disconnecting from %s", ms.broker)
		mc.client.Disconnect(200)
		impl.clientMux.Lock()
		delete(impl.mqttClients, mc.broker)
		impl.clientMux.Unlock()
	}
}

type respFilter struct {
	wildcard bool
}

func (f *respFilter) Process(v interface{}) (value *string, err error) {
	if m, ok := v.(mqtt.Message); !ok {
		err = fmt.Errorf("unexpected traper conversion input type %T", v)
	} else {
		var tmp string
		if f.wildcard {
			j, err := json.Marshal(map[string]string{m.Topic(): string(m.Payload())})
			if err != nil {
				return nil, err
			}
			tmp = string(j)
		} else {
			tmp = string(m.Payload())
		}
		value = &tmp
	}
	return
}

func (ms *mqttSub) NewFilter(key string) (filter watch.EventFilter, err error) {
	return &respFilter{ms.wildCard}, nil
}

func (p *Plugin) EventSourceByKey(key string) (es watch.EventSource, err error) {
	var params []string
	if _, params, err = itemutil.ParseKey(key); err != nil {
		return
	}

	if len(params) != 2 {
		return nil, fmt.Errorf(
			"Incorrect key format for mqtt subscribe. Must be mqtt.get[<broker URL>,<topic>]")
	}

	topic := params[1]
	url, err := parseURL(params[0])
	if err != nil {
		return nil, err
	}

	broker := url.String()
	impl.clientMux.Lock()
	defer impl.clientMux.Unlock()
	if _, ok := p.mqttClients[broker]; !ok {
		impl.Debugf("Creating client options for %s", broker)
		p.mqttClients[broker] = &mqttClient{
			nil, broker, make(map[string]*mqttSub), p.createOptions("Zabbix Agent 2", url.Query().Get("username"),
				url.Query().Get("password"), broker), &sync.RWMutex{}}
	}

	p.mqttClients[broker].subMux.Lock()
	defer p.mqttClients[broker].subMux.Unlock()
	if _, ok := p.mqttClients[broker].subs[topic]; !ok {
		impl.Debugf("Creating subscriber for %s", topic)
		p.mqttClients[broker].subs[topic] = &mqttSub{
			broker, topic, hasWildCards(topic), false}
	}

	return p.mqttClients[broker].subs[topic], nil
}

func hasWildCards(topic string) bool {
	return strings.Contains(topic, "#") || strings.Contains(topic, "+")
}

func parseURL(broker string) (out *url.URL, err error) {
	scheme, urlString, err := web.RemoveScheme(broker)
	if err != nil {
		return nil, err
	}

	if scheme == "" {
		scheme = "tcp"
	}

	if urlString == "" {
		urlString = "localhost"
	}

	out, err = url.Parse(fmt.Sprintf("%s://%s", scheme, web.EncloseIPv6(urlString)))
	if err != nil {
		return
	}

	if out.Port() == "" {
		out.Host = fmt.Sprintf("%s:%d", out.Host, 1883)
	}

	return
}

func init() {
	impl.manager = watch.NewManager(&impl)
	impl.mqttClients = make(map[string]*mqttClient)
	impl.clientMux = &sync.RWMutex{}

	plugin.RegisterMetrics(&impl, "MQTT", "mqtt.get", "Listen on MQTT topics for published messages.")
}

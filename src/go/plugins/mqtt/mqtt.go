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

******************************************************************************
*
* We use the library Eclipse Paho (eclipse/paho.mqtt.golang), which is
* distributed under the terms of the Eclipse Distribution License 1.0 (The 3-Clause BSD License)
* available at https://www.eclipse.org/org/documents/edl-v10.php
*
******************************************************************************

**/

package mqtt

import (
	"encoding/json"
	"fmt"
	"net/url"
	"strings"
	"time"

	mqtt "github.com/eclipse/paho.mqtt.golang"
	"zabbix.com/pkg/itemutil"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/watch"
)

type mqttClient struct {
	client    mqtt.Client
	broker    string
	subs      map[string]*mqttSub
	opts      *mqtt.ClientOptions
	connected bool
}

type mqttSub struct {
	broker   string
	topic    string
	wildCard bool
}

type Plugin struct {
	plugin.Base
	manager     *watch.Manager
	mqttClients map[string]*mqttClient
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

	opts.OnConnectionLost = func(client mqtt.Client, reason error) {
		impl.Errf("Connection lost to %s, reason: %s", broker, reason.Error())
	}
	opts.OnConnect = func(client mqtt.Client) {
		//Will show the query password and username in log
		impl.Debugf("Connected to %s", broker)
		impl.manager.Lock()
		defer impl.manager.Unlock()
		mc, found := p.mqttClients[broker]
		if found {
			mc.connected = true
			for _, ms := range mc.subs {
				if err := ms.subscribe(mc); err != nil {
					impl.Errf("Failed subscribing to %s after connecting to %s\n", ms.topic, broker)
				}
			}
			return
		}
	}

	return opts
}

func newClient(broker string, options *mqtt.ClientOptions) (mqtt.Client, error) {
	c := mqtt.NewClient(options)
	if token := c.Connect(); token.WaitTimeout(60*time.Second) && token.Error() != nil {
		return nil, token.Error()
	}

	return c, nil
}

func (ms *mqttSub) handler(client mqtt.Client, msg mqtt.Message) {
	impl.manager.Lock()
	impl.Tracef("Received publication from %s: [%s] %s", ms.broker, msg.Topic(), string(msg.Payload()))
	impl.manager.Notify(ms, msg)
	impl.manager.Unlock()
}

func (ms *mqttSub) subscribe(mc *mqttClient) error {
	impl.Tracef("Subscribing to %s", ms.broker)
	if token := mc.client.Subscribe(ms.topic, 0, ms.handler); token.WaitTimeout(60*time.Second) && token.Error() != nil {
		return error(token.Error())
	}

	impl.Tracef("Subscribed to %s", ms.broker)
	return nil
}

//Watch MQTT plugin
func (p *Plugin) Watch(requests []*plugin.Request, ctx plugin.ContextProvider) {
	impl.manager.Lock()
	impl.manager.Update(ctx.ClientID(), ctx.Output(), requests)
	impl.manager.Unlock()
}

func (ms *mqttSub) Initialize() (err error) {
	mc, ok := impl.mqttClients[ms.broker]
	if !ok {
		return fmt.Errorf("client missing for broker %s", ms.broker)

	}

	if mc.client == nil {
		impl.Debugf("Creating client for %s", ms.broker)
		mc.client, err = newClient(ms.broker, mc.opts)
		return
	}

	if mc.connected {
		return ms.subscribe(mc)
	}

	return
}

func (ms *mqttSub) Release() {
	mc, ok := impl.mqttClients[ms.broker]
	if !ok || mc == nil || mc.client == nil {
		impl.Errf("Client not found during release for broker %s\n", ms.broker)
		return
	}

	impl.Tracef("Unsubscribing topic from %s", ms.topic)
	if token := mc.client.Unsubscribe(ms.topic); token.WaitTimeout(60*time.Second) && token.Error() != nil {
		impl.Errf("Failed to unsubscribe from %s:%s", ms.topic, token.Error())
	}

	delete(mc.subs, ms.topic)
	impl.Tracef("Unsubscribed from %s", ms.topic)
	if len(mc.subs) == 0 {
		impl.Debugf("Disconnecting from %s", ms.broker)
		mc.client.Disconnect(200)
		delete(impl.mqttClients, mc.broker)
	}
}

type respFilter struct {
	wildcard bool
}

func (f *respFilter) Process(v interface{}) (*string, error) {
	m, ok := v.(mqtt.Message)
	if !ok {
		return nil, fmt.Errorf("unexpected mqtt response conversion input type %T", v)
	}

	var value string
	if f.wildcard {
		j, err := json.Marshal(map[string]string{m.Topic(): string(m.Payload())})
		if err != nil {
			return nil, err
		}
		value = string(j)
	} else {
		value = string(m.Payload())
	}

	return &value, nil
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
		return nil, fmt.Errorf("Incorrect key format for mqtt subscribe. Must be mqtt.get[<broker URL>,<topic>]")
	}

	topic := params[1]
	url, err := parseURL(params[0])
	if err != nil {
		return nil, err
	}

	broker := url.String()
	if _, ok := p.mqttClients[broker]; !ok {
		impl.Tracef("Creating client options for %s", broker)
		p.mqttClients[broker] = &mqttClient{
			nil, broker, make(map[string]*mqttSub), p.createOptions("Zabbix Agent 2", url.Query().Get("username"),
				url.Query().Get("password"), broker), false}
	}

	if _, ok := p.mqttClients[broker].subs[topic]; !ok {
		impl.Tracef("Creating subscriber for %s", topic)
		p.mqttClients[broker].subs[topic] = &mqttSub{
			broker, topic, hasWildCards(topic)}
	}

	return p.mqttClients[broker].subs[topic], nil
}

func hasWildCards(topic string) bool {
	return strings.Contains(topic, "#") || strings.Contains(topic, "+")
}

func parseURL(broker string) (out *url.URL, err error) {
	if len(broker) > 0 && broker[0] == ':' {
		broker = "localhost" + broker
	}

	if !strings.Contains(broker, "://") {
		broker = "tcp://" + broker
	}

	out, err = url.Parse(broker)
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

	plugin.RegisterMetrics(&impl, "MQTT", "mqtt.get", "Listen on MQTT topics for published messages.")
}

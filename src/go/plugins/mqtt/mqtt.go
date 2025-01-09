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

/*
** We use the library Eclipse Paho (eclipse/paho.mqtt.golang), which is
** distributed under the terms of the Eclipse Distribution License 1.0 (The 3-Clause BSD License)
** available at https://www.eclipse.org/org/documents/edl-v10.php
**/

package mqtt

import (
	"crypto/tls"
	"encoding/json"
	"errors"
	"fmt"
	"math/rand"
	"net/url"
	"strings"
	"time"

	mqtt "github.com/eclipse/paho.mqtt.golang"
	"golang.zabbix.com/agent2/pkg/itemutil"
	"golang.zabbix.com/agent2/pkg/watch"
	"golang.zabbix.com/sdk/metric"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/tlsconfig"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	pluginName = "MQTT"
)

type mqttClient struct {
	client    mqtt.Client
	broker    broker
	subs      map[string]*mqttSub
	opts      *mqtt.ClientOptions
	connected bool
}

type mqttSub struct {
	broker   broker
	topic    string
	wildCard bool
}

type broker struct {
	url      string
	username string
	password string
}
type Plugin struct {
	plugin.Base
	options     Options
	manager     *watch.Manager
	mqttClients map[broker]*mqttClient
}

var impl Plugin

func (p *Plugin) createOptions(
	clientid, username, password string, b broker, details tlsconfig.Details) (*mqtt.ClientOptions, error) {
	opts := mqtt.NewClientOptions().AddBroker(b.url).SetClientID(clientid).SetCleanSession(true).SetConnectTimeout(
		time.Duration(impl.options.Timeout) * time.Second)
	if username != "" {
		opts.SetUsername(username)
		if password != "" {
			opts.SetPassword(password)
		}
	}

	opts.OnConnectionLost = func(client mqtt.Client, reason error) {
		impl.Warningf("connection lost to [%s]: %s", b.url, reason.Error())
	}

	opts.OnConnect = func(client mqtt.Client) {
		impl.Debugf("connected to [%s]", b.url)

		impl.manager.Lock()
		defer impl.manager.Unlock()

		mc, ok := p.mqttClients[b]
		if !ok || mc == nil || mc.client == nil {
			impl.Warningf("cannot subscribe to [%s]: broker is not connected", b.url)
			return
		}

		mc.connected = true
		for _, ms := range mc.subs {
			if err := ms.subscribe(mc); err != nil {
				impl.Warningf("cannot subscribe topic '%s' to [%s]: %s", ms.topic, b.url, err)
				impl.manager.Notify(ms, err)
			}
		}
	}

	t, err := getTlsConfig(details)
	if err != nil {
		return nil, err
	}

	opts.SetTLSConfig(t)

	return opts, nil
}

func getTlsConfig(d tlsconfig.Details) (*tls.Config, error) {
	if d.TlsCaFile == "" && d.TlsCertFile == "" && d.TlsKeyFile == "" {
		return nil, nil
	}

	return tlsconfig.CreateConfig(
		tlsconfig.Details{
			TlsCaFile:   d.TlsCaFile,
			TlsCertFile: d.TlsCertFile,
			TlsKeyFile:  d.TlsKeyFile,
			RawUri:      d.RawUri,
		},
		false,
	)
}

func newClient(options *mqtt.ClientOptions) (mqtt.Client, error) {
	c := mqtt.NewClient(options)
	token := c.Connect()
	if !token.WaitTimeout(time.Duration(impl.options.Timeout) * time.Second) {
		c.Disconnect(200)
		return nil, fmt.Errorf("timed out while connecting")
	}

	if token.Error() != nil {
		return nil, token.Error()
	}

	return c, nil
}

func (ms *mqttSub) handler(client mqtt.Client, msg mqtt.Message) {
	impl.manager.Lock()
	impl.Tracef("received publish from [%s] on topic '%s' got: %s", ms.broker.url, msg.Topic(), string(msg.Payload()))
	impl.manager.Notify(ms, msg)
	impl.manager.Unlock()
}

func (ms *mqttSub) subscribe(mc *mqttClient) error {
	impl.Tracef("subscribing '%s' to [%s]", ms.topic, ms.broker.url)

	token := mc.client.Subscribe(ms.topic, 0, ms.handler)
	if !token.WaitTimeout(time.Duration(impl.options.Timeout) * time.Second) {
		return fmt.Errorf("timed out while subscribing")
	}

	if token.Error() != nil {
		return token.Error()
	}

	impl.Tracef("subscribed '%s' to [%s]", ms.topic, ms.broker.url)
	return nil
}

// Watch MQTT plugin
func (p *Plugin) Watch(items []*plugin.Item, ctx plugin.ContextProvider) {
	impl.manager.Lock()
	impl.manager.Update(ctx.ClientID(), ctx.Output(), items)
	impl.manager.Unlock()
}

func (ms *mqttSub) Initialize() (err error) {
	mc, ok := impl.mqttClients[ms.broker]
	if !ok || mc == nil {
		return fmt.Errorf("Cannot connect to [%s]: broker could not be initialized", ms.broker.url)
	}

	if mc.client == nil {
		impl.Debugf("establishing connection to [%s]", ms.broker.url)
		mc.client, err = newClient(mc.opts)
		if err != nil {
			impl.Warningf("cannot establish connection to [%s]: %s", ms.broker.url, err)
			return
		}

		impl.Debugf("established connection to [%s]", ms.broker.url)
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
		impl.Errf("cannot release [%s]: broker was not initialized", ms.broker.url)
		return
	}

	impl.Tracef("unsubscribing topic '%s' from [%s]", ms.topic, ms.broker.url)
	token := mc.client.Unsubscribe(ms.topic)
	if !token.WaitTimeout(time.Duration(impl.options.Timeout) * time.Second) {
		impl.Errf("cannot unsubscribe topic '%s' from [%s]: timed out", ms.topic, ms.broker.url)
	}

	if token.Error() != nil {
		impl.Errf("cannot unsubscribe topic '%s' from [%s]: %s", ms.topic, ms.broker.url, token.Error())
	}

	delete(mc.subs, ms.topic)
	impl.Tracef("unsubscribed topic '%s' from [%s]", ms.topic, ms.broker.url)
	if len(mc.subs) == 0 {
		impl.Debugf("disconnecting from [%s]", ms.broker.url)
		mc.client.Disconnect(200)
		delete(impl.mqttClients, mc.broker)
	}
}

type respFilter struct {
	wildcard bool
}

func (f *respFilter) Process(v interface{}) (s *string, err error) {
	m, ok := v.(mqtt.Message)
	if !ok {
		if err, ok = v.(error); !ok {
			err = fmt.Errorf("unexpected input type %T", v)
		}
		return
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

func (p *Plugin) EventSourceByKey(rawKey string) (es watch.EventSource, err error) {
	var key string
	var raw []string
	if key, raw, err = itemutil.ParseKey(rawKey); err != nil {
		return
	}

	params, _, hc, err := metrics[key].EvalParams(raw, p.options.Sessions)
	if err != nil {
		return nil, err
	}

	err = metric.SetDefaults(params, hc, p.options.Default)
	if err != nil {
		return nil, err
	}

	topic := params["Topic"]
	username := params["User"]
	password := params["Password"]
	url, err := parseURL(params["URL"])
	if err != nil {
		return nil, err
	}

	if topic == "" {
		return nil, zbxerr.ErrorTooFewParameters.Wrap(errors.New("second parameter \"Topic\" is required."))
	}

	broker := broker{url.String(), username, password}
	var client *mqttClient
	var ok bool

	opt, err := p.createOptions(
		getClientID(rand.NewSource(time.Now().UnixNano())),
		username,
		password,
		broker,
		tlsconfig.Details{
			TlsCaFile:   params["TLSCAFile"],
			TlsCertFile: params["TLSCertFile"],
			TlsKeyFile:  params["TLSKeyFile"],
			RawUri:      url.String(),
		},
	)

	if err != nil {
		return nil, err
	}

	if client, ok = p.mqttClients[broker]; !ok {
		impl.Tracef("creating client for [%s]", broker.url)
		client = &mqttClient{
			nil,
			broker,
			make(map[string]*mqttSub),
			opt,
			false,
		}
		p.mqttClients[broker] = client
	}

	var sub *mqttSub
	if sub, ok = client.subs[topic]; !ok {
		impl.Tracef("creating new subscriber on topic '%s' for [%s]", topic, broker.url)

		sub = &mqttSub{broker, topic, hasWildCards(topic)}
		client.subs[topic] = sub
	}

	return sub, nil
}

func getClientID(src rand.Source) string {
	const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"

	var result = make([]byte, 8)

	//nolint:gosec
	// we are okey with using a weaker random number generator as this is not intended to be a secure token
	r := rand.New(src)

	for i := range result {
		result[i] = charset[r.Intn(len(charset))]
	}

	return "ZabbixAgent2" + string(result)
}

func hasWildCards(topic string) bool {
	return strings.HasSuffix(topic, "#") || strings.Contains(topic, "+")
}

func parseURL(rawUrl string) (out *url.URL, err error) {
	if !strings.Contains(rawUrl, "://") {
		rawUrl = "tcp://" + rawUrl
	}

	out, err = url.Parse(rawUrl)
	if err != nil {
		return
	}

	if out.Port() != "" && out.Hostname() == "" {
		return nil, errors.New("Host is required.")
	}

	if out.Port() == "" {
		out.Host = fmt.Sprintf("%s:1883", out.Host)
	}

	if len(out.Query()) > 0 {
		return nil, errors.New("URL should not contain query parameters.")
	}

	return
}

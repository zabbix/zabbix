package mqtt

import (
	"crypto/tls"
	"encoding/json"
	"fmt"
	"strings"

	mqtt "github.com/eclipse/paho.mqtt.golang"
	"zabbix.com/pkg/itemutil"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/watch"
)

type mqttClient struct {
	client mqtt.Client
	broker string
	subs   map[string]*mqttSub
	opts   *mqtt.ClientOptions
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
		p.Errf("Connection lost to %s, reason: %s", broker, reason.Error())
	}
	opts.OnConnect = func(client mqtt.Client) {
		p.Infof("Connected to %s", broker)
		mc, found := p.mqttClients[broker]
		if found {
			if mc.broker == broker {
				for _, ms := range mc.subs {
					if ms.subscribed {
						err := ms.subscribe(mc)
						if err != nil {
							p.Errf("Failed subscribing to %s after connecting to %s\n", ms.topic, broker)
						}
					}

				}
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

func (ms *mqttSub) subscribe(mc *mqttClient) error {
	token := mc.client.Subscribe(
		ms.topic, 0, func(client mqtt.Client, msg mqtt.Message) {
			impl.manager.Lock()
			resp[msg.Topic()] = string(msg.Payload())
			//add the msg to debug?
			fmt.Printf("* [%s] %s\n", msg.Topic(), string(msg.Payload()))
			impl.manager.Notify(ms, msg)
			impl.manager.Unlock()
		})

	if token.Wait() && token.Error() != nil {
		return error(token.Error())
	}

	ms.subscribed = true

	return nil
}

//Watch MQTT plugin
func (p *Plugin) Watch(requests []*plugin.Request, ctx plugin.ContextProvider) {
	p.manager.Lock()
	for broker, mc := range impl.mqttClients {
		if mc != nil && mc.client != nil && !mc.client.IsConnected() {
			delete(impl.mqttClients, broker)
		}
	}
	p.manager.Update(ctx.ClientID(), ctx.Output(), requests)
	p.manager.Unlock()
}

var resp = make(map[string]string)

func (ms *mqttSub) Initialize() (err error) {
	mc, ok := impl.mqttClients[ms.broker]
	if !ok {
		return fmt.Errorf("mqtt client missing for broker %s", ms.broker)

	}

	if mc.client == nil {
		mc.client, err = newClient(ms.broker, mc.opts)
		if err != nil {
			return err
		}

	}
	return ms.subscribe(mc)
}

func (ms *mqttSub) Release() {
	mc, ok := impl.mqttClients[ms.broker]
	if !ok || mc == nil || mc.client == nil {
		impl.Errf("MQTT client not found during release for broker %s\n", ms.broker)
	}
	mc.client.Unsubscribe(ms.topic)
	delete(mc.subs, ms.topic)
	if len(mc.subs) == 0 {
		mc.client.Disconnect(200)
		delete(impl.mqttClients, mc.broker)
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
				fmt.Println("err", err.Error())
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

	broker := params[0]
	topic := params[1]
	if _, ok := p.mqttClients[broker]; !ok {
		//TODO: parse url for options (pwd, username, borker) password and options should be set later to allow multiple different
		p.mqttClients[broker] = &mqttClient{nil, broker, make(map[string]*mqttSub), p.createOptions("Zabbix Agent 2", "", "", broker)}
	}

	if _, ok := p.mqttClients[broker].subs[topic]; !ok {
		p.mqttClients[broker].subs[topic] = &mqttSub{
			broker, topic, hasWildCards(topic), false}
	}

	return p.mqttClients[broker].subs[topic], nil
}

func hasWildCards(topic string) bool {
	return strings.Contains(topic, "#") || strings.Contains(topic, "+")
}

func init() {
	impl.manager = watch.NewManager(&impl)
	impl.mqttClients = make(map[string]*mqttClient)

	plugin.RegisterMetrics(&impl, "MQTT", "mqtt.get", "Listen on MQTT topics for published messages.")
}

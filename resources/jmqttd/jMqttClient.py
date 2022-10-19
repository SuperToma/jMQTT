# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.

from binascii import b2a_base64
import logging
import queue
import sys
import threading
from os import unlink
from tempfile import NamedTemporaryFile
from zlib import decompress as zlib_decompress

# import AddLogging

try:
	import paho.mqtt.client as mqtt
except ImportError:
	print("Error: importing module paho.mqtt")
	sys.exit(1)


class jMqttClient:
	def __init__(self, jcom, message):
		self._log       = logging.getLogger('Client')
#		self._log.debug('jMqttClient.init(): message=%r', message)
		self.jcom       = jcom
		self.message    = message
		self.mqttclient = None

	def on_connect(self, client, userdata, flags, rc):
		self.connected = True
		if self.mqttlwt:
			client.will_set(self.mqttlwt_topic, self.mqttlwt_offline, 1, True)
			client.publish(self.mqttlwt_topic, self.mqttlwt_online, 1, True)
		self._log.info('Connected to broker %s:%d', self.mqtthostname, self.mqttport)
		with self.mqttsub_lock:
			for topic in self.mqttsubscribedtopics:
				self.subscribe_topic(topic, self.mqttsubscribedtopics[topic], False)
		self.jcom.send_async({"cmd":"brokerUp","id":self.id})

	def on_disconnect(self, client, userdata, rc):
		self.connected = False
		self.jcom.send_async({"cmd":"brokerDown","id":self.id})
		if rc == mqtt.MQTT_ERR_SUCCESS:
			self._log.info('Disconnected from broker.')
		else:
			self._log.error('Unexpected disconnection from broker!')

	def on_message(self, client, userdata, message):
		try:
			usablePayload = message.payload.decode('utf-8')
			form = '' # Successfully decoded as utf8
		except:
			try: # jMQTT will try automaticaly to decompress the payload (requested in issue #135)
				unzip = zlib_decompress(message.payload, wbits=-15)
				usablePayload = unzip.decode('utf-8')
				form = ' (decompressed)'
			except: # If payload cannot be decoded or decompressed it is returned in base64
				usablePayload = b2a_base64(message.payload, newline=False).decode('utf-8')
				form = ' (bin in base64)'
		self._log.info('Message received (topic="%s", payload="%s"%s, QoS=%s, retain=%s)', message.topic, usablePayload, form, message.qos, bool(message.retain))
		self.jcom.send_async({"cmd":"messageIn","id":self.id,"topic":message.topic,"payload":usablePayload,"qos":message.qos,"retain":bool(message.retain)})

	def subscribe_topic(self, topic, qos, lock=True):
		try:
			res = self.mqttclient.subscribe(topic, qos)
			if res[0] == mqtt.MQTT_ERR_SUCCESS or res[0] == mqtt.MQTT_ERR_NO_CONN:
				if lock:
					with self.mqttsub_lock:
						self.mqttsubscribedtopics[topic] = qos
				self._log.info('Topic subscribed "%s"', topic)
				return
		except ValueError: # Only catch ValueError
			pass
		self._log.error('Topic subscription failed "%s"', topic)

	def unsubscribe_topic(self, topic):
		with self.mqttsub_lock:
			if topic not in self.mqttsubscribedtopics:
				self._log.info('Cannot unsubscribe not subscribed topic "%s"', topic)
				return
			try:
				res = self.mqttclient.unsubscribe(topic)
				if res[0] == mqtt.MQTT_ERR_SUCCESS or res[0] == mqtt.MQTT_ERR_NO_CONN:
					del self.mqttsubscribedtopics[topic]
					self._log.info('Topic unsubscribed "%s"', topic)
					return
			except ValueError: # Only catch ValueError
				pass
			self._log.error('Topic unsubscription failed "%s"', topic)

	def publish(self, topic, payload, qos, retain):
		if self.mqttclient is None:
			self._log.info('Could not send message Broker not started')
			return
		self.mqttclient.publish(topic, payload, qos, retain)
		# Python Client : publish(topic, payload=None, qos=0, retain=False)
		# Returns a MQTTMessageInfo which expose the following attributes and methods:
		#  - rc, the result of the publishing. It could be MQTT_ERR_SUCCESS to indicate success, MQTT_ERR_NO_CONN if the client is not currently connected, or MQTT_ERR_QUEUE_SIZE when max_queued_messages_set is used to indicate that message is neither queued nor sent.
		#  - mid is the message ID for the publish request. The mid value can be used to track the publish request by checking against the mid argument in the on_publish() callback if it is defined. wait_for_publish may be easier depending on your use-case.
		#  - wait_for_publish() will block until the message is published. It will raise ValueError if the message is not queued (rc == MQTT_ERR_QUEUE_SIZE).
		#  - is_published returns True if the message has been published. It will raise ValueError if the message is not queued (rc == MQTT_ERR_QUEUE_SIZE).
		#  - A ValueError will be raised if topic is None, has zero length or is invalid (contains a wildcard), if qos is not one of 0, 1 or 2, or if the length of the payload is greater than 268435455 bytes.
		self._log.info('Sending message to broker (topic="%s", payload="%s", QoS=%s, retain=%s)', topic, payload, qos, retain)

	def start(self):
		if self.mqttclient is not None:
			self._log.info('jMqttClient already started (start ignored), should have used restart?')
			return
		self.id = self.message['id']
		self._log = logging.getLogger('Client'+self.id)
		self.mqtthostname = self.message['hostname']
		if 'proto' not in self.message:
			self.message['proto'] = 'mqtt'
		if 'port' not in self.message:
			self.message['port'] = ''
		if self.message['port'] == '':
			self.mqttport = {'mqtt': 1883, 'mqtts': 8883, 'ws': 1884, 'wss': 8884}.get(message['proto'], 1883)
		self.mqttport = self.message['port'] if 'port' in self.message else 1883
		self.mqttlwt = self.message['lwt']
		self.mqttlwt_topic = self.message['lwtTopic']
		self.mqttlwt_online = self.message['lwtOnline']
		self.mqttlwt_offline = self.message['lwtOffline']
		if 'clientid' not in self.message:
			self.message['clientid'] = ''
		if 'username' not in self.message:
			self.message['username'] = ''
		if 'password' not in self.message:
			self.message['password'] = ''
		self.mqttsub_lock = threading.Lock()
		self.mqttsubscribedtopics = {}
		self.connected = False
#		self._log.debug('jMqttClient.init() SELF dump: %r', [(attr, getattr(self, attr)) for attr in vars(self) if not callable(getattr(self, attr)) and not attr.startswith("__")])

		# Create MQTT Client
		if self.message['proto'].startswith('ws'):
			self.mqttclient = mqtt.Client(self.message['clientid'], transport="websockets")
		else:
			self.mqttclient = mqtt.Client(self.message['clientid'])
		# Enable Paho logging functions
		if self._log.isEnabledFor(logging.VERBOSE):
			self.mqttclient.enable_logger(self._log)
		else:
			self.mqttclient.disable_logger()
		if self.message['username'] != '':
			if self.message['password'] != '':
				self.mqttclient.username_pw_set(self.message['username'], self.message['password'])
			else:
				self.mqttclient.username_pw_set(self.message['username'])
		if self.message['proto'] == 'mqtts':
			try:
				ca = NamedTemporaryFile(delete=False)
				ca.write(str.encode(self.message['tlsca']))
				ca.close()
				cert = NamedTemporaryFile(delete=False)
				cert.write(str.encode(self.message['tlsclicert']))
				cert.close()
				key = NamedTemporaryFile(delete=False)
				key.write(str.encode(self.message['tlsclikey']))
				key.close()
				self.mqttclient.tls_set(ca_certs=ca.name, certfile=cert.name, keyfile=key.name)
				self.mqttclient.tls_insecure_set(('tlsinsecure' in self.message) and self.message['tlsinsecure'])
				unlink(ca.name)
				unlink(cert.name)
				unlink(key.name)
			except:
				self._log.exception('Fatal TLS Certificate import Exception, this connection will most likely fail!')

		self.mqttclient.reconnect_delay_set(5, 15)
		self.mqttclient.on_connect = self.on_connect
		self.mqttclient.on_disconnect = self.on_disconnect
		self.mqttclient.on_message = self.on_message
		try:
			self.mqttclient.connect(self.mqtthostname, self.mqttport, 30)
			self.mqttclient.loop_start()
			if self.mqttclient._thread is not None:
				self.mqttclient._thread.name = 'Brk' + self.id + 'Th'
		except Exception as e:
			if self._log.isEnabledFor(logging.DEBUG):
				self._log.exception('jMqttClient.start() Exception')
			else:
				self._log.error('Could not start MQTT client: %s', e)

	def stop(self):
		if self.mqttclient is not None:
			if self.mqttlwt:
				self.mqttclient.publish(self.mqttlwt_topic, self.mqttlwt_offline, 1, True)
			self.mqttclient.disconnect()
			self.mqttclient.loop_stop()
			self.mqttclient = None
		self._log.debug('jMqttClient ended')

	def restart(self, message=None):
		if message is not None:
			self.message = message
		self.stop()
		self.start()


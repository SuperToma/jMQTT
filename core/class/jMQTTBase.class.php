<?php
/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';
require_once __DIR__  . '/../../resources/mosquitto_topic_matches_sub.php';

class jMQTTBase {

   const DEFAULT_PYTHON_PORT = 1025;
   const DEFAULT_WEBSOCKET_PORT = 1026;

   // Do not rely on backtrace as it is designed for debug and performances cannot be guaranteed
   // private static function get_calling_class() {
   //    $backtrace = debug_backtrace();
   //    foreach ($backtrace as $stackFrame) {
   //       if (array_key_exists('class', $stackFrame) && $stackFrame['class'] != __CLASS__) return $stackFrame['class'];
   //    }
   // }

   private static function get_default_python_port($pluginClass) {
      return property_exists($pluginClass, 'DEFAULT_PYTHON_PORT') ? $pluginClass::DEFAULT_PYTHON_PORT : self::DEFAULT_PYTHON_PORT;
   }
   private static function get_default_websocket_port($pluginClass) {
      return property_exists($pluginClass, 'DEFAULT_WEBSOCKET_PORT') ? $pluginClass::DEFAULT_WEBSOCKET_PORT : self::DEFAULT_WEBSOCKET_PORT;
   }

   public static function dependancy_info($pluginClass) {
      return jMQTT::dependancy_info();
   }

   public static function dependancy_install($pluginClass) {
      return array();
   }


   public static function deamon_info($pluginClass) {
      $return = array();
      $return['log'] = $pluginClass;
      $return['state'] = 'nok';
      $return['launchable'] = 'nok';

      $python_daemon = false;
      $websocket_daemon = false;

      $pid_file1 = jeedom::getTmpFolder($pluginClass) . '/jmqttd.py.pid';
      if (file_exists($pid_file1)) {
         if (@posix_getsid(trim(file_get_contents($pid_file1)))) {
            $python_daemon = true;
         } else {
            shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file1 . ' 2>&1 > /dev/null');
         }
      }

      $pid_file2 = jeedom::getTmpFolder($pluginClass) . '/jmqttd.php.pid';
      if (file_exists($pid_file2)) {
         if (@posix_getsid(trim(file_get_contents($pid_file2)))) {
            $websocket_daemon = true;
         } else {
            shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file2 . ' 2>&1 > /dev/null');
         }
      }

      if($python_daemon && $websocket_daemon){
         $return['state'] = 'ok';
      }

      if (config::byKey('pythonsocketport', $pluginClass, self::get_default_python_port($pluginClass)) != config::byKey('websocketport', $pluginClass, self::get_default_websocket_port($pluginClass))) {
         $return['launchable'] = 'ok';
      }
      return $return;
   }

   public static function deamon_start($pluginClass) {
      self::deamon_stop($pluginClass);
      $daemon_info = self::deamon_info($pluginClass);
      if ($daemon_info['launchable'] != 'ok') {
         throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
      }

      // Get default ports for daemons
      $defaultPythonPort = self::get_default_python_port($pluginClass);
      $defaultWebSocketPort = self::get_default_websocket_port($pluginClass);

      // Check python daemon port is available
      $output=null;
      $retval=null;
      exec(system::getCmdSudo() . 'fuser ' . config::byKey('pythonsocketport', $pluginClass, $defaultPythonPort) . '/tcp', $output, $retval);
      if ($retval == 0 && count($output) > 0) {
         $pid = trim($output[0]);
         unset($output);
         exec(system::getCmdSudo() . 'ps -p ' . $pid . ' -o command=', $output, $retval);
         if ($retval == 0 && count($output) > 0) $commandline = $output[0];
         throw new Exception(__('Le port du démon python (' . config::byKey('pythonsocketport', $pluginClass, $defaultPythonPort) . ') est déjà utilisé par le pid ' . $pid . ' : ' . $commandline, __FILE__));
      }

      // Check websocket daemon port is available
      $output=null;
      $retval=null;
      exec(system::getCmdSudo() . 'fuser ' . config::byKey('websocketport', $pluginClass, $defaultWebSocketPort) . '/tcp', $output, $retval);
      if ($retval == 0 && count($output) > 0) {
         $pid = trim($output[0]);
         unset($output);
         exec(system::getCmdSudo() . 'ps -p ' . $pid . ' -o command=', $output, $retval);
         if ($retval == 0 && count($output) > 0) $commandline = $output[0];
         throw new Exception(__('Le port du démon websocket (' . config::byKey('websocketport', $pluginClass, $defaultWebSocketPort) . ') est déjà utilisé par le pid ' . $pid . ' : ' . $commandline, __FILE__));
      }

      // Start Python daemon
      $path1 = realpath(dirname(__FILE__) . '/../../resources/jmqttd');
      $cmd1 = 'python3 ' . $path1 . '/jmqttd.py';
      $cmd1 .= ' --plugin ' . $pluginClass;
      $cmd1 .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel($pluginClass));
      $cmd1 .= ' --socketport ' . config::byKey('pythonsocketport', $pluginClass, $defaultPythonPort);
      $cmd1 .= ' --apikey ' . jeedom::getApiKey($pluginClass);
      $cmd1 .= ' --pid ' . jeedom::getTmpFolder($pluginClass) . '/jmqttd.py.pid';
      log::add($pluginClass, 'info', 'Lancement du démon python jMQTT pour le plugin '.$pluginClass);
      $result1 = exec($cmd1 . ' >> ' . log::getPathToLog($pluginClass.'_daemon') . ' 2>&1 &');

      // Start WebSocket daemon 
      $path2 = realpath(dirname(__FILE__) . '/../../resources/jmqttd/');
      $cmd2 = 'php ' . $path2 . '/jmqttd.php';
      $cmd2 .= ' --plugin ' . $pluginClass;
      $cmd2 .= ' --socketport ' . config::byKey('websocketport', $pluginClass, $defaultWebSocketPort);
      $cmd2 .= ' --pid ' . jeedom::getTmpFolder($pluginClass) . '/jmqttd.php.pid';
      log::add($pluginClass, 'info', 'Lancement du démon websocket jMQTT pour le plugin '.$pluginClass);
      $result2 = exec($cmd2 . ' >> ' . log::getPathToLog($pluginClass) . ' 2>&1 &');

      //wait up to 10 seconds for daemons start
      for ($i = 1; $i <= 40; $i++) {
         $daemon_info = self::deamon_info($pluginClass);
         if ($daemon_info['state'] == 'ok') break;
         usleep(250000);
      }

      if ($daemon_info['state'] != 'ok') {
         // If only one of both daemon runs we still need to stop
         self::deamon_stop($pluginClass);
         log::add($pluginClass, 'error', __('Impossible de lancer le démon jMQTT, vérifiez le log',__FILE__), 'unableStartDaemon');
         return false;
      }
      message::removeAll($pluginClass, 'unableStartDaemon');
      return true;
   }

   public static function deamon_stop($pluginClass) {
      $pid_file1 = jeedom::getTmpFolder($pluginClass) . '/jmqttd.py.pid';
      if (file_exists($pid_file1)) {
         $pid1 = intval(trim(file_get_contents($pid_file1)));
         system::kill($pid1, false);
         //wait up to 10 seconds for python daemon stop
         for ($i = 1; $i <= 40; $i++) {
            if (! @posix_getsid($pid1)) break;
            usleep(250000);
         }
      }
      $pid_file2 = jeedom::getTmpFolder($pluginClass) . '/jmqttd.php.pid';
      if (file_exists($pid_file2)) {
         $pid2 = intval(trim(file_get_contents($pid_file2)));
         system::kill($pid2, false);
         //wait up to 10 seconds for websocket daemon stop
         for ($i = 1; $i <= 40; $i++) {
            if (! @posix_getsid($pid2)) break;
            usleep(250000);
         }
      }
   }

   public static function log_missing_callback($pluginClass, $functionName) {
      switch ($functionName) {
         case 'on_daemon_connect':
            log::add($pluginClass, 'debug', 'You need to implement "public static function on_daemon_connect($id)" in the class \''.$pluginClass.'\' to handle daemon connect event.');
            break;
         case 'on_daemon_disconnect':
            log::add($pluginClass, 'debug', 'You need to implement "public static function on_daemon_disconnect($id)" in the class \''.$pluginClass.'\' to handle daemon disconnect event.');
            break;
         case 'on_mqtt_connect':
            log::add($pluginClass, 'debug', 'You need to implement "public static function on_mqtt_connect($id)" in the class \''.$pluginClass.'\' to handle mqtt connect event.');
            break;
         case 'on_mqtt_disconnect':
            log::add($pluginClass, 'debug', 'You need to implement "public static function on_mqtt_disconnect($id)" in the class \''.$pluginClass.'\' to handle mqtt disconnect event.');
            break;
         case 'on_mqtt_message':
            log::add($pluginClass, 'debug', 'You need to implement "public static function on_mqtt_message($id, $topic, $payload, $qos, $retain)" in the class \''.$pluginClass.'\' to handle mqtt messages.');
            break;
         default:
         log::add($pluginClass, 'debug', 'You need to implement ... "public static ... function on_mq... What?! You should have never came back here! Don\'t read this! The faulty plugin is here => \''.$pluginClass . '\'');
            break;
      }
   }

   protected static function send_to_mqtt_daemon($pluginClass, $params) {
      $daemon_info = self::deamon_info($pluginClass);
      if ($daemon_info['state'] != 'ok') {
         throw new Exception("Le démon n'est pas démarré");
      }
      $params['apikey'] = jeedom::getApiKey($pluginClass);
      $payload = json_encode($params);
      $socket = socket_create(AF_INET, SOCK_STREAM, 0);
      socket_connect($socket, '127.0.0.1', config::byKey('pythonsocketport', $pluginClass, self::get_default_python_port($pluginClass)));
      socket_write($socket, $payload, strlen($payload));
      socket_close($socket);
   }

   public static function new_mqtt_client($pluginClass, $id, $hostname, $params = array()) {
      $params['cmd']                  = 'newMqttClient';
      $params['id']                   = $id;
      $params['hostname']             = $hostname;
      $params['callback']             = 'ws://127.0.0.1:'.config::byKey('websocketport', $pluginClass, self::get_default_websocket_port($pluginClass)).'/plugins/jMQTT/resources/jmqttd/jmqttd.php';
      if       ($params['tls'] == 'custom') {
         $params['tls']               = True;
      } elseif ($params['tls'] == 'enable') {
         $params['tls']               = True;
         $params['tlscafile']         = '';
      } else {
         $params['tls']               = False;
         $params['tlssecure']         = '0';
         $params['tlscafile']         = '';
         $params['tlsclicertfile']    = '';
         $params['tlsclikeyfile']     = '';
      }
      // set port IF (port not 0 and numeric) THEN (intval) ELSE (default for TLS and clear MQTT) #DoubleTernaryAreCute
      $params['port']=($params['port'] != 0 && is_numeric($params['port'])) ? intval($params['port']) : (($params['tls']) ? 8883 : 1883);

      self::send_to_mqtt_daemon($pluginClass, $params);
   }

   public static function remove_mqtt_client($pluginClass, $id) {
      $params['cmd']='removeMqttClient';
      $params['id']=$id;
      self::send_to_mqtt_daemon($params);
   }

   public static function subscribe_mqtt_topic($pluginClass, $id, $topic, $qos = 1) {
      if (empty($topic)) return;
      $params['cmd']='subscribeTopic';
      $params['id']=$id;
      $params['topic']=$topic;
      $params['qos']=$qos;
      self::send_to_mqtt_daemon($pluginClass, $params);
   }

   public static function unsubscribe_mqtt_topic($pluginClass, $id, $topic) {
      if (empty($topic)) return;
      $params['cmd']='unsubscribeTopic';
      $params['id']=$id;
      $params['topic']=$topic;
      self::send_to_mqtt_daemon($pluginClass, $params);
   }

   public static function publish_mqtt_message($pluginClass, $id, $topic, $payload, $qos = 1, $retain = false) {
      if (empty($topic)) return;
      $params['cmd']='messageOut';
      $params['id']=$id;
      $params['topic']=$topic;
      $params['payload']=$payload;
      $params['qos']=$qos;
      $params['retain']=$retain;
      self::send_to_mqtt_daemon($pluginClass, $params);
   }
}

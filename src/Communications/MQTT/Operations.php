<?php

declare(strict_types=1);

namespace unreal4u\rpiCommonLibrary\Communications\MQTT;

use unreal4u\MQTT\Client;
use unreal4u\MQTT\DataTypes\ClientId;
use unreal4u\MQTT\DataTypes\Message;
use unreal4u\MQTT\DataTypes\QoSLevel;
use unreal4u\MQTT\DataTypes\TopicName;
use unreal4u\MQTT\Protocol\Connect;
use unreal4u\MQTT\Protocol\Connect\Parameters;
use unreal4u\MQTT\Protocol\Publish;
use unreal4u\rpiCommonLibrary\Communications\Communications;

final class Operations extends Communications {
    /**
     * @var Client
     */
    private $mqttClient;

    private function createMQTTConnection(): self
    {
        if ($this->mqttClient !== null) {
            return $this;
        }

        if (!defined('MQTT_HOST')) {
            throw new \RuntimeException('A host must be provided');
        }

        $this->mqttClient = new Client();

        $connect = new Connect();
        $parameters = new Parameters(new ClientId($this->internalName . '-' . crc32(time())), MQTT_HOST);
        if (defined('MQTT_USERNAME') && defined('MQTT_PASSWORD')) {
            $parameters->setCredentials(MQTT_USERNAME, MQTT_PASSWORD);
        }

        $connect->setConnectionParameters($parameters);
        $this->mqttClient->processObject($connect);

        return $this;
    }

    /**
     * @param string $topicName
     * @param string $payload
     * @return bool
     * @throws \unreal4u\MQTT\Exceptions\ServerClosedConnection
     */
    public function sendMessage(string $topicName, string $payload): bool
    {
        $this->createMQTTConnection();
        $message = new Message($payload, new TopicName($topicName));

        // House rule: all last values sensor data MUST be retained by the broker
        if (strpos($topicName, 'sensors/') === 0) {
            $message->setRetainFlag(true);
        }

        // House rule: all sent commands MUST be at least QoS lvl1 and be retained
        if (strpos($topicName, 'commands') !== false) {
            $message
                ->setQoSLevel(new QoSLevel(2))
                ->setRetainFlag(true)
            ;
        }

        $publish = new Publish();
        $publish->setMessage($message);
        $this->mqttClient->processObject($publish);

        return true;
    }
}

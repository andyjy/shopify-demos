<?php
/**
 * Process Shopify webhooks received via message queue
 *
 * https://devcenter.heroku.com/articles/php-workers
 * http://www.rabbitmq.com/tutorials/tutorial-two-php.html
 */
require 'vendor/autoload.php';

define('AMQP_DEBUG', true);
use PhpAmqpLib\Connection\AMQPStreamConnection;

$url = parse_url(getenv('CLOUDAMQP_URL'));
$conn = new AMQPStreamConnection($url['host'], 5672, $url['user'], $url['pass'], substr($url['path'], 1));
$channel = $conn->channel();

$queue = 'shopify_webhooks';
$channel->queue_declare(
  $queue, // name
  false, // passive
  true, // durable - the queue will survive server restarts
  false, // exclusive - the queue can be accessed in other channels
  false // auto_delete - the queue won't be deleted once the channel is closed
  );

$stores = json_decode($_ENV['STORES_JSON'], true);

$process_message = function($msg) use ($stores) {
  $message = json_decode($msg->body, true);
  $data = json_decode($message['payload'], true);

  error_log("Received webhook job: {$message['shop_subdomain']}:{$message['topic']}");

  switch ($message['topic']) {
    case 'customers/create':
    case 'customers/update':
      if ($message['shop_subdomain'] != $stores['root']['shopify_subdomain']) {
        error_log(" - Received webhook {$message['topic']} for store {$message['shop_subdomain']} - ignoring.");
        return;
      }
      $customer = $data;

      $stores_to_process = $stores['children'];
      foreach ($stores_to_process as $store) {
        try {
          error_log(" - Processing store: " . $store['handle']);
          $client = new \Shopify\Client([
            "shopUrl" => $store['shopify_subdomain'] . '.myshopify.com',
            "X-Shopify-Access-Token" => $store['shopify_token']
          ]);
          $customers = $client->getCustomerSearch(['query' => 'email:' . $customer['email']]);
          foreach ($customers['customers'] as $match) {
            if ($match && ($match['email'] == $customer['email'])) {
              error_log(" - Updating customer {$match['id']} in store {$store['shopify_subdomain']}");
              $client->updateCustomer(['id' => $match['id'], 'customer' => ['tags' => $customer['tags']]]);
              break;
            }
          }
        } catch (Exception $e) {
          trigger_error($e->getMessage());
        }

      }
  }

  // send ack now we've processed successfully
  $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
};

// enable fair dispatch - max 1 message to each worker a time
$channel->basic_qos(null, 1, null);

// consume!
$channel->basic_consume(
  $queue, // queue: Queue from where to get the messages
  '', // consumer_tag: Consumer identifier
  false, // no_local: Don't receive messages published by this consumer
  false, // no_ack: Tells the server if the consumer will acknowledge the messages
  false, // exclusive: Request exclusive consumer access, meaning only this consumer can access the queue
  false, // nowait
  $process_message // callback: A PHP Callback
  );

while(count($channel->callbacks)) {
    $channel->wait();
}

$channel->close();
$conn->close();

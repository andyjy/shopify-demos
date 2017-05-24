<?php
/**
 * Endpoint to receive Shopify Webhooks
 */
require '../vendor/autoload.php';

$stores = json_decode($_ENV['STORES_JSON'], true);

$shop_domain = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'];
$topic = $_SERVER['HTTP_X_SHOPIFY_TOPIC'];
$hmac_header = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];
$data_json = file_get_contents('php://input');

error_log("Received webhook {$shop_domain}:{$topic}");

function verify_webhook($data, $hmac_header, $secret) {
  $calculated_hmac = base64_encode(hash_hmac('sha256', $data, $secret, true));
  return ($hmac_header == $calculated_hmac);
}

$shop_subdomain = str_replace('.myshopify.com', '', $shop_domain);
$webhook_store = null;
foreach ($stores as $store) {
    if ($shop_subdomain == $store['shopify_subdomain']) {
        $webhook_store = $store;
        break;
    }
}

if (!$webhook_store) {
    error_log("- store config not found for {$shop_subdomain}; ignoring.");
    return;
}

$verified = verify_webhook($data_json, $hmac_header, $webhook_store['webhook_secret']);
if (!$verified) {
    error_log("- webhook verification failed for {$shop_subdomain}; ignoring.");
    return;
}

//
// all good - now add webhook to queue
//
// https://devcenter.heroku.com/articles/php-workers
// http://www.rabbitmq.com/tutorials/tutorial-two-php.html

define('AMQP_DEBUG', true);
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
$url = parse_url(getenv('CLOUDAMQP_URL'));
$conn = new AMQPConnection($url['host'], 5672, $url['user'], $url['pass'], substr($url['path'], 1));
$ch = $conn->channel();

$queue = 'shopify_webhooks';
$ch->queue_declare(
  $queue, // name
  false, // passive
  true, // durable - the queue will survive server restarts
  false, // exclusive - the queue can be accessed in other channels
  false // auto_delete - the queue won't be deleted once the channel is closed
  );

$msg_body = json_encode(['topic' => $topic, 'shop_subdomain' => $shop_subdomain, 'payload' => $data_json]);
$msg = new AMQPMessage($msg_body, array(
    'delivery_mode' => 2 // make message persistent so we don't lose it
    ));
$ch->basic_publish($msg, '', $queue);
$ch->close();
$conn->close();

<?php
/**
 * Log customers into multiple Shopify stores using Multipass
 */

date_default_timezone_set("UTC");

require '../vendor/autoload.php';
require '../lib/ShopifyMultipass.php';

$stores = json_decode($_ENV['STORES_JSON']);

$return_to = $_REQUEST['return_to'] ? $_REQUEST['return_to'] : $_SERVER['HTTP_REFERER'];
$target_site = preg_replace('/^(https?:\/\/[^\/]+\/).*/', '$1', $return_to);
$target_host = preg_replace('/^https?:\/\/([^\/]+)\//', '$1', $target_site);
error_log(print_r($target_host, true));
$target_store = null;
foreach ($stores->children as $store) {
  if ($store->host == $target_host) {
    $target_store = $store;
    break;
  }
}
error_log(print_r($target_store, true));

if (!$target_store) {
  // unknown target
  header('Location: ' . $return_to . (strpos($return_to, '?') !== false ? '&' : '?') . 'unknown_store');
  die();
}

$client = new \Shopify\Client([
   "shopUrl" => $stores->root->shopify_subdomain . '.myshopify.com',
   "X-Shopify-Access-Token" => $stores->root->shopify_token
]);

try {
  $customer_id = $_REQUEST['customer_id'];
  $customer = null;
  $customer = $client->getCustomer(array('id' => $customer_id));
} catch (Exception $e) {
  trigger_error($e->getMessage());
}
if (!$customer) {
  header('Location: ' . $return_to . (strpos($return_to, '?') !== false ? '&' : '?') . 'customer_not_found');
  die();
}

try {
  $metafields = null;
  $metafields = $client->getCustomerMetafields(array('id' => $customer_id));
} catch (Exception $e) {
  trigger_error($e->getMessage());
}

error_log(print_r($metafields, true));

$secret = null;
foreach ($metafields['metafields'] as $f) {
  if ($f['namespace'] == 'multipass' && $f['key'] == 'secret') {
    $secret = $f['value'];
    break;
  }
}

error_log(print_r($secret, true));

if (!$secret) {
  $secret = md5(microtime());
  try {
    $client->updateCustomer(array('id' => $customer_id, 'customer' => array('metafields' => array(
      array('key' => 'secret', 'namespace' => 'multipass', 'value' => $secret, 'value_type' => 'string')
    ))));
  } catch (Exception $e) {
    trigger_error($e->getMessage());
  }

  header('Location: ' . $_SERVER['HTTP_REFERER']);
  die();
}

if ($secret != $_REQUEST['code']) {
  // secret mismatch - don't log in to destination site
  header('Location: ' . $return_to . (strpos($return_to, '?') !== false ? '&' : '?') . 'multipass_mismatch');
  die();
}

// we're good! proceed with login

$customer_data = array(
  "email" => $customer['customer']['email'],
  "first_name" => $customer['customer']['first_name'],
  "last_name" => $customer['customer']['last_name'],
  "remote_ip" => $_SERVER['REMOTE_IP'],
  "return_to" => $return_to,
  "tag_string" => $customer['customer']['tags']
);

$multipass = new ShopifyMultipass($target_store->multipass_secret);
$token = $multipass->generate_token($customer_data);

header('Location: ' . $target_site . 'account/login/multipass/' . $token);
die();

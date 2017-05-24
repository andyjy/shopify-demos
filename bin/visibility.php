<h1>Update blog &amp; page visiblity</h1>

<pre>
<?php
/**
 * Update the published status of blog articles and pages based on tags
 */

date_default_timezone_set("UTC");
require 'vendor/autoload.php';

$stores = json_decode($_ENV['STORES_JSON'], true);
$stores_to_process = array_merge([$stores['root']], $stores['children']);

foreach ($stores_to_process as $store) {
  process_store($store);
}

function process_store($store) {
  echo "Processing store: " . $store['handle'] . "\n";

  try {
    $client = new \Shopify\Client([
       "shopUrl" => $store['shopify_subdomain'] . '.myshopify.com',
       "X-Shopify-Access-Token" => $store['shopify_token']
    ]);

    // blogs
    $blogs = $client->getBlogs();
    foreach ($blogs['blogs'] as $blog) {
      echo "- blog: {$blog['handle']}\n";
      $articles = $client->getArticles(['blog_id' => $blog['id'], 'limit' => 250, 'fields' => "id,title,handle,tags"]);
      foreach ($articles['articles'] as $article) {
        if (should_hide($article, $store)) {
          echo " -- hiding article {$article['id']} - {$article['handle']}\n";
          $client->updateArticle(['blog_id' => $blog['id'], 'article_id' => $article['id'], 'article' => ['published' => false]]);
        }
      }
    }

    // pages
    // TODO: use metafields
    
  } catch (Exception $e) {
    trigger_error($e->getMessage());
  }
}

function should_hide($article, $store) {
  $suffix = $store['handle']; // 'us', 'eu', 'gb' etc
  $tags = explode(',', str_replace(' ', '', $article['tags']));

  if (in_array(':hide-' . $suffix, $tags)) {
    // hide if :hide-{suffix} tag set
    return true;
  }

  if (in_array(':hide-all', $tags) && !in_array(':show-' . $suffix, $tags)) {
    // hide if :hide-all and not :show-{suffix}
    return true;
  }
}

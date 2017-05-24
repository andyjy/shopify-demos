$_ENV['STORES_JSON'] is configured under Heroku to look something like the following JSON string:

{
    // master store is not accessed by the public; used as a "staging" store
    "master": {
        "handle": "master",
        "shopify_subdomain": "factiontest-master",
        "host": "factiontest-master.myshopify.com",
        "shopify_token": "xxxxx"
        },

    // "root" store is the www. hostname - used for the US store
    "root": {
        "handle": "us",
        "shopify_subdomain": "www-factiontest",
        "host": "www-factiontest.myshopify.com",
        "shopify_token": "xxxx"
        },

    // child stores for all othe regions
    "children": [{
        "handle": "eu",
        "shopify_subdomain": "eu-factiontest",
        "host": "eu-factiontest.myshopify.com",
        "shopify_token": "xxxx",
        "multipass_secret": "xxxx"
        }, {
        "handle": "gb",
        "shopify_subdomain": "gb-factiontest",
        "host": "gb-factiontest.myshopify.com",
        "shopify_token": "xxxx",
        "multipass_secret": "xxxx"
    }]
}

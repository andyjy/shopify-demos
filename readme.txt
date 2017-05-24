see www/multipass.php for the multipass demo


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


Multipass overview:

- multipass off the "root"/"parent" www.factionskis.com store user database.
- when the user browses to / selects a specific store, we set cookie on root domain (so accessible to all subdomain stores) recording current store selected.
- links in HTML site template to log in/register all point to root domain rather than current store.
- root domain detects current store cookie and redirects back to subdomain at same URL path - but only if not on login/register pages(!)
- if logged in when redirecting back to subsomain, redirect is passed via multipass script (hosted on heroku in demo) with URL parameters for email address to log in as, and URL to redirect on to. 
- destination store can be determined from domain in destination URL; script requires array of multipass/api keys for each store.
- challenge is then how to handle security so we don't have a permalink that can *always* be used by anyone to log in as particular user on subdomains. Ideally would involve some sort of HMAC secret with time-based element that can be verified via server-side multipassredirect script.. I didn't figure this part out yet :)
- Shopify setting for user accounts configured to "optional"; use checkout.scss to hide tickbox to save details for later to avoid creating accounts just on subdomain stores.

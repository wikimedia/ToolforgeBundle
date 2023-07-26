Toolforge Bundle
================

A Symfony 4/5 bundle that provides some common parts of web-based tools in Wikimedia Toolforge.

Features:

* OAuth user authentication against [Meta Wiki](https://meta.wikimedia.org/).
* Internationalization with the [Intuition](https://intuition.toolforge.org/) and jQuery.i18n libraries.
* Interface to connect and query the [replica databases](https://wikitech.wikimedia.org/wiki/Wiki_replicas)
* PHP Code Sniffer ruleset
* Base Wikimedia UI stylesheet (LESS)

Still to come:

* Universal Language Selector (ULS)
* Localizable routes
* OOUI
* CSSJanus
* Addwiki
* Critical error reporting to tool maintainers' email

[![Packagist](https://img.shields.io/packagist/v/wikimedia/toolforge-bundle.svg)](https://packagist.org/packages/wikimedia/toolforge-bundle)
[![License](https://img.shields.io/github/license/wikimedia/ToolforgeBundle.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![GitHub issues](https://img.shields.io/github/issues/wikimedia/ToolforgeBundle.svg)](https://github.com/wikimedia/ToolforgeBundle/issues)
[![Build Status](https://github.com/wikimedia/ToolforgeBundle/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/wikimedia/ToolforgeBundle/actions/workflows/ci.yml?query=branch%3Amaster)

Please report all issues either on [Github](https://github.com/wikimedia/ToolforgeBundle/issues)
or on [Phabricator](https://phabricator.wikimedia.org/tag/community-tech) (tagged with `community-tech`).

## Table of Contents

* [Installation](#installation)
* [Configuration](#configuration)
    * [OAuth](#oauth)
    * [Internationalization (Intuition and jQuery.i18n)](#internationalization-intuition-and-jqueryi18n)
    * [Replicas connection manager](#replicas-connection-manager)
    * [PHP Code Sniffer](#php-code-sniffer)
    * [Wikimedia UI styles](#wikimedia-ui-styles)
    * [Deployment script](#deployment-script)
    * [Sessions](#sessions)
* [Examples](#examples)
* [License](#license)

## Installation

### New project

To get a new project up and running quickly
first make sure you've got [Composer](https://getcomposer.org) and the [Symfony CLI](https://symfony.com/download) installed
and then use the [Toolforge Skeleton](https://packagist.org/packages/wikimedia/toolforge-skeleton):

    composer create-project wikimedia/toolforge-skeleton ./my-cool-tool
    cd my-cool-tool
    symfony server:start -d

Navigate to http://localhost:8000 and you should see your new tool up and running.

### Existing project

Install the code in an existing Symfony project:

    composer require wikimedia/toolforge-bundle

Register the bundle in your `AppKernel`:

    class AppKernel extends Kernel {
        public function registerBundles() {
            $bundles = [
                new Wikimedia\ToolforgeBundle\ToolforgeBundle(),
            ];
            return $bundles;
        }
    }

Or `config/bundles.php`

    Wikimedia\ToolforgeBundle\ToolforgeBundle::class => ['dev' => true],

## Configuration

### OAuth

The bundle creates three new routes `/login`, `/oauth_callback`, and `/logout`.
Your application should have a route called `home`.
You need to register these with your application
by adding the following to your `config/routes.yaml` file (or equivalent):

    toolforge:
      resource: '@ToolforgeBundle/Resources/config/routes.yaml'

To configure OAuth, first
[apply for an OAuth consumer](https://meta.wikimedia.org/wiki/Special:OAuthConsumerRegistration/propose)
on Meta Wiki with a callback URL of `<your-base-url>/oauth_callback`
and add the consumer key and secret to your `.env` file.
Then connect these to your application's config with the following in `config/packages/toolforge.yaml`:

    toolforge:
      oauth:
        consumer_key: '%env(OAUTH_KEY)%'
        consumer_secret: '%env(OAUTH_SECRET)%'

If you need to authenticate to a different wiki,
you can also set the `toolforge.oauth.url` parameter
to the full URL to `Special:OAuth`.

Add a login link to the relevant Twig template (often `base.html.twig`), e.g.:

    {% if logged_in_user() %}
      {{ msg( 'toolforge-logged-in-as', [ logged_in_user().username ] ) }}
      <a href="{{ path('toolforge_logout') }}">{{ msg('toolforge-logout') }}</a>
    {% else %}
      <a href="{{ path('toolforge_login') }}">{{ msg('toolforge-login') }}</a>
    {% endif %}

The internationalization parts of this are explained below.
The OAuth-specific part is the `logged_in_user()`,
which is a bungle-provided Twig function
that gives you access to the currently logged-in user.

While in development, it can be useful to not have to log your user in all the time.
To force login of a particular user (but note that you still have to click the 'login' link),
add a `logged_in_user` key to your `config/packages/toolforge.yml` file, e.g.:

    toolforge:
      oauth:
        logged_in_user: '%env(LOGGED_IN_USER)%'

In controllers, you can test whether the user is logged in by checking:

    $this->get('session')->get('logged_in_user')

#### Redirecting after login

After the user logs in, you may want to redirect them back to the page they originally tried to
view, instead of the `home` route. To do this, first make sure you registered your OAuth consumer
to accept a callback URL using the "Allow consumer to specify a callback..." option. The value for
the callback would for example be `https://my-tool.toolforge.org/oauth_callback`.

The implementation in your views is best explained by example. Let's assume the current page
the user sees shows a login link, and you want to redirect them back to the same page after
they authenticate. The code in your Twig template should look something like:

    <a href="{{ path('toolforge_login', {'callback': url('toolforge_oauth_callback', {'redirect': app.request.uri})}) }}">Login</a>

Here `app.request.uri` evaluates to the current URL the user is viewing. It is provided as the
`redirect` for the `oauth_callback` route, which is provided as the `callback` for the `login` route.
The URL for the login link ends up being something like:

    https://my-tool.toolforge.org/login?callback=https%3A//my-tool.toolforge.org/oauth_callback%3Fredirect%3Dhttps%253A//my-tool.toolforge.org/my-page%253Ffoo%253Dbar

Note the double-encoding of the URL used for the value of `redirect`. In this example the user will
ultimately be redirected back to `https://my-tool.toolforge.org/my-page?foo=bar`.

### Internationalization (Intuition and jQuery.i18n)

Internationalization is handled similarly to how it is done in MediaWiki,
with translated strings being stored in `i18n/` directories.
The bundle comes with some strings of its own, all prefixed with `toolforge_`;
it is recommended that these are used where possible because it reduces the work for translators.

#### 1. PHP

In PHP, set your application's i18n 'domain' with the following in `config/packages/toolforge.yaml`:

    toolforge:
        intuition:
            domain: 'app-name-here'

You can inject (the bundle's subclass of) Intuition into your controllers via type hinting, e.g.:

    public function indexAction( Request $request, \Wikimedia\ToolforgeBundle\Service\Intuition $intuition ) { /*...*/ }

The following Twig functions and filters are available:

* `msg( msg, params )` *string* Get a single message.
* `bdi( text )` *string* Wrap a string with <bdi> tags for bidirectional isolation
* `msg_exists( msg )` *bool* Check to see if a given message exists.
* `msg_if_exists( msg, params )` *string* Get a message if it exists, or else return the provided string.
* `lang( lang )` *string* The code of the current or given language.
* `lang_name( lang )` *string* The name of the current or given language.
* `all_langs()` *string[]* List of all languages defined in JSON files in the `i18n/` directory (code => name).
* `is_rtl()` *bool* Whether the current language is right-to-left.
* `git_tag()` *string* The current Git tag, or the short hash if there are no tags.
* `git_branch()` *string* The current Git branch.
* `git_hash()` *string* The current Git hash.
* `git_hash_short()` *string* The short version of the current Git hash.
* `<number>|num_format` *int|float* Format a number according to the current Locale.
* `<strings>|list_format` *string[]* Format an array of strings as a separated inline-list. In English this is comma-separate with 'and' before the last item.

#### 2. Javascript

In Javascript, you need to do three things to enable internationalisation:

1. Add the following to your main JS file (e.g. `app.js`) or `webpack.config.js`:

       require('../vendor/wikimedia/toolforge-bundle/Resources/assets/toolforge.js');

2. This to your HTML template (before your `app.js`):

       <script type="text/javascript" src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
       {% include '@toolforge/i18n.html.twig' %}

   (The jQuery can be left out if you're already loading that through other means.)

3. And symlink your `i18n/` directory from `public/i18n/`,
   so that the language files can be loaded by from Javascript.

Then you can get i18n messages anywhere with: `$.i18n( 'msg-name', paramOne, paramTwo )`

### Replicas connection manager

If your tool connects to multiple databases on the
[Toolforge replicas](https://wikitech.wikimedia.org/wiki/Help:Toolforge/Database),
you can take advantage of ToolforgeBundle's `ReplicasClient` service to ensure your application
opens no more connections than it needs to.

For this to work, you first need to add the following to your `config/packages/doctrine.yaml`:

<details>
<summary>doctrine.yaml</summary>

```
doctrine:
  dbal:
    connections:
      toolforge_s1:
        host: '%env(REPLICAS_HOST_S1)%'
        port: '%env(REPLICAS_PORT_S1)%'
        user: '%env(REPLICAS_USERNAME)%'
        password: '%env(REPLICAS_PASSWORD)%'
      toolforge_s2:
        host: '%env(REPLICAS_HOST_S2)%'
        port: '%env(REPLICAS_PORT_S2)%'
        user: '%env(REPLICAS_USERNAME)%'
        password: '%env(REPLICAS_PASSWORD)%'
      toolforge_s3:
        host: '%env(REPLICAS_HOST_S3)%'
        port: '%env(REPLICAS_PORT_S3)%'
        user: '%env(REPLICAS_USERNAME)%'
        password: '%env(REPLICAS_PASSWORD)%'
      toolforge_s4:
        host: '%env(REPLICAS_HOST_S4)%'
        port: '%env(REPLICAS_PORT_S4)%'
        user: '%env(REPLICAS_USERNAME)%'
        password: '%env(REPLICAS_PASSWORD)%'
      toolforge_s5:
        host: '%env(REPLICAS_HOST_S5)%'
        port: '%env(REPLICAS_PORT_S5)%'
        user: '%env(REPLICAS_USERNAME)%'
        password: '%env(REPLICAS_PASSWORD)%'
      toolforge_s6:
        host: '%env(REPLICAS_HOST_S6)%'
        port: '%env(REPLICAS_PORT_S6)%'
        user: '%env(REPLICAS_USERNAME)%'
        password: '%env(REPLICAS_PASSWORD)%'
      toolforge_s7:
        host: '%env(REPLICAS_HOST_S7)%'
        port: '%env(REPLICAS_PORT_S7)%'
        user: '%env(REPLICAS_USERNAME)%'
        password: '%env(REPLICAS_PASSWORD)%'
      toolforge_s8:
        host: '%env(REPLICAS_HOST_S8)%'
        port: '%env(REPLICAS_PORT_S8)%'
        user: '%env(REPLICAS_USERNAME)%'
        password: '%env(REPLICAS_PASSWORD)%'
      # If you need to work with toolsdb
      toolforge_toolsdb:
        host: '%env(TOOLSDB_HOST)%'
        port: '%env(TOOLSDB_PORT)%'
        user: '%env(TOOLSDB_USERNAME)%'
        password: '%env(TOOLSDB_PASSWORD)%'
```

</details>

Also adding the `REPLICAS_HOST_`, `REPLICAS_USERNAME`, `REPLICAS_PASSWORD` and each
`REPLICAS_PORT_` to .env as necessary. If new sections are added (which is rare), you will
need to update these accordingly.

In **production**, the `REPLICAS_HOST_S1` variables should be `s1.web.db.svc.wikimedia.cloud`
(or `analytics` instead of `web`), and similarly for each section. The `REPLICAS_PORT_` vars
should be `3306` in production. For **local environments**, use `127.0.0.1` for the host vars
and any safe range of ports (such as 4711 for `s1`, 4712 for `s2`, and so on).

Next, establish an SSH tunnel to the replicas (only necessary on local environments):

    php bin/console toolforge:ssh

Use the `--bind-address` flag to change the binding address, if needed. This may be necessary
for Docker installations.

If you need to work against [tools-db](https://wikitech.wikimedia.org/wiki/Help:Toolforge/Database#User_databases),
pass the `--toolsdb` flag and make sure the `TOOLSBD_` env variables are set correctly.
Unless you have a private database, you should be able to use the same username and password
as `REPLICAS_USERNAME` and `REPLICAS_PASSWORD`.

To query the replicas, inject the `ReplicasClient` service then call the `getConnection()`
method, passing in a valid database, and you should get a `Doctrine\DBAL\Connection` object.
For example:

    # src/Controller/MyController.php
    public function myMethod(ReplicasClient $client) {
        $frConnection = $client->getConnection('frwiki');
        $frUserId = $frConnection->executeQuery("SELECT user_id FROM user LIMIT 1")->fetch();
        $ruConnection = $client->getConnection('ruwiki');
        $ruUserId = $ruConnection->executeQuery("SELECT user_id FROM user LIMIT 1")->fetch();
        # ...
    }

In this example, `$frConnection` and `$ruConnection` actually point to the same `Connection`
instance, since (at the time of writing) both `frwiki` and `ruwiki` live on the
[same section](https://noc.wikimedia.org/conf/highlight.php?file=dblists/s6.dblist).
`ReplicasClient` knows to do this because it queries (and caches) the dblists at
https://noc.wikimedia.org.

### PHP Code Sniffer

You can use the bundle's phpcs rules by adding the following
to the `require-dev` section of your project's `composer.json`:

    "slevomat/coding-standard": "^4.8"

And then referencing the bundle's ruleset with the following in your project's `.phpcs.xml`:

    <rule ref="./vendor/wikimedia/toolforge-bundle/Resources/phpcs/ruleset.xml" />

### Wikimedia UI styles

You may want your tool to conform to the
[Wikimedia Design Style Guide](https://design.wikimedia.org/style-guide/).
A basic [LESS](http://lesscss.org/) stylesheet that applies some of these design elements
is available in the bundle. To use it, first install the required packages:

    npm install wikimedia-ui-base less less-loader

And then import both it and the bundle's CSS file for it
(e.g. at the top of your `assets/app.less` file):

    @import '../node_modules/wikimedia-ui-base/wikimedia-ui-base.less';
    @import '../vendor/wikimedia/toolforge-bundle/Resources/assets/wikimedia-base.less';

### Deployment script

The bundle comes with a deployment script for use on Toolforge
where an application is run on the Kubernetes cluster.

It should be added to your tool's crontab to run e.g. every ten minutes:

    */10 * * * * /usr/bin/jsub -once -quiet /data/project/<toolname>/<app-dir>/vendor/wikimedia/toolforge-bundle/bin/deploy.sh prod /data/project/<toolname>/<app-dir>/

* The first argument is either `prod` or `dev`,
  depending on whether you want to run the highest tagged version,
  or the latest master branch.
* The second is the path to the tool's top-level directory,
  which is usually either the tool's home directory or a directory within it
  (e.g. `/data/project/<toolname>/app`).

### Sessions

By default Symfony uses `/` for sessions' cookie path, but this isn't secure on Toolforge
because it means that different tools can access each other's cookies. Additionally, Toolforge
may by default use the fallback to the session expiry defined in php.ini, which is only
24 minutes. To fix this, set the following in your `framework.yaml`:

    framework:
      session:
        storage_id: Wikimedia\ToolforgeBundle\Service\NativeSessionStorage
        handler_id: 'session.handler.native_file'
        save_path: '%kernel.project_dir%/var/sessions/%kernel.environment%'
        cookie_lifetime: 604800 # one week

## Examples

This bundle is currently in use on the following projects:

1. [Event Metrics](https://meta.wikimedia.org/wiki/Special:MyLanguage/Event_Metrics)
2. [SVG Translate](https://commons.wikimedia.org/wiki/Special:MyLanguage/Commons:SVG_Translate_tool)
3. [Global Search](https://global-search.toolforge.org)
4. [Flickr Dashboard](https://flickrdash.toolforge.org)
5. [Wikisource Export](https://wsexport.wmcloud.org)
6. [Wikimedia OCR](https://ocr.wmcloud.org)
7. [CopyPatrol](https://copypatrol.toolforge.org)
8. [Wikisource Contests](https://wscontest.toolforge.org)

## License

GPL 3.0 or later.

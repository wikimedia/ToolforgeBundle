Toolforge Bundle
================

A Symfony 4 bundle that provides some common parts of web-based tools in Wikimedia Toolforge.

Features:

* OAuth user authentication against [Meta Wiki](https://meta.wikimedia.org/).
* Internationalization with the Intuition and jQuery.i18n libraries.

Still to come:

* Universal Language Selector (ULS)
* Localizable routes
* OOUI
* CSSJanus
* Addwiki
* Configuration for the replica DBs
* Critical error reporting to tool maintainers' email

[![Packagist](https://img.shields.io/packagist/v/wikimedia/toolforge-bundle.svg)](https://packagist.org/packages/wikimedia/toolforge-bundle)
[![License](https://img.shields.io/github/license/wikimedia/ToolforgeBundle.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![GitHub issues](https://img.shields.io/github/issues/wikimedia/ToolforgeBundle.svg)](https://github.com/wikimedia/ToolforgeBundle/issues)
[![Build Status](https://travis-ci.org/wikimedia/ToolforgeBundle.svg)](https://travis-ci.org/wikimedia/ToolforgeBundle)

## Table of Contents

* [Installation](#installation)
* [Configuration](#configuration)
  * [OAuth](#oauth)
  * [Internationalization (Intuition and jQuery.i18n)](#internationalization-intuition-and-jqueryi18n)
* [Examples](#examples)
* [License](#license)

## Installation

Install the code (in an existing Symfony project):

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

Add a login link to the relevant Twig template (often `base.html.twig`), e.g.:

    {% if logged_in_user() %}
      {{ msg( 'logged-in-as', [ logged_in_user().username ] ) }}
      <a href="{{ path('toolforge_logout') }}">{{ msg('logout') }}</a>
    {% else %}
      <a href="{{ path('toolforge_login') }}">{{ msg('login') }}</a>
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

### Internationalization (Intuition and jQuery.i18n)

#### 1. PHP

In PHP, set your application's i18n 'domain' with the following in `config/packages/toolforge.yaml`:

    toolforge:
        intuition:
            domain: 'app-name-here'

You can inject Intuition into your controllers via type hinting, e.g.:

    public function indexAction( Request $request, Intuition $intuition ) { /*...*/ }

The following Twig functions are available:

* `msg( msg, params )` *string* Get a single message.
* `msg_exists( msg )` *bool* Check to see if a given message exists.
* `msg_if_exists( msg, params )` *string* Get a message if it exists, or else return the provided string.
* `lang( lang )` *string* The code of the current or given language.
* `lang_name( lang )` *string* The name of the current or given language.
* `all_langs()` *string[]* List of all languages defined in JSON files in the `i18n/` directory (code => name).
* `is_rtl()` *bool* Whether the current language is right-to-left.

#### 2. Javascript

In Javascript, you need to add the following to your main JS file (e.g. `app.js`) or `webpack.config.js`:

    require('../vendor/wikimedia/toolforge-bundle/Resources/assets/toolforge.js');

And this to your HTML template (before your `app.js`):

    <script type="text/javascript" src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    {% include '@toolforge/i18n.html.twig' %}

Then you can get i18n messages with `$.i18n( 'msg-name', paramOne, paramTwo )`

## Examples

This bundle is currently in use on the following projects:

1. [Grant Metrics](https://meta.wikimedia.org/wiki/Grant_Metrics_tool)

## License

GPL 3.0 or later.

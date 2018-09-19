Toolforge Bundle
================

A Symfony 4 bundle that provides some common parts of web-based tools in Wikimedia Toolforge.

Features:

* OAuth user authentication against Meta Wiki.
* Internationalization with the Intuition and jQuery.i18n libraries.

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
You need to register these with your application
by adding the following to your `config/routes.yaml` file (or equivalent):

    toolforge:
      resource: '@ToolforgeBundle/Resources/config/routes.yaml'

To configure OAuth, first
[apply for an OAuth consumer](https://meta.wikimedia.org/wiki/Special:OAuthConsumerRegistration/propose)
on Meta Wiki, and add the consumer key and secret to your `parameters.yml` or `.env` file.
Then connect these to your app's config with the following in `config.yml`:

    toolforge:
      oauth:
        consumer_key: '%oauth.key%'
        consumer_secret: '%oauth.secret%'

While in development, it can be useful to not have to log your user in all the time.
To force login of a particular user (but note that you still have to click the 'login' link),
add a `toolforge.oauth.logged_in_user` key to your `config.yml` file, e.g.:

    logged_in_user: '%app.logged_in_user%'

Add a login link to the relevant Twig template (often `base.html.twig`), e.g.:

    {% if logged_in_user() %}
      {{ msg( 'logged-in-as', [ logged_in_user().username ] ) }}
      <a href="{{ path('logout') }}">{{ msg('logout') }}</a>
    {% else %}
      <a href="{{ path('login') }}">{{ msg('login') }}</a>
    {% endif %}

The i18n parts of this are explained below.
The OAuth-specific parts is the `logged_in_user`,
which is a bungle-provided global Twig variable
that gives you access to the currently logged-in user.

### Internationalization (Intuition and jQuery.i18n)

#### 1. PHP

In PHP, set your application's i18n 'domain' with the following in `config/packages/toolforge.yaml`:

    toolforge:
        intuition:
            domain: 'app-name-here'

You can inject Intuition into your controllers via type hinting, e.g.:

    public function indexAction( Request $request, Intuition $intuition ) { /*...*/ }

#### 2. Javascript

In Javascript, you need to add the following to your main JS file (e.g. `app.js`):

    require('../vendor/wikimedia/toolforge-bundle/assets/toolforge.js');

And this to your HTML template (before your `app.js`):

    <script type="text/javascript" src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    {% include '@toolforge/i18n.html.twig' %}

Then you can get i18n messages with `$.i18n( 'msg-name', paramOne, paramTwo )`

## License

GPL 3.0 or later.

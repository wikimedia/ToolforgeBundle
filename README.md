Toolforge Bundle
================

A Symfony 4 bundle that provides some common parts of web-based tools in Wikimedia Toolforge.

Features:

* OAuth user authentication against Meta Wiki.
* Internationalization with the Intuition library.

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

Add the bundle's routing to your app's `config/routing.yml` file:

    toolforge_bundle:
      resource: "@ToolforgeBundle/Controller/"
      type: annotation

The bundle creates these new routes:

    /login
    /login/callback
    /logout

[Apply for an OAuth consumer](https://meta.wikimedia.org/wiki/Special:OAuthConsumerRegistration/propose)
on Meta Wiki, and add the consumer key and secret to your `parameters.yml` file.
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

    {% if logged_in_user %}
      {{ msg( 'logged-in-as', [ logged_in_user.username ] ) }}
      <a href="{{ path('logout') }}">{{ msg('logout') }}</a>
    {% else %}
      <a href="{{ path('login') }}">{{ msg('login') }}</a>
    {% endif %}

The i18n parts of this are explained below.
The OAuth-specific parts is the `logged_in_user`,
which is a bungle-provided global Twig variable
that gives you access to the currently logged-in user.

### Intuition (i18n)

Docs @TODO.

    toolforge:
        intuition:
            domain: 'app-name-here'

### OOUI

@TODO

## License

GPL 3.0 or later.

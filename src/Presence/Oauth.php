<?php

namespace Presence;

use \Silex\Provider\SecurityServiceProvider;
use \Gigablah\Silex\OAuth\Security\User\Provider\OAuthInMemoryUserProvider;
use \Symfony\Component\HttpFoundation\Response;
use \Symfony\Component\HttpFoundation\Request;

/**
 * Registers Oauth provider and handler
 */
class Oauth
{
    public static function register($app, $config)
    {

        self::registerOauthProvider($app, $config['oauth']);
        self::registerSessionProvider($app, $config['sessionsPath']);
        self::registerSecurityProvider($app);
        self::registerHandler($app);
    }

    private static function registerSessionProvider($app, $path)
    {
        $app->register(
            new \Silex\Provider\SessionServiceProvider(),
            array(
                'session.storage.save_path' => $path
            )
        );
    }

    private static function registerOauthProvider($app, $config)
    {
        $app->register(
            new \Gigablah\Silex\OAuth\OAuthServiceProvider(),
            array(
                'oauth.services' => array(
                    'google' => array(
                        'key' => $config['key'],
                        'secret' => $config['secret'],
                        'scope' => $config['scope'],
                        'user_endpoint' => $config['user_endpoint']
                    ),
                )
            )
        );
    }

    private static function registerSecurityProvider($app)
    {
        $app->register(
            new SecurityServiceProvider(),
            array(
                'security.firewalls' => array(
                    'default' => array(
                        'pattern' => '^/',
                        'anonymous' => true,
                        'oauth' => array(
                            'failure_path' => '/login',
                            'with_csrf' => true
                        ),
                        'logout' => array(
                            'logout_path' => '/logout',
                            'with_csrf' => true
                        ),
                        'users' => new OAuthInMemoryUserProvider
                    )
                ),
                'security.access_rules' => array(
                    array('^/auth', 'ROLE_USER')
                )
            )
        );
    }

    private static function registerHandler($app)
    {
        $app->before(
            function (Request $request) use ($app) {
                $token = $app['security']->getToken();
                $app['user'] = null;

                if ($token && !$app['security.trust_resolver']->isAnonymous($token)) {
                    $app['user'] = $token->getUser();
                }

                if (empty($app['user'])) {
                    return $app->redirect(
                        $app['url_generator']->generate(
                            '_auth_service',
                            array(
                                'service' => 'google',
                                '_csrf_token' => $app['form.csrf_provider']->generateCsrfToken('oauth')
                            )
                        )
                    );
                } else {
                    if (!preg_match('/@liip.ch$/', $app['user']->getEmail())) {
                        return new Response('Access denied.', 403);
                    }
                }
            }
        );
    }
}

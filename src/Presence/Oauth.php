<?php

namespace Presence;

use \Silex\Provider\SecurityServiceProvider;
use \Gigablah\Silex\OAuth\Security\User\Provider\OAuthInMemoryUserProvider;
use \Symfony\Component\HttpFoundation\Response;
use \Symfony\Component\HttpFoundation\Request;
use \Silex\Provider\FormServiceProvider;

/**
 * Registers Oauth provider and handler
 */
class Oauth
{
    public static function register($app, $config)
    {

        $app->register(new FormServiceProvider());
        self::registerOauthProvider($app, $config['google']);
        self::registerSessionProvider($app);
        self::registerSecurityProvider($app);
        self::registerHandler($app);
    }

    private static function registerSessionProvider($app)
    {
        $app->register(
            new \Silex\Provider\SessionServiceProvider(),
            array(
                'session.storage.save_path' => __DIR__ . '/../../cache/sessions'
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
                    array('^/(?!login).*$', 'ROLE_USER'),
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
                    $email = $app['user']->getEmail();
                    if (!preg_match('/@liip.ch$/', $email)) {
                        return new Response('Access denied.', 403);
                    }
                    $refreshToken = $token->getAccessToken()->getRefreshToken();
                    $username = $app['user']->getUsername();
                    if ($email && $username) {
                        $persons = $app['db']->fetchAll('SELECT * FROM persons WHERE email = ?', array($email));
                    }
                    if (empty($persons)) {
                        $app['db']->insert(
                            'persons',
                            array(
                                'name' => $username,
                                'email' => $email,
                                'refreshtoken' => $refreshToken,
                            )
                        );
                    } elseif (!empty($refreshToken)) {
                        $app['db']->update(
                            'persons',
                            array('refreshtoken' => $refreshToken),
                            array('email' => $email)
                        );
                    }
                }
            }
        );
    }
}

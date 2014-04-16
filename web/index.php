<?php

namespace Presence;

// we need APC for the caching
if (! (extension_loaded('apc') && ini_get('apc.enabled'))) {
    throw new \Exception("Cannot find the PHP APC module. Please install or enable it for this app to work");
}

require_once __DIR__ . '/../vendor/autoload.php';

// parse and store the yaml configuration
$yaml             = new \Symfony\Component\Yaml\Parser();
$config           = new Config();
$config->settings = $yaml->parse(file_get_contents('../config/settings.yaml'));
$config->people   = $yaml->parse(file_get_contents('../config/people.yaml'));
$oauth = $config->settings['oauth'];

// load Silex and register providers
$app = new \Silex\Application();
$app->register(new \Silex\Provider\TwigServiceProvider(), array('twig.path' => __DIR__.'/../views',));
$app->register(new \Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new \Gigablah\Silex\OAuth\OAuthServiceProvider(), array(
    'oauth.services' => array(
        'google' => array(
            'key' => $oauth['key'],
            'secret' => $oauth['secret'],
            'scope' => $oauth['scope'],
            'user_endpoint' => $oauth['user_endpoint']
        ),
    )
));

$app->register(new \Silex\Provider\FormServiceProvider());
$app->register(new \Silex\Provider\SessionServiceProvider(), array(
    'session.storage.save_path' => __DIR__ . '/../sessions'
));


$app['twig']->getExtension('core')->setTimezone(
    isset($settings['timezone']) ? $settings['timezone']:'Europe/Zurich'
);

$app->register(new \Silex\Provider\SecurityServiceProvider(), array(
    'security.firewalls' => array(
        'default' => array(
            'pattern' => '^/',
            'anonymous' => true,
            'oauth' => array(
                //'login_path' => '/auth/{service}',
                //'callback_path' => '/auth/{service}/callback',
                //'check_path' => '/auth/{service}/check',
                'failure_path' => '/login',
                'with_csrf' => true
            ),
            'logout' => array(
                'logout_path' => '/logout',
                'with_csrf' => true
            ),
            'users' => new \Gigablah\Silex\OAuth\Security\User\Provider\OAuthInMemoryUserProvider()
        )
    ),
    'security.access_rules' => array(
        array('^/auth', 'ROLE_USER')
    )
));

$app->before(function (\Symfony\Component\HttpFoundation\Request $request) use ($app) {
    $token = $app['security']->getToken();
    $app['user'] = null;

    if ($token && !$app['security.trust_resolver']->isAnonymous($token)) {
        $app['user'] = $token->getUser();
    }
});

$app->get('/login', function () use ($app) {
    $services = array_keys($app['oauth.services']);

    return $app['twig']->render('login.twig', array(
        'login_paths' => array_map(function ($service) use ($app) {
            return $app['url_generator']->generate('_auth_service', array(
                'service' => $service,
                '_csrf_token' => $app['form.csrf_provider']->generateCsrfToken('oauth')
            ));
        }, array_combine($services, $services)),
        'logout_path' => $app['url_generator']->generate('logout', array(
            '_csrf_token' => $app['form.csrf_provider']->generateCsrfToken('logout')
        ))
    ));
});

$app->match('/logout', function () {})->bind('logout');
$app['debug'] = true;

/**
 * List with all teams
 */
$app->get(
    '/',
    function () use ($app, $config) {
        ksort($config->people['persons']);

        return $app['twig']->render(
            'index.twig',
            array(
                'teams'   => $config->people['teams'],
                'persons' => $config->people['persons'],
            )
        );
    }
)
->bind('homepage');

$app->get(
    '/search/people',
    function () use ($app, $config) {
        $query = strtolower($app['request']->get('q'));
        $result = array();
        if (!empty($query)) {
            $persons = $config->people['persons'];

            $result = array_filter(
                $persons,
                function ($array) use ($query, &$persons) {
                    $len = strlen($query);

                    $name_spl = explode(" ", trim(strtolower($array['name'])));

                    foreach ($name_spl as $name) {
                        if (substr($name, 0, $len) == $query) {
                            return true;
                        }
                    }

                    $mail_spl = explode(".", trim(strtolower($array['mail'])));

                    foreach ($name_spl as $name) {
                        if (substr($name, 0, $len) == $query) {
                            return true;
                        }
                    }

                    return false;
                }
            );
        }
        return $app->json($result);
    }
)
->bind("peoplesearch");

/**
 * Instruction
 */
$app->get(
    '/instruction',
    function () use ($app) {
        return $app['twig']->render('instruction.twig');
    }
)
->bind('instruction');

/**
 * Get team availabilities
 */
$app->get(
    '/{teamId}',
    function ($teamId) use ($app, $config) {

        try {

            $config->people['refresh'] = $app['request']->get('refresh');

            $helper       = new DateHelper();
            $projectsMode = ($app['request']->get('mode', 'availability') === 'projects');
            $startDate    = $helper->getStartDate($app['request']->get('week'));
            $weeks        = $app['request']->get('view', 1);
            $showDetails  = $app['request']->get('details', 1);
            $endDate      = $helper->getEndDate($weeks);
            $days         = $helper->getDays($startDate, $endDate);
            $calendar     = new GoogleCalendar($config->settings['google'], $startDate, $endDate);

            if (!empty($config->people['teams'][$teamId])) {
                $team = new Team(
                    $teamId,
                    $config->people,
                    $calendar
                );
            } elseif (!empty($config->people['persons'][$teamId])) {
                $team = new TeamOfOne(
                    $teamId,
                    $config->people,
                    $calendar
                );
            } else {
                $app->abort(404, 'No team or person found with ID ' . $teamId . ' does not exist');
            }

        } catch (\Exception $e) {
            $app->abort(404, $e->getMessage());
        }

        // render the twig template, the team object with the members and their events is passed
        return $app['twig']->render(
          (($projectsMode) ? 'projects' : 'availabilities' ). '.twig',
            array(
                'teams'               => $config->people['teams'],
                'team'                => $team,
                'days'                => $days,
                'weeks'               => $weeks,
                'showDetails'         => $showDetails,
                'projectsMode'        => $projectsMode,
                'serviceAccountEmail' => $config->settings['google']['serviceAccountName']
            )
        );
    }
)
->bind('availabilities');

$app->run();

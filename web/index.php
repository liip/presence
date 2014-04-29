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

// load Silex and register providers
$app = new \Silex\Application();
$app->register(new \Silex\Provider\TwigServiceProvider(), array('twig.path' => __DIR__.'/../views',));
$app->register(new \Silex\Provider\UrlGeneratorServiceProvider());

$app['twig']->getExtension('core')->setTimezone(
    isset($settings['timezone']) ? $settings['timezone']:'Europe/Zurich'
);

Oauth::register($app, $config->settings);

Sqlite::register($app, $config->settings);
if (!file_exists($config->settings['dbPath'])) {
    Sqlite::create($app, $config);
    $people = $yaml->parse(file_get_contents('../config/people.yaml'));
    $persons = $people['persons'];
    $teams = $people['teams'];
    Sqlite::populate($app, $persons, $teams);
}

// Get email address and user name here, as Oauth has just checked the user has an @liip.ch email address.


$app['debug'] = true;
$app->get(
    '/login',
    function () use ($app) {
        return $app['twig']->render(
            'login.twig',
            array(
                'loginPath' => $app['url_generator']->generate(
                    '_auth_service',
                    array(
                        'service' => 'google',
                        '_csrf_token' => $app['form.csrf_provider']->generateCsrfToken('oauth')
                    )
                ),
                'logoutPath'  => $app['url_generator']->generate(
                    'logout',
                    array('_csrf_token' => $app['form.csrf_provider']->generateCsrfToken('logout'))
                )
            )
        );
    }
);

$app->match(
    '/logout',
    function () {
    }
)->bind('logout');

/**
 * List with all teams
 */
$app->get(
    '/',
    function () use ($app, $config) {

        return $app['twig']->render(
            'index.twig',
            array(
                'teams'   => Sqlite::allTeams($app),
                'persons' => Sqlite::allPersons($app),
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
            $calendar     = new GoogleCalendar($app, $config->settings['google'], $startDate, $endDate);

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
            )
        );
    }
)
->bind('availabilities');

$app->run();

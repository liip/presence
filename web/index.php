<?php

namespace Presence;

use Symfony\Component\Yaml\Dumper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Silex\Provider\DoctrineServiceProvider;

// we need APC for the caching
if (! (extension_loaded('apc') && ini_get('apc.enabled'))) {
    throw new \Exception("Cannot find the PHP APC module. Please install or enable it for this app to work");
}

require_once __DIR__ . '/../vendor/autoload.php';

// parse and store the yaml configuration
$yaml             = new \Symfony\Component\Yaml\Parser();
$config           = new Config();
$config->settings = $yaml->parse(file_get_contents(__DIR__ . '/../config/settings.yaml'));
$config->people   = $yaml->parse(file_get_contents(__DIR__ . '/../config/people.yaml'));

// load Silex and register providers
$app = new \Silex\Application();
$app->register(new \Silex\Provider\TwigServiceProvider(), array('twig.path' => __DIR__.'/../views',));
$app->register(new \Silex\Provider\UrlGeneratorServiceProvider());

$app['twig']->getExtension('core')->setTimezone(
    isset($settings['timezone']) ? $settings['timezone']:'Europe/Zurich'
);

Oauth::register($app, $config->settings);

$app['debug'] = true;

// register the db
$app->register(
    new DoctrineServiceProvider(),
    array(
        'db.options' => array(
            'driver' => 'pdo_sqlite',
            'path' => $config->settings['dbPath']
        )
    )
);

$sqlite = new Sqlite($app['db']);

if (!file_exists($config->settings['dbPath'])) {
    $sqlite->create();
    $people = $yaml->parse(file_get_contents(__DIR__ . '/../config/people.yaml'));
    $persons = $people['persons'];
    $teams = $people['teams'];
    $sqlite->populate($persons, $teams);
}

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
    function () use ($app, $config, $sqlite) {

        $helper       = new DateHelper();
        $startDate    = $helper->getStartDate($app['request']->get('week'));
        $weeks        = $app['request']->get('view', 1);
        $endDate      = $helper->getEndDate($weeks);
        $days         = $helper->getDays($startDate, $endDate);
        $calendar     = new GoogleCalendar($config->settings['google'], $startDate, $endDate);

        return $app['twig']->render(
            'index.twig',
            array(
                'teams'   => $sqlite->allTeams(),
                'persons' => $sqlite->allPersons(),
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
            $persons = $sqlite->allPersons();

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

                    $mail_spl = explode(".", trim(strtolower($array['email'])));

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
    function ($teamId) use ($app, $config, $sqlite) {

        try {

            $helper       = new DateHelper();
            $projectsMode = ($app['request']->get('mode', 'availability') === 'projects');
            $startDate    = $helper->getStartDate($app['request']->get('week'));
            $weeks        = $app['request']->get('view', 1);
            $showDetails  = $app['request']->get('details', 1);
            $endDate      = $helper->getEndDate($weeks);
            $days         = $helper->getDays($startDate, $endDate);
            $calendar     = new GoogleCalendar($config->settings['google'], $startDate, $endDate);
            $getTeam      = $sqlite->getTeam($teamId);
            $nonTeam      = $sqlite->getTeamsNonMembers($teamId);
            $slack        = $sqlite->getSlackChannel($teamId);
            $refresh      = $app['request']->get('refresh');

            if ($getTeam) {
                $team = new Team(
                    $teamId,
                    $calendar,
                    $sqlite,
                    $refresh
                );
            } elseif ($sqlite->getPerson($teamId)) {
                $team = new TeamOfOne(
                    $teamId,
                    $calendar,
                    $sqlite,
                    $refresh
                );
            } else {
                $app->abort(404, 'No team or person found with ID ' . $teamId . '.');
            }

        } catch (\Exception $e) {
            $app->abort(404, $e->getMessage());
        }

        // render the twig template, the team object with the members and their events is passed
        return $app['twig']->render(
            (($projectsMode) ? 'projects' : 'availabilities' ). '.twig',
            array(
                'teams'               => $sqlite->allTeams(),
                'team'                => $team,
                'days'                => $days,
                'weeks'               => $weeks,
                'showDetails'         => $showDetails,
                'projectsMode'        => $projectsMode,
                'nonteam'             => $nonTeam,
                'slack'               => $slack
            )
        );
    }
)
->bind('availabilities');

/**
 * Add member to team
 */
$app->get(
    '/{teamId}/{personId}/add',
    function($teamId, $personId) use ($app, $sqlite) {
        try {

            $getTeam    = $sqlite->getTeam($teamId);
            $getPerson  = $sqlite->getPerson($personId);

            if ($getTeam) {
                if ($getPerson) {
                    // Add person to team
                    $sqlite->addToTeam($getTeam, $getPerson);
                } else {
                    $app->abort(404, 'No person found with ID ' . $personId . '.');
                }
            } else {
                $app->abort(404, 'No team or person found with ID ' . $teamId . '.');
            }
            return $app->redirect('/' . $teamId);
        } catch (\Exception $e) {
            $app->abort(404, $e->getMessage());
        }
    }
)
->bind('add');

/**
 * Delete member from team
 */
$app->get(
    '/{teamId}/{personId}/delete',
    function($teamId, $personId) use ($app, $sqlite) {
        try {

            $getTeam    = $sqlite->getTeam($teamId);
            $getPerson  = $sqlite->getPerson($personId);

            if ($getTeam) {
                if ($getPerson) {
                    if ($sqlite->personInTeam($getTeam, $getPerson)) {
                        // Delete person from team
                        $sqlite->removeFromTeam($getTeam, $getPerson);
                    }
                } else {
                    $app->abort(404, 'No person found with ID ' . $personId . '.');

                }
            } else {
                $app->abort(404, 'No team found with ID ' . $teamId . '.');
            }
            return $app->redirect('/' . $teamId);
        } catch (\Exception $e) {
            $app->abort(404, $e->getMessage());
        }
    }
)
->bind('delete');

/**
 * Create team
 */
$app->post(
    '/create',
    function(Request $request) use ($app, $sqlite) {
        try {
            $name = $request->request->get('teamName');

            $slug = ($sqlite->createTeam($name));
            if ($slug) {
                return $app->redirect('/' . $slug);
            } else {
                return $app->abort(404, 'A team with the name ' . $name . ' already exists.');
            }
        } catch (\Exception $e) {
            $app->abort(404, $e->getMessage());
        }
    }
)
->bind('createTeam');

$app->match(
    '/{teamId}/delete',
    function($teamId) use ($app, $sqlite) {
        try {
            $teamDeleted = $sqlite->deleteTeam($teamId, $app['request']->get('isPerson', 'false'));
            return $app->redirect('/');
        } catch (\Exception $e) {
            $app->abort(404, $e->getMessage());
        }
    }
)
->method('GET|POST')
->bind('deleteTeam');

$app->post(
    '/slack',
    function (Request $request) use ($app, $sqlite) {
        try {
            $channel = $request->request->get('channel');
            $teamId = $request->request->get('teamId');
            $sqlite->setSlackChannel($teamId, $channel);
            return $app->redirect('/' . $teamId);
        } catch (\Exception $e) {
            $app->abort(404, $e->getMessage());
        }
    }
)
->bind('slack');

$app->run();

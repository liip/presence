<?php

namespace Presence;

use Symfony\Component\Yaml\Dumper;

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

        $helper       = new DateHelper();
        $startDate    = $helper->getStartDate($app['request']->get('week'));
        $weeks        = $app['request']->get('view', 1);
        $endDate      = $helper->getEndDate($weeks);
        $days         = $helper->getDays($startDate, $endDate);
        $calendar     = new GoogleCalendar($config->settings['google'], $startDate, $endDate);

        return $app['twig']->render(
            'index.twig',
            array(
                'teams'   => Sqlite::allTeams($app, $calendar),
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
            $persons = Sqlite::allPersons($app);

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

            $helper       = new DateHelper();
            $projectsMode = ($app['request']->get('mode', 'availability') === 'projects');
            $startDate    = $helper->getStartDate($app['request']->get('week'));
            $weeks        = $app['request']->get('view', 1);
            $showDetails  = $app['request']->get('details', 1);
            $endDate      = $helper->getEndDate($weeks);
            $days         = $helper->getDays($startDate, $endDate);
            $calendar     = new GoogleCalendar($app, $config->settings['google'], $startDate, $endDate);
            $persons      = Sqlite::allPersons($app);
            $getTeam      = Sqlite::getTeam($app, $teamId);
            $nonTeam      = Sqlite::getTeamsNonMembers($app, $teamId);

            if ($getTeam) {
                $team = new Team(
                    $app,
                    $teamId,
                    $calendar
                );
            } elseif (Sqlite::getPerson($app, $teamId)) {
                $team = new TeamOfOne(
                    $app,
                    $teamId,
                    $calendar
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
                'teams'               => Sqlite::allTeams($app, $calendar),
                'team'                => $team,
                'days'                => $days,
                'weeks'               => $weeks,
                'showDetails'         => $showDetails,
                'projectsMode'        => $projectsMode,
                'nonteam'             => $nonTeam
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
    function($teamId, $personId) use ($app) {
        try {

            $getTeam    = Sqlite::getTeam($app, $teamId);
            $getPerson  = Sqlite::getPerson($app, $personId);

            if ($getTeam) {
                if ($getPerson) {
                    // Add person to team
                    Sqlite::addToTeam($app, $getTeam, $getPerson);
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
    function($teamId, $personId) use ($app) {
        try {

            $getTeam    = Sqlite::getTeam($app, $teamId);
            $getPerson  = Sqlite::getPerson($app, $personId);

            if ($getTeam) {
                if ($getPerson) {
                    if (Sqlite::personInTeam($app, $getTeam, $getPerson)) {
                        // Delete person from team
                        Sqlite::removeFromTeam($app, $getTeam, $getPerson);
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
$app->get(
    '/{teamId}/create',
    function($teamId) use ($app) {
        try {
            $teamCreated = (Sqlite::createTeam($app, $teamId));
            if ($teamCreated) {
                return $app->redirect('/{teamId}');
            } else {
                return $app->abort(404, 'A team with the slug ' . $teamId . ' already exists.');
            }
        } catch (\Exception $e) {
            $app->abort(404, $e->getMessage());
        }
    }
)
->bind('createTeam');

$app->get(
    '/{teamId}/delete',
    function($teamId) use ($app) {
        try {
            $teamDeleted = (Sqlite::deleteTeam($app, $teamId));
            return $app->redirect('/');
        } catch (\Exception $e) {
            $app->abort(404, $e->getMessage());
        }
    }
)
->bind('deleteTeam');

$app->run();

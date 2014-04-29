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

// Oauth::register($app, $config->settings);

Sqlite::register($app, $config->settings);
if (!file_exists($config->settings['dbPath'])) {
    Sqlite::create($app, $config);
    $persons = $yaml->parse(file_get_contents('../config/people.yaml'))['persons'];
    $teams = $yaml->parse(file_get_contents('../config/people.yaml'))['teams'];
    Sqlite::populate($app, $persons, $teams);
}

// Get email address and user name here, as Oauth has just checked the user has an @liip.ch email address. 


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
            $calendar     = new GoogleCalendar($config->settings['google'], $startDate, $endDate);
            $persons      = $config->people['persons'];

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
            
            $nonteam = array();
            
            foreach ($persons as $id=>$person) {
                if (!in_array($teamId, array_keys($person['teams']))) {
                    $nonteam[$id] = $person;
                }
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
                'serviceAccountEmail' => $config->settings['google']['serviceAccountName'],
                'nonteam'             => $nonteam
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
    function($teamId, $personId) use ($app, $config) {
        if (!empty($config->people['teams'][$teamId])) {
            if (!empty($config->people['persons'][$personId])) {
                // Add team to person
                $config->people['persons'][$personId]['teams'][$teamId] = null;
                
                // Save config file
                $dumper = new Dumper();
                $yaml = $dumper->dump($config->people, 4);
                file_put_contents('../config/people.yaml', $yaml);
            } else {
                $app->abort(404, 'No person found with ID ' . $personId . ' does not exist');
            }
        } else {
            $app->abort(404, 'No team or person found with ID ' . $teamId . ' does not exist');
        }
                
        return $app->redirect('/' . $teamId);
    }
)
->bind('add');
 
/**
 * Delete member from team
 */
$app->get(
    '/{teamId}/{personId}/delete',
    function($teamId, $personId) use ($app, $config) {
        if (!empty($config->people['teams'][$teamId])) {
            if (!empty($config->people['persons'][$personId])) {
                if (array_key_exists($teamId, $config->people['persons'][$personId]['teams'])) {
                    // Delete team from person
                    unset($config->people['persons'][$personId]['teams'][$teamId]);
                    
                    // Save config file
                    $dumper = new Dumper();
                    $yaml = $dumper->dump($config->people, 4);
                    file_put_contents('../config/people.yaml', $yaml);
                }
            } else {
                $app->abort(404, 'No person found with ID ' . $personId . ' does not exist');
            }
        } else {
            $app->abort(404, 'No team found with ID ' . $teamId . ' does not exist');
        }
                
        return $app->redirect('/' . $teamId);
    }
)
->bind('delete');

$app->run();

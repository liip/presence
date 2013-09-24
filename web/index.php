<?php

namespace Teamavailabilities;

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

/**
 * List with all teams
 */
$app->get(
    '/',
    function () use ($app, $config) {
        return $app['twig']->render('index.twig', array('teams' => $config->people['teams']));
    }
)
->bind('homepage');

/**
 * Get team availabilities
 */
$app->get(
    '/{teamId}',
    function ($teamId) use ($app, $config) {

        try {

            $config->people['refresh'] = $app['request']->get('refresh');

            $helper      = new DateHelper();
            $startDate   = $helper->getStartDate($app['request']->get('week'));
            $weeks       = $app['request']->get('view', 1);
            $showDetails = $app['request']->get('details', 1);
            $endDate     = $helper->getEndDate($weeks);
            $days        = $helper->getDays($startDate, $endDate);

            $team = new Team(
                $teamId,
                $config->people,
                new GoogleCalendar($config->settings['google'], $startDate, $endDate)
            );

        } catch (\Exception $e) {
            $app->abort(404, $e->getMessage());
        }

        // render the twig template, the team object with the members and their events is passed
        return $app['twig']->render(
            'availabilities.twig',
            array(
                'teams'               => $config->people['teams'],
                'team'                => $team,
                'days'                => $days,
                'weeks'             => $weeks,
                'showDetails'         => $showDetails,
                'serviceAccountEmail' => $config->settings['google']['serviceAccountName']
            )
        );
    }
)
->bind('availabilities');

$app->run();

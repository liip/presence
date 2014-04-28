<?php

namespace Presence;

use Silex\Provider\DoctrineServiceProvider;

/**
 * Registers Doctrine service provider with SQLite database.
 */

class Sqlite {

    public static function register($app, $config) {
        $app->register(new DoctrineServiceProvider(), array(
            'db.options' => array(
                'driver' => 'pdo_sqlite',
                'path' => $config['dbPath']
            )
        ));
    }
    
    public static function create($app, $config) {
        // Create tables and populate from people.yaml.
        $setup = $app['db']->prepare(
            'PRAGMA foreign_keys = ON;'
        );
        $setup->execute();
        $setup = $app['db']->prepare(
            'CREATE TABLE teams (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT UNIQUE NOT NULL,
                name TEXT UNIQUE NOT NULL
            );'
        );
        $setup->execute();
        $setup = $app['db']->prepare(
            'CREATE TABLE persons (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL
            );'
        );
        $setup->execute();
        $setup = $app['db']->prepare(
            'CREATE TABLE teams_to_persons (
                teams_id INT REFERENCES teams (id) ON DELETE CASCADE,
                persons_id INT REFERENCES persons (id) ON DELETE CASCADE,
                UNIQUE (teams_id, persons_id) ON CONFLICT IGNORE
            );'
        );
        $setup->execute();
        
    }

    public static function populate($app, $config) {        
        $persons = $config->people['persons'];
        foreach ($persons as $id=>$person) {
            $app['db']->insert(
                'persons',
                array('email' => $id, 'name' => $person['name'])
            );
        }
        
        $teams = $config->people['teams'];
        foreach ($teams as $id=>$team) {
            $app['db']->insert(
                'teams',
                array('slug' => $id, 'name' => $team['name'])
            );
        }
        
        foreach ($persons as $id=>$person) {
            $sql = "SELECT * FROM persons WHERE email = ?";
            $stmt = $app['db']->prepare($sql);
            $stmt->bindValue(1, $id);
            $stmt->execute();
            $persons_id = $stmt->fetchAll()[0]['id'];
            foreach ($persons[$id]['teams'] as $team=>$null) {
                $sql = "SELECT * FROM teams WHERE slug = ?";
                $stmt = $app['db']->prepare($sql);
                $stmt->bindValue(1, $team);
                $stmt->execute();
                $teams_id = $stmt->fetchAll()[0]['id'];
                $app['db']->insert(
                    'teams_to_persons',
                    $a = array('teams_id' => $teams_id, 'persons_id' => $persons_id)
                );
            }
        }
    }
    
}




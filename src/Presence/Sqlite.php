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

    public static function create($app) {
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
                name TEXT NOT NULL,
                refreshtoken TEXT
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

    public static function populate($app, $persons, $teams) {
        foreach ($persons as $id=>$person) {
            $app['db']->insert(
                'persons',
                array('email' => $id, 'name' => $person['name'])
            );
        }

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
            $results = $stmt->fetchAll();
            $persons_id = $results[0]['id'];
            foreach ($persons[$id]['teams'] as $team=>$null) {
                $sql = "SELECT * FROM teams WHERE slug = ?";
                $stmt = $app['db']->prepare($sql);
                $stmt->bindValue(1, $team);
                $stmt->execute();
                $results = $stmt->fetchAll();
                $teams_id = $results[0]['id'];
                $app['db']->insert(
                    'teams_to_persons',
                    $a = array('teams_id' => $teams_id, 'persons_id' => $persons_id)
                );
            }
        }
    }

    // Returns array of Person objects for all rows in persons db table.
    public static function allPersons($app) {
        $sql = "SELECT * FROM persons p";
        $stmt = $app['db']->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll();
        $persons = array();
        foreach ($results as $result) {
            $id = $result['email'];
            $data = array(
                'name' => $result['name'],
                'teams' => Sqlite::getPersonsTeams($app, $result['id'])
            );
            $person = new Person($id, $data);
            array_push($persons, $person);
        }
        return $persons;
    }

    //Returns array of Team objects for all rows in teams db table.
    public static function allTeams($app) {
        $sql = "SELECT * FROM teams t";
        $stmt = $app['db']->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
        $teams = array();
        foreach ($results as $result) {
            $id = $result['slug'];
            $data = array(
                'name' => $result['name'],
                'members' => Sqlite::getTeamsMembers($app, $result['id'])
            );
            $person = new Person($id, $data);
            array_push($persons, $person);
        }
        return $persons;
    }

    public static function getTeam($app, $slug) {
        $sql = "SELECT * FROM teams
                WHERE slug = ?";
        $stmt = $app['db']->prepare($sql);
        $stmt->bindValue(1, $slug);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getPersonsTeams($app, $persons_id) {
        $sql = "SELECT * FROM teams t
                JOIN teams_to_persons tp
                ON tp.teams_id = t.id
                AND tp.persons_id = ?";
        $stmt = $app['db']->prepare($sql);
        $stmt->bindValue(1, $persons_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    public static function getTeamsMembers($app, $teams_id) {
        $sql = "SELECT * FROM persons p
                JOIN teams_to_persons tp
                ON tp.persons_id = p.id
                AND tp.teams_id = ?";
        $stmt = $app['db']->prepare($sql);
        $stmt->bindValue(1, $team);
        $stmt->execute();
        $results = $stmt->fetchAll();
        return $results[0];
    }

}

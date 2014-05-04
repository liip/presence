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
    public static function allTeams($app, $calendar) {
        $sql = "SELECT * FROM teams t";
        $stmt = $app['db']->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll();
        $teams = array();
        foreach ($results as $result) {
            $id = $result['slug'];
            $team = new Team($app, $id, $calendar);
            array_push($teams, $team);
        }
        return $teams;
    }
    
    public static function getTeam($app, $slug) {
        $sql = "SELECT * FROM teams
                WHERE slug = ?";
        $stmt = $app['db']->prepare($sql);
        $stmt->bindValue(1, $slug);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public static function getPerson($app, $email) {
        $sql = "SELECT * FROM persons
                WHERE email = ?";
        $stmt = $app['db']->prepare($sql);
        $stmt->bindValue(1, $email);
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
    
    public static function getTeamsMembers($app, $slug) {
        $sql = "SELECT * FROM persons p 
                JOIN teams_to_persons tp 
                ON tp.persons_id = p.id 
                AND tp.teams_id = (SELECT id FROM teams WHERE slug = ?)";
        $stmt = $app['db']->prepare($sql);
        $stmt->bindValue(1, $slug);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public static function getTeamsNonMembers($app, $slug) {
        $sql = "SELECT * FROM persons p 
                WHERE NOT EXISTS (
                    SELECT * FROM teams_to_persons
                    WHERE persons_id = p.id
                    AND teams_id = (SELECT id FROM teams WHERE slug = ?)
                )";
        $stmt = $app['db']->prepare($sql);
        $stmt->bindValue(1, $slug);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public static function addToTeam($app, $team, $person) {
        $sql = "INSERT INTO teams_to_persons (teams_id, persons_id) VALUES (?, ?)";
        $stmt = $app['db']->prepare($sql);
        $stmt->bindValue(1, $team[0]['id']);
        $stmt->bindValue(2, $person[0]['id']);
        $stmt->execute();
    }
    
    public static function personInTeam($app, $team, $person) {
        $sql = "SELECT * FROM teams_to_persons 
                WHERE teams_id = ? 
                AND persons_id = ?";
        $stmt = $app['db']->prepare($sql);
        $stmt->bindValue(1, $team[0]['id']);
        $stmt->bindValue(2, $person[0]['id']);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public static function removeFromTeam($app, $team, $person) {
        $sql = "DELETE FROM teams_to_persons 
                WHERE teams_id = ? 
                AND persons_id = ?";
        $stmt = $app['db']->prepare($sql);
        $stmt->bindValue(1, $team[0]['id']);
        $stmt->bindValue(2, $person[0]['id']);
        $stmt->execute();
    }

    public static function createTeam($app, $name) {
        $pattern = '/[^a-z0-9]/';
        $replacement = '';
        $subject = strtolower($name);
        $slug = preg_replace($pattern, $replacement, $subject);
        $sql = "SELECT * FROM teams WHERE slug = ?";
        $stmt = $app['db']->prepare($sql);
        $stmt->bindValue(1, $slug);
        $stmt->execute();
        $result = $stmt->fetchAll();
        if (!$result) {
            $sql = "INSERT INTO teams (slug, name) VALUES (?, ?)";
            $stmt = $app['db']->prepare($sql);
            $stmt->bindValue(1, $slug);
            $stmt->bindValue(2, $name);
            $stmt->execute();
            return 1;
        } else {
            return 0;
        }
    }

    public static function deleteTeam($app, $slug) {
        $sql = "DELETE FROM teams
                WHERE slug = ?";
        $stmt = $app['db']->prepare($sql);
        $stmt->bindValue(1, $slug);
        $stmt->execute();
    }

}




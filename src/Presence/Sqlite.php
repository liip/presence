<?php

namespace Presence;

use Silex\Provider\DoctrineServiceProvider;

/**
 * Registers Doctrine service provider with SQLite database.
 */

class Sqlite {

    public function __construct($app) {
        $this->app = $app;
    }

    public function register($config) {
        $this->app->register(new DoctrineServiceProvider(), array(
            'db.options' => array(
                'driver' => 'pdo_sqlite',
                'path' => $config['dbPath']
            )
        ));
    }

    public function create() {
        // Create tables and populate from people.yaml.
        $setup = $this->app['db']->prepare(
            'PRAGMA foreign_keys = ON;'
        );
        $setup->execute();
        $setup = $this->app['db']->prepare(
            'CREATE TABLE teams (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT UNIQUE NOT NULL,
                name TEXT UNIQUE NOT NULL
            );'
        );
        $setup->execute();
        $setup = $this->app['db']->prepare(
            'CREATE TABLE persons (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                refreshtoken TEXT
            );'
        );
        $setup->execute();
        $setup = $this->app['db']->prepare(
            'CREATE TABLE teams_to_persons (
                teams_id INT REFERENCES teams (id) ON DELETE CASCADE,
                persons_id INT REFERENCES persons (id) ON DELETE CASCADE,
                UNIQUE (teams_id, persons_id) ON CONFLICT IGNORE
            );'
        );
        $setup->execute();

    }

    public function populate($persons, $teams) {
        foreach ($persons as $id=>$person) {
            $this->app['db']->insert(
                'persons',
                array('email' => $id, 'name' => $person['name'])
            );
        }

        foreach ($teams as $id=>$team) {
            $this->app['db']->insert(
                'teams',
                array('slug' => $id, 'name' => $team['name'])
            );
        }

        foreach ($persons as $id=>$person) {
            $sql = "SELECT * FROM persons WHERE email = ?";
            $stmt = $this->app['db']->prepare($sql);
            $stmt->bindValue(1, $id);
            $stmt->execute();
            $results = $stmt->fetchAll();
            $persons_id = $results[0]['id'];
            foreach ($persons[$id]['teams'] as $team=>$null) {
                $sql = "SELECT * FROM teams WHERE slug = ?";
                $stmt = $this->app['db']->prepare($sql);
                $stmt->bindValue(1, $team);
                $stmt->execute();
                $results = $stmt->fetchAll();
                $teams_id = $results[0]['id'];
                $this->app['db']->insert(
                    'teams_to_persons',
                    $a = array('teams_id' => $teams_id, 'persons_id' => $persons_id)
                );
            }
        }
    }

    // Returns array of Person objects for all rows in persons db table.
    public function allPersons() {
        $sql = "SELECT * FROM persons p";
        $stmt = $this->app['db']->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    //Returns array of Team objects for all rows in teams db table.
    public function allTeams($calendar) {
        $sql = "SELECT * FROM teams t";
        $stmt = $this->app['db']->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTeam($slug) {
        $sql = "SELECT * FROM teams
                WHERE slug = ?";
        $stmt = $this->app['db']->prepare($sql);
        $stmt->bindValue(1, $slug);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPerson($email) {
        $sql = "SELECT * FROM persons
                WHERE email = ?";
        $stmt = $this->app['db']->prepare($sql);
        $stmt->bindValue(1, $email);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPersonsTeams($persons_id) {
        $sql = "SELECT * FROM teams t
                JOIN teams_to_persons tp
                ON tp.teams_id = t.id
                AND tp.persons_id = ?";
        $stmt = $this->app['db']->prepare($sql);
        $stmt->bindValue(1, $persons_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTeamsMembers($slug) {
        $sql = "SELECT * FROM persons p
                JOIN teams_to_persons tp
                ON tp.persons_id = p.id
                AND tp.teams_id = (SELECT id FROM teams WHERE slug = ?)";

        $stmt = $this->app['db']->prepare($sql);
        $stmt->bindValue(1, $slug);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTeamsNonMembers($slug) {
        $sql = "SELECT * FROM persons p
                WHERE NOT EXISTS (
                    SELECT * FROM teams_to_persons
                    WHERE persons_id = p.id
                    AND teams_id = (SELECT id FROM teams WHERE slug = ?)
                )";
        $stmt = $this->app['db']->prepare($sql);
        $stmt->bindValue(1, $slug);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function addToTeam($team, $person) {
        $sql = "INSERT INTO teams_to_persons (teams_id, persons_id) VALUES (?, ?)";
        $stmt = $this->app['db']->prepare($sql);
        $stmt->bindValue(1, $team[0]['id']);
        $stmt->bindValue(2, $person[0]['id']);
        $stmt->execute();
    }

    public function personInTeam($team, $person) {
        $sql = "SELECT * FROM teams_to_persons
                WHERE teams_id = ?
                AND persons_id = ?";
        $stmt = $this->app['db']->prepare($sql);
        $stmt->bindValue(1, $team[0]['id']);
        $stmt->bindValue(2, $person[0]['id']);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function removeFromTeam($team, $person) {
        $sql = "DELETE FROM teams_to_persons
                WHERE teams_id = ?
                AND persons_id = ?";
        $stmt = $this->app['db']->prepare($sql);
        $stmt->bindValue(1, $team[0]['id']);
        $stmt->bindValue(2, $person[0]['id']);
        $stmt->execute();
    }

    public function createTeam($name) {
        $pattern = '/[^a-z0-9]/';
        $replacement = '';
        $subject = strtolower($name);
        $slug = preg_replace($pattern, $replacement, $subject);
        $sql = "SELECT * FROM teams WHERE slug = ?";
        $stmt = $this->app['db']->prepare($sql);
        $stmt->bindValue(1, $slug);
        $stmt->execute();
        $result = $stmt->fetchAll();
        if (!$result) {
            $sql = "INSERT INTO teams (slug, name) VALUES (?, ?)";
            $stmt = $this->app['db']->prepare($sql);
            $stmt->bindValue(1, $slug);
            $stmt->bindValue(2, $name);
            $stmt->execute();
            return $slug;
        } else {
            return 0;
        }
    }

    public function deleteTeam($slug) {
        $sql = "DELETE FROM teams
                WHERE slug = ?";
        $stmt = $this->app['db']->prepare($sql);
        $stmt->bindValue(1, $slug);
        $stmt->execute();
    }
}

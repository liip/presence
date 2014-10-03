<?php

namespace Presence;

class Sqlite {

    public function __construct($db) {
        $this->db = $db;
    }

    public function create() {
        // Create tables and populate from people.yaml.
        $setup = $this->db->prepare(
            'PRAGMA foreign_keys = ON;'
        );
        $setup->execute();
        $setup = $this->db->prepare(
            'CREATE TABLE teams (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT UNIQUE NOT NULL,
                name TEXT UNIQUE NOT NULL,
                slack TEXT
            );'
        );
        $setup->execute();
        $setup = $this->db->prepare(
            'CREATE TABLE persons (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                refreshtoken TEXT
            );'
        );
        $setup->execute();
        $setup = $this->db->prepare(
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
            $this->db->insert(
                'persons',
                array('email' => $id, 'name' => $person['name'])
            );
        }

        foreach ($teams as $id=>$team) {
            $this->db->insert(
                'teams',
                array('slug' => $id, 'name' => $team['name'])
            );
        }

        foreach ($persons as $id=>$person) {
            $sql = "SELECT * FROM persons WHERE email = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(1, $id);
            $stmt->execute();
            $results = $stmt->fetchAll();
            $persons_id = $results[0]['id'];
            foreach ($persons[$id]['teams'] as $team=>$null) {
                $sql = "SELECT * FROM teams WHERE slug = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(1, $team);
                $stmt->execute();
                $results = $stmt->fetchAll();
                $teams_id = $results[0]['id'];
                $this->db->insert(
                    'teams_to_persons',
                    $a = array('teams_id' => $teams_id, 'persons_id' => $persons_id)
                );
            }
        }
    }

    // Returns array of Person objects for all rows in persons db table.
    public function allPersons() {
        $sql = "SELECT * FROM persons p";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    //Returns array of Team objects for all rows in teams db table.
    public function allTeams($calendar) {
        $sql = "SELECT * FROM teams t";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTeam($slug) {
        $sql = "SELECT * FROM teams
                WHERE slug = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $slug);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPerson($email) {
        $sql = "SELECT * FROM persons
                WHERE email = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $email);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPersonsTeams($persons_id) {
        $sql = "SELECT * FROM teams t
                JOIN teams_to_persons tp
                ON tp.teams_id = t.id
                AND tp.persons_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $persons_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTeamsMembers($slug) {
        $sql = "SELECT * FROM persons p
                JOIN teams_to_persons tp
                ON tp.persons_id = p.id
                AND tp.teams_id = (SELECT id FROM teams WHERE slug = ?)";

        $stmt = $this->db->prepare($sql);
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
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $slug);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function addToTeam($team, $person) {
        $sql = "INSERT INTO teams_to_persons (teams_id, persons_id) VALUES (?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $team[0]['id']);
        $stmt->bindValue(2, $person[0]['id']);
        $stmt->execute();
    }

    public function personInTeam($team, $person) {
        $sql = "SELECT * FROM teams_to_persons
                WHERE teams_id = ?
                AND persons_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $team[0]['id']);
        $stmt->bindValue(2, $person[0]['id']);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function removeFromTeam($team, $person) {
        $sql = "DELETE FROM teams_to_persons
                WHERE teams_id = ?
                AND persons_id = ?";
        $stmt = $this->db->prepare($sql);
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
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $slug);
        $stmt->execute();
        $result = $stmt->fetchAll();
        if (!$result) {
            $sql = "INSERT INTO teams (slug, name) VALUES (?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(1, $slug);
            $stmt->bindValue(2, $name);
            $stmt->execute();
            return $slug;
        } else {
            return 0;
        }
    }

    public function deleteTeam($slug, $person) {
        $this->db->beginTransaction();
        if ('true' === $person) {
            $stmt = $this->db->prepare("DELETE FROM teams_to_persons WHERE persons_id IN (SELECT id FROM persons WHERE email = ?)");
            $stmt->bindValue(1, $slug);
            $stmt->execute();
            $stmt = $this->db->prepare("DELETE FROM persons WHERE email = ?");
            $stmt->bindValue(1, $slug);
            $stmt->execute();
        } else {
            $stmt = $this->db->prepare("DELETE FROM teams WHERE slug = ?");
            $stmt->bindValue(1, $slug);
            $stmt->execute();
        }
        $this->db->commit();
    }

    public function setSlackChannel($slug, $slack) {
        $sql = "UPDATE teams SET slack = ?
                WHERE slug = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $slack);
        $stmt->bindValue(2, $slug);
        $stmt->execute();
    }

    public function getSlackChannel($slug) {
        $sql = "SELECT slack from teams
                WHERE slug = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $slug);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getSlackTeams() {
        $sql = "SELECT slug, slack from teams
                WHERE slack <> ''";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();

    }
}

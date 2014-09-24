<?php

namespace Presence;

/**
 * Team that the calendar will be displayed for.
 */
class Team
{
    /**
     * The id of this team.
     *
     * @var string
     */
    protected $id = '';

    /**
     * The name of this team.
     *
     * @var string
     */
    protected $name = '';

    /**
     * The members of this team.
     *
     * @var array containing the Person objects
     */
    protected $members = array();

    /**
     * Calendar object.
     *
     * @var CalendarInterface a Calendar object
     */
    protected $calendar = null;

    /**
     * Refresh GET parameter.
     *
     * @var string
     */
    protected $refresh  = '';

    /**
     * Assemble the team for this instance.
     *
     * @param string            $id       The team id.
     * @param CalendarInterface $calendar Calendar object.
     * @param Sqlite            $sqlite   Sqlite object.
     * @param boolean           $refresh  Refresh or not.
     */
    public function __construct($id, CalendarInterface $calendar, Sqlite $sqlite, $refresh)
    {
        // TODO add validation

        $this->id       = $id;
        $this->calendar = $calendar;
        $this->refresh  = $refresh;
        $team           = $sqlite->getTeam($id);
        $this->name     = $team[0]['name'];
        $this->members  = $this->getTeamMembers($sqlite->getTeamsMembers($id));
    }

    /**
     * Check if the team exists and extract the name.
     *
     * @param array $teams All the existing persons.
     *
     * @throws \Exception When a team could not be found.
     * @return string the name of the team
     */
    protected function getTeamName(array $teams)
    {
        if (isset($teams[$this->id])) {
            return $teams[$this->id]['name'];
        } else {
            throw new \Exception('Team with the ID ' . $this->id . ' does not exist');
        }
    }

    /**
     * Find the team members and instantiate them.
     *
     * @param array $persons All the existing persons.
     *
     * @return array members that belong to this team
     */
    protected function getTeamMembers(array $persons)
    {
        $members = array();

        foreach ($persons as $person) {
            $members[] = $this->getPerson($person['email'], $person['name']);
        }

        return $members;
    }

    /**
     * Instantiate a Person (team member) from scratch or loads it from the cache.
     *
     * @param string            $email    The email address of a person (used to identify a person).
     * @param array             $person   Information about a person.
     * @param CalendarInterface $calendar Calendar object.
     *
     * @return Person a Person object
     */
    protected function getPerson($email, $name)
    {
        if ($this->calendar) {
            $cacheIdParts = array(
                $email,
                $this->calendar->getStartDate(),
                $this->calendar->getEndDate()
            );
            $cacheId = implode('_', $cacheIdParts);

            $this->refresh === 'all' || $this->refresh === $email ?
                $member = false : $member = apc_fetch($cacheId);

            if (!$member) {
                $member = new Person($email, $name);
                $member->getSchedule($this->calendar);
                apc_store($cacheId, $member, $this->calendar->getCacheTtl());
            }
        } else {
            $member = new Person($email, $name);
        }

        return $member;
    }


    /**
     * Get the team name.
     *
     * @return string team name
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * Get the team id.
     *
     * @return string team id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the team members.
     *
     * @return string team members
     */
    public function getMembers()
    {
        return $this->members;
    }
}

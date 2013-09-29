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
     * @param array             $people   Configuration.
     * @param CalendarInterface $calendar Calendar object.
     */
    public function __construct($id, array $people, CalendarInterface $calendar)
    {
        // TODO add validation
        $this->id       = $id;
        $this->calendar = $calendar;
        $this->refresh  = $people['refresh'];
        $this->name     = $this->getTeamName($people['teams']);
        $this->members  = $this->getTeamMembers($people['persons']);
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

        foreach ($persons as $email => $person) {

            if (in_array($this->id, array_keys($person['teams']))) {
                $members[] = $this->getPerson($email, $person);
            }
        }

        return $members;
    }

    /**
     * Instantiate a Person (team member) from scratch or loads it from the cache.
     *
     * @param string $email  The email address of a person (used to identify a person).
     * @param array  $person Information about a person.
     *
     * @return Person a Person object
     */
    protected function getPerson($email, array $person)
    {
        $cacheIdParts = array(
            $email,
            $this->calendar->getStartDate(),
            $this->calendar->getEndDate()
        );
        $cacheId = implode('_', $cacheIdParts);

        $this->refresh === 'all' || $this->refresh === $email ?
            $member = false : $member = apc_fetch($cacheId);

        if (!$member) {
            $member = new Person($email, $person);
            $member->getSchedule($this->calendar);
            apc_store($cacheId, $member, $this->calendar->getCacheTtl());
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

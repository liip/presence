<?php

namespace Presence;

/**
 * A single person to display is treated as a team of one.
 */
class TeamOfOne extends Team
{

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
        $this->name     = $people['persons'][$id]['name'];
        $this->members  = array($this->getPerson($id, $people['persons'][$id]));
    }
}

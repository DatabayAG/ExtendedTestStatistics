<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Class ilExteStatSourceParticipant
 */
class ilExteStatSourceParticipant
{
    /**
     * the participant id
     */
    public int $active_id;

    /**
     * index of the last pass
     */
    public int $last_pass;

    /**
     * index of the best pass
     */
    public int $best_pass;

    /**
     * index of the first pass
     */
    public int $first_pass;

    /**
     * index of the scored pass
     */
    public int $scored_pass;

    /**
     * points that can be reached by the participant in the currently selected pass
     */
    public float $current_maximum_points;

    /**
     * points reached by the participant in the currently selected pass
     */
    public float $current_reached_points;
}

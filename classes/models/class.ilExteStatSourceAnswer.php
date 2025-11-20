<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Data model for the single answer of a test question
 * This model is created for every question presented to a participant
 * It is even created if the question is not answered
 */
class ilExteStatSourceAnswer
{
    /**
     * id of the answered question
     */
    public int $question_id;

    /**
     * id of the active participant
     */
    public int $active_id;

    /**
     *number of the test pass in which the question was presented
     */
    public int $pass;

    /**
     * sequence number of the question in the pass
     */
    public int $sequence;

    /**
     * the question was answered by the participant
     */
    public bool $answered = false;

    /**
     * actual points reached for the question
     */
    public float $reached_points = 0;

    /**
     * score is set manually
     */
    public bool $manual_scored = false;
}

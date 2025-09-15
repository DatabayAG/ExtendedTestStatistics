<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Data model for basic evaluation results of a question in a test
 *
 * This data is calculated for all questions before the evaluations
 * It can be used to sort and slice the questions presented on a screen
 * The further evaluation are only calculated for those questions
 */
class ilExteStatSourceQuestion
{
    /**
     * question id
     */
    public int $question_id = 0;

    /**
     * id of the original question
     */
    public ?int $original_id = null;

    /**
     * type tag of the question, e.g. 'assSingleChoice'
     */
    public string $question_type = '';

    /**
     * label of the question type, e.g. 'Single Choice Question'
     */
    public string $question_type_label = '';

    /**
     * question title
     */
    public string $question_title = '';

    /**
     * maximum points that can be reached in the question
     */
    public float $maximum_points = 0;

    /**
     * order position of the question in a fixed test
     */
    public int $order_position = 0;

    /**
     * average of points reached by the participants that got this question assigned
     */
    public float $average_points = 0;

    /**
     * average percentage reached by the participants that got this question assigned
     */
    public float $average_percentage = 0;

    /**
     * number of users who answered the question
     */
    public int $answers_count = 0;

    /**
     * number of users who got this question assigned
     */
    public int $assigned_count = 0;
}

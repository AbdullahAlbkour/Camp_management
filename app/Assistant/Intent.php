<?php

namespace App\Assistant;

use App\Models\User;

/**
 * One question the assistant knows how to answer.
 *
 * Every intent runs a fixed, parameterised Eloquent query that a developer
 * wrote and a test covers. Nothing here builds SQL from the user's words: the
 * question only selects which prepared query runs and supplies its bound
 * values, so a typed sentence can never widen what is read.
 */
abstract class Intent
{
    /** Stable identifier, reported in the response and asserted in tests. */
    abstract public function name(): string;

    /**
     * The area this intent reads from, checked against the asking user's role
     * scope before `handle()` is reached.
     */
    abstract public function group(): string;

    /**
     * How strongly this intent claims the question, or null for no claim.
     *
     * The score is the number of independent signals matched, so a question
     * carrying both a topic and a qualifier ("كم وحدة فارغة") beats one that
     * only shares the topic.
     */
    abstract public function score(AssistantQuery $query): ?int;

    abstract public function handle(AssistantQuery $query, User $user): Answer;

    /**
     * Sample questions shown in the widget. The first is used as the intent's
     * label in the suggestion list.
     *
     * @return list<string>
     */
    abstract public function examples(): array;
}

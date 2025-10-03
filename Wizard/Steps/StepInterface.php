<?php

declare(strict_types=1);

namespace JTL\Backend\Wizard\Steps;

use Illuminate\Support\Collection;
use JTL\Backend\Wizard\QuestionInterface;

/**
 * Interface StepInterface
 * @package JTL\Backend\Wizard\Steps
 */
interface StepInterface
{
    /**
     * @return string
     */
    public function getTitle(): string;

    /**
     * @param string $title
     */
    public function setTitle(string $title): void;

    /**
     * @return string
     */
    public function getDescription(): string;

    /**
     * @param string $description
     */
    public function setDescription(string $description): void;

    /**
     * @return int
     */
    public function getID(): int;

    /**
     * @param int $id
     */
    public function setID(int $id): void;

    /**
     * @param Collection<int, QuestionInterface> $questions
     */
    public function setQuestions(Collection $questions): void;

    /**
     * @param QuestionInterface $question
     */
    public function addQuestion(QuestionInterface $question): void;

    /**
     * @return Collection<int, QuestionInterface>
     */
    public function getQuestions(): Collection;

    /**
     * @param int   $questionID
     * @param mixed $value
     * @return QuestionInterface
     */
    public function answerQuestionByID(int $questionID, mixed $value): QuestionInterface;

    /**
     * @return QuestionInterface[]
     */
    public function getFilteredQuestions(): array;

    /**
     * @return Collection<int, Error>
     */
    public function getErrors(): Collection;

    /**
     * @param Collection<int, Error> $errors
     */
    public function setErrors(Collection $errors): void;

    public function addError(Error $error): void;

    public function hasCriticalError(): bool;
}

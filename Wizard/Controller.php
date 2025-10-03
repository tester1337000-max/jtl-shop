<?php

declare(strict_types=1);

namespace JTL\Backend\Wizard;

use Illuminate\Support\Collection;
use JTL\Backend\Wizard\Steps\Error;
use JTL\Backend\Wizard\Steps\ErrorCode;
use JTL\Backend\Wizard\Steps\StepInterface;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\L10n\GetText;
use JTL\Session\Backend;

/**
 * Class Controller
 * @package JTL\Backend\Wizard
 */
final class Controller
{
    /**
     * @var Collection<int, StepInterface>
     */
    private Collection $steps;

    public function __construct(
        DefaultFactory $factory,
        private readonly DbInterface $db,
        private readonly JTLCacheInterface $cache,
        GetText $getText
    ) {
        $getText->loadAdminLocale('pages/pluginverwaltung');
        $this->steps = $factory->getSteps();
    }

    /**
     * @param array<mixed> $post
     * @return Error[]
     */
    public function answerQuestions(array $post): array
    {
        if (empty($post)) {
            return [];
        }
        $post = $this->serializeToArray($post);
        foreach ($this->getSteps() as $step) {
            foreach ($step->getQuestions() as $question) {
                $question->answerFromPost($post);
            }
        }

        return $this->finish();
    }

    /**
     * @return Error[]
     */
    private function finish(): array
    {
        $errorMessages = [];
        foreach ($this->getSteps() as $step) {
            foreach ($step->getQuestions() as $question) {
                if (($errorCode = $question->save()) !== ErrorCode::OK) {
                    $step->addError(new Error($step->getID(), $question->getID(), $errorCode));
                }
            }
            $errorMessages = \array_merge($errorMessages, $step->getErrors()->toArray());
        }
        if (!$this->hasCriticalError()) {
            $this->db->update(
                'teinstellungen',
                'cName',
                'global_wizard_done',
                (object)['cWert' => 'Y']
            );
            $this->cache->flushAll();
            unset($_SESSION['wizard']);
        }

        return $errorMessages;
    }

    /**
     * @param array<mixed> $post
     * @return Error[]
     */
    public function validateStep(array $post): array
    {
        $post          = $this->serializeToArray($post);
        $errorMessages = [];
        foreach ($this->getSteps() as $step) {
            foreach ($step->getQuestions() as $question) {
                $idx = 'question-' . $question->getID();
                if (isset($post[$idx])) {
                    $question->answerFromPost($post);
                    if (($errorCode = $question->validate()) !== ErrorCode::OK) {
                        $step->addError(new Error($step->getID(), $question->getID(), $errorCode));
                    }
                }
            }
            $errorMessages = \array_merge($errorMessages, $step->getErrors()->toArray());
        }
        Backend::set('wizard', \array_merge(Backend::get('wizard') ?? [], $post));

        return $errorMessages;
    }

    /**
     * @param array<mixed> $post
     * @return array<mixed>
     */
    public function serializeToArray(array $post): array
    {
        if (\is_array($post[0])) {
            $postTMP = [];
            foreach ($post as $postItem) {
                if (\str_contains($postItem['name'], '[]')) {
                    $postTMP[\explode('[]', $postItem['name'])[0]][] = $postItem['value'];
                } else {
                    $postTMP[$postItem['name']] = $postItem['value'];
                }
            }
            $post = $postTMP;
        }

        return $post;
    }

    /**
     * @return Collection<int, StepInterface>
     */
    public function getSteps(): Collection
    {
        return $this->steps;
    }

    /**
     * @param Collection<int, StepInterface> $steps
     */
    public function setSteps(Collection $steps): void
    {
        $this->steps = $steps;
    }

    public function hasCriticalError(): bool
    {
        foreach ($this->getSteps() as $step) {
            if ($step->hasCriticalError()) {
                return true;
            }
        }

        return false;
    }
}

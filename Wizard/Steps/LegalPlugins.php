<?php

declare(strict_types=1);

namespace JTL\Backend\Wizard\Steps;

use Illuminate\Support\Collection;
use JTL\Backend\Wizard\ExtensionInstaller;
use JTL\Backend\Wizard\Question;
use JTL\Backend\Wizard\QuestionInterface;
use JTL\Backend\Wizard\QuestionType;
use JTL\Backend\Wizard\QuestionValidation;
use JTL\Backend\Wizard\SelectOption;
use JTL\DB\DbInterface;
use JTL\Recommendation\Manager;
use JTL\Recommendation\Recommendation;
use JTL\Services\JTL\AlertServiceInterface;

/**
 * Class LegalPlugins
 * @package JTL\Backend\Wizard\Steps
 */
final class LegalPlugins extends AbstractStep
{
    public function __construct(DbInterface $db, AlertServiceInterface $alertService)
    {
        parent::__construct($db, $alertService);
        $collection = new Collection();
        $this->setTitle(\__('stepThree'));
        $this->setDescription(\__('stepThreeDesc'));
        $this->setID(3);

        $scope           = Manager::SCOPE_WIZARD_LEGAL_TEXTS;
        $recommendations = new Manager($this->alertService, $scope);

        $question = new Question($db);
        $question->setID(9);
        $question->setSubheading(\__('weRecommend') . ':');
        $question->setSubheadingDescription(\__('weRecommendLegalDesc'));
        $question->setSummaryText(\__('legalTexts'));
        $question->setType(QuestionType::PLUGIN);
        $question->setIsFullWidth(true);
        $question->setIsRequired(false);
        $question->setValue(false);
        $question->setScope($scope);
        $question->setValidation(static function (QuestionInterface $question): int {
            $questionValidation = new QuestionValidation($question);
            $questionValidation->checkSSL(true);

            return $questionValidation->getValidationError();
        });

        $recommendations->getRecommendations()->each(
            static function (Recommendation $recommendation) use ($question, $collection): void {
                $option = new SelectOption();
                $option->setName($recommendation->getTitle());
                $option->setValue($recommendation->getId());
                $option->setLogoPath($recommendation->getPreviewImage());
                $option->setDescription($recommendation->getTeaser());
                $option->setLink($recommendation->getUrl());
                $question->addOption($option);
                $collection->push($recommendation);
            }
        );

        $question->setOnSave(function (QuestionInterface $question) use ($collection): void {
            $requested = $question->getValue();
            if (!\is_array($requested) || \count($requested) === 0) {
                return;
            }
            $installer = new ExtensionInstaller($this->db);
            $installer->setRecommendations($collection);
            $errorMsg = $installer->onSaveStep($requested);
            if ($errorMsg !== '') {
                $error = new Error($this->getID(), $question->getID(), ErrorCode::ERROR_PLUGIN);
                $error->setMessage($errorMsg);
                $this->addError($error);
            }
        });
        $this->addQuestion($question);
    }
}

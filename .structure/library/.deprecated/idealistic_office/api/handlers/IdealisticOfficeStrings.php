<?php

namespace idealistic_office\api\handlers;

use account\api\objects\account\Account;
use account\api\objects\information\AccountTranslation;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\interfaces\IdealisticOfficeParticipant;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;
use idealistic_office\api\objects\helpers\IdealisticOfficeAI;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use idealistic_office\api\objects\IdealisticOfficeRole;
use idealistic_office\api\objects\IdealisticOfficeUser;
use IdealisticOfficeAccessPlatform;
use MethodReply;
use PhpAsyncSerializable;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

class IdealisticOfficeStrings implements PhpAsyncSerializable
{

    public static function getPlatformFromID(int $platformID): ?string
    {
        return IdealisticOfficeAccessPlatform::getValidPlatforms()[$platformID] ?? null;
    }

    public static function translateMessage(
        string                       $message,
        ?IdealisticOfficeParticipant $referenceToTranslateTo = null,
        bool                         $save = true,
        ?IdealisticOfficePrompt      $prompt = null
    ): PromiseInterface|string
    {
        if ($referenceToTranslateTo === null) {
            if ($prompt === null) {
                return $message;
            } else {
                return resolve($message);
            }
        } else {
            $translation = new Account(Account::IDEALISTIC_OFFICE_APPLICATION_ID);
            $loop = $prompt?->getLoop() ?? null;
            $outcome = $translation->getTranslation()->translate(
                IdealisticOfficeVariable::DEFAULT_LANGUAGE,
                $prompt?->getCache()->getLanguageSelection()->getLanguage()
                ?? $referenceToTranslateTo->getLanguageSelection(false)->getLanguage(),
                $message,
                null,
                false,
                $save,
                $loop
            );

            if ($loop === null) {
                return self::store(
                    3,
                    $outcome,
                    $message,
                    $referenceToTranslateTo,
                    $translation->getTranslation(),
                    $prompt
                );
            } else {
                return $outcome->then(
                    function (MethodReply $outcome) use ($message, $referenceToTranslateTo, $translation, $prompt) {
                        return self::store(
                            4,
                            $outcome,
                            $message,
                            $referenceToTranslateTo,
                            $translation->getTranslation(),
                            $prompt
                        );
                    },
                    function (Throwable $e) use ($message, $referenceToTranslateTo) {
                        IdealisticOfficeError::storeThrowable(
                            $referenceToTranslateTo instanceof AbstractIdealisticOfficeTeam
                                ? $referenceToTranslateTo
                                : ($referenceToTranslateTo instanceof IdealisticOfficeUser
                            || $referenceToTranslateTo instanceof IdealisticOfficeRole
                                ? $referenceToTranslateTo->getTeam()
                                : null),
                            $referenceToTranslateTo instanceof IdealisticOfficeUser
                                ? $referenceToTranslateTo
                                : null,
                            $e
                        );
                        return $message;
                    }
                );
            }
        }
    }

    private static function store(
        int                          $translationID,
        MethodReply                  $outcome,
        string                       $message,
        ?IdealisticOfficeParticipant $referenceToTranslateTo,
        AccountTranslation           $translation,
        ?IdealisticOfficePrompt      $prompt
    ): string
    {
        if ($outcome->isPositiveOutcome()) {
            IdealisticOfficeAI::storeCost(
                $translation,
                $prompt ?? $referenceToTranslateTo,
                $translation->getLastCost(),
                $translation->getLastCost(),
                $translation->getLastCurrency(),
                __METHOD__ . "_" . $translationID
            );
            return $outcome->getObject();
        } else {
            IdealisticOfficeError::storeString(
                $referenceToTranslateTo instanceof AbstractIdealisticOfficeTeam
                    ? $referenceToTranslateTo
                    : ($referenceToTranslateTo instanceof IdealisticOfficeUser
                || $referenceToTranslateTo instanceof IdealisticOfficeRole
                    ? $referenceToTranslateTo->getTeam()
                    : null),
                $referenceToTranslateTo instanceof IdealisticOfficeUser
                    ? $referenceToTranslateTo
                    : null,
                $outcome->getMessage()
            );
            return $message;
        }
    }

}

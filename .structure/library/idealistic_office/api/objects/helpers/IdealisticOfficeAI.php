<?php

use React\Promise\Promise;
use function React\Promise\resolve;

class IdealisticOfficeAI implements PhpAsyncSerializable
{

    public static function storeCost(
        AccountTranslation|AccountEmbeddings|AIManager|null                           $manager,
        AbstractIdealisticOfficeTeam|IdealisticOfficeUser|IdealisticOfficePrompt|null $reference,
        ?float                                                                        $originalCost,
        ?float                                                                        $cost,
        ?int                                                                          $currency,
        string                                                                        $referenceKey
    ): void
    {
        if ($reference === null
            || $originalCost !== null && $originalCost <= 0) {
            return;
        }
        $team = $reference instanceof AbstractIdealisticOfficeTeam
            ? ($reference->hasEstablishedAccess() ? $reference : null)
            : $reference->getTeam();
        $user = $reference instanceof IdealisticOfficeUser
            ? $reference
            : ($reference instanceof IdealisticOfficePrompt
                ? $reference->getUser()
                : null);

        if ($team === null) {
            return;
        }
        $prompt = $reference instanceof IdealisticOfficePrompt
            ? $reference
            : null;
        $timezone = ($user ?? $team)->getTimezone(false);
        $costUnknown = !is_numeric($originalCost);

        if (empty($originalCost)) {
            $originalCost = IdealisticOfficeVariable::CURRENCY_USAGE_FOR_UNKNOWN_AI_COST;
        }
        if (empty($cost)) {
            $cost = IdealisticOfficeVariable::CURRENCY_USAGE_FOR_UNKNOWN_AI_COST;
        }
        if (IdealisticOfficeError::sqlInsert(
                $team,
                $user,
                IdealisticOfficeTable::AI_REQUESTS_TABLE,
                array(
                    "team_id" => $team->hasEstablishedAccess() ? $team->getId() : null,
                    "account_id" => $user?->hasEstablishedAccess() ? $user?->getId() : null,
                    "query_id" => $manager?->getLastQueryId(),
                    "reference_key" => $referenceKey,
                    "cost" => $originalCost,
                    "cost_unknown" => $costUnknown,
                    "currency" => $currency ?? IdealisticOfficeVariable::DEFAULT_CURRENCY_ID,
                    "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                    "time_zone" => $timezone->getTimeZone(),
                )
            )
            && $team->getCredit()->canChargeCredits($prompt)) {
            $team->getCredit()->subtract(
                $prompt?->getHistory()->getId(),
                max($originalCost, $cost),
                $prompt
            );
        }
    }

    public static function getEmbedding(
        AbstractIdealisticOfficeTeam|IdealisticOfficeUser|IdealisticOfficePrompt|null $reference,
        string                                                                        $string,
        bool                                                                          $save = false
    ): mixed
    {
        $embeder = new Account();
        $embeder = $embeder->getEmbeddings();
        $methodName = __METHOD__;
        $function = function ($result) use ($embeder, $reference, $methodName) {
            if ($result->isPositiveOutcome()) {
                $array = $result->getObject()[0];
                IdealisticOfficeAI::storeCost(
                    $embeder,
                    $reference,
                    $embeder->getLastCost(),
                    $embeder->getLastCost(),
                    $embeder->getLastCurrency(),
                    $methodName
                );
                return $array;
            } else {
                return null;
            }
        };
        $loop = $reference instanceof IdealisticOfficePrompt
            ? $reference->getLoop()
            : null;

        if ($loop === null) {
            $result = $embeder->objectify(
                IdealisticOfficeVariable::EMBEDDING_MODEL,
                $string,
                null,
                false,
                $save
            );
            return $function($result);
        } else {
            return $embeder->objectify(
                IdealisticOfficeVariable::EMBEDDING_MODEL,
                $string,
                null,
                false,
                $save,
                $loop
            )->then(
                function ($result) use ($function) {
                    return $function($result);
                },
                function (Throwable $e) {
                    IdealisticOfficeError::storeThrowable(
                        $this->getTeam(),
                        $this->prompt->getUser(),
                        $e
                    );
                    return null;
                }
            );
        }
    }

    // Separator

    private ?AIModel $lastModel;
    private AIManager $manager;
    private string $modelFamily;
    private IdealisticOfficeUser|AbstractIdealisticOfficeTeam $reference;
    private ?IdealisticOfficePrompt $prompt;
    private ?string $referenceKey;
    private mixed $lastReply;
    private float $minimumCost;

    public function __construct(
        IdealisticOfficeUser|AbstractIdealisticOfficeTeam|IdealisticOfficePrompt $reference,
        int|AIModel                                                              $modelFamily,
        ?string                                                                  $referenceKey = null,
        float                                                                    $minimumCost = 0.0
    )
    {
        $this->manager = new AIManager(
            $modelFamily,
            AIHelper::getAuthorization(AIAuthorization::OPENAI)
        );
        $this->modelFamily = $modelFamily instanceof AIModel
            ? $modelFamily->getFamilyID()
            : $modelFamily;
        $this->lastModel = null;
        $this->lastReply = null;
        $this->minimumCost = $minimumCost;

        if ($reference instanceof IdealisticOfficePrompt) {
            $this->reference = $reference->getUser();
            $this->prompt = $reference;
            $this->referenceKey = $reference->getTaskID() ?? $referenceKey;
        } else {
            $this->reference = $reference;
            $this->prompt = null;
            $this->referenceKey = $referenceKey;
        }
    }

    public function getReactResult(
        array             $parameters = [],
        string|array|null $input = null,
        int               $timeoutSeconds = 0
    ): ?Promise
    {
        if ($this->prompt === null) {
            return resolve(null);
        }
        return $this->getResult(
            $parameters,
            $input,
            $timeoutSeconds
        );
    }

    public function getResult(
        array             $parameters = [],
        string|array|null $input = null,
        int               $timeoutSeconds = 0
    ): mixed
    {
        $loop = $this->prompt?->getLoop();
        $result = $this->manager->getResult(
            IdealisticOfficeVariable::AI_HASH,
            $parameters,
            $input,
            $timeoutSeconds,
            $loop
        );

        if ($loop === null) {
            return $this->resolve($result);
        } else {
            return $result->then(
                function (array $result) {
                    return $this->resolve($result);
                },
                function (Throwable $e) {
                    IdealisticOfficeError::storeThrowable(
                        $this->getTeam(),
                        $this->getUser(),
                        $e
                    );
                    return null;
                }
            );
        }
    }

    private function resolve(array $result): mixed
    {
        if (array_shift($result)) {
            $model = array_shift($result);
            $reply = array_shift($result);
            $cost = $model->getCost($reply);
            $this->lastModel = $model;
            $this->lastReply = $reply;

            self::storeCost(
                $this->manager,
                $this->prompt ?? $this->reference,
                $cost,
                max($cost ?? 0, $this->minimumCost),
                $model->getCurrency()?->id,
                $this->referenceKey
            );
            return $model->getRightAiInformation($reply);
        } else {
            if ($this->prompt !== null) {
                $result["prompt"] = $this->prompt->getTaskID();
            }
            IdealisticOfficeError::storeString(
                $this->getTeam(),
                $this->getUser(),
                IdealisticOfficeReader::jsonObject($result)
            );
            return null;
        }
    }

    public function adjustCertainty(
        array           &$arguments,
        ?string         $verbosity = IdealisticOfficeVariable::TASK_AI_LOW_VERBOSITY,
        string|int|null $reasoningEffort = null
    ): void
    {
        if ($verbosity !== null
            && AIHelper::supportsVerbosity($this->modelFamily)) {
            $arguments["verbosity"] = $verbosity;
        }
        if (AIHelper::isReasoningModel($this->modelFamily)) {
            if (!is_string($reasoningEffort)) {
                $reasoningEffort = IdealisticOfficeLimit::REASONING_EFFORT_PER_TASK_AI[$this->modelFamily] ?? null;

                if ($reasoningEffort !== null) {
                    $arguments["reasoning_effort"] = $reasoningEffort;
                }
            } else {
                $arguments["reasoning_effort"] = $reasoningEffort;
            }
        } else if (is_numeric($reasoningEffort)) {
            $arguments["temperature"] = (int)$reasoningEffort;
        }
    }

    public function getTeam(): AbstractIdealisticOfficeTeam
    {
        return $this->reference instanceof IdealisticOfficeUser
            ? $this->reference->getTeam()
            : $this->reference;
    }

    public function getUser(): ?IdealisticOfficeUser
    {
        return $this->reference instanceof IdealisticOfficeUser
            ? $this->reference
            : $this->prompt?->getUser();
    }

    public function getModel(): ?AIModel
    {
        return $this->lastModel;
    }

    public function getReply(): mixed
    {
        return $this->lastReply;
    }

}
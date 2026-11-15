<?php

use React\Promise\PromiseInterface;
use function React\Promise\resolve;

class IdealisticOfficeAttachment extends AbstractIdealisticOfficeAttachment
{

    private mixed $data;
    private bool $base64;

    public function __construct(
        int|string|null                  $id,
        ?string                          $name,
        ?string                          $format,
        ?string                          $url,
        int                              $bytes,
        ?int                             $xSize,
        ?int                             $ySize,
        ?string                          $analyzedDescription,
        mixed                            $data,
        int|float|null                   $dataHash,
        bool                             $base64,
        string                           $creationDate,
        string                           $creationTimeZone,
        int|Account|IdealisticOfficeUser $createdBy,
        bool                             $json = true
    )
    {
        parent::__construct($id, $name, $format, $url, $bytes, $xSize, $ySize, $analyzedDescription, $dataHash, $creationDate, $creationTimeZone, $createdBy, $json);
        $this->data = $data;
        $this->base64 = $base64;

        if ($dataHash === null) {
            $this->dataHash = overflow_long((string_to_integer($format, true) * 31) + $bytes);
            $this->dataHash = overflow_long($this->dataHash + string_to_integer($data, true));
            $this->dataHash = overflow_long(($this->dataHash * 31) + ($xSize ?? 0));
            $this->dataHash = overflow_long(($this->dataHash * 31) + ($ySize ?? 0));
        }
        if ($bytes <= 0) {
            if ($base64) {
                $decode = @base64_decode($data);

                if (is_string($decode)) {
                    $this->bytes = mb_strlen($decode);
                } else {
                    $this->bytes = mb_strlen($data);
                }
            } else {
                $this->bytes = mb_strlen($data);
            }
        }
        self::updateJSON();
    }

    public function analyzeDescription(
        ?IdealisticOfficePrompt      $prompt,
        AbstractIdealisticOfficeTeam $team,
        int                          $lengthLimit = IdealisticOfficeLimit::K13_LENGTH
    ): PromiseInterface|string|null
    {
        if ($this->analyzedDescription === null) {
            if ($this->isUserUploaded()
                && $this->dataHash !== null) {
                $query = get_sql_query(
                    IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
                    array(
                        "analyzed_description",
                    ),
                    array(
                        array("attachment_data_hash", $this->dataHash),
                        array("analyzed_description", "IS NOT", null)
                    ),
                    null,
                    1
                );

                if (!empty($query)
                    && !empty($query[0]->analyzed_description)) {
                    $this->setAnalyzedDescription($query[0]->analyzed_description, $lengthLimit);
                    return $prompt?->getLoop() === null
                        ? $this->getAnalyzedDescription()
                        : resolve($this->getAnalyzedDescription());
                }
            }
            $process = IdealisticOfficeProcessor::analyzeAttachment($prompt, $team, $this, $lengthLimit);
            $function = function (?string $result) use ($lengthLimit) {
                if ($result === null) {
                    return null;
                }
                $this->setAnalyzedDescription($result, $lengthLimit);
                return $this->analyzedDescription;
            };

            if ($prompt?->getLoop() === null) {
                return $function($process);
            } else {
                return $process->then($function);
            }
        } else {
            return $prompt?->getLoop() === null
                ? null
                : resolve(null);
        }
    }

    public function findName(
        ?IdealisticOfficePrompt      $prompt,
        AbstractIdealisticOfficeTeam $team
    ): PromiseInterface|string|null
    {
        if ($this->isUserUploaded()
            && $this->dataHash !== null) {
            $query = get_sql_query(
                IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
                array(
                    "attachment_name",
                ),
                array(
                    array("attachment_data_hash", $this->dataHash),
                    array("attachment_name", "IS NOT", null),
                    array("id", "!=", $this->id)
                ),
                null,
                1
            );

            if (!empty($query)
                && !empty($query[0]->attachment_name)) {
                $this->name = $query[0]->attachment_name;
                return resolve($this->getName());
            }
        }
        return IdealisticOfficeProcessor::findAttachmentName($prompt, $team, $this)->then(
            function (?string $result) {
                if ($result === null) {
                    return null;
                }
                $this->name = $result;
                return $result;
            }
        );
    }

    public function isBase64(): bool
    {
        return $this->base64;
    }

    public function getRawData(): mixed
    {
        return $this->data;
    }

    public function getDecodedData(): mixed
    {
        if ($this->base64
            && $this->data !== null) {
            $decode = @base64_decode($this->data);

            if ($decode === false) {
                return null;
            }
            return $decode;
        } else {
            return $this->data;
        }
    }

    public function transferAudioData(string $toType, array $keepIf = []): ?array
    {
        if ($this->isAudio()
            && $this->data !== null) {
            $simpleType = $this->getSimpleFormat();

            if ($simpleType === $toType
                || in_array($simpleType, $keepIf)) {
                return array(
                    $simpleType,
                    $this->data
                );
            } else {
                $data = null;

                switch ($simpleType) {
                    case 'ogg':
                        switch ($toType) {
                            case 'mp3':
                                $data = convert_base64_ogg_to_base64_mp3($this->data);
                                break;
                            case 'wav':
                                $data = convert_base64_ogg_to_base64_wav($this->data);
                                break;
                            default:
                                break;
                        }
                        break;
                    default:
                        break;
                }

                if ($data === null) {
                    return null;
                } else {
                    return array($toType, $data);
                }
            }
        } else {
            return null;
        }
    }

    public function transferVideoData(string $toType, array $keepIf = []): ?array
    {
        if ($this->isVideo()
            && $this->data !== null) {
            $simpleType = $this->getSimpleFormat();

            if ($simpleType === $toType
                || in_array($simpleType, $keepIf)) {
                return array(
                    $simpleType,
                    $this->data
                );
            } else {
                $data = null;

                switch ($simpleType) {
                    case 'mp4':
                        switch ($toType) {
                            case 'mp3':
                                $data = convert_base64_mp4_to_base64_mp3($this->data);
                                break;
                            case 'wav':
                                $data = convert_base64_mp4_to_base64_wav($this->data);
                                break;
                            default:
                                break;
                        }
                        break;
                    default:
                        break;
                }

                if ($data === null) {
                    return null;
                } else {
                    return array($toType, $data);
                }
            }
        } else {
            return null;
        }
    }

}

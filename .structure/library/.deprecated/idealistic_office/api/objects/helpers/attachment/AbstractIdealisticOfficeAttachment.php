<?php

namespace idealistic_office\api\objects\helpers\attachment;

use account\api\objects\account\Account;
use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;
use idealistic_office\api\objects\helpers\IdealisticOfficeJSON;
use idealistic_office\api\objects\IdealisticOfficeUser;
use IdealisticOfficeLimit;
use IdealisticOfficeTable;

abstract class AbstractIdealisticOfficeAttachment extends IdealisticOfficeJSON
{

    private const mimeMap = [
        // --- Images ---
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'webp' => 'image/webp',
        'ico' => 'image/vnd.microsoft.icon',
        'svg' => 'image/svg+xml',
        'heic' => 'image/heic',
        'heif' => 'image/heif',

        // --- Videos ---
        'mp4' => 'video/mp4',
        'm4v' => 'video/x-m4v',
        'webm' => 'video/webm',
        'avi' => 'video/x-msvideo',
        'mov' => 'video/quicktime',
        'flv' => 'video/x-flv',
        'mkv' => 'video/x-matroska',
        'wmv' => 'video/x-ms-wmv',
        '3gp' => 'video/3gpp',
        '3g2' => 'video/3gpp2',
        'ogv' => 'video/ogg',

        // --- Audio ---
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'oga' => 'audio/ogg',
        'flac' => 'audio/flac',
        'aac' => 'audio/aac',
        'm4a' => 'audio/mp4',
        'wma' => 'audio/x-ms-wma',
        'opus' => 'audio/opus',
        'amr' => 'audio/amr',
        'aiff' => 'audio/aiff',
        'mid' => 'audio/midi',
        'midi' => 'audio/midi',

        // --- Text ---
        'txt' => 'text/plain',
        'text' => 'text/plain',
        'md' => 'text/markdown',
        'markdown' => 'text/markdown',
        'csv' => 'text/csv',
        'log' => 'text/plain',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'yaml' => 'text/yaml',
        'yml' => 'text/yaml',
        'ini' => 'text/plain',
        'rtf' => 'application/rtf',
        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'ts' => 'application/typescript',

        // --- Documents ---
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odp' => 'application/vnd.oasis.opendocument.presentation',

        // --- Archives ---
        'zip' => 'application/zip',
        'rar' => 'application/vnd.rar',
        '7z' => 'application/x-7z-compressed',
        'tar' => 'application/x-tar',
        'gz' => 'application/gzip',
        'bz2' => 'application/x-bzip2',
        'xz' => 'application/x-xz',
        'iso' => 'application/x-iso9660-image',

        // --- Fonts ---
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',

        // --- Code ---
        'php' => 'text/x-php',
        'java' => 'text/x-java-source',
        'py' => 'text/x-python',
        'rb' => 'text/x-ruby',
        'go' => 'text/x-go',
        'cpp' => 'text/x-c++src',
        'c' => 'text/x-csrc',
        'h' => 'text/x-chdr',
        'cs' => 'text/x-csharp',
        'sh' => 'application/x-sh',
        'bat' => 'application/x-msdos-program',
    ];

    public static function extractTextFromFile(string $filePath, string $extension): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }
        $extension = strtolower(trim($extension));
        $plainTextFormats = ['txt', 'csv', 'json', 'xml', 'md', 'html', 'log'];

        if (in_array($extension, $plainTextFormats, true)) {
            $content = @file_get_contents($filePath);
            return $content !== false ? strip_tags($content) : null;
        }

        if ($extension === 'pdf') {
            if (class_exists('\Smalot\PdfParser\Parser')) {
                try {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($filePath);
                    return $pdf->getText();
                } catch (Throwable $e) {
                    return null;
                }
            }
            throw new Exception("Library missing: smalot/pdfparser");
        }

        if (in_array($extension, ['doc', 'docx', 'odt', 'rtf'], true)) {
            if (class_exists('\PhpOffice\PhpWord\IOFactory')) {
                try {
                    $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
                    $text = '';
                    foreach ($phpWord->getSections() as $section) {
                        foreach ($section->getElements() as $element) {
                            if (method_exists($element, 'getText')) {
                                $text .= $element->getText() . "\n";
                            } elseif (method_exists($element, 'getElements')) {
                                foreach ($element->getElements() as $childElement) {
                                    if (method_exists($childElement, 'getText')) {
                                        $text .= $childElement->getText() . "\n";
                                    }
                                }
                            }
                        }
                    }
                    return $text;
                } catch (Throwable $e) {
                    return null;
                }
            }
            throw new Exception("Library missing: phpoffice/phpword");
        }

        if (in_array($extension, ['xls', 'xlsx', 'ods'], true)) {
            if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                try {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
                    $text = '';
                    foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                        foreach ($worksheet->getRowIterator() as $row) {
                            $cellIterator = $row->getCellIterator();
                            $cellIterator->setIterateOnlyExistingCells(false);
                            foreach ($cellIterator as $cell) {
                                $val = $cell->getValue();
                                if ($val !== null) {
                                    $text .= $val . " ";
                                }
                            }
                            $text .= "\n";
                        }
                    }
                    return $text;
                } catch (Throwable $e) {
                    return null;
                }
            }
            throw new Exception("Library missing: phpoffice/phpspreadsheet");
        }

        if (in_array($extension, ['ppt', 'pptx', 'odp'], true)) {
            if (class_exists('\PhpOffice\PhpPresentation\IOFactory')) {
                try {
                    $presentation = \PhpOffice\PhpPresentation\IOFactory::load($filePath);
                    $text = '';
                    foreach ($presentation->getAllSlides() as $slide) {
                        foreach ($slide->getShapeCollection() as $shape) {
                            if (method_exists($shape, 'getParagraphs')) {
                                foreach ($shape->getParagraphs() as $paragraph) {
                                    $text .= $paragraph->getPlainText() . "\n";
                                }
                            }
                        }
                    }
                    return $text;
                } catch (Throwable $e) {
                    return null;
                }
            }
            throw new Exception("Library missing: phpoffice/phppresentation");
        }
        return null;
    }

    public static final function getNameFromURL(string $url): ?string
    {
        $parts = parse_url($url);

        if (isset($parts['path'])) {
            $path = $parts['path'];
            $name = basename($path);
            return $name ?: null;
        }
        return null;
    }

    public static final function sendFileHeaders(?string $name, ?string $format, int $bytes, bool $inline): void
    {
        $format = strtolower(trim((string)$format));

        if (preg_match('/^[a-z0-9][a-z0-9.+\-]*\/[a-z0-9][a-z0-9.+\-]*$/', $format) !== 1) {
            $format = "application/octet-stream";
        }
        $passive = in_array($format, array("image/jpeg", "image/png", "image/gif", "image/webp", "image/bmp", "application/pdf", "text/plain"), true)
            || str_starts_with($format, "audio/")
            || str_starts_with($format, "video/");
        $name = trim((string)preg_replace('~[\x00-\x1F\x7F"\\\\/]+~u', "_", basename((string)$name)));
        $name = $name === "" ? "file" : $name;
        $ascii = preg_replace('/[^A-Za-z0-9._ -]+/', "_", $name);

        header('Content-Type: ' . $format);
        header('Content-Length: ' . $bytes);
        header('Content-Disposition: ' . ($inline && $passive ? 'inline' : 'attachment')
            . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: sandbox; default-src 'none'");
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: private, must-revalidate, max-age=0');
    }

    public static final function getFormatFromName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }
        $name = explode("?", $name, 2)[0];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!$ext) {
            return null;
        }
        return self::mimeMap[$ext] ?? null;
    }


    // Separator

    protected string|bool|null $url;
    protected ?string $name, $format, $analyzedDescription, $userPrompt;
    protected ?string $analyzedDescriptionPositions;
    protected int $bytes;
    protected ?int $id, $xSize, $ySize;
    protected int|float|null $dataHash;
    private string $creationDate, $creationTimeZone;
    private int|Account|IdealisticOfficeUser $createdBy;
    private bool $json;

    public function __construct(
        int|string|null                  $id,
        ?string                          $name,
        ?string                          $format,
        string|bool|null                 $url,
        int                              $bytes,
        ?int                             $xSize,
        ?int                             $ySize,
        ?string                          $analyzedDescription,
        int|float|null                   $dataHash,
        string                           $creationDate,
        string                           $creationTimeZone,
        int|Account|IdealisticOfficeUser $createdBy,
        bool                             $json = true
    )
    {
        parent::__construct();
        $this->id = $id;

        if (mb_strlen($name) > IdealisticOfficeLimit::H512_LENGTH) {
            $formatFromName = self::getFormatFromName($name);
            $this->name = mb_substr(
                    $name,
                    0,
                    IdealisticOfficeLimit::H512_LENGTH - ($formatFromName !== null ? mb_strlen($formatFromName) + 1 : 0)
                ) . ($formatFromName !== null ? "." . $formatFromName : "");
        } else {
            $this->name = $name;
        }
        $this->url = $url !== false
        && $url !== null
        && mb_strlen($url) <= IdealisticOfficeLimit::K3_LENGTH
            ? $url
            : null;
        $this->bytes = $bytes;
        $this->xSize = $xSize;
        $this->ySize = $ySize;
        $this->analyzedDescriptionPositions = null;
        $this->dataHash = $dataHash;
        $this->userPrompt = null;
        $this->creationDate = $creationDate;
        $this->creationTimeZone = $creationTimeZone;
        $this->createdBy = $createdBy;
        $this->json = $json;

        if ($format === null
            && $name !== null) {
            $format = self::getFormatFromName($name);

            if ($format !== null) {
                $this->format = $format;
            } else {
                $this->format = null;
            }
        } else {
            $this->format = $format;
        }
        $this->setAnalyzedDescription($analyzedDescription);
    }

    public final function enableJSON(): void
    {
        $this->json = true;
        self::updateJSON();
    }

    protected final function updateJSON(): void
    {
        if ($this->json) {
            self::setJSON(
                IdealisticOfficeReader::getAttachment(
                    false,
                    $this,
                    true
                )
            );
        }
    }

    public final function getId(): int|string|null
    {
        return $this->id;
    }

    public final function setId(?int $id): void
    {
        $this->id = $id;
        self::updateJSON();
    }

    public final function getName(): string
    {
        $name = $this->name;

        if ($name === null) {
            if ($this->dataHash === null) {
                $name = "attachment_id_" . ($this->id ?? 0);
            } else {
                $name = "attachment_hash_" . abs(round($this->dataHash));
            }
            return $name . ($this->format !== null ? "." . $this->getSimpleFormat() : "");
        } else {
            $formatFromName = self::getFormatFromName($name);

            if ($formatFromName !== null) {
                return $name;
            }
            $simpleFormat = $this->getSimpleFormat();
            return $name . ($simpleFormat !== null ? "." . $simpleFormat : "");
        }
    }

    public final function getNoFormatName(): ?string
    {
        $name = $this->getName();
        $formatFromName = self::getFormatFromName($name);

        if ($formatFromName !== null) {
            $formatFromName = array_search($formatFromName, self::mimeMap, true);

            if ($formatFromName !== false) {
                return substr(
                    $name,
                    0,
                    strlen($name) - strlen($formatFromName) - 1
                );
            }
        }
        return $name;
    }

    public final function getFormat(bool $backup = false): ?string
    {
        if ($this->format === null) {
            $formatFromName = self::getFormatFromName($this->name);

            if ($formatFromName !== null) {
                $this->format = $formatFromName;
            } else {
                return $backup ? "application/octet-stream" : null;
            }
        }
        return $this->format;
    }

    public final function getFormatFamily(bool $backup = false): ?string
    {
        $format = $this->getFormat($backup);

        if ($format === null) {
            return null;
        }
        $explode = explode("/", $format, 2);
        return sizeof($explode) > 1
            ? strtolower($explode[0])
            : null;
    }

    public final function getSimpleFormat(): ?string
    {
        $name = explode("?", $this->getName(), 2)[0];
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    public final function getUrl(bool $expired = false, bool $create = false, bool $download = true): ?string
    {
        if ($this->url === false) {
            return null;
        }
        if ($this->url === null
            || !$create && str_starts_with($this->url, IdealisticOfficeVariable::ATTACHMENT_INPUT_OUTPUT_URL)) {
            $url = $this->url;
        } else {
            $url = empty($this->id)
                ? $this->url
                : null;
        }

        if ($create
            && $url === null
            && $this->id !== null) {
            $query = $expired
                ? null
                : get_sql_query(
                    IdealisticOfficeTable::HISTORY_ATTACHMENT_ACCESS_TABLE,
                    array(
                        "random_id",
                        "expiration_date",
                        "expiration_time_zone"
                    ),
                    array(
                        array("attachment_id", $this->id),
                        array("user_uploaded", $this->isUserUploaded())
                    ),
                    null,
                    1
                );

            if (empty($query)) {
                $randomID = random_string(IdealisticOfficeLimit::URL_RANDOM_ID_LENGTH);

                if (IdealisticOfficeError::sqlInsert(
                    null,
                    null,
                    IdealisticOfficeTable::HISTORY_ATTACHMENT_ACCESS_TABLE,
                    array(
                        "random_id" => $randomID,
                        "attachment_id" => $this->id,
                        "user_uploaded" => $this->isUserUploaded(),
                        "creation_date" => IdealisticOfficeReader::getCurrentDate(),
                        "creation_time_zone" => IdealisticOfficeVariable::DEFAULT_TIME_ZONE,
                        "expiration_date" => IdealisticOfficeReader::getFutureDate(IdealisticOfficeLimit::DEFAULT_URL_EXPIRATION_TIME),
                        "expiration_time_zone" => IdealisticOfficeVariable::DEFAULT_TIME_ZONE
                    )
                )) {
                    $this->url = ($download
                            ? IdealisticOfficeVariable::ATTACHMENT_DOWNLOAD_INPUT_OUTPUT_URL
                            : IdealisticOfficeVariable::ATTACHMENT_VIEW_INPUT_OUTPUT_URL)
                        . "?id=" . $randomID;
                    $url = $this->url;
                }
            } else {
                $row = $query[0];

                if ($row->expiration_date !== null
                    && IdealisticOfficeReader::getCurrentDate($row->expiration_time_zone) > $row->expiration_date) {
                    return $this->getUrl(true, true, $download);
                } else {
                    $this->url = ($download
                            ? IdealisticOfficeVariable::ATTACHMENT_DOWNLOAD_INPUT_OUTPUT_URL
                            : IdealisticOfficeVariable::ATTACHMENT_VIEW_INPUT_OUTPUT_URL)
                        . "?id=" . $row->random_id;
                    $url = $this->url;
                }
            }
        }
        return $url;
    }

    public final function disableURL(): void
    {
        $this->url = false;
        self::updateJSON();
    }

    public final function enableURL(): void
    {
        $this->url = null;
        self::updateJSON();
    }

    public final function getBytes(): int
    {
        return $this->bytes;
    }

    public final function getFullBytes(): int
    {
        return $this->bytes + mb_strlen($this->getName());
    }

    public final function getDataHash(): int|float|null
    {
        return $this->dataHash;
    }

    // Date

    public final function getCreationDate(): string
    {
        return $this->creationDate;
    }

    public final function getCreationTimeZone(): string
    {
        return $this->creationTimeZone;
    }

    public final function getCreator(): ?IdealisticOfficeUser
    {
        if ($this->createdBy instanceof IdealisticOfficeUser) {
            return $this->createdBy;
        }
        $user = AbstractIdealisticOfficeTeam::findUserDynamic($this->getCreatorAccount(), $this->json);

        if ($user instanceof IdealisticOfficeUser) {
            $this->createdBy = $user;
            return $user;
        } else {
            return null;
        }
    }

    public final function getCreatorAccount(): Account
    {
        if (is_int($this->createdBy)) {
            $this->createdBy = new Account(
                Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                $this->createdBy
            );
        } else if ($this->createdBy instanceof IdealisticOfficeUser) {
            return $this->createdBy->getAccount();
        }
        return $this->createdBy;
    }

    public final function getCreatorID(): int
    {
        if (is_int($this->createdBy)) {
            return $this->createdBy;
        } else if ($this->createdBy instanceof IdealisticOfficeUser) {
            return $this->createdBy->getId();
        } else {
            return $this->createdBy->getDetail("id", 0);
        }
    }

    // Image

    public final function getXSize(): ?int
    {
        return $this->xSize;
    }

    public final function getYSize(): ?int
    {
        return $this->ySize;
    }

    public final function getImageRatio(int $precision = 0): ?float
    {
        if ($this->xSize !== null
            && $this->ySize !== null) {
            $min = min($this->xSize, $this->ySize);
            $max = max($this->xSize, $this->ySize);
            return $precision === 0
                ? $min / $max
                : cut_decimal($min / $max, $precision);
        }
        return null;
    }

    // Member Prompt

    public final function setUserPrompt(?string $userPrompt): void
    {
        if ($userPrompt !== null) {
            $this->userPrompt = mb_substr(
                $userPrompt,
                0,
                IdealisticOfficeLimit::K1_LENGTH
            );
        } else {
            $this->userPrompt = null;
        }
        self::updateJSON();
    }

    public final function getUserPrompt(): ?string
    {
        return $this->userPrompt;
    }

    public final function isUserUploaded(): bool
    {
        return $this->userPrompt === null;
    }

    // Analysis

    public final function getAnalyzedDescription(): ?string
    {
        return $this->analyzedDescription;
    }

    public function getAnalyzedDescriptionPositions(): ?string
    {
        return $this->analyzedDescriptionPositions;
    }

    public final function setAnalyzedDescription(
        ?string $analyzedDescription,
        int     $lengthLimit = IdealisticOfficeLimit::K13_LENGTH
    ): void
    {
        if ($analyzedDescription !== null) {
            if (mb_strlen($analyzedDescription) > $lengthLimit) {
                $this->analyzedDescription = mb_substr(
                        $analyzedDescription,
                        0,
                        $lengthLimit - 3
                    ) . "...";
            } else {
                $this->analyzedDescription = $analyzedDescription;
            }
        } else {
            $this->analyzedDescription = null;
        }
        self::updateJSON();
    }

    public final function setAnalyzedDescriptionPositions(?string $analyzedDescriptionPositions): void
    {
        $this->analyzedDescriptionPositions = $analyzedDescriptionPositions;
    }

    // Validate

    public final function isImage(): bool
    {
        if ($this->xSize !== null
            && $this->ySize !== null) {
            return true;
        }
        $family = $this->getFormatFamily();

        if ($family === "image") {
            return true;
        } else {
            $simple = $this->getSimpleFormat();

            switch ($simple) {
                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'gif':
                case 'bmp':
                case 'webp':
                case 'tiff':
                case 'svg':
                    return true;
                default:
                    break;
            }
            return false;
        }
    }

    public final function isAudio(): bool
    {
        $family = $this->getFormatFamily();

        if ($family === "audio") {
            return true;
        } else {
            $simple = $this->getSimpleFormat();

            switch ($simple) {
                case 'mp3':
                case 'wav':
                case 'ogg':
                case 'flac':
                case 'aac':
                case 'wma':
                case 'm4a':
                case 'opus':
                    return true;
                default:
                    break;
            }
            return false;
        }
    }

    public final function isVideo(): bool
    {
        $family = $this->getFormatFamily();

        if ($family === "video") {
            return true;
        } else {
            $simple = $this->getSimpleFormat();

            switch ($simple) {
                case 'mp4':
                case 'webm':
                case 'avi':
                case 'mkv':
                case 'flv':
                case 'mov':
                case 'wmv':
                case 'm4v':
                case '3gp':
                case 'gif':
                    return true;
                default:
                    break;
            }
            return false;
        }
    }

    public final function canBeAudio(): bool
    {
        return $this->isAudio()
            || $this->isVideo();
    }

    public final function isValid(): bool
    {
        return !empty($this->format)
            && mb_strlen($this->format) <= IdealisticOfficeLimit::T64_LENGTH
            && $this->bytes <= IdealisticOfficeLimit::MAX_ATTACHMENT_BYTES_SIZE;
    }

}

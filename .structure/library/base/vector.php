<?php

class VectorMath implements PhpAsyncSerializable
{
    private static ?\FFI $ffi = null;
    private static bool $ffiAttempted = false;
    private static ?string $ffiError = null;

    public function __construct()
    {
        if (self::$ffiAttempted) {
            return;
        }
        self::$ffiAttempted = true;

        if (!class_exists(\FFI::class)) {
            self::$ffiError = "FFI extension is not loaded.";
            error_log("VectorMath: " . self::$ffiError . " — falling back to pure PHP.");
            return;
        }
        $ffiEnable = strtolower((string)ini_get("ffi.enable"));

        if ($ffiEnable === "preload" && PHP_SAPI !== "cli") {
            // Default PHP setting: FFI::cdef() is only allowed in CLI or preloaded scripts
            self::$ffiError = "FFI is restricted by ffi.enable=preload (set ffi.enable=true for " . PHP_SAPI . ").";
            error_log("VectorMath: " . self::$ffiError . " — falling back to pure PHP.");
            return;
        }
        $headers = "
            float cblas_sdot(const int N, const void *X, const int incX, const void *Y, const int incY);
            float cblas_snrm2(const int N, const void *X, const int incX);
        ";
        $candidates = [
            'libopenblas.so.0',
            'libopenblas.so.3',
            'libopenblas.so',
            '/usr/lib/aarch64-linux-gnu/libopenblas.so.0', // ARM fallback
            '/usr/lib/x86_64-linux-gnu/libopenblas.so.0',  // x86 fallback
            '/usr/lib/x86_64-linux-gnu/libopenblas.so.3',
        ];
        $errors = [];

        foreach ($candidates as $lib) {
            try {
                self::$ffi = \FFI::cdef($headers, $lib);
                return;
            } catch (\Throwable $e) {
                $errors[] = $lib . ": " . $e->getMessage();
            }
        }
        self::$ffiError = "Failed to load OpenBLAS (" . php_uname("m") . "): " . implode(" | ", $errors);
        error_log("VectorMath: " . self::$ffiError . " — falling back to pure PHP.");
    }

    public function isAccelerated(): bool
    {
        return self::$ffi !== null;
    }

    public function getLoadError(): ?string
    {
        return self::$ffiError;
    }

    public function dotProductBinary(string $vecA, string $vecB, int $len): float
    {
        if (self::$ffi !== null) {
            // incX/incY = 1 means we process every element consecutively
            return self::$ffi->cblas_sdot($len, $vecA, 1, $vecB, 1);
        }
        $a = unpack("f" . $len, $vecA);
        $b = unpack("f" . $len, $vecB);
        $dot = 0.0;

        for ($i = 1; $i <= $len; $i++) {
            $dot += $a[$i] * $b[$i];
        }
        return $dot;
    }

    private function norm(string $vec, int $len): float
    {
        if (self::$ffi !== null) {
            // snrm2 returns sqrt(sum(x_i^2))
            return self::$ffi->cblas_snrm2($len, $vec, 1);
        }
        $sum = 0.0;

        foreach (unpack("f" . $len, $vec) as $value) {
            $sum += $value * $value;
        }
        return sqrt($sum);
    }

    public function fullCosineSimilarityBinary(string $vecA, string $vecB, int $len): float
    {
        $dot = $this->dotProductBinary($vecA, $vecB, $len);
        $denom = $this->norm($vecA, $len) * $this->norm($vecB, $len);
        return $denom == 0.0 ? 0.0 : $dot / $denom;
    }

    public function cosineSimilarityMixed(array $vecA, string $vecB): float
    {
        $binA = pack("f*", ...$vecA);
        return $this->dotProductBinary($binA, $vecB, count($vecA));
    }

    public function fullCosineSimilarityMixed(array $vecA, string $vecB): float
    {
        $binA = pack("f*", ...$vecA);
        return $this->fullCosineSimilarityBinary($binA, $vecB, count($vecA));
    }

    public function normalizeBinaryVector(string $binaryString): string
    {
        $floats = unpack("f*", $binaryString);

        if (empty($floats)) {
            return $binaryString;
        }
        $sumOfSquares = 0.0;

        foreach ($floats as $value) {
            $sumOfSquares += ($value * $value);
        }
        $magnitude = sqrt($sumOfSquares);

        if ($magnitude === 0.0) {
            return $binaryString;
        }
        foreach ($floats as &$value) {
            $value = $value / $magnitude;
        }
        unset($value);
        return pack("f*", ...array_values($floats));
    }

}
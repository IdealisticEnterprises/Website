<?php

abstract class IdealisticOfficeJSON implements JsonSerializable, PhpAsyncSerializable
{

    private array|object $json;

    public function __construct()
    {
        $this->json = array();
    }

    public final function setJSON(array|object $json): void
    {
        $this->json = $json;
    }

    public final function addToJSON(int|string|float|bool|null $key, mixed $value): void
    {
        if (is_object($this->json)) {
            if (!property_exists($this->json, $key)) {
                $this->json->{$key} = $value;
            }
        } else if (!array_key_exists($key, $this->json)) {
            $this->json[$key] = $value;
        }
    }

    public final function addMultipleToJSON(array $array): void
    {
        foreach ($array as $key => $value) {
            $this->addToJSON($key, $value);
        }
    }

    public final function setToJSON(int|string|float|bool|null $key, mixed $value): void
    {
        if (is_object($this->json)) {
            $this->json->{$key} = $value;
        } else {
            $this->json[$key] = $value;
        }
    }

    public final function removeFromJSON(int|string|float|bool|null $key): void
    {
        if (is_object($this->json)) {
            unset($this->json->{$key});
        } else {
            unset($this->json[$key]);
        }
    }

    public final function jsonSerialize(): array|object
    {
        return $this->json;
    }

}

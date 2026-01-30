<?php

namespace MediaWiki\Extension\JsonForms;

/**
 * Generic parameter processor
 */
class ProcessParameters
{
    protected array $defaultParameters = [];

    protected array $flatDefaults = [];
    protected array $values = [];
    protected array $options = [];
    protected array $query = [];

    public function __construct(array $argv = [])
    {
        $this->prepareDefaults();
        $this->parse($argv);
        $this->applyDefaults();
    }

    protected function prepareDefaults(): void
    {
        $this->flatDefaults = [];
        foreach ($this->defaultParameters as $key => $def) {
            $this->flatDefaults[$key] = [
                $def["default"] ?? null,
                $def["type"] ?? null,
            ];
        }
    }

    protected function parse(array $argv): void
    {
        $unnamed = [];
        $known = [];
        $unknown = [];
        $prevKey = null;

        foreach ($argv as $key => $value) {
            if (strpos($value, "+") === 0) {
                $argv[$prevKey] .= " |+" . urlencode(substr($value, 1));
                unset($argv[$key]);
            } else {
                $prevKey = $key;
            }
        }

        foreach ($argv as $value) {
            if (strpos($value, "=") !== false) {
                [$k, $v] = explode("=", $value, 2);
                $k = trim($k);
                $k_ = str_replace(" ", "-", $k);
                $v = trim($v);

                if (
                    array_key_exists($k, $this->flatDefaults) ||
                    array_key_exists($k_, $this->flatDefaults)
                ) {
                    $known[$k_] = $v;
                    $prevKey = $k_;
                } else {
                    $unknown[$k] = $v;
                    $prevKey = $k;
                }
            } else {
                $unnamed[] = $value;
            }
        }

        $this->values = $unnamed;
        $this->options = $known;
        $this->query = $unknown;
    }

    protected function applyDefaults(): void
    {
        foreach ($this->flatDefaults as $key => [$defaultValue, $type]) {
            $val = $this->options[$key] ?? $defaultValue;
            $this->options[$key] = $this->castValueByType(
                $type,
                $val,
                $defaultValue
            );
        }
    }

    protected function castValueByType(?string $type, $value, $default = null)
    {
        if ($value === null) {
            return $default;
        }

        switch ($type) {
            case "int":
            case "integer":
                return (int) $value;

            case "float":
            case "number":
                return (float) $value;

            case "bool":
            case "boolean":
                return (bool) $value;

            case "string":
                return (string) $value;

            case "array":
                return is_array($value) ? $value : $this->splitString($value);

            case "array-chunks":
                return is_array($value) ? $value : str_split((string) $value);

            case "array-string":
            case "array-int":
            case "array-integer":
            case "array-float":
            case "array-number":
            case "array-bool":
            case "array-boolean":
                // Convert to array if needed
                $values = is_array($value)
                    ? $value
                    : $this->splitString((string) $value);

                $subType = explode("-", $type)[1] ?? null;
                $result = [];
                foreach ($values as $v) {
                    $result[] = $this->castValueByType($subType, $v, $default);
                }
                return $result;

            default:
                return $value;
        }
    }

    protected function splitString(string $str): array
    {
        return array_map("trim", explode(",", $str));
    }

    public function getValues(): array
    {
        return $this->values;
    }
    public function getOptions(): array
    {
        return $this->options;
    }
    public function getQuery(): array
    {
        return $this->query;
    }
}


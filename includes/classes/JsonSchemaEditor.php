<?php

namespace MediaWiki\Extension\JsonForms;

if (is_readable(__DIR__ . "/../../vendor/autoload.php")) {
    include_once __DIR__ . "/../../vendor/autoload.php";
}

use Opis\JsonSchema\Validator;

class JsonSchemaEditor
{
    protected Validator $validator;
    protected array $visited = [];

    public function __construct(?Validator $validator = null)
    {
        $this->validator = $validator ?? new Validator();
    }

    /**
     * Traverse the schema (array or object) and apply $fn to each node.
     * $ref is resolved but NOT replaced in the original schema.
     */
    public function traverse(array &$schema, callable $fn): void
    {
        $this->walk($schema, $fn);
    }

    protected function walk(array &$node, callable $fn): void
    {
        // 🔹 Handle $ref if present (only objects have $ref)
        if (isset($node['$ref'])) {
            try {
                $resolved = $this->validator->loader()->resolve($node['$ref']);
                if ($resolved && is_object($resolved)) {
                    $id = spl_object_id($resolved);
                    if (!isset($this->visited[$id])) {
                        $this->visited[$id] = true;
                        $this->walk((array) $resolved, $fn);
                    }
                }
            } catch (\Throwable $e) {
                // ignore unresolved refs
            }
        }

        // Apply mutation to current node
        $fn($node);

        $subschemaKeys = [
            "properties",
            "definitions",
            '$defs',
            "patternProperties",
            "items",
            "additionalProperties",
            "not",
            "allOf",
            "anyOf",
            "oneOf",
        ];

        foreach ($subschemaKeys as $key) {
            if (!isset($node[$key])) {
                continue;
            }

            $child = &$node[$key];

            if (is_array($child)) {
                // Array of subschemas (items, allOf, oneOf, etc.)
                foreach ($child as &$sub) {
                    if (is_array($sub)) {
                        $this->walk($sub, $fn);
                    } elseif (is_object($sub)) {
                        $this->walk((array) $sub, $fn);
                    }
                }
            } elseif (is_object($child)) {
                // Map of properties (properties, definitions, $defs, etc.)
                foreach ($child as $prop => &$sub) {
                    if (is_array($sub)) {
                        $this->walk($sub, $fn);
                    } elseif (is_object($sub)) {
                        $this->walk((array) $sub, $fn);
                    }
                }
            }
        }
    }
}


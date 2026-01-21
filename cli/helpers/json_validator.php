<?php
/**
 * JSON Schema Validator
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\helpers;

defined('MOODLE_INTERNAL') || die();

/**
 * JSON Validator class
 * 
 * Validates JSON payloads against schemas per PRD Section 13
 */
class json_validator {
    
    /** @var array Cached schemas */
    protected static $schemas = [];
    
    /**
     * Validate JSON against schema
     * 
     * @param mixed $data Data to validate
     * @param array $schema JSON schema
     * @return array Validation result [valid => bool, errors => array]
     */
    public static function validate($data, array $schema): array {
        $errors = [];
        
        // Validate against schema
        self::validate_node($data, $schema, '', $errors);
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Recursive validation of JSON nodes
     * 
     * @param mixed $data Data node
     * @param array $schema Schema node
     * @param string $path Current path
     * @param array &$errors Error array
     */
    protected static function validate_node($data, array $schema, string $path, array &$errors) {
        // Check type
        if (isset($schema['type'])) {
            if (!self::validate_type($data, $schema['type'])) {
                $errors[] = "Type mismatch at $path: expected {$schema['type']}, got " . gettype($data);
                return;
            }
        }
        
        // Handle object properties
        if (isset($schema['type']) && $schema['type'] === 'object') {
            if (!is_array($data) && !is_object($data)) {
                $errors[] = "Expected object at $path";
                return;
            }
            
            $data = (array)$data;
            
            // Check required properties
            if (isset($schema['required'])) {
                foreach ($schema['required'] as $required) {
                    if (!array_key_exists($required, $data)) {
                        $errors[] = "Missing required property: $path.$required";
                    }
                }
            }
            
            // Validate properties
            if (isset($schema['properties'])) {
                foreach ($schema['properties'] as $prop => $propschema) {
                    if (array_key_exists($prop, $data)) {
                        self::validate_node($data[$prop], $propschema, "$path.$prop", $errors);
                    }
                }
            }
            
            // Check for additional properties
            if (isset($schema['additionalProperties']) && $schema['additionalProperties'] === false) {
                $defined = array_keys($schema['properties'] ?? []);
                $actual = array_keys($data);
                $additional = array_diff($actual, $defined);
                if (!empty($additional)) {
                    $errors[] = "Additional properties not allowed at $path: " . implode(', ', $additional);
                }
            }
        }
        
        // Handle arrays
        if (isset($schema['type']) && $schema['type'] === 'array') {
            if (!is_array($data)) {
                $errors[] = "Expected array at $path";
                return;
            }
            
            // Validate array items
            if (isset($schema['items'])) {
                foreach ($data as $index => $item) {
                    self::validate_node($item, $schema['items'], "$path[$index]", $errors);
                }
            }
            
            // Check min/max items
            if (isset($schema['minItems']) && count($data) < $schema['minItems']) {
                $errors[] = "Array at $path has too few items (minimum {$schema['minItems']})";
            }
            if (isset($schema['maxItems']) && count($data) > $schema['maxItems']) {
                $errors[] = "Array at $path has too many items (maximum {$schema['maxItems']})";
            }
        }
        
        // String validation
        if (isset($schema['type']) && $schema['type'] === 'string') {
            if (isset($schema['minLength']) && strlen($data) < $schema['minLength']) {
                $errors[] = "String at $path is too short (minimum {$schema['minLength']} characters)";
            }
            if (isset($schema['maxLength']) && strlen($data) > $schema['maxLength']) {
                $errors[] = "String at $path is too long (maximum {$schema['maxLength']} characters)";
            }
            if (isset($schema['pattern'])) {
                if (!preg_match('/' . $schema['pattern'] . '/', $data)) {
                    $errors[] = "String at $path does not match pattern: {$schema['pattern']}";
                }
            }
            if (isset($schema['enum'])) {
                if (!in_array($data, $schema['enum'])) {
                    $errors[] = "Value at $path must be one of: " . implode(', ', $schema['enum']);
                }
            }
        }
        
        // Number validation
        if (isset($schema['type']) && in_array($schema['type'], ['number', 'integer'])) {
            if (isset($schema['minimum']) && $data < $schema['minimum']) {
                $errors[] = "Number at $path is below minimum ({$schema['minimum']})";
            }
            if (isset($schema['maximum']) && $data > $schema['maximum']) {
                $errors[] = "Number at $path exceeds maximum ({$schema['maximum']})";
            }
        }
    }
    
    /**
     * Validate data type
     * 
     * @param mixed $data Data to check
     * @param string $type Expected type
     * @return bool Valid type
     */
    protected static function validate_type($data, string $type): bool {
        switch ($type) {
            case 'string':
                return is_string($data);
            case 'number':
                return is_numeric($data);
            case 'integer':
                return is_int($data);
            case 'boolean':
                return is_bool($data);
            case 'array':
                return is_array($data);
            case 'object':
                return is_array($data) || is_object($data);
            case 'null':
                return is_null($data);
            default:
                return false;
        }
    }
    
    /**
     * Repair JSON to match schema
     * 
     * @param mixed $data Data to repair
     * @param array $schema Schema to match
     * @return mixed Repaired data
     */
    public static function repair($data, array $schema) {
        // Convert to array if needed
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            } else {
                // Try to fix common JSON errors
                $data = self::fix_json_string($data);
                $decoded = json_decode($data, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $decoded;
                }
            }
        }
        
        return self::repair_node($data, $schema);
    }
    
    /**
     * Repair a node to match schema
     * 
     * @param mixed $data Data node
     * @param array $schema Schema node
     * @return mixed Repaired data
     */
    protected static function repair_node($data, array $schema) {
        // Handle type mismatches
        if (isset($schema['type'])) {
            $data = self::coerce_type($data, $schema['type']);
        }
        
        // Handle objects
        if (isset($schema['type']) && $schema['type'] === 'object') {
            if (!is_array($data) && !is_object($data)) {
                $data = [];
            }
            
            $data = (array)$data;
            $repaired = [];
            
            // Add required properties with defaults
            if (isset($schema['required'])) {
                foreach ($schema['required'] as $required) {
                    if (!array_key_exists($required, $data)) {
                        if (isset($schema['properties'][$required])) {
                            $repaired[$required] = self::get_default_value($schema['properties'][$required]);
                        }
                    }
                }
            }
            
            // Process existing properties
            if (isset($schema['properties'])) {
                foreach ($schema['properties'] as $prop => $propschema) {
                    if (array_key_exists($prop, $data)) {
                        $repaired[$prop] = self::repair_node($data[$prop], $propschema);
                    } elseif (!isset($repaired[$prop]) && isset($propschema['default'])) {
                        $repaired[$prop] = $propschema['default'];
                    }
                }
            }
            
            return $repaired;
        }
        
        // Handle arrays
        if (isset($schema['type']) && $schema['type'] === 'array') {
            if (!is_array($data)) {
                return [];
            }
            
            $repaired = [];
            if (isset($schema['items'])) {
                foreach ($data as $item) {
                    $repaired[] = self::repair_node($item, $schema['items']);
                }
            } else {
                $repaired = $data;
            }
            
            return $repaired;
        }
        
        return $data;
    }
    
    /**
     * Coerce data to specified type
     * 
     * @param mixed $data Data to coerce
     * @param string $type Target type
     * @return mixed Coerced data
     */
    protected static function coerce_type($data, string $type) {
        switch ($type) {
            case 'string':
                return strval($data);
            case 'number':
                return is_numeric($data) ? floatval($data) : 0;
            case 'integer':
                return intval($data);
            case 'boolean':
                return boolval($data);
            case 'array':
                return is_array($data) ? $data : [$data];
            case 'object':
                return is_array($data) || is_object($data) ? (array)$data : [];
            case 'null':
                return null;
            default:
                return $data;
        }
    }
    
    /**
     * Get default value for schema type
     * 
     * @param array $schema Schema definition
     * @return mixed Default value
     */
    protected static function get_default_value(array $schema) {
        if (isset($schema['default'])) {
            return $schema['default'];
        }
        
        if (!isset($schema['type'])) {
            return null;
        }
        
        switch ($schema['type']) {
            case 'string':
                return '';
            case 'number':
                return 0;
            case 'integer':
                return 0;
            case 'boolean':
                return false;
            case 'array':
                return [];
            case 'object':
                return [];
            case 'null':
                return null;
            default:
                return null;
        }
    }
    
    /**
     * Fix common JSON string errors
     * 
     * @param string $json JSON string to fix
     * @return string Fixed JSON
     */
    protected static function fix_json_string(string $json): string {
        // Remove BOM if present
        $json = preg_replace('/^\xEF\xBB\xBF/', '', $json);
        
        // Fix unescaped quotes
        $json = preg_replace('/([^\\\\])"([^"]*)"([^:,\]\}])/', '$1\"$2\"$3', $json);
        
        // Fix trailing commas
        $json = preg_replace('/,\s*([\]\}])/', '$1', $json);
        
        // Add missing quotes to keys
        $json = preg_replace('/(\{|,)\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*:/', '$1"$2":', $json);
        
        return $json;
    }
}
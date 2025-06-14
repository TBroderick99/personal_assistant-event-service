<?php

namespace App\Transformers;

use Illuminate\Validation\Validator as IlluminateValidator;
use Illuminate\Support\Str;

class ValidationErrorFormatter
{
    protected static array $ruleMap = [];
    protected static bool $isInitialized = false;

    /**
     * Initializes the formatter with a default set of rule mappings.
     * Should be called once, e.g., in a Service Provider's boot method.
     */
    public static function initialize(): void
    {
        if (!self::$isInitialized) {
            self::$ruleMap = self::getDefaultRuleMap();
            self::$isInitialized = true;
        }
    }

    /**
     * Allows overriding or extending the existing rule map.
     * Merges provided map with the current map (new entries override existing ones).
     *
     * @param array $customMap The custom rule map to merge. Example: `['MyRule' => ['code' => 'MY_CODE'], 'Min' => ['code' => 'OVERRIDDEN_MIN_CODE']]`
     */
    public static function setRuleMap(array $customMap): void
    {
        self::initialize(); // Ensure defaults are loaded if not already
        self::$ruleMap = array_merge(self::$ruleMap, $customMap);
    }

    /**
     * Formats the validation errors from a Validator instance.
     *
     * @param \Illuminate\Validation\Validator $validator The Laravel Validator instance after validation has been performed. Example: `$validator = Validator::make(...); $validator->fails(); // Pass this $validator instance`
     * @return array The formatted error messages. Example: `['email' => [['code' => 'REQUIRED', 'params' => {}]]]`
     */
    public static function format(IlluminateValidator $validator): array
    {
        self::initialize(); // Ensure map is loaded

        $formattedErrors = [];
        $failedRules = $validator->failed();

        foreach ($failedRules as $field => $rules) {
            $fieldErrors = [];
            foreach ($rules as $ruleName => $ruleParams) {
                $errorData = self::processRule($ruleName, $ruleParams, $field);
                $fieldErrors[] = $errorData;
            }
            if (!empty($fieldErrors)) {
                $formattedErrors[$field] = $fieldErrors;
            }
        }
        return $formattedErrors;
    }

    /**
     * Processes a single failed rule.
     *
     * @param string $ruleName Name of the failed rule (e.g., "Required", "Min"). Example: `'Min'`, `'Unique'`
     * @param array  $ruleParams Parameters of the failed rule. Example: `[8]` (for Min rule), `['users', 'email_column']` (for Unique rule)
     * @param string $field The field name. Example: `'password'`, `'user.email'`
     * @return array Structured error data with 'code' and 'params'. Example: `['code' => 'MIN_LENGTH', 'params' => (object)['minLength' => 8]]`
     */
    public static function processRule(string $ruleName, array $ruleParams, string $field): array
    {
        $code = '';
        $params = [];

        if (isset(self::$ruleMap[$ruleName])) {
            $mapEntry = self::$ruleMap[$ruleName];
            $code = $mapEntry['code'];

            if (isset($mapEntry['params_mapper']) && is_callable($mapEntry['params_mapper'])) {
                $params = call_user_func($mapEntry['params_mapper'], $ruleParams, $field);
            } elseif (isset($mapEntry['params_config'])) {
                foreach ($mapEntry['params_config'] as $outputKey => $inputIndex) {
                    // Allow literal values: ['expectedType' => ['value' => true]]
                    if (is_array($inputIndex) && array_key_exists('value', $inputIndex)) {
                        $params[$outputKey] = $inputIndex['value'];
                    } elseif (is_string($inputIndex) && !is_numeric($inputIndex)) {
                        $params[$outputKey] = $inputIndex;
                    } elseif (isset($ruleParams[$inputIndex])) {
                        $params[$outputKey] = $ruleParams[$inputIndex];
                    }
                }
            }
        } else {
            // Fallback for unmapped rules
            $code = strtoupper(Str::snake($ruleName)); // Converts CamelCase to UPPER_SNAKE_CASE
            $fallbackParams = [];
            if (!empty($ruleParams)) {
                foreach($ruleParams as $index => $paramValue) {
                    // Only include non-null parameters for the fallback
                    if ($paramValue !== null) {
                         $fallbackParams['param' . ($index + 1)] = $paramValue;
                    }
                }
            }
            $params = $fallbackParams;
        }

        return [
            'code'   => $code,
            'params' => (object) $params, // Ensures params is {} if empty
        ];
    }

    /**
     * Not gonna lie, i used ai to generate this massive map, not even sure if all of it is configured correctly.
     * Defines the default map for common validation rules.
     * RuleName is the CamelCase name from $validator->failed().
     * 'code' is your desired error code.
     * 'params_config' maps output param names to their index in $ruleParams.
     * 'params_mapper' is a callback for complex parameter transformation.
     *
     * @return array The default rule map.
     */
    public static function getDefaultRuleMap(): array
    {
        return [
            // --- General & Common Rules ---
            'Required'        => ['code' => 'REQUIRED'],
            'Present'         => ['code' => 'FIELD_NOT_PRESENT'],
            'Filled'          => ['code' => 'FIELD_NOT_FILLED_WHEN_PRESENT'],
            'Nullable'        => ['code' => 'NULLABLE_CONSTRAINT_FAILED'], // Typically affects other rules, doesn't "fail" on its own

            // --- Type Checks ---
            'String'          => ['code' => 'INVALID_TYPE', 'params_config' => ['expectedType' => 'string']], // 'string' is a fixed value, not from $ruleParams
            'Numeric'         => ['code' => 'INVALID_TYPE', 'params_config' => ['expectedType' => 'numeric']],
            'Integer'         => ['code' => 'INVALID_TYPE', 'params_config' => ['expectedType' => 'integer']],
            'Array'           => ['code' => 'INVALID_TYPE_ARRAY', 'params_mapper' => function($params) {
                                    $p = ['expectedType' => 'array'];
                                    if (!empty($params[0])) { $p['expectedKeys'] = $params[0]; } // For array:key1,key2 rule
                                    return $p;
                                }],
            'Boolean'         => ['code' => 'INVALID_TYPE', 'params_config' => ['expectedType' => 'boolean']],
            'File'            => ['code' => 'INVALID_FILE'],
            'Image'           => ['code' => 'INVALID_IMAGE'],
            'Json'            => ['code' => 'INVALID_JSON_FORMAT'],

            // --- Size & Length & Count ---
            // For Min/Max/Size, code could be more specific (MIN_LENGTH, MIN_VALUE, etc.)
            // The user can customize this by overriding the map. Defaulting to user's example.
            'Min'             => ['code' => 'MIN_LENGTH', 'params_config' => ['minLength' => 0]],
            'Max'             => ['code' => 'MAX_LENGTH', 'params_config' => ['maxLength' => 0]],
            'Size'            => ['code' => 'INVALID_SIZE', 'params_config' => ['requiredSize' => 0]],
            'Between'         => [
                'code' => 'NOT_BETWEEN',
                'params_config' => ['min' => 0, 'max' => 1]
            ],

            // --- Format & Pattern ---
            'Email'           => ['code' => 'INVALID_EMAIL_FORMAT'], // $ruleParams might contain validation types (e.g. ['rfc', 'dns'])
            'Url'             => ['code' => 'INVALID_URL_FORMAT'], // $ruleParams might contain expected schemes
            'ActiveUrl'       => ['code' => 'URL_NOT_ACTIVE'],
            'Ip'              => ['code' => 'INVALID_IP_ADDRESS'],
            'Ipv4'            => ['code' => 'INVALID_IPV4_ADDRESS'],
            'Ipv6'            => ['code' => 'INVALID_IPV6_ADDRESS'],
            'MacAddress'      => ['code' => 'INVALID_MAC_ADDRESS'],
            'Uuid'            => ['code' => 'INVALID_UUID_FORMAT'],
            'Regex'           => ['code' => 'INVALID_FORMAT_REGEX'], // $ruleParams[0] is the pattern (usually not exposed)
            'NotRegex'        => ['code' => 'INVALID_FORMAT_NOT_REGEX'],
            'Alpha'           => ['code' => 'INVALID_ALPHA'],
            'AlphaDash'       => ['code' => 'INVALID_ALPHA_DASH'],
            'AlphaNum'        => ['code' => 'INVALID_ALPHA_NUM'],
            'Digits'          => ['code' => 'INVALID_DIGITS_LENGTH', 'params_config' => ['length' => 0]],
            'DigitsBetween'   => ['code' => 'INVALID_DIGITS_LENGTH_BETWEEN', 'params_config' => ['min' => 0, 'max' => 1]],

            // --- Date & Time ---
            'Date'            => ['code' => 'INVALID_DATE'],
            'DateFormat'      => ['code' => 'INVALID_DATE_FORMAT', 'params_config' => ['expectedFormat' => 0]],
            'Before'          => ['code' => 'DATE_NOT_BEFORE', 'params_config' => ['date' => 0]],
            'After'           => ['code' => 'DATE_NOT_AFTER', 'params_config' => ['date' => 0]],
            'BeforeOrEqual'   => ['code' => 'DATE_NOT_BEFORE_OR_EQUAL', 'params_config' => ['date' => 0]],
            'AfterOrEqual'    => ['code' => 'DATE_NOT_AFTER_OR_EQUAL', 'params_config' => ['date' => 0]],
            'DateEquals'      => ['code' => 'DATE_NOT_EQUAL', 'params_config' => ['date' => 0]],
            'Timezone'        => ['code' => 'INVALID_TIMEZONE'], // $ruleParams[0] can be region/country code

            // --- Comparisons & Relations ---
            'Confirmed'       => ['code' => 'CONFIRMATION_MISMATCH'], // Implicitly compares to field_confirmation
            'Same'            => ['code' => 'FIELD_NOT_SAME', 'params_config' => ['otherField' => 0]],
            'Different'       => ['code' => 'FIELD_IS_SAME', 'params_config' => ['otherField' => 0]],
            'Gt'              => ['code' => 'NOT_GREATER_THAN', 'params_config' => ['value' => 0]], // param can be other field or value
            'Gte'             => ['code' => 'NOT_GREATER_THAN_OR_EQUAL', 'params_config' => ['value' => 0]],
            'Lt'              => ['code' => 'NOT_LESS_THAN', 'params_config' => ['value' => 0]],
            'Lte'             => ['code' => 'NOT_LESS_THAN_OR_EQUAL', 'params_config' => ['value' => 0]],

            // --- Database Related ---
            'Unique'          => [
                'code' => 'NOT_UNIQUE',
                'params_mapper' => function ($ruleParams) {
                    $p = ['table' => $ruleParams[0]];
                    if (isset($ruleParams[1]) && $ruleParams[1] !== null && strtolower((string)$ruleParams[1]) !== 'null') {
                        $p['column'] = $ruleParams[1];
                    }
                    if (isset($ruleParams[2]) && $ruleParams[2] !== null) { // exceptId
                        $p['except'] = $ruleParams[2];
                    }
                    // $ruleParams[3] is idColumn
                    return $p;
                }
            ],
            'Exists'          => [
                'code' => 'DOES_NOT_EXIST',
                'params_mapper' => function ($ruleParams) {
                    $p = ['table' => $ruleParams[0]];
                     if (isset($ruleParams[1]) && $ruleParams[1] !== null && strtolower((string)$ruleParams[1]) !== 'null') {
                        $p['column'] = $ruleParams[1];
                    }
                    // Additional where clauses can be in $ruleParams from index 2.
                    return $p;
                }
            ],

            // --- Conditional Validation ---
            'RequiredIf'      => ['code' => 'REQUIRED_CONDITIONALLY', 'params_config' => ['otherField' => 0, 'value' => 1]], // value can be array
            'RequiredUnless'  => ['code' => 'REQUIRED_CONDITIONALLY', 'params_config' => ['otherField' => 0, 'value' => 1]],
            'RequiredWith'    => ['code' => 'REQUIRED_WITH', 'params_mapper' => function($params){ return ['otherFields' => $params]; }],
            'RequiredWithAll' => ['code' => 'REQUIRED_WITH_ALL', 'params_mapper' => function($params){ return ['otherFields' => $params]; }],
            'RequiredWithout' => ['code' => 'REQUIRED_WITHOUT', 'params_mapper' => function($params){ return ['otherFields' => $params]; }],
            'RequiredWithoutAll' => ['code' => 'REQUIRED_WITHOUT_ALL', 'params_mapper' => function($params){ return ['otherFields' => $params]; }],
            'Prohibited'      => ['code' => 'FIELD_IS_PROHIBITED'],
            'ProhibitedIf'    => ['code' => 'PROHIBITED_IF', 'params_config' => ['otherField' => 0, 'value' => 1]],
            'ProhibitedUnless'=> ['code' => 'PROHIBITED_UNLESS', 'params_config' => ['otherField' => 0, 'value' => 1]],

            // --- File/Image Specific ---
            'Mimes'           => ['code' => 'INVALID_FILE_MIME_TYPE', 'params_mapper' => function ($params) { return ['allowedTypes' => $params]; }],
            'Mimetypes'       => ['code' => 'INVALID_FILE_MIME_TYPE', 'params_mapper' => function ($params) { return ['allowedTypes' => $params]; }],
            'Dimensions'      => [
                'code' => 'INVALID_IMAGE_DIMENSIONS',
                'params_mapper' => function ($ruleParams) { return $ruleParams; } // Params are already key-value pairs
            ],

            // --- List / Set Based ---
            'In'              => ['code' => 'VALUE_NOT_IN_ALLOWED_SET', 'params_mapper' => function ($params) { return ['allowedValues' => $params]; }],
            'NotIn'           => ['code' => 'VALUE_IN_PROHIBITED_SET', 'params_mapper' => function ($params) { return ['prohibitedValues' => $params]; }],
            'InArray'         => ['code' => 'VALUE_NOT_IN_ARRAY_FIELD', 'params_config' => ['otherField' => 0]],
            'Distinct'        => ['code' => 'NOT_DISTINCT'], // $ruleParams[0] can be 'ignore_case' or strictness level

            // --- Acceptance ---
            'Accepted'        => ['code' => 'NOT_ACCEPTED'], // e.g. for terms of service
            'AcceptedIf'      => ['code' => 'NOT_ACCEPTED_IF', 'params_config' => ['otherField' => 0, 'value' => 1]],
            'Declined'        => ['code' => 'NOT_DECLINED'],
            'DeclinedIf'      => ['code' => 'NOT_DECLINED_IF', 'params_config' => ['otherField' => 0, 'value' => 1]],

            // --- String Content ---
            'StartsWith'      => ['code' => 'DOES_NOT_START_WITH', 'params_mapper' => function($params){ return ['expectedPrefixes' => $params]; }],
            'EndsWith'        => ['code' => 'DOES_NOT_END_WITH', 'params_mapper' => function($params){ return ['expectedSuffixes' => $params]; }],
            'DoesntStartWith' => ['code' => 'STARTS_WITH_PROHIBITED', 'params_mapper' => function($params){ return ['prohibitedPrefixes' => $params]; }],
            'DoesntEndWith'   => ['code' => 'ENDS_WITH_PROHIBITED', 'params_mapper' => function($params){ return ['prohibitedSuffixes' => $params]; }],

            // --- Password rules (Laravel 10+) ---
            // These are often part of the Password rule object, which might report its own "Password" failure
            // or specific sub-rule failures.
            'PasswordLetters' => ['code' => 'PASSWORD_REQUIRES_LETTERS'],
            'PasswordMixedCase' => ['code' => 'PASSWORD_REQUIRES_MIXED_CASE'],
            'PasswordNumbers' => ['code' => 'PASSWORD_REQUIRES_NUMBERS'],
            'PasswordSymbols' => ['code' => 'PASSWORD_REQUIRES_SYMBOLS'],
            'PasswordUncompromised' => ['code' => 'PASSWORD_COMPROMISED', 'params_config' => ['threshold' => 0]], // if threshold is passed
            'CurrentPassword' => ['code' => 'INVALID_CURRENT_PASSWORD'], // $ruleParams[0] can be the guard

            // Add more as needed. This covers more than 50 common scenarios.
        ];
    }
}
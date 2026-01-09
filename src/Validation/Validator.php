<?php

namespace Swilen\Validation;

use Swilen\Shared\Support\Arrayable;
use Swilen\Validation\Contract\ValidatorContract;
use Swilen\Validation\Exception\RuleNotFoundException;

class Validator implements ValidatorContract, Arrayable
{
    /**
     * The validation errors messages bag.
     *
     * @var \Swilen\Validation\MessageBag
     */
    protected $messages;

    /**
     * The inputs array for validate.
     *
     * @var string[]
     */
    protected $inputs = [];

    /**
     * The rules array for validate.
     *
     * @var string[]
     */
    protected $rules = [];

    /**
     * The hash table for validate rules.
     *
     * @var array<string, \Swilen\Validation\Rules\BaseRule>
     */
    protected $validators = [
        Rule::ALPHA => \Swilen\Validation\Rules\Alpha::class,
        Rule::BETWEEN => \Swilen\Validation\Rules\Between::class,
        Rule::BOOLEAN => \Swilen\Validation\Rules\Boolean::class,
        Rule::DATE => \Swilen\Validation\Rules\Date::class,
        // Rule::DIFFERENT => \Swilen\Validation\Rules\Different::class,
        Rule::EMAIL => \Swilen\Validation\Rules\Email::class,
        // Rule::EXT => \Swilen\Validation\Rules\Ext::class,
        Rule::IN => \Swilen\Validation\Rules\In::class,
        Rule::INTEGER => \Swilen\Validation\Rules\Integer::class,
        Rule::IP => \Swilen\Validation\Rules\Ip::class,
        Rule::LOWERCASE => \Swilen\Validation\Rules\Lowercase::class,
        // Rule::MAX => \Swilen\Validation\Rules\Max::class,
        // Rule::MIN => \Swilen\Validation\Rules\Min::class,
        Rule::NOT_IN => \Swilen\Validation\Rules\NotIn::class,
        Rule::NULLABLE => \Swilen\Validation\Rules\Nullable::class,
        Rule::NUMBER => \Swilen\Validation\Rules\Number::class,
        Rule::REGEX => \Swilen\Validation\Rules\Regex::class,
        Rule::REQUIRED => \Swilen\Validation\Rules\Required::class,
        Rule::ARRAY => \Swilen\Validation\Rules\RuleArray::class,
        Rule::OBJECT => \Swilen\Validation\Rules\RuleObject::class,
        // Rule::SAME => \Swilen\Validation\Rules\Same::class,
        // Rule::SIZE => \Swilen\Validation\Rules\Size::class,
        Rule::UPPERCASE => \Swilen\Validation\Rules\Uppercase::class,
        Rule::URL => \Swilen\Validation\Rules\Url::class,
    ];

    /**
     * Create new Validator instance with given inputs.
     *
     * @param array $inputs
     *
     * @return void
     */
    public function __construct(array $inputs = [])
    {
        $this->messages = new MessageBag([]);
        $this->inputs   = $inputs;
    }

    /**
     * Set validatable data.
     *
     * @param array $inputs
     * @param array $rules
     *
     * @return $this
     */
    public static function make(array $inputs, array $rules = [])
    {
        $validator = new static($inputs);

        if ($rules !== []) {
            return $validator->validate($rules);
        }

        return $validator;
    }

    /**
     * Main function for validate with validator rules.
     *
     * @param array $rules
     *
     * @return $this
     */
    public function validate(array $rules)
    {
        $this->messages = new MessageBag([]);
        $this->rules    = $rules;

        foreach ($this->rules as $attribute => $rawRules) {
            $this->validateAttribute($rawRules, $attribute);
        }

        return $this;
    }

    /**
     * Validate given rule and add error to error bag.
     *
     * @param string[]|string $rule
     * @param string          $attribute
     *
     * @return void
     */
    public function validateAttribute($rules, $attribute)
    {
        $value = $this->getValue($attribute);
        $rules = $this->normalizeRules($rules);

        if (!in_array(Rule::REQUIRED, $rules) && !$value) {
            return;
        }

        foreach ($rules as $_rule) {
            [$rule, $params] = $this->parseStringRule($_rule);

            $this->validateRuleWithAtrributes(
                $rule, $value, $attribute, $params
            );
        }
    }

    /**
     * Validate given rule and add error to error bag.
     *
     * @param string   $rule
     * @param mixed    $value
     * @param string   $attribute
     * @param string[] $params
     *
     * @return void
     */
    public function validateRuleWithAtrributes($rule, $value, $attribute, $params)
    {
        $rule = $this->bindRuleValidator($rule, $value, $attribute)
            ->setParameters($params);

        if (!$rule->validate()) {
            $this->messages->add($attribute, $rule->message());
        }
    }

    /**
     * Normalize rules passed for validation, if use string with | notation.
     *
     * @param array|string $rules
     *
     * @return array
     */
    protected function normalizeRules($rules)
    {
        return is_array($rules) ? $rules : explode('|', $rules);
    }

    /**
     * Parse a string based rule.
     *
     * @param string $rule
     *
     * @return array{0:string,1:array}
     */
    protected function parseStringRule(string $rule)
    {
        $parameters = [];

        if (strpos($rule, ':') !== false) {
            [$rule, $parameter] = explode(':', $rule, 2);

            $parameters = $this->parseParameters($rule, $parameter);
        }

        return [trim($rule), $parameters];
    }

    /**
     * Parse a parameter list.
     *
     * @param string $rule
     * @param string $parameter
     *
     * @return array
     */
    protected function parseParameters($rule, $parameter)
    {
        $rule = strtolower($rule);

        if (in_array($rule, ['regex'], true)) {
            return [$parameter];
        }

        return str_getcsv($parameter);
    }

    /**
     * Get validator for rule or throw error if not exists.
     *
     * @param string $rule
     * @param mixed  $value
     * @param string $attribute
     *
     * @return \Swilen\Validation\Rules\BaseRule
     *
     * @throws \Swilen\Validation\Exception\RuleNotFoundException
     */
    protected function bindRuleValidator(string $rule, $value, string $attribute)
    {
        if (isset($this->validators[$rule])) {
            return new $this->validators[$rule]($value, $attribute);
        }

        throw new RuleNotFoundException($rule);
    }

    /**
     * Get the value fro given $attribute in $inputs store.
     *
     * @param string $attribute
     *
     * @return mixed|null
     */
    protected function getValue(string $attribute)
    {
        return $this->inputs[$attribute] ?? null;
    }

    /**
     * Retrieve errors from messages bag instance.
     *
     * @param string|null $key
     *
     * @return \Swilen\Validation\MessageBag|array|string
     */
    public function errors($key = null)
    {
        if ($key === null) {
            return $this->messages;
        }

        return $this->messages->get($key);
    }

    /**
     * Return if the validation is failed.
     *
     * @return bool
     */
    public function fails()
    {
        return $this->messages->isNotEmpty();
    }

    /**
     * Get inputs storaged dynamicaly.
     *
     * @param string|int $key
     *
     * @return string|null
     */
    public function __get($key)
    {
        return $this->inputs[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return [
            'inputs' => $this->inputs,
            'errors' => $this->messages->toArray(),
            'rules' => $this->rules,
        ];
    }
}

<?php

namespace Swilen\Validation\Rules;

/**
 * @codeCoverageIgnore
 */
class Between extends BaseRule
{
    /**
     * The response message when is invalid.
     *
     * @var string
     */
    protected $message = 'The :attribute must be between :min and :max.';

    /**
     * The fillable parameters.
     *
     * @var array
     */
    protected $fillableParams = ['min', 'max'];

    /**
     * Check value is valid with given attribute.
     *
     * @return bool
     */
    public function validate(): bool
    {
        $this->requireParameters(['min', 'max']);
        
        $min = $this->parameter('min');
        $max = $this->parameter('max');
        
        // Handle numeric values
        if (is_numeric($this->value)) {
            $value = (float) $this->value;
            return $value >= $min && $value <= $max;
        }
        
        // Handle string length
        if (is_string($this->value)) {
            $length = strlen($this->value);
            return $length >= $min && $length <= $max;
        }
        
        // Handle array count
        if (is_array($this->value)) {
            $count = count($this->value);
            return $count >= $min && $count <= $max;
        }
        
        return false;
    }
}
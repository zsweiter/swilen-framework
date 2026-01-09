<?php

namespace Swilen\Validation;

final class Rule
{
    public const ALPHA      = 'alpha';
    public const BETWEEN    = 'between';
    public const BOOLEAN    = 'boolean';
    public const DATE       = 'date';
    public const DIFFERENT  = 'different';
    public const EMAIL      = 'email';
    public const EXT        = 'ext';
    public const IN         = 'in';
    public const INTEGER    = 'int';
    public const IP         = 'ip';
    public const LOWERCASE  = 'lowercase';
    public const MAX        = 'max';
    public const MIN        = 'min';
    public const NOT_IN     = 'not_id';
    public const NULLABLE   = 'nullable';
    public const NUMBER     = 'number';
    public const REGEX      = 'regex';
    public const REQUIRED   = 'required';
    public const ARRAY      = 'array';
    public const OBJECT     = 'object';
    public const SAME       = 'same';
    public const SIZE       = 'size';
    public const UPPERCASE  = 'uppercase';
    public const URL        = 'url';

    public const PLACEHOLDER_ATTRIBUTE         = ':attribute';
    public const PLACEHOLDER_ANOTHER_ATTRIBUTE = ':another-attribute';
    public const PLACEHOLDER_VALUE             = ':value';
    public const PLACEHOLDER_ALLOW             = ':allowed';
    public const PLACEHOLDER_MESSAGE           = ':message';
}

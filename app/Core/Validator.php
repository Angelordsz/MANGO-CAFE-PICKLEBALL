<?php
namespace App\Core;

/**
 * Rule-string validator.
 *
 *   $v = Validator::make($data, [
 *       'email'    => 'required|email|unique:users,email',
 *       'password' => 'required|min:8|confirmed',
 *       'age'      => 'nullable|integer|between:10,100',
 *   ], ['email.unique' => 'That email is already registered.']);
 *
 *   if ($v->fails()) { ... $v->errors() ... }
 */
final class Validator
{
    private array $data;
    private array $rules;
    private array $messages;
    private array $errors = [];

    private function __construct(array $data, array $rules, array $messages)
    {
        $this->data     = $data;
        $this->rules    = $rules;
        $this->messages = $messages;
        $this->run();
    }

    public static function make(array $data, array $rules, array $messages = []): self
    {
        return new self($data, $rules, $messages);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** ['field' => 'first error message'] */
    public function errors(): array
    {
        return array_map(static fn($list) => $list[0], $this->errors);
    }

    /**
     * The validated fields, keyed by rule.
     *
     * Every rule key is present in the result, set to null when the input was
     * not submitted at all. Without this, an optional field that a form omits
     * entirely would be missing from the array and every `$data['optional']`
     * read in a controller would raise an undefined-key warning.
     */
    public function validated(): array
    {
        $validated = [];

        foreach (array_keys($this->rules) as $field) {
            $value = $this->data[$field] ?? null;
            $validated[$field] = ($value === '') ? null : $value;
        }

        return $validated;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $value    = $this->data[$field] ?? null;
            $rules    = explode('|', $ruleString);
            $nullable = in_array('nullable', $rules, true);

            if ($nullable && ($value === null || $value === '')) {
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }

                [$name, $args] = array_pad(explode(':', $rule, 2), 2, null);
                $args = $args !== null ? explode(',', $args) : [];

                if (!$this->check($name, $field, $value, $args)) {
                    $this->addError($field, $name, $args);
                    break; // one message per field
                }
            }
        }
    }

    private function check(string $rule, string $field, mixed $value, array $args): bool
    {
        return match ($rule) {
            'required'  => !($value === null || $value === '' || (is_array($value) && $value === [])),
            'email'     => (bool) filter_var($value, FILTER_VALIDATE_EMAIL),
            'integer'   => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'numeric'   => is_numeric($value),
            'boolean'   => in_array($value, [true, false, 0, 1, '0', '1', 'on', 'off'], true),
            'min'       => $this->sizeOf($value) >= (float) $args[0],
            'max'       => $this->sizeOf($value) <= (float) $args[0],
            'between'   => $this->sizeOf($value) >= (float) $args[0] && $this->sizeOf($value) <= (float) $args[1],
            'in'        => in_array((string) $value, $args, true),
            'date'      => strtotime((string) $value) !== false,
            'after'     => strtotime((string) $value) > strtotime($args[0] === 'today' ? 'today' : $args[0]),
            'after_or_equal' => strtotime((string) $value) >= strtotime($args[0] === 'today' ? 'today' : $args[0]),
            'before'    => strtotime((string) $value) < strtotime($args[0] === 'today' ? 'today' : $args[0]),
            'confirmed' => $value === ($this->data[$field . '_confirmation'] ?? null),
            'same'      => $value === ($this->data[$args[0]] ?? null),
            'alpha_num' => (bool) preg_match('/^[A-Za-z0-9]+$/', (string) $value),
            'alpha_dash'=> (bool) preg_match('/^[A-Za-z0-9._-]+$/', (string) $value),
            'phone'     => (bool) preg_match('/^[0-9+()\s-]{7,20}$/', (string) $value),
            'regex'     => (bool) preg_match($args[0], (string) $value),
            'unique'    => $this->isUnique($args, $value),
            'exists'    => $this->exists($args, $value),
            default     => true,
        };
    }

    /** Numeric values compare by magnitude; strings and arrays by length/count. */
    private function sizeOf(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_array($value)) {
            return (float) count($value);
        }
        return (float) mb_strlen((string) $value);
    }

    /** unique:table,column[,ignoreId[,idColumn]] */
    private function isUnique(array $args, mixed $value): bool
    {
        [$table, $column] = $args;
        $ignoreId  = $args[2] ?? null;
        $idColumn  = $args[3] ?? 'id';

        $sql    = "SELECT COUNT(*) FROM `$table` WHERE `$column` = :v";
        $params = ['v' => $value];

        if ($ignoreId !== null && $ignoreId !== '') {
            $sql .= " AND `$idColumn` != :ignore";
            $params['ignore'] = $ignoreId;
        }

        return (int) Database::scalar($sql, $params, 0) === 0;
    }

    /** exists:table,column */
    private function exists(array $args, mixed $value): bool
    {
        [$table, $column] = $args;
        return (int) Database::scalar("SELECT COUNT(*) FROM `$table` WHERE `$column` = :v", ['v' => $value], 0) > 0;
    }

    private function addError(string $field, string $rule, array $args): void
    {
        $label  = ucfirst(str_replace('_', ' ', $field));
        $custom = $this->messages["$field.$rule"] ?? null;

        $this->errors[$field][] = $custom ?? match ($rule) {
            'required'  => "$label is required.",
            'email'     => "Enter a valid email address.",
            'integer',
            'numeric'   => "$label must be a number.",
            'min'       => is_numeric($this->data[$field] ?? '')
                            ? "$label must be at least {$args[0]}."
                            : "$label must be at least {$args[0]} characters.",
            'max'       => is_numeric($this->data[$field] ?? '')
                            ? "$label must not exceed {$args[0]}."
                            : "$label must not exceed {$args[0]} characters.",
            'between'   => "$label must be between {$args[0]} and {$args[1]}.",
            'in'        => "$label is not a valid choice.",
            'date'      => "$label must be a valid date.",
            'after'     => "$label must be after {$args[0]}.",
            'after_or_equal' => "$label cannot be in the past.",
            'before'    => "$label must be before {$args[0]}.",
            'confirmed' => "$label confirmation does not match.",
            'same'      => "$label does not match.",
            'alpha_num' => "$label may only contain letters and numbers.",
            'alpha_dash'=> "$label may only contain letters, numbers, dots, dashes and underscores.",
            'phone'     => "Enter a valid contact number.",
            'unique'    => "That $label is already taken.",
            'exists'    => "The selected $label does not exist.",
            default     => "$label is invalid.",
        };
    }
}

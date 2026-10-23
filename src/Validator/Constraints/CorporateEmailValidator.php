<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class CorporateEmailValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CorporateEmail) {
            throw new UnexpectedTypeException($constraint, CorporateEmail::class);
        }

        // Blank values are the concern of NotBlank; stay silent so a single
        // field does not report two errors at once.
        if ($value === null || $value === "") {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, "string");
        }

        $at = strrpos($value, "@");
        if ($at === false) {
            return; // Malformed input is reported by the Email constraint.
        }

        $domain = strtolower(trim(substr($value, $at + 1)));

        if (in_array($domain, $constraint->blockedDomains, true)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter("{{ value }}", $value)
                ->addViolation();
        }
    }
}

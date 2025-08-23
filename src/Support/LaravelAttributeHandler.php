<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Support;

class LaravelAttributeHandler
{
    public function translateLaravelAttribute(string $column, string $operator, mixed $value): ?string
    {
        $formattedValue = $this->formatValue($value);

        return match ($column) {
            'created_at' => $this->translateCreatedAt($operator, $formattedValue),
            'updated_at' => $this->translateUpdatedAt($operator, $formattedValue),
            'deleted_at' => $this->translateDeletedAt($operator, $formattedValue),
            'email_verified_at' => $this->translateEmailVerifiedAt($operator, $formattedValue),
            'last_used_at' => $this->translateLastUsedAt($operator, $formattedValue),
            'password' => $this->translatePassword($operator, $formattedValue),
            'remember_token' => $this->translateRememberToken($operator, $formattedValue),
            'current_team_id' => $this->translateCurrentTeamId($operator, $formattedValue),
            default => null,
        };
    }

    public function translateLaravelAttributeNull(string $column, bool $isNull): ?string
    {
        return match ($column) {
            'created_at' => $isNull ? 'not yet created' : 'that have been created',
            'updated_at' => $isNull ? 'never updated' : 'that have been updated',
            'deleted_at' => $isNull ? 'not deleted' : 'that have been deleted',
            'email_verified_at' => $isNull ? 'with unverified email' : 'with verified email',
            'last_used_at' => $isNull ? 'never used' : 'that have been used',
            'password' => $isNull ? 'without a password' : 'with a password',
            'remember_token' => $isNull ? 'without remember me token' : 'with remember me token',
            'current_team_id' => $isNull ? 'not assigned to any team' : 'assigned to a team',
            'profile_photo_path' => $isNull ? 'without a profile picture' : 'with a profile picture',
            'two_factor_secret' => $isNull ? 'without two-factor authentication' : 'with two-factor authentication enabled',
            'two_factor_recovery_codes' => $isNull ? 'without backup recovery codes' : 'with backup recovery codes',
            default => null,
        };
    }

    public function translateLaravelAttributeDate(string $column, string $operator, mixed $value): ?string
    {
        $formattedValue = $this->formatValue($value);

        return match ($column) {
            'created_at' => $this->translateCreatedAtDate($operator, $formattedValue),
            'updated_at' => $this->translateUpdatedAtDate($operator, $formattedValue),
            'deleted_at' => $this->translateDeletedAtDate($operator, $formattedValue),
            'email_verified_at' => $this->translateEmailVerifiedAtDate($operator, $formattedValue),
            'last_used_at' => $this->translateLastUsedAtDate($operator, $formattedValue),
            default => null,
        };
    }

    private function translateCreatedAt(string $operator, string $formattedValue): ?string
    {
        return match ($operator) {
            '=' => "created on {$formattedValue}",
            '!=' => "not created on {$formattedValue}",
            '>' => "created after {$formattedValue}",
            '>=' => "created on or after {$formattedValue}",
            '<' => "created before {$formattedValue}",
            '<=' => "created on or before {$formattedValue}",
            default => null,
        };
    }

    private function translateUpdatedAt(string $operator, string $formattedValue): ?string
    {
        return match ($operator) {
            '=' => "last modified on {$formattedValue}",
            '!=' => "not modified on {$formattedValue}",
            '>' => "modified after {$formattedValue}",
            '>=' => "modified on or after {$formattedValue}",
            '<' => "modified before {$formattedValue}",
            '<=' => "modified on or before {$formattedValue}",
            default => null,
        };
    }

    private function translateDeletedAt(string $operator, string $formattedValue): ?string
    {
        return match ($operator) {
            '=' => "deleted on {$formattedValue}",
            '!=' => "not deleted on {$formattedValue}",
            '>' => "deleted after {$formattedValue}",
            '>=' => "deleted on or after {$formattedValue}",
            '<' => "deleted before {$formattedValue}",
            '<=' => "deleted on or before {$formattedValue}",
            default => null,
        };
    }

    private function translateEmailVerifiedAt(string $operator, string $formattedValue): ?string
    {
        return match ($operator) {
            '=' => "verified their email on {$formattedValue}",
            '!=' => "didn't verify their email on {$formattedValue}",
            '>' => "verified their email after {$formattedValue}",
            '>=' => "verified their email on or after {$formattedValue}",
            '<' => "verified their email before {$formattedValue}",
            '<=' => "verified their email on or before {$formattedValue}",
            default => null,
        };
    }

    private function translateLastUsedAt(string $operator, string $formattedValue): ?string
    {
        return match ($operator) {
            '=' => "last used on {$formattedValue}",
            '!=' => "not used on {$formattedValue}",
            '>' => "used after {$formattedValue}",
            '>=' => "used on or after {$formattedValue}",
            '<' => "used before {$formattedValue}",
            '<=' => "used on or before {$formattedValue}",
            default => null,
        };
    }

    private function translatePassword(string $operator, string $formattedValue): ?string
    {
        return match ($operator) {
            '=' => "with password {$formattedValue}",
            '!=' => "without password {$formattedValue}",
            'like' => "with password containing {$formattedValue}",
            'not like' => "with password not containing {$formattedValue}",
            default => null,
        };
    }

    private function translateRememberToken(string $operator, string $formattedValue): ?string
    {
        return match ($operator) {
            '=' => "with remember token {$formattedValue}",
            '!=' => "without remember token {$formattedValue}",
            default => null,
        };
    }

    private function translateCurrentTeamId(string $operator, string $formattedValue): ?string
    {
        return match ($operator) {
            '=' => "currently in team {$formattedValue}",
            '!=' => "not in team {$formattedValue}",
            '>' => "in team with ID greater than {$formattedValue}",
            '<' => "in team with ID less than {$formattedValue}",
            default => null,
        };
    }

    private function translateCreatedAtDate(string $operator, string $formattedValue): ?string
    {
        return match ($operator) {
            '=' => "with creation date on {$formattedValue}",
            '!=' => "with creation date not on {$formattedValue}",
            '>' => "with creation date after {$formattedValue}",
            '>=' => "with creation date on or after {$formattedValue}",
            '<' => "with creation date before {$formattedValue}",
            '<=' => "with creation date on or before {$formattedValue}",
            default => null,
        };
    }

    private function translateUpdatedAtDate(string $operator, string $formattedValue): ?string
    {
        return match ($operator) {
            '=' => "with last modification date on {$formattedValue}",
            '!=' => "with last modification date not on {$formattedValue}",
            '>' => "with last modification date after {$formattedValue}",
            '>=' => "with last modification date on or after {$formattedValue}",
            '<' => "with last modification date before {$formattedValue}",
            '<=' => "with last modification date on or before {$formattedValue}",
            default => null,
        };
    }

    private function translateDeletedAtDate(string $operator, string $formattedValue): ?string
    {
        return match ($operator) {
            '=' => "with deletion date on {$formattedValue}",
            '!=' => "with deletion date not on {$formattedValue}",
            '>' => "with deletion date after {$formattedValue}",
            '>=' => "with deletion date on or after {$formattedValue}",
            '<' => "with deletion date before {$formattedValue}",
            '<=' => "with deletion date on or before {$formattedValue}",
            default => null,
        };
    }

    private function translateEmailVerifiedAtDate(string $operator, string $formattedValue): ?string
    {
        return match ($operator) {
            '=' => "who verified their email on {$formattedValue}",
            '!=' => "who didn't verify their email on {$formattedValue}",
            '>' => "who verified their email after {$formattedValue}",
            '>=' => "who verified their email on or after {$formattedValue}",
            '<' => "who verified their email before {$formattedValue}",
            '<=' => "who verified their email on or before {$formattedValue}",
            default => null,
        };
    }

    private function translateLastUsedAtDate(string $operator, string $formattedValue): ?string
    {
        return match ($operator) {
            '=' => "last used on {$formattedValue}",
            '!=' => "not used on {$formattedValue}",
            '>' => "used after {$formattedValue}",
            '>=' => "used on or after {$formattedValue}",
            '<' => "used before {$formattedValue}",
            '<=' => "used on or before {$formattedValue}",
            default => null,
        };
    }

    private function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            return "'{$value}'";
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        if (is_array($value)) {
            $formatted = array_map([$this, 'formatValue'], $value);

            return '['.implode(', ', $formatted).']';
        }

        return (string) $value;
    }
}

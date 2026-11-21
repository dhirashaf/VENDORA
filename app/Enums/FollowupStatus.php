<?php

namespace App\Enums;

enum FollowupStatus: string
{
    case Open = 'Open';
    case EthicsReview = 'Ethics_Review';
    case MeetingInvited = 'Meeting_Invited';
    case Clarification = 'Clarification';
    case CorrectiveActionSubmitted = 'Corrective_Action_Submitted';
    case Monitoring = 'Monitoring';
    case DecisionMade = 'Decision_Made';
    case Closed = 'Closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Dibuka',
            self::EthicsReview => 'Review Kode Etik',
            self::MeetingInvited => 'Undangan Meeting',
            self::Clarification => 'Klarifikasi Vendor',
            self::CorrectiveActionSubmitted => 'Corrective Action',
            self::Monitoring => 'Monitoring Progres',
            self::DecisionMade => 'Keputusan Dibuat',
            self::Closed => 'Ditutup',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Open => 'secondary',
            self::EthicsReview => 'info',
            self::MeetingInvited => 'info',
            self::Clarification => 'warning',
            self::CorrectiveActionSubmitted => 'primary',
            self::Monitoring => 'primary',
            self::DecisionMade => 'success',
            self::Closed => 'dark',
        };
    }

    /**
     * Tahapan proses pascaevaluasi sesuai SOP Vendor Evaluation & Development (FR-19).
     *
     * @return list<self>
     */
    public static function workflow(): array
    {
        return [
            self::Open,
            self::EthicsReview,
            self::MeetingInvited,
            self::Clarification,
            self::CorrectiveActionSubmitted,
            self::Monitoring,
            self::DecisionMade,
            self::Closed,
        ];
    }

    public function position(): int
    {
        return (int) array_search($this, self::workflow(), true);
    }

    public function next(): ?self
    {
        return self::workflow()[$this->position() + 1] ?? null;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::DecisionMade, self::Closed], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}

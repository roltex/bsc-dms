<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Draft = 'draft';
    case PendingManager = 'pending_manager';
    case PendingLawyer = 'pending_lawyer';
    case PendingInitiator = 'pending_initiator';
    case PendingFinalLawyer = 'pending_final_lawyer';
    case PendingFinalManager = 'pending_final_manager';
    case PendingPartner = 'pending_partner';
    case PendingGM = 'pending_gm';
    case NeedsRevision = 'needs_revision';
    case Approved = 'approved';
    case Archived = 'archived';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingManager => 'Pending Manager',
            self::PendingLawyer => 'Pending Lawyer',
            self::PendingInitiator => 'Pending Initiator (Negotiation)',
            self::PendingFinalLawyer => 'Pending Final Lawyer Review',
            self::PendingFinalManager => 'Pending Final Manager Approval',
            self::PendingPartner => 'Pending Partner',
            self::PendingGM => 'Pending GM Approval',
            self::NeedsRevision => 'Needs Revision',
            self::Approved => 'Approved',
            self::Archived => 'Archived',
            self::Rejected => 'Rejected',
        };
    }

    public function isPending(): bool
    {
        return in_array($this, [
            self::PendingManager,
            self::PendingLawyer,
            self::PendingInitiator,
            self::PendingFinalLawyer,
            self::PendingFinalManager,
            self::PendingPartner,
            self::PendingGM,
            self::NeedsRevision,
        ]);
    }

    public function stepNumber(): int
    {
        return match ($this) {
            self::Draft => 0,
            self::PendingManager => 1,
            self::PendingLawyer => 2,
            self::PendingInitiator => 3,
            self::PendingFinalLawyer => 4,
            self::PendingFinalManager => 5,
            self::PendingPartner => 6,
            self::PendingGM => 6,
            self::NeedsRevision => -2,
            self::Approved => 7,
            self::Archived => 7,
            self::Rejected => -1,
        };
    }
}

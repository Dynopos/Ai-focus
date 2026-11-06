<?php

namespace App\Services\WhatsApp;

final class Intent
{
    public const APPROVE = 'approve';

    public const REJECT = 'reject';

    public const MODIFY = 'modify';

    public const TOP_PRIORITIES = 'top_priorities';

    public const LIST_PENDING = 'list_pending';

    public const MARK_DONE = 'mark_done';

    public const SET_STATUS = 'set_status';

    public const DRAFT_FOLLOWUP = 'draft_followup';

    public const ADD_TASK = 'add_task';

    public const HELP = 'help';

    public const QUESTION = 'question';

    /** Intents that authorise external or consequential actions. Never produced by the AI. */
    public const PRIVILEGED = [self::APPROVE, self::REJECT, self::MODIFY];

    public function __construct(
        public readonly string $name,
        public readonly ?string $target = null,  // task/customer text, approval code, etc.
        public readonly ?string $value = null,   // new status, new text, task title
        public readonly string $via = 'rules',   // rules | ai | button
    ) {}

    public function isPrivileged(): bool
    {
        return in_array($this->name, self::PRIVILEGED, true);
    }
}

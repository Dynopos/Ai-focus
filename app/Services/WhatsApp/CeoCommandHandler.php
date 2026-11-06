<?php

namespace App\Services\WhatsApp;

use App\Enums\ApprovalStatus;
use App\Enums\TaskStatus;
use App\Exceptions\ApprovalException;
use App\Models\Approval;
use App\Models\Business;
use App\Models\Task;
use App\Models\User;
use App\Services\Executive\ApprovalService;
use App\Services\Executive\CeoBrain;
use App\Services\Executive\FollowUpDrafter;
use App\Services\Executive\PriorityEngine;
use App\Services\Executive\TaskService;
use App\Support\Actor;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Executes an Intent for an authenticated CEO and returns the WhatsApp reply.
 */
class CeoCommandHandler
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly PriorityEngine $priorities,
        private readonly CeoBrain $brain,
        private readonly ApprovalService $approvals,
        private readonly FollowUpDrafter $drafter,
    ) {}

    /**
     * @param  array{forwarded?: bool, raw?: string, button?: bool}  $context
     */
    public function handle(Business $business, User $ceo, Intent $intent, array $context = []): string
    {
        $actor = Actor::user($ceo, 'whatsapp');

        if ($intent->isPrivileged() && ($reason = $this->privilegedRefusal($context))) {
            return $reason;
        }

        try {
            return match ($intent->name) {
                Intent::TOP_PRIORITIES => $this->topPriorities($business),
                Intent::LIST_PENDING => $this->pending($business),
                Intent::MARK_DONE => $this->markDone($business, $intent, $actor, $context['raw'] ?? ''),
                Intent::SET_STATUS => $this->setStatus($business, $intent, $actor),
                Intent::ADD_TASK => $this->addTask($business, $intent, $actor),
                Intent::DRAFT_FOLLOWUP => $this->draftFollowUp($business, $intent, $actor),
                Intent::APPROVE => $this->decide($business, $ceo, $intent, true, $context),
                Intent::REJECT => $this->decide($business, $ceo, $intent, false, $context),
                Intent::MODIFY => $this->modify($business, $ceo, $intent),
                Intent::HELP => $this->help(),
                default => $this->brain->answer($business, $intent->value ?? '') ?? $this->help("Maaf, saya belum faham arahan itu.\n\n"),
            };
        } catch (ApprovalException $e) {
            return '⚠️ '.$e->getMessage();
        }
    }

    /** Forwarded or pasted content must never count as the CEO's authorisation. */
    private function privilegedRefusal(array $context): ?string
    {
        if ($context['forwarded'] ?? false) {
            return '🔒 Mesej yang di-forward tidak diterima sebagai kelulusan. Taip sendiri: APPROVE <kod>.';
        }

        $raw = trim($context['raw'] ?? '');
        if (! ($context['button'] ?? false) && (mb_strlen($raw) > 80 || str_contains($raw, "\n"))) {
            return '🔒 Untuk keselamatan, hantar arahan kelulusan sebagai mesej pendek sahaja, contohnya: APPROVE K7Q2.';
        }

        return null;
    }

    private function topPriorities(Business $business): string
    {
        $ranked = $this->priorities->rank($business, 3);

        if ($ranked->isEmpty()) {
            return "Tiada tugasan terbuka. 🎉\nBalas *tambah tugasan: ...* untuk tambah kerja baharu.";
        }

        $lines = ['*Keutamaan hari ini:*'];
        foreach ($ranked as $i => $row) {
            $task = $row['task'];
            $lines[] = ($i + 1).'. '.$task->title.' _('.$task->reference().')_';
            if ($row['reasons']) {
                $lines[] = '   ↳ '.implode(', ', $row['reasons']);
            }
        }

        $rec = $this->brain->recommendations($business)[0] ?? null;
        if ($rec) {
            $lines[] = '';
            $lines[] = '*Cadangan saya:* '.$rec['action'];
        }

        $withCustomer = $ranked->first(fn ($row) => $row['task']->contact_id !== null);
        if ($withCustomer) {
            $name = $withCustomer['task']->contact->name;
            $lines[] = '';
            $lines[] = "Mahu saya sediakan mesej follow-up? Balas: *sediakan follow-up {$name}*";
        }

        return implode("\n", $lines);
    }

    private function pending(Business $business): string
    {
        $open = $business->tasks()->open()->with('contact')->get();
        $approvals = $business->approvals()->where('status', ApprovalStatus::Pending->value)->where('expires_at', '>', now())->get();

        if ($open->isEmpty() && $approvals->isEmpty()) {
            return 'Tiada yang pending. Semua tugasan selesai. ✅';
        }

        $sorted = $open->sortBy(fn (Task $t) => [$t->isOverdue() ? 0 : 1, $t->deadline?->timestamp ?? PHP_INT_MAX])->values();

        $lines = ['*Pending ('.$open->count().' tugasan):*'];
        foreach ($sorted->take(10) as $task) {
            $flag = $task->isOverdue() ? '🔴' : ($task->status === TaskStatus::WaitingForCeo ? '🟠' : '⚪');
            $lines[] = "{$flag} {$task->title} _({$task->reference()} · {$task->status->label()})_";
        }
        if ($open->count() > 10) {
            $lines[] = '…dan '.($open->count() - 10).' lagi di dashboard.';
        }

        if ($approvals->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '*Menunggu kelulusan anda:*';
            foreach ($approvals as $a) {
                $lines[] = "• {$a->code} — {$a->title}";
            }
        }

        return implode("\n", $lines);
    }

    private function markDone(Business $business, Intent $intent, Actor $actor, string $raw): string
    {
        $task = $this->resolveTask($business, (string) $intent->target, $reply);
        if (! $task) {
            return $reply;
        }

        $this->tasks->changeStatus($task, TaskStatus::Completed, $actor, 'Dikemas kini oleh CEO melalui WhatsApp.', 'CEO mengesahkan melalui WhatsApp: "'.Str::limit($raw, 200).'"');

        return "✅ *{$task->title}* ({$task->reference()}) ditanda *selesai*.\nDashboard dan laporan petang sudah dikemas kini.";
    }

    private function setStatus(Business $business, Intent $intent, Actor $actor): string
    {
        $task = $this->resolveTask($business, (string) $intent->target, $reply);
        if (! $task) {
            return $reply;
        }

        $status = TaskStatus::from((string) $intent->value);
        $this->tasks->changeStatus($task, $status, $actor, 'Dikemas kini oleh CEO melalui WhatsApp.');

        return "👍 *{$task->title}* ({$task->reference()}) kini: *{$status->label()}*.";
    }

    private function addTask(Business $business, Intent $intent, Actor $actor): string
    {
        $title = Str::limit(trim((string) $intent->value), 200, '');
        [$task, $created] = $this->tasks->create($business, ['title' => $title, 'priority' => 'medium'], $actor);

        return $created
            ? "📝 Tugasan baharu {$task->reference()}: *{$task->title}*. Saya akan pantau sehingga selesai."
            : "Tugasan ini sudah wujud: {$task->reference()} *{$task->title}* ({$task->status->label()}).";
    }

    private function draftFollowUp(Business $business, Intent $intent, Actor $actor): string
    {
        $target = trim(preg_replace('/\bfollow[\s\-]?up\b/iu', '', (string) $intent->target) ?? '');
        $task = $this->resolveTask($business, $target, $reply, fn (Task $t) => $t->contact_id !== null);

        if (! $task) {
            // No task yet: look for the customer and open a follow-up task for them.
            $contacts = $this->matchContacts($business, $target);
            if ($contacts->count() !== 1) {
                return $contacts->isEmpty()
                    ? $reply
                    : "Ada beberapa pelanggan sepadan:\n".$contacts->map(fn ($c) => '• '.$c->displayName())->implode("\n")."\nSila nyatakan nama penuh.";
            }
            $contact = $contacts->first();
            [$task] = $this->tasks->create($business, [
                'title' => 'Follow-up '.$contact->displayName(),
                'contact_id' => $contact->id,
                'priority' => 'high',
            ], $actor);
        }

        $approval = $this->drafter->draftForTask($task, $actor);

        return "Baik CEO. Draf follow-up untuk *{$approval->recipient_name}* sedia untuk semakan (kod *{$approval->code}*).\n"
            .'Saya tidak akan hantar kepada pelanggan sebelum anda luluskan.';
    }

    private function decide(Business $business, User $ceo, Intent $intent, bool $approve, array $context): string
    {
        $approval = $this->resolveApproval($business, (string) $intent->target, $reply);
        if (! $approval) {
            return $reply;
        }

        // Which version did the CEO actually see? Buttons carry it; typed codes use the last one sent.
        $seenVersion = $context['button_version'] ?? $approval->notified_version;

        if ($approve) {
            if ($seenVersion === null || (int) $seenVersion !== $approval->version) {
                $this->approvals->notifyCeo($approval);

                return "Draf {$approval->code} telah berubah sejak anda lihat. Saya hantar versi terkini — sila semak dan balas APPROVE {$approval->code} sekali lagi.";
            }

            $approval = $this->approvals->approve($approval, $ceo, (int) $seenVersion, 'whatsapp');

            return $approval->status === ApprovalStatus::Acknowledged
                ? "📌 Keputusan {$approval->code} direkodkan. Ini tindakan Level 3 — AI tidak melaksanakannya; sila teruskan sendiri."
                : "✅ Diluluskan ({$approval->code}). Saya laksanakan sekarang dan akan maklumkan jika ada masalah.";
        }

        $this->approvals->reject($approval, $ceo, 'whatsapp');

        return "❌ Ditolak ({$approval->code}). Tiada apa yang dihantar.";
    }

    private function modify(Business $business, User $ceo, Intent $intent): string
    {
        $approval = $this->resolveApproval($business, (string) $intent->target, $reply);
        if (! $approval) {
            return $reply;
        }

        if (! array_key_exists('message', $approval->payload)) {
            return 'Tindakan ini tiada teks untuk diubah. Sila ubah di Decision Room.';
        }

        $text = Str::limit(trim((string) $intent->value), 1500, '');
        $approval = $this->approvals->modify($approval, $ceo, ['message' => $text], $text, 'whatsapp');
        $this->approvals->notifyCeo($approval);

        return "✏️ Draf {$approval->code} dikemas kini (versi {$approval->version}). Semak pratonton di atas dan balas APPROVE {$approval->code}.";
    }

    private function help(string $prefix = ''): string
    {
        return $prefix."*Contoh arahan:*\n"
            ."• Apa kerja penting hari ni?\n"
            ."• Apa yang pending?\n"
            ."• Fatihah dah settle\n"
            ."• Mula T-12 / T-12 tunggu customer\n"
            ."• Tambah tugasan: hubungi supplier\n"
            ."• Sediakan follow-up Derol\n"
            ."• APPROVE K7Q2 / REJECT K7Q2\n"
            .'• UBAH K7Q2: <teks baharu>';
    }

    /** @param  callable(Task): bool|null  $filter */
    private function resolveTask(Business $business, string $query, ?string &$reply, ?callable $filter = null): ?Task
    {
        $matches = $this->tasks->search($business, $query);
        if ($filter) {
            $matches = $matches->filter($filter)->values();
        }

        if ($matches->count() === 1) {
            return $matches->first();
        }

        $reply = $matches->isEmpty()
            ? "Saya tidak jumpa tugasan terbuka untuk \"{$query}\". Balas *pending* untuk senarai."
            : "Ada {$matches->count()} tugasan sepadan:\n".$matches->take(5)->map(fn (Task $t) => "• {$t->reference()} {$t->title}")->implode("\n")
                ."\nBalas dengan rujukan, contoh: *done {$matches->first()->reference()}*";

        return null;
    }

    private function resolveApproval(Business $business, string $target, ?string &$reply): ?Approval
    {
        $pending = $business->approvals()->where('status', ApprovalStatus::Pending->value);

        $codeReply = null;
        if (preg_match('/^[A-HJ-NP-Z2-9]{4}$/i', $target)) {
            $approval = (clone $pending)->where('code', strtoupper($target))->first();
            if ($approval) {
                return $approval;
            }
            $any = $business->approvals()->where('code', strtoupper($target))->first();
            $codeReply = $any
                ? "Permintaan {$any->code} sudah {$any->status->value}; kelulusan tidak boleh diguna semula."
                : "Tiada permintaan dengan kod {$target}.";
        }

        $words = collect(preg_split('/\s+/', mb_strtolower(preg_replace('/\bfollow[\s\-]?up\b/iu', '', $target) ?? '')))
            ->filter(fn ($w) => mb_strlen($w) >= 3);

        $matches = $pending->get()->filter(function (Approval $a) use ($words) {
            $hay = mb_strtolower($a->title.' '.$a->recipient_name);

            return $words->isNotEmpty() && $words->every(fn ($w) => str_contains($hay, $w));
        })->values();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        $reply = $matches->isEmpty()
            ? ($codeReply ?? "Tiada permintaan menunggu kelulusan untuk \"{$target}\".")
            : "Ada beberapa permintaan sepadan:\n".$matches->map(fn ($a) => "• {$a->code} — {$a->title}")->implode("\n")."\nBalas dengan kod, contoh: APPROVE {$matches->first()->code}";

        return null;
    }

    private function matchContacts(Business $business, string $query): Collection
    {
        $words = collect(preg_split('/\s+/', mb_strtolower($query)))->filter(fn ($w) => mb_strlen($w) >= 3);
        if ($words->isEmpty()) {
            return collect();
        }

        return $business->contacts()->get()->filter(function ($c) use ($words) {
            $hay = mb_strtolower($c->name.' '.$c->company);

            return $words->contains(fn ($w) => str_contains($hay, $w));
        })->values();
    }
}

<?php

namespace App\Services\Executive;

use App\Models\Business;
use App\Models\Task;

/**
 * Builds briefing content for each channel from the same live snapshot,
 * so email, WhatsApp and the dashboard always agree.
 */
class ReportBuilder
{
    public function __construct(
        private readonly CeoBrain $brain,
        private readonly PriorityEngine $priorities,
    ) {}

    /** @return array{subject: string, html: string} */
    public function email(Business $business, string $moment): array
    {
        $snapshot = $this->brain->snapshot($business);
        $date = $business->localNow()->translatedFormat('l, j F Y');

        $subject = $moment === 'evening'
            ? "Laporan Petang · {$business->name} · {$date}"
            : "Executive Briefing Pagi · {$business->name} · {$date}";

        $html = view('emails.executive-report', [
            'business' => $business,
            'moment' => $moment,
            'date' => $date,
            'summary' => $this->brain->executiveSummary($business, $moment, $snapshot),
            'snapshot' => $snapshot,
            'recommendations' => $this->brain->recommendations($business),
            'dashboardUrl' => url('/dashboard'),
        ])->render();

        return ['subject' => $subject, 'html' => $html];
    }

    public function whatsappPriorities(Business $business): string
    {
        $ranked = $this->priorities->rank($business, 3);
        $name = $business->primaryCeo()?->name ?? 'CEO';

        if ($ranked->isEmpty()) {
            return "*Selamat pagi, {$name}.*\nTiada tugasan terbuka hari ini. Balas *tambah tugasan: ...* untuk mula.";
        }

        $lines = ["*Selamat pagi, {$name}.*", 'Berikut keutamaan hari ini:'];
        foreach ($ranked as $i => $row) {
            $lines[] = ($i + 1).'. '.$row['task']->title.' _('.$row['task']->reference().')_';
        }

        $rec = $this->brain->recommendations($business)[0] ?? null;
        if ($rec) {
            $lines[] = '';
            $lines[] = '*Cadangan saya:* '.$rec['action'];
        }

        $lines[] = '';
        $lines[] = 'Tugasan mana anda mahu mula dahulu? Balas contohnya: *mula '.$ranked->first()['task']->reference().'*';

        return implode("\n", $lines);
    }

    public function whatsappProgress(Business $business): string
    {
        $open = $business->tasks()->open()->get()
            ->sortByDesc(fn (Task $t) => $this->priorities->score($t))
            ->take(5);

        if ($open->isEmpty()) {
            return '*Semakan petang:* semua tugasan selesai. Syabas! ✅';
        }

        $lines = ['*Semakan kemajuan (2:00 petang)*', 'Apa status tugasan ini?'];
        foreach ($open as $task) {
            $lines[] = '• '.$task->reference().' '.$task->title.' — _'.$task->status->label().'_';
        }
        $lines[] = '';
        $lines[] = 'Balas contohnya: *done '.$open->first()->reference().'* atau *'.$open->first()->reference().' tunggu customer*.';

        return implode("\n", $lines);
    }
}

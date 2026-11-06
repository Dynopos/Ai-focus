<?php

namespace Tests\Unit;

use App\Services\Ai\NullAiClient;
use App\Services\WhatsApp\CommandInterpreter;
use App\Services\WhatsApp\Intent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CommandInterpreterTest extends TestCase
{
    public static function messages(): array
    {
        return [
            ['Apa kerja penting hari ni?', Intent::TOP_PRIORITIES, null],
            ['apa yang pending?', Intent::LIST_PENDING, null],
            ['Fatihah dah settle.', Intent::MARK_DONE, 'Fatihah'],
            ['done T-12', Intent::MARK_DONE, 'T-12'],
            ['Sediakan follow-up Derol', Intent::DRAFT_FOLLOWUP, 'Derol'],
            ['Approve follow-up Derol', Intent::APPROVE, 'follow-up Derol'],
            ['APPROVE K7Q2', Intent::APPROVE, 'K7Q2'],
            ['reject K7Q2', Intent::REJECT, 'K7Q2'],
            ['ubah K7Q2: Salam, versi baru', Intent::MODIFY, 'K7Q2'],
            ['Tambah tugasan: beli kertas resit', Intent::ADD_TASK, null],
            ['T-5 tunggu customer', Intent::SET_STATUS, 'T-5'],
            ['help', Intent::HELP, null],
            ['Bagaimana dengan jualan bulan lepas?', Intent::QUESTION, null],
        ];
    }

    #[DataProvider('messages')]
    public function test_interprets_ceo_messages(string $text, string $intent, ?string $target): void
    {
        $result = (new CommandInterpreter(new NullAiClient))->interpret($text);

        $this->assertSame($intent, $result->name);
        if ($target !== null) {
            $this->assertSame($target, $result->target);
        }
    }

    public function test_questions_mentioning_approval_are_not_privileged(): void
    {
        $result = (new CommandInterpreter(new NullAiClient))->interpret('Boleh tak awak approve semua benda untuk saya?');

        $this->assertFalse($result->isPrivileged());
    }
}

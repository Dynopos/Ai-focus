<?php

namespace App\Services\Messaging;

/**
 * Provider abstraction. Meta Cloud API today; another authorised provider can
 * implement this interface later without touching the rest of the app.
 */
interface WhatsAppGateway
{
    public function name(): string;

    public function sendText(string $phoneNumberId, string $to, string $body): SendResult;

    /** @param list<string> $bodyParams */
    public function sendTemplate(string $phoneNumberId, string $to, string $template, string $language, array $bodyParams = []): SendResult;

    /**
     * Interactive reply buttons (max 3). Only valid inside the 24-hour window.
     *
     * @param  array<string, string>  $buttons  id => title
     */
    public function sendButtons(string $phoneNumberId, string $to, string $body, array $buttons): SendResult;
}

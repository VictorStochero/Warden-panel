<?php

namespace App\Alerting;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;
use VictorStochero\Warden\Contracts\AlertChannel;
use VictorStochero\Warden\Models\AlertSetting;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Models\Setting;

/**
 * Panel alert channel: delivers the DIDACTIC message (exception + the app
 * frame to fix + a deep link) over a chat webhook and/or e-mail. Replaces the
 * package's terse MailAlertChannel. Best-effort — never throws into evaluate().
 */
class PanelDidacticChannel implements AlertChannel
{
    private const SEVERITY_RANK = ['info' => 0, 'warning' => 1, 'critical' => 2];

    public function __construct(protected AlertComposer $composer) {}

    public function send(Incident $incident, string $event): void
    {
        try {
            $msg = $this->composer->forIncident($incident);
            $text = strtoupper($event)."\n".$this->composer->text($msg);

            $this->toWebhook($text);
            $this->emailRaw($text, "[Warden] {$event}: {$msg['title']} · {$msg['project']}", $incident->severity);
        } catch (Throwable) {
            // Alerting is best-effort.
        }
    }

    /** Deliver an arbitrary didactic message (e.g. a digest) over the same transports. */
    public function deliver(string $text, string $subject, string $severity = 'info'): void
    {
        try {
            $this->toWebhook($text);
            $this->emailRaw($text, $subject, $severity);
        } catch (Throwable) {
            // best-effort
        }
    }

    private function toWebhook(string $text): void
    {
        $url = trim((string) Setting::read('panel.alert_webhook', ''));
        if ($url === '') {
            return;
        }

        try {
            // Discord wants `content`; Slack-compatible endpoints want `text`.
            $payload = str_contains($url, 'discord') ? ['content' => $text] : ['text' => $text];
            Http::timeout(5)->post($url, $payload);
        } catch (Throwable) {
            // ignore
        }
    }

    private function emailRaw(string $text, string $subject, string $severity): void
    {
        $settings = AlertSetting::current();
        $recipients = $settings->recipients ?? [];

        if (! $settings->email_enabled || $recipients === []) {
            return;
        }
        if (! $this->severityAllowed($severity, $settings->min_severity)) {
            return;
        }

        try {
            Mail::raw($text, function (Message $message) use ($recipients, $subject): void {
                $message->to($recipients)->subject($subject);
            });
        } catch (Throwable) {
            // ignore
        }
    }

    private function severityAllowed(string $severity, string $minimum): bool
    {
        return (self::SEVERITY_RANK[$severity] ?? 0) >= (self::SEVERITY_RANK[$minimum] ?? 1);
    }
}

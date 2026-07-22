<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * De Microsoft Graph-mailtransport: haalt een OAuth-token op (client credentials)
 * en verstuurt via /users/{mailbox}/sendMail — inclusief een GEDEELDE mailbox als
 * verzendadres, met cc en een PDF-bijlage.
 */
class MicrosoftGraphMailTest extends TestCase
{
    public function test_graph_transport_verstuurt_via_de_gedeelde_mailbox(): void
    {
        Cache::flush();
        config([
            'mail.default' => 'graph',
            'mail.mailers.graph' => [
                'transport' => 'microsoft-graph',
                'tenant' => 'tenant-123',
                'client_id' => 'client-abc',
                'client_secret' => 'geheim',
                'from' => 'examencommissie@iuasr.nl', // gedeelde mailbox
            ],
        ]);

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok-xyz', 'expires_in' => 3600]),
            'graph.microsoft.com/*' => Http::response('', 202),
        ]);

        Mail::raw('Hallo wereld', function ($m) {
            $m->from('noreply@iuasr.nl', 'IUASR')
                ->to('student@example.test')
                ->cc('cc@example.test')
                ->subject('Test onderwerp')
                ->attachData('PDFDATA', 'bijlage.pdf', ['mime' => 'application/pdf']);
        });

        // 1) Token opgehaald bij de juiste tenant.
        Http::assertSent(fn ($req) => str_contains($req->url(), 'login.microsoftonline.com')
            && str_contains($req->url(), 'tenant-123/oauth2/v2.0/token'));

        // 2) Verstuurd via de GEDEELDE mailbox, met onderwerp, ontvangers, cc en bijlage.
        Http::assertSent(function ($req) {
            if (! str_contains($req->url(), 'graph.microsoft.com/v1.0/users/examencommissie%40iuasr.nl/sendMail')) {
                return false;
            }
            $b = $req->data();

            return ($b['message']['subject'] ?? null) === 'Test onderwerp'
                && ($b['message']['toRecipients'][0]['emailAddress']['address'] ?? null) === 'student@example.test'
                && ($b['message']['ccRecipients'][0]['emailAddress']['address'] ?? null) === 'cc@example.test'
                && ($b['message']['attachments'][0]['name'] ?? null) === 'bijlage.pdf'
                && base64_decode($b['message']['attachments'][0]['contentBytes'] ?? '') === 'PDFDATA';
        });
    }
}

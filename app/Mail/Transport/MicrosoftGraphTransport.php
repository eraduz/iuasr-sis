<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Verstuurt e-mail via de Microsoft Graph API (Microsoft 365), met OAuth2
 * client-credentials — geen basic auth, past bij Entra ID. De app-registratie
 * heeft de application-permission `Mail.Send`; bescherm die met een Application
 * Access Policy zodat de app alléén als de verzendmailbox mag versturen.
 *
 * Instellen via `config/mail.php` (mailer 'graph') met de env-variabelen
 * MS_GRAPH_TENANT_ID, MS_GRAPH_CLIENT_ID, MS_GRAPH_CLIENT_SECRET en MS_GRAPH_FROM.
 */
class MicrosoftGraphTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly ?string $standaardAfzender = null,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $afzender = $this->afzender($email);

        $response = Http::withToken($this->token())
            ->acceptJson()
            ->post('https://graph.microsoft.com/v1.0/users/'.rawurlencode($afzender).'/sendMail', [
                'message' => $this->bouwBericht($email),
                'saveToSentItems' => true,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Microsoft Graph sendMail mislukt (HTTP '.$response->status().'): '.$response->body());
        }
    }

    /** Access-token via client-credentials; gecacht tot kort vóór het verloopt. */
    private function token(): string
    {
        return Cache::remember('msgraph:token:'.$this->clientId, now()->addMinutes(55), function () {
            $response = Http::asForm()->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]);

            if ($response->failed()) {
                throw new \RuntimeException('Microsoft Graph token ophalen mislukt (HTTP '.$response->status().'): '.$response->body());
            }

            return (string) $response->json('access_token');
        });
    }

    /**
     * De verzendmailbox (de `{id}` in /users/{id}/sendMail). MS_GRAPH_FROM heeft
     * voorrang: is de app afgeschermd tot één (gedeelde) mailbox — bijvoorbeeld de
     * gedeelde examencommissie-mailbox — dan MOET altijd via die mailbox worden
     * verstuurd. Zonder MS_GRAPH_FROM valt het terug op de From van de mail.
     */
    private function afzender(Email $email): string
    {
        if ($this->standaardAfzender) {
            return $this->standaardAfzender;
        }
        $from = $email->getFrom();
        if ($from !== []) {
            return $from[0]->getAddress();
        }

        throw new \RuntimeException('Microsoft Graph: geen From op de mail en geen MS_GRAPH_FROM ingesteld.');
    }

    /** @return array<string,mixed> */
    private function bouwBericht(Email $email): array
    {
        $bericht = [
            'subject' => $email->getSubject() ?? '',
            'body' => [
                'contentType' => 'HTML',
                'content' => $email->getHtmlBody() ?? $email->getTextBody() ?? '',
            ],
            'toRecipients' => $this->ontvangers($email->getTo()),
        ];

        if ($email->getCc() !== []) {
            $bericht['ccRecipients'] = $this->ontvangers($email->getCc());
        }
        if ($email->getBcc() !== []) {
            $bericht['bccRecipients'] = $this->ontvangers($email->getBcc());
        }
        if ($email->getReplyTo() !== []) {
            $bericht['replyTo'] = $this->ontvangers($email->getReplyTo());
        }

        $bijlagen = [];
        foreach ($email->getAttachments() as $attachment) {
            $bijlagen[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $attachment->getFilename() ?? 'bijlage',
                'contentType' => $attachment->getMediaType().'/'.$attachment->getMediaSubtype(),
                'contentBytes' => base64_encode($attachment->getBody()),
            ];
        }
        if ($bijlagen !== []) {
            $bericht['attachments'] = $bijlagen;
        }

        return $bericht;
    }

    /**
     * @param  array<int, Address>  $adressen
     * @return array<int, array{emailAddress: array{address: string, name?: string}}>
     */
    private function ontvangers(array $adressen): array
    {
        return array_map(function (Address $a) {
            $mail = ['address' => $a->getAddress()];
            if ($a->getName() !== '') {
                $mail['name'] = $a->getName();
            }

            return ['emailAddress' => $mail];
        }, $adressen);
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }
}

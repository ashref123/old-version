<?php

namespace App\Services\Notifications;

use App\Models\Admin\Driver;
use App\Models\Request\Request as RequestModel;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RideAcceptedSmsService
{
    protected const DEFAULT_TEMPLATE = 'Hi {customer_name}, your ride #{request_number} has been accepted by {driver_name} ({driver_mobile}). Please contact the driver if needed.';

    /**
     * Send the ride-accepted SMS to the rider for web bookings.
     *
     * This is best-effort only. Any SMS failure is logged and never blocks
     * the ride acceptance flow.
     */
    public function send(RequestModel $request, Driver $driver): bool
    {
        if (!filter_var((string) get_sms_settings('enable_ride_accept_sms'), FILTER_VALIDATE_BOOLEAN)) {
            Log::info('Ride accepted SMS skipped: service disabled', [
                'request_id' => $request->id,
                'request_number' => $request->request_number,
            ]);

            return false;
        }

        if (!$this->shouldNotify($request)) {
            return false;
        }

        $recipient = $this->resolveRecipient($request);
        if ($recipient === null) {
            Log::info('Ride accepted SMS skipped: no valid recipient number', [
                'request_id' => $request->id,
                'request_number' => $request->request_number,
            ]);
            return false;
        }

        $message = $this->buildMessage($request, $driver);

        try {
            $gateway = $this->resolveActiveSmsGateway();

            if ($gateway === null) {
                Log::info('Ride accepted SMS skipped: no active SMS gateway configured', [
                    'request_id' => $request->id,
                    'request_number' => $request->request_number,
                ]);

                return false;
            }

            $this->sendViaActiveGateway($gateway, $recipient, $message);

            Log::info('Ride accepted SMS queued', [
                'request_id' => $request->id,
                'request_number' => $request->request_number,
                'recipient' => $this->maskMobile($recipient),
                'driver_id' => $driver->id,
                'gateway' => $gateway ?: 'sms-library',
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Ride accepted SMS failed', [
                'request_id' => $request->id,
                'request_number' => $request->request_number,
                'recipient' => $this->maskMobile($recipient),
                'driver_id' => $driver->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Keep this limited to booking sources created from the web side.
     */
    protected function shouldNotify(RequestModel $request): bool
    {
        return (bool) (
            $request->if_dispatch ||
            $request->web_booking ||
            $request->booked_by
        );
    }

    protected function resolveRecipient(RequestModel $request): ?string
    {
        if ((int) ($request->book_for_other ?? 0) === 1 && !empty($request->book_for_other_contact)) {
            return $this->normalizeMobile((string) $request->book_for_other_contact, null);
        }

        $user = $request->userDetail;
        if (!$user instanceof User) {
            return null;
        }

        return $this->normalizeMobile(
            (string) $user->getRawOriginal('mobile'),
            (string) optional($user->countryDetail)->dial_code
        );
    }

    protected function buildMessage(RequestModel $request, Driver $driver): string
    {
        $requestNumber = trim((string) $request->request_number);
        $driverName = trim((string) ($driver->name ?: 'Driver'));
        $driverMobile = $this->resolveDriverMobile($driver);
        $customerName = trim((string) ($request->book_for_other_contact_name ?: $request->userDetail?->name ?: 'Customer'));
        $template = trim((string) get_sms_settings('ride_accept_sms_template'));

        if ($template === '') {
            $template = self::DEFAULT_TEMPLATE;
        }

        return $this->renderTemplate($template, [
            '{customer_name}' => $customerName,
            '{request_number}' => $requestNumber,
            '{driver_name}' => $driverName,
            '{driver_mobile}' => $driverMobile ?? '',
        ]);
    }

    protected function resolveDriverMobile(Driver $driver): ?string
    {
        $user = $driver->user;
        if (!$user instanceof User) {
            return null;
        }

        return $this->normalizeMobile(
            (string) $user->getRawOriginal('mobile'),
            (string) optional($user->countryDetail)->dial_code
        );
    }

    protected function normalizeMobile(string $mobile, ?string $countryCode = null): ?string
    {
        $mobile = preg_replace('/\D+/', '', trim($mobile));
        $countryCode = $countryCode !== null ? preg_replace('/\D+/', '', trim($countryCode)) : null;

        if ($mobile === '') {
            return null;
        }

        if ($countryCode !== null && $countryCode !== '' && !str_starts_with($mobile, $countryCode)) {
            $mobile = $countryCode . $mobile;
        }

        return $mobile;
    }

    protected function renderTemplate(string $template, array $replacements): string
    {
        return trim(strtr($template, $replacements));
    }

    protected function sendViaTwilio(string $recipient, string $message): void
    {
        $mobile = preg_replace('/\D+/', '', $recipient);
        $twilioSid = trim((string) get_sms_settings('twilio_sid'));
        $twilioToken = trim((string) get_sms_settings('twilio_token'));
        $twilioPhoneNumber = trim((string) get_sms_settings('twilio_mobile_number'));

        if ($twilioSid === '' || $twilioToken === '' || $twilioPhoneNumber === '') {
            throw new \RuntimeException('Twilio SMS settings are incomplete.');
        }

        $to = str_starts_with($recipient, '+') ? $recipient : '+' . $mobile;

        $response = Http::asForm()
            ->withBasicAuth($twilioSid, $twilioToken)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$twilioSid}/Messages.json", [
                'From' => $twilioPhoneNumber,
                'To' => $to,
                'Body' => $message,
            ]);

        if (!$response->successful()) {
            Log::warning('Ride accepted Twilio SMS failed', [
                'recipient' => $this->maskMobile($mobile),
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Twilio SMS sending failed.');
        }
    }

    protected function sendViaActiveGateway(string $gateway, string $recipient, string $message): void
    {
        switch ($gateway) {
            case 'enable_twilio':
                $this->sendViaTwilio($recipient, $message);
                return;
            case 'enable_sms_india_hub':
                $this->sendViaSmsIndiaHub($recipient, $message);
                return;
            case 'enable_sparrow':
                $this->sendViaSparrow($recipient, $message);
                return;
            case 'enable_kudi_sms_api_key':
                $this->sendViaKudi($recipient, $message);
                return;
            case 'enable_sms_ala':
                $this->sendViaSmsAla($recipient, $message);
                return;
            case 'enable_msg91':
                $this->sendViaMsg91($recipient, $message);
                return;
            case 'enable_infobip':
                $this->sendViaInfobip($recipient, $message);
                return;
            case 'enable_msgowl':
                $this->sendViaMsgowl($recipient, $message);
                return;
            default:
                throw new \RuntimeException("Unsupported SMS gateway: {$gateway}");
        }
    }

    protected function resolveActiveSmsGateway(): ?string
    {
        $gateways = [
            'enable_twilio',
            'enable_sms_ala',
            'enable_msg91',
            'enable_sparrow',
            'enable_sms_india_hub',
            'enable_kudi_sms_api_key',
            'enable_infobip',
            'enable_msgowl',
        ];

        foreach ($gateways as $gateway) {
            if (filter_var((string) get_sms_settings($gateway), FILTER_VALIDATE_BOOLEAN)) {
                return $gateway;
            }
        }

        return null;
    }

    protected function sendViaSmsIndiaHub(string $recipient, string $message): void
    {
        $mobile = $this->normalizeRecipientDigits($recipient);
        $apiKey = trim((string) get_sms_settings('sms_india_hub_api_key'));
        $sid = trim((string) get_sms_settings('sms_india_hub_sid'));

        if ($apiKey === '' || $sid === '') {
            throw new \RuntimeException('SMS India Hub settings are incomplete.');
        }

        $response = Http::get('http://cloud.smsindiahub.in/vendorsms/pushsms.aspx', [
            'APIKey' => $apiKey,
            'msisdn' => $mobile,
            'sid' => $sid,
            'msg' => $message,
            'fl' => '0',
            'gwid' => '2',
        ]);

        $this->ensureSuccess($response->successful(), 'SMS India Hub', $recipient, $response->status(), $response->body());
    }

    protected function sendViaSparrow(string $recipient, string $message): void
    {
        $mobile = $this->normalizeRecipientDigits($recipient);
        $token = trim((string) get_sms_settings('sparrow_sender_id'));
        $id = trim((string) get_sms_settings('sparrow_token'));

        if ($token === '' || $id === '') {
            throw new \RuntimeException('Sparrow settings are incomplete.');
        }

        $response = Http::asForm()->post('http://api.sparrowsms.com/v2/sms/', [
            'token' => $token . '.M4Wm',
            'from' => $id,
            'to' => $mobile,
            'text' => $message,
        ]);

        $payload = $response->json();
        $ok = $response->successful() && (int) data_get($payload, 'status', 0) === 200;
        $this->ensureSuccess($ok, 'Sparrow', $recipient, $response->status(), $response->body());
    }

    protected function sendViaKudi(string $recipient, string $message): void
    {
        $mobile = $this->normalizeRecipientDigits($recipient);
        $senderId = trim((string) get_sms_settings('kudi_sms_sender_id'));
        $apiKey = trim((string) get_sms_settings('kudi_sms_api_key'));

        if ($senderId === '' || $apiKey === '') {
            throw new \RuntimeException('Kudi SMS settings are incomplete.');
        }

        $response = Http::asJson()->post('https://my.kudisms.net/api/corporate', [
            'token' => $apiKey,
            'senderID' => $senderId,
            'Body' => $message,
            'recipients' => $mobile,
            'message' => $message,
        ]);

        $payload = $response->json();
        $ok = $response->successful() && (data_get($payload, 'status') !== 'error');
        $this->ensureSuccess($ok, 'Kudi SMS', $recipient, $response->status(), $response->body());
    }

    protected function sendViaSmsAla(string $recipient, string $message): void
    {
        $mobile = $this->normalizeRecipientDigits($recipient);
        $apiKey = trim((string) get_sms_settings('smsala_api_key'));
        $apiPassword = trim((string) get_sms_settings('smsala_api_password'));
        $senderId = trim((string) get_sms_settings('smsala_sender_id'));

        if ($apiKey === '' || $apiPassword === '' || $senderId === '') {
            throw new \RuntimeException('SMSAla settings are incomplete.');
        }

        $response = Http::get('http://api.smsala.com/api/SendSMS', [
            'api_id' => $apiKey,
            'api_password' => $apiPassword,
            'sms_type' => 'P',
            'encoding' => 'T',
            'sender_id' => $senderId,
            'phonenumber' => $mobile,
            'textmessage' => $message,
        ]);

        $this->ensureSuccess($response->successful(), 'SMSAla', $recipient, $response->status(), $response->body());
    }

    protected function sendViaMsg91(string $recipient, string $message): void
    {
        $mobile = $this->normalizeRecipientDigits($recipient);
        $authKey = trim((string) get_sms_settings('msg91_auth_key'));
        $senderId = trim((string) (config('sms.providers.msg91.sender_id') ?: 'WE3'));

        if ($authKey === '' || $senderId === '') {
            throw new \RuntimeException('MSG91 settings are incomplete.');
        }

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'authkey' => $authKey,
            'content-type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post('https://control.msg91.com/api/v2/sendsms', [
            'mobiles' => $mobile,
            'message' => $message,
            'sender' => $senderId,
            'route' => '4',
            'country' => '0',
        ]);

        $this->ensureSuccess($response->successful(), 'MSG91', $recipient, $response->status(), $response->body());
    }

    protected function sendViaInfobip(string $recipient, string $message): void
    {
        $mobile = '+' . $this->normalizeRecipientDigits($recipient);
        $baseUrl = rtrim((string) get_sms_settings('infobip_base_url'), '/');
        $apiKey = trim((string) get_sms_settings('infobip_api_key'));

        if ($baseUrl === '' || $apiKey === '') {
            throw new \RuntimeException('Infobip settings are incomplete.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'App ' . $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($baseUrl . '/sms/2/text/advanced', [
            'messages' => [
                [
                    'destinations' => [
                        ['to' => $mobile],
                    ],
                    'text' => $message,
                ],
            ],
        ]);

        $this->ensureSuccess($response->successful(), 'Infobip', $recipient, $response->status(), $response->body());
    }

    protected function sendViaMsgowl(string $recipient, string $message): void
    {
        $mobile = $this->normalizeRecipientDigits($recipient);
        $apiUrl = trim((string) get_sms_settings('msgowl_api_url'));
        $apiKey = trim((string) get_sms_settings('msgowl_api_key'));
        $sender = trim((string) get_sms_settings('msgowl_sender_id'));

        if ($apiUrl === '' || $apiKey === '') {
            throw new \RuntimeException('Msgowl settings are incomplete.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'AccessKey ' . $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($apiUrl, [
            'recipients' => $mobile,
            'sender_id' => $sender,
            'body' => $message,
        ]);

        $this->ensureSuccess($response->successful(), 'Msgowl', $recipient, $response->status(), $response->body());
    }

    protected function normalizeRecipientDigits(string $recipient): string
    {
        return preg_replace('/\D+/', '', trim($recipient));
    }

    protected function ensureSuccess(bool $ok, string $gatewayName, string $recipient, int $status, string $body): void
    {
        if ($ok) {
            return;
        }

        Log::warning("Ride accepted {$gatewayName} SMS failed", [
            'recipient' => $this->maskMobile($recipient),
            'status' => $status,
            'body' => $body,
        ]);

        throw new \RuntimeException("{$gatewayName} SMS sending failed.");
    }

    protected function maskMobile(string $mobile): string
    {
        $mobile = trim($mobile);
        if ($mobile === '') {
            return '';
        }

        return strlen($mobile) <= 4
            ? '****'
            : substr($mobile, 0, 2) . str_repeat('*', max(0, strlen($mobile) - 4)) . substr($mobile, -2);
    }
}

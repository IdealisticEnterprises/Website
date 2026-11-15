<?php
require '/var/www/.structure/library/idealistic_office/init.php';

use GuzzleHttp\Psr7\Request;
use Paddle\SDK\Notifications\Secret;
use Paddle\SDK\Notifications\Verifier;

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_PADDLE_SIGNATURE'] ?? '';
$webhookSecret = get_keys_from_file("paddle_webhook_secret", 1);

if ($webhookSecret === null) {
    http_response_code(500);
    return;
}

$request = new Request('POST', '/', ['Paddle-Signature' => $signature], $payload);
$verifier = new Verifier();

try {
    $isValid = $verifier->verify($request, new Secret($webhookSecret[0]));

    if (!$isValid) {
        http_response_code(400);
        return;
    }
    $event = json_decode($payload);

    if (!isset($event->event_type)) {
        http_response_code(400);
        return;
    }
    switch ($event->event_type) {
        case 'subscription.created':
            $customerId = $event->data->customer_id ?? null;

            if ($customerId !== null) {
                $paddleApi = new IdealisticPaddleApi(false);
                $customer = $paddleApi->getCustomerById($customerId);

                if ($customer !== null) {
                    IdealisticOfficeTeamInitiator::createAccount(
                        $customer->email
                    );
                    IdealisticOfficeEmailer::send(
                        "0-paddleIdealisticSubscriptionCreated",
                        $customer->email,
                        array(
                            "link" => IdealisticOfficeTeamInitiator::createSignInLink($customer->email)
                                ?? IdealisticOfficeVariable::COMPANY_WEBSITE_URL
                        ),
                        false
                    );
                }
            }
            break;
        default:
            break;
    }
    http_response_code(200);
} catch (Throwable $e) {
    http_response_code(500);
}
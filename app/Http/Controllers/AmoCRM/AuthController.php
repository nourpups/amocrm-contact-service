<?php

declare(strict_types=1);

namespace App\Http\Controllers\AmoCRM;

use AmoCRM\Client\AmoCRMApiClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class AuthController extends Controller
{
    public function auth(): RedirectResponse
    {
        $apiClient = new AmoCRMApiClient(
            config('amocrm.client_id'),
            config('amocrm.client_secret'),
            config('amocrm.client_redirect_uri'),
        );

        $apiClient->setAccountBaseDomain(config('amocrm.account_domain'));

        $token = $apiClient->getOAuthClient()->getAccessTokenByCode(request('code'));
        session(['token' => $token]);

        return to_route('contacts.create')->with('success', 'Фсо отлично, го тестить');
    }
}

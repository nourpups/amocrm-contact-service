<?php

namespace App\Services;

use AmoCRM\Client\AmoCRMApiClient;
use League\OAuth2\Client\Token\AccessToken;

class AmoCRM
{
    public AmoCRMApiClient $apiClient;

    public function __construct() {
        $apiClient = new AmoCRMApiClient(
            config('amocrm.client_id'),
            config('amocrm.client_secret'),
            config('amocrm.client_redirect_uri'),
        );

        $apiClient->setAccountBaseDomain(config('amocrm.account_domain'));

        $apiClient->setAccessToken(session('token'));

        $this->apiClient = $apiClient;
    }
}

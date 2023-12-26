<?php

use App\Http\Controllers\ContactController;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Route;

Route::get('/auth', function () {
    $apiClient = new \AmoCRM\Client\AmoCRMApiClient(
      config('amocrm.client_id'),
      config('amocrm.client_secret'),
      config('amocrm.client_redirect_uri'),
    );

    $apiClient->setAccountBaseDomain(config('amocrm.account_domain'));

    $token = $apiClient->getOAuthClient()->getAccessTokenByCode(request('code'));
    session(['token' => $token]);

    return to_route('contacts.create')->with('success', 'Фсо отлично, го тестить');
});

Route::get('carbon', function () {

       $createdAt = Carbon::createFromTimestamp(now()->getTimestamp(), 'Asia/Tashkent');
        $workingHoursStart = '9:00';
        $workingHoursEnd = '18:00';

        $startTime = Carbon::parse($workingHoursStart);
        $endTime = Carbon::parse($workingHoursEnd);

        if($createdAt->greaterThan($endTime)) {
            $createdAt->addWeekday();
        }
        $createdAt->setTime($startTime->hour, $startTime->minute);

        dd($createdAt->addWeekDays(4)->getTimestamp());
});

Route::redirect('/', 'contacts/create');
Route::get('/contacts/create', [ContactController::class, 'create'])->name('contacts.create');
Route::post('/contacts/create', [ContactController::class, 'store'])->name('contacts.store');

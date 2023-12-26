<?php

namespace App\Http\Controllers;

use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMMissedTokenException;
use AmoCRM\Exceptions\AmoCRMoAuthApiException;
use AmoCRM\Models\Customers\CustomerModel;
use App\Enums\Genders;
use App\Http\Requests\StoreContactRequest;
use App\Services\AmoCRM;

class ContactController extends Controller
{

    public function create()
    {
        $genders = Genders::cases();

        return view('contacts.create', compact('genders'));
    }

    /**
     * @throws AmoCRMApiException
     * @throws AmoCRMMissedTokenException
     * @throws AmoCRMoAuthApiException
     */
    public function store(StoreContactRequest $request, AmoCRM $amoCRM)
    {
        $data = $request->validated();

        $contacts = $amoCRM->apiClient->contacts()->get();
        if (!$amoCRM->isContactUnique($contacts, $data['custom_fields_values']['phone'])) {

            $customer = (new CustomerModel())->setName('ПомПимПомПимПомПомПимПом');
            $customer = $amoCRM->apiClient->customers()->addOne($customer);

            $contact = $contacts->last(); // "существующий контакт"

            $amoCRM->linkContactToCustomer($contact, $customer);

            return to_route('contacts.store')->with(
                'success',
                'Контакт существует, по этому создал Покупателя с привязанным существующим контактом'
            );
        }

        // создаю Контакт
        $contact = $amoCRM->makeContact($data);

        // Создаю Сделку, прикрепляю ранее созданного Контакта
        $lead = $amoCRM->createLead($contact);

        // Создаю Задачу согласно условию в ТЗ* и прикрепляю к Сделке
        // *(через 4 дня после создания сделки, но только на «рабочее время» (пн-пт с 9 до 18)
        $amoCRM->createTask($lead);

        // Создаю 2 Товара, связываю Сделку с ранее созданными Элементами Каталога "Товары"
        $productsElements = $amoCRM->createProducts();
        $amoCRM->linkProductsToLead($lead, $productsElements);



        return to_route('contacts.create')->with('success', 'Всё чики пуки');
    }

}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\Customers\CustomerModel;
use AmoCRM\Models\NoteType\CommonNote;
use App\Enums\Genders;
use App\Enums\TasksDetails;
use App\Http\Requests\StoreContactRequest;
use App\Services\AmoCRM;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function create(): View
    {
        $genders = Genders::cases();

        return view('contacts.create', compact('genders'));
    }

    public function store(StoreContactRequest $request, AmoCRM $amoCRM): JsonResponse
    {
        $data = $request->validated();

        $contacts = $amoCRM->getClient()->contacts()->get(with: (array)ContactModel::LEADS);
        $contact = $amoCRM->getValidContact($contacts, $data['custom_fields_values']['phone']);

        if ($contact !== null) {
            $customer = (new CustomerModel())->setName('ПомПимПомПимПомПомПимПом');
            $customer = $amoCRM->getClient()->customers()->addOne($customer);

            $text = date('d.m.Y').' был создан покупатель с названием «'.$customer->getName().'»';
            $note = (new CommonNote())->setEntityId($contact->getId())
                    ->setCreatedBy($contact->getResponsibleUserId())
                    ->setText($text);

            $amoCRM->linkContactToCustomer($contact, $customer);
            $amoCRM->getClient()->notes(EntityTypesInterface::CONTACTS)->addOne($note);

            return response()->json([
                'success' => 'Контакт существует и все Сделки Контакта успешные, по этому создал Покупателя с привязанным существующим контактом'
            ]);
        }

        // создаю Контакт
        $contact = $amoCRM->createContact($data);
        // Создаю Сделку
        $lead = $amoCRM->createLeadByContact($contact);
        // Связываю Сделку с Контактом
        $amoCRM->linkLeadToContact($contact, $lead);
        // Создаю Задачу согласно условию в ТЗ* и прикрепляю к Сделке
        // *(через 4 дня после создания сделки, но только на «рабочее время» (пн-пт с 9 до 18)
        $amoCRM->createTaskByLead($lead);

        // Создаю 2 Товара, связываю Сделку с ранее созданными Элементами Каталога "Товары"
        $productsElements = $amoCRM->createProducts();
        $amoCRM->linkProductsToLead($lead, $productsElements);

        return response()->json([
            'success' => 'Всё чики пуки'
        ]);
    }
}

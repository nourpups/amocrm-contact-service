<?php

namespace App\Http\Controllers;

use AmoCRM\Collections\NotesCollection;
use AmoCRM\EntitiesServices\Interfaces\HasParentEntity;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMMissedTokenException;
use AmoCRM\Exceptions\AmoCRMoAuthApiException;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\Customers\CustomerModel;
use AmoCRM\Models\Factories\NoteFactory;
use AmoCRM\Models\NoteModel;
use AmoCRM\Models\NoteType\CommonNote;
use AmoCRM\Models\TaskModel;
use App\Enums\Genders;
use App\Http\Requests\StoreContactRequest;
use App\Services\AmoCRM;
use Illuminate\Http\Request;

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

        $contacts = $amoCRM->apiClient->contacts()->get(with: (array)ContactModel::LEADS);
        if (!$amoCRM->isContactValid($contacts, $data['custom_fields_values']['phone'])) {

            $customer = (new CustomerModel())->setName('ПомПимПомПимПомПомПимПом');
            $customer = $amoCRM->apiClient->customers()->addOne($customer);

            $contact = $contacts->last(); // "существующий контакт"

            $text = date('d.m.Y').' был создан покупатель с названием «'.$customer->getName().'»';
            $note = (new CommonNote())->setEntityId($contact->getId())
                    ->setCreatedBy($contact->getResponsibleUserId())
                    ->setText($text);

            $amoCRM->linkContactToCustomer($contact, $customer);
            $amoCRM->apiClient->notes(EntityTypesInterface::CONTACTS)->addOne($note);

            return response()->json([
                'success' => 'Контакт существует и все Сделки Контакта успешные, по этому создал Покупателя с привязанным существующим контактом'
            ]);
        }

        // создаю Контакт
        $contact = $amoCRM->createContact($data);

        // Создаю Сделку
        $lead = $amoCRM->createLead($contact);
        // Связываю Сделку с Контактом
        $amoCRM->linkLeadToContact($contact, $lead);
        // Создаю Задачу согласно условию в ТЗ* и прикрепляю к Сделке
        // *(через 4 дня после создания сделки, но только на «рабочее время» (пн-пт с 9 до 18)
        $amoCRM->createTask($lead);

        // Создаю 2 Товара, связываю Сделку с ранее созданными Элементами Каталога "Товары"
        $productsElements = $amoCRM->createProducts();
        $amoCRM->linkProductsToLead($lead, $productsElements);



        return response()->json([
            'success' => 'Всё чики пуки'
        ]);
    }

}

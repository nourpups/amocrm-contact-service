<?php

namespace App\Http\Controllers;

use AmoCRM\Collections\CatalogElementsCollection;
use AmoCRM\Collections\ContactsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\LinksCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMMissedTokenException;
use AmoCRM\Exceptions\AmoCRMoAuthApiException;
use AmoCRM\Filters\CatalogElementsFilter;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\CatalogElementModel;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\Customers\CustomerModel;
use AmoCRM\Models\CustomFieldsValues\MultitextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\SelectCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\MultitextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\SelectCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\MultitextCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\SelectCustomFieldValueModel;
use AmoCRM\Models\LeadModel;
use AmoCRM\Models\LinkModel;
use AmoCRM\Models\TaskModel;
use App\Enums\Genders;
use App\Http\Requests\StoreContactRequest;
use App\Services\AmoCRM;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use function Sodium\add;

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

        if (!$this->isContactUnique($contacts, $data['custom_fields_values']['phone'])) {

            // Создаю Покупателя
            $customer = (new CustomerModel())->setName('ПомПимПомПимПомПомПимПом');
            $customer = $amoCRM->apiClient->customers()->addOne($customer);

            //Привязываю существующий контакт к созданному покупателю
            $links = new LinksCollection();
            $links->add($contacts->last());
            $amoCRM->apiClient->customers()->link($customer, $links);

            return to_route('contacts.store')->with(
                'success',
                'Контакт существует, по этому создал Покупателя с привязанным существующим контактом');
        }

        // создаю модель Контакта
        $contact = new ContactModel();
        $contact->setFirstName($data['first_name']);
        $contact->setLastName($data['last_name']);
        $contact->setName($data['first_name']. ' ' .$data['last_name']);

        // беру (не) случайного пользователя и делаю ответственным за контакт
        $responsibleUserId = $amoCRM->apiClient->account()
            ->getCurrent()
            ->getCurrentUserId(); // (не) cлучайный пользователь
        $contact->setResponsibleUserId($responsibleUserId)->setCreatedBy($responsibleUserId);

        // Заполняю поля (телефон, почта, возраст, пол) для Контакта
        $genderFieldId = 723489;
        $ageFieldId = 723491;
        $contactCustomFieldValues = (new CustomFieldsValuesCollection())
            ->add(
                (new MultitextCustomFieldValuesModel())
                    ->setFieldCode('PHONE')
                    ->setValues(
                        (new MultitextCustomFieldValueCollection())
                            ->add(
                                (new MultitextCustomFieldValueModel())
                                    ->setEnum('WORK')
                                    ->setValue(
                                        $data['custom_fields_values']['phone']
                                    )
                            )
                    )
            )
            ->add(
                (new MultitextCustomFieldValuesModel())
                    ->setFieldCode('EMAIL')
                    ->setValues(
                        (new MultitextCustomFieldValueCollection())
                            ->add(
                                (new MultitextCustomFieldValueModel())
                                    ->setEnum('WORK')
                                    ->setValue(
                                        $data['custom_fields_values']['email']
                                    )
                            )
                    )
            )
            ->add(
                (new NumericCustomFieldValuesModel())
                    ->setFieldId($ageFieldId)
                    ->setValues(
                        (new NumericCustomFieldValueCollection())->add(
                            (new NumericCustomFieldValueModel())->setValue(
                                $data['custom_fields_values']['age']
                            )
                        )
                    )
            )
            ->add(
                (new SelectCustomFieldValuesModel())
                    ->setFieldId($genderFieldId)
                    ->setValues(
                        (new SelectCustomFieldValueCollection())->add(
                            (new SelectCustomFieldValueModel())->setValue(
                                $data['custom_fields_values']['gender']
                            )
                        )
                    )
            );

        $contact->setCustomFieldsValues($contactCustomFieldValues);

        // Создаю Сделку прикрепляю ранее созданного Контакта
        $lead = new LeadModel();
        $lead->setName('Сделка с контактом ' . $contact->getName())
            ->setContacts((new ContactsCollection())->add($contact));
//            ->setCreatedAt(now()->getTimestamp()) // созданная Сделка всё равно не возврашает поле created_at
        $lead = $amoCRM->apiClient->leads()->addOneComplex($lead);

        // Создаю Задачу согласно условию в ТЗ* и прикрепляю к Сделке
        // *(через 4 дня после создания сделки, но только на «рабочее время» (пн-пт с 9 до 18)
        $task = new TaskModel();
        $task->setTaskTypeId(TaskModel::TASK_TYPE_ID_FOLLOW_UP)
            ->setText('Новая задача для сделки с id ' . $lead->getId())
            ->setEntityType(EntityTypesInterface::LEADS)
            ->setEntityId($lead->getId())
            ->setDuration($this->calculateDuration())
            ->setCompleteTill(
                $this->calculateCompleteTill(now('Asia/Tashkent'))
            )
            ->setResponsibleUserId($responsibleUserId);

        $amoCRM->apiClient->tasks()->addOne($task);

        // Создаю 2 Товара и прикрепляю к ранее созданной Сделке
        $catalogs = $amoCRM->apiClient->catalogs()->get();
        $productsCatalog = $catalogs->getBy('name', 'Товары');

        // Заполняю поле (Цена) для Товара
        $priceCustomFieldValue = (new CustomFieldsValuesCollection)
            ->add((new NumericCustomFieldValuesModel())
                ->setFieldCode('PRICE')
                ->setValues((new NumericCustomFieldValueCollection())
                    ->add((new NumericCustomFieldValueModel())->setValue(58008))
                )
            );

        // Создаю Элементов Каталога "Товары" и прикрепляю к ним цену
        $productsCatalogElementsCollection = (new CatalogElementsCollection())
            ->add((new CatalogElementModel())
                ->setName('Первый товар которого можно определить самому')
                ->setCustomFieldsValues($priceCustomFieldValue)
            )
            ->add((new CatalogElementModel())
                ->setName('Второй товар которого можно определить самому')
                ->setCustomFieldsValues($priceCustomFieldValue)
            );

       $productsCatalogElements = $amoCRM->apiClient->catalogElements($productsCatalog->getId())
           ->add($productsCatalogElementsCollection);

       // Связываю Сделку с ранее созданными Элементами Каталога "Товары"
        $links = new LinksCollection();

        $priceId = 671469;
        /** @var CatalogElementModel $element */
        foreach ($productsCatalogElements as $element) {

            $links->add((new CatalogElementModel())
                ->setCatalogId($element->getCatalogId())
                ->setId($element->getId())
                ->setQuantity(rand(1,4)) // куонтити разные, сумма сделки разная (порадовать глаз)
                ->setPriceId($priceId)
            );
        }
       $amoCRM->apiClient->leads()->link($lead, $links);

        return to_route('contacts.create')->with('success', 'Всё чики пуки');
    }

    private function calculateCompleteTill(
        Carbon $createdAt,
        string $workingHoursStart = '9:00',
        string $workingHoursEnd = '18:00'
    ): int {
        $startTime = Carbon::parse($workingHoursStart);
        $endTime = Carbon::parse($workingHoursEnd);

        if($createdAt->greaterThan($endTime)) { // если Задача создана после рабочего времени, переносим Задачу на завтра
            $createdAt->addWeekday();
        }
        $createdAt->setTime($startTime->hour, $startTime->minute); // задача начинается с 9:00

        return $createdAt->addWeekDays(4)->getTimestamp();
    }

    private function calculateDuration(string $startTime = '9:00', string $endTime = '18:00'): int
    {
        $startTime = Carbon::parse($startTime);
        $endTime = Carbon::parse($endTime);

        if($startTime->greaterThan($endTime)) { // например работа с 19:00 до 2:00
            $endTime->addWeekday();
        }

        return $startTime->diffInSeconds($endTime);
    }

    private function isContactUnique(ContactsCollection $contacts, string $phone): bool {
        /**
         * @var ContactModel $contact
         * @var MultitextCustomFieldValueModel $phoneNumber
         */

        foreach ($contacts as $contact) {
            $contactsPhoneNumbers = $contact->getCustomFieldsValues()->getBy('fieldCode', 'PHONE')->getValues();

            foreach ($contactsPhoneNumbers as $phoneNumber) {
                if($phoneNumber->getValue() === $phone) {
                    return false;
                }
            }
        }

        return true;
    }
}

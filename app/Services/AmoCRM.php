<?php

namespace App\Services;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\CatalogElementsCollection;
use AmoCRM\Collections\ContactsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\LinksCollection;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\CatalogElementModel;
use AmoCRM\Models\ContactModel;
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
use AmoCRM\Models\TaskModel;
use Carbon\Carbon;

class AmoCRM
{

    // Константы Контактов
    protected const CONTACT_GENDER_FIELD_ID = 723489;

    public const CONTACT_AGE_FIELD_ID = 723491;

    public const CONTACT_PHONE_CUSTOM_FIELD_CODE = 'PHONE';

    public const CONTACT_EMAIL_CUSTOM_FIELD_CODE = 'EMAIL';

    public const CONTACT_PHONE_CUSTOM_FIELD_VALUE_ENUM = 'WORK';

    // Константы Элементов Каталога "Товары"
    public const PRODUCTS_CATALOG_ELEMENTS_PRICE_FIELD_CODE = 'PRICE';

    public const PRODUCTS_CATALOG_ELEMENTS_PRICE_DEFAULT_VALUE = 58008;

    private const PRODUCTS_CATALOG_ELEMENTS_QUANTITY_DEFAULT_VALUE = 2;

    private const PRODUCTS_CATALOG_ELEMENTS_PRICE_FIELD_ID = 671469;

    // Константы Задач
    public const UZBEKISTAN_WORK_TIME_START = '9:00';

    public const UZBEKISTAN_WORK_TIME_END = '18:00';

    public const UZBEKISTAN_TIMEZONE = 'Asia/Tashkent';

    private AmoCRMApiClient $apiClient;

    public function __construct()
    {
        $apiClient = new AmoCRMApiClient(
            config('amocrm.client_id'),
            config('amocrm.client_secret'),
            config('amocrm.client_redirect_uri'),
        );

        $apiClient->setAccountBaseDomain(config('amocrm.account_domain'));
        $apiClient->setAccessToken(session('token'));

        $this->apiClient = $apiClient;

    }

    public function getClient(): AmoCRMApiClient
    {
        return $this->apiClient;
    }

    public function createLead(ContactModel $contact): LeadModel
    {
        $lead = (new LeadModel())
            ->setName('Сделка с контактом ' . $contact->getName())
            ->setContacts((new ContactsCollection())->add($contact));

        return $this->apiClient->leads()->addOneComplex($lead);
    }

    public function makeContact(array $data): ContactModel
    {
        $contact = new ContactModel();
        $contact->setFirstName($data['first_name']);
        $contact->setLastName($data['last_name']);
        $contact->setName($data['first_name'] . ' ' . $data['last_name']);

        // беру (не) случайного пользователя и делаю ответственным за контакт

        $contact->setResponsibleUserId($this->getResponsibleUserId())
            ->setCreatedBy($this->getResponsibleUserId());

        // Заполняю поля (телефон, почта, возраст, пол) для Контакта
        $contactCustomFieldValues = $this->getContactCustomFieldValues($data['custom_fields_values']);

        return $contact->setCustomFieldsValues($contactCustomFieldValues);
    }

    public function linkContactToCustomer($contact, $customer): LinksCollection
    {
        $links = (new LinksCollection())->add($contact);

        return $this->apiClient->customers()->link($customer, $links);
    }

    public function createTask(LeadModel $lead): TaskModel
    {
        $task = new TaskModel();
        $task->setTaskTypeId(TaskModel::TASK_TYPE_ID_FOLLOW_UP)
            ->setText('Новая задача для сделки с id ' . $lead->getId())
            ->setEntityType(EntityTypesInterface::LEADS)
            ->setEntityId($lead->getId())
            ->setDuration($this->calculateDuration())
            ->setCompleteTill(
                $this->calculateCompleteTill(now(static::UZBEKISTAN_TIMEZONE))
            )
            ->setResponsibleUserId($this->getResponsibleUserId());

        return $this->apiClient->tasks()->addOne($task);
    }

    public function createProducts(): CatalogElementsCollection
    {
        $catalogs = $this->apiClient->catalogs()->get();
        $productsCatalog = $catalogs->getBy('name', 'Товары');

        // Создаю Элементов Каталога "Товары" и прикрепляю к ним цену
        $productsElementsCollection = $this->makeProductsCollection();

        return $this->apiClient->catalogElements($productsCatalog->getId())
            ->add($productsElementsCollection);
    }

    private function makeProductsCollection(): CatalogElementsCollection {
        // Заполняю поле (Цена) для Товара
        $priceCustomFieldValue = $this->makePriceCustomFieldValue();

        return (new CatalogElementsCollection())
            ->add((new CatalogElementModel())
                ->setName('Первый товар которого можно определить самому')
                ->setCustomFieldsValues($priceCustomFieldValue)
            )
            ->add((new CatalogElementModel())
                ->setName('Второй товар которого можно определить самому')
                ->setCustomFieldsValues($priceCustomFieldValue)
            );
    }

    private function makePriceCustomFieldValue(): CustomFieldsValuesCollection {

        return (new CustomFieldsValuesCollection)
            ->add((new NumericCustomFieldValuesModel())
                ->setFieldCode(static::PRODUCTS_CATALOG_ELEMENTS_PRICE_FIELD_CODE)
                ->setValues((new NumericCustomFieldValueCollection())
                    ->add((new NumericCustomFieldValueModel())
                        ->setValue(static::PRODUCTS_CATALOG_ELEMENTS_PRICE_DEFAULT_VALUE))
                )
            );
    }

    public function linkProductsToLead($lead, $productsElements): LinksCollection {
        $links = new LinksCollection();

        /** @var CatalogElementModel $element */
        foreach ($productsElements as $element) {

            $links->add((new CatalogElementModel())
                ->setCatalogId($element->getCatalogId())
                ->setId($element->getId())
                ->setQuantity(static::PRODUCTS_CATALOG_ELEMENTS_QUANTITY_DEFAULT_VALUE)
                ->setPriceId(static::PRODUCTS_CATALOG_ELEMENTS_PRICE_FIELD_ID)
            );
        }

        return $this->apiClient->leads()->link($lead, $links);
    }

    public function isContactUnique(ContactsCollection $contacts, string $phone): bool {
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

    private function getResponsibleUserId(): int {
        return $this->apiClient->account()
            ->getCurrent()
            ->getCurrentUserId(); // (не) cлучайный пользователь
    }

    private function getContactCustomFieldValues(array $customFieldValues): CustomFieldsValuesCollection {
        return (new CustomFieldsValuesCollection())
            ->add(
                (new MultitextCustomFieldValuesModel())
                    ->setFieldCode(static::CONTACT_PHONE_CUSTOM_FIELD_CODE)
                    ->setValues((new MultitextCustomFieldValueCollection())
                        ->add((new MultitextCustomFieldValueModel())
                            ->setEnum(static::CONTACT_PHONE_CUSTOM_FIELD_VALUE_ENUM)
                            ->setValue($customFieldValues['phone'])
                        )
                    )
            )
            ->add(
                (new MultitextCustomFieldValuesModel())
                    ->setFieldCode(static::CONTACT_EMAIL_CUSTOM_FIELD_CODE)
                    ->setValues((new MultitextCustomFieldValueCollection())
                        ->add((new MultitextCustomFieldValueModel())
                            ->setEnum(static::CONTACT_PHONE_CUSTOM_FIELD_VALUE_ENUM)
                            ->setValue($customFieldValues['email'])
                        )
                    )
            )
            ->add(
                (new NumericCustomFieldValuesModel())
                    ->setFieldId(static::CONTACT_AGE_FIELD_ID)
                    ->setValues((new NumericCustomFieldValueCollection())->add(
                            (new NumericCustomFieldValueModel())->setValue($customFieldValues['age'])
                        )
                    )
            )
            ->add(
                (new SelectCustomFieldValuesModel())
                    ->setFieldId(static::CONTACT_GENDER_FIELD_ID)
                    ->setValues((new SelectCustomFieldValueCollection())->add(
                            (new SelectCustomFieldValueModel())->setValue($customFieldValues['gender'])
                        )
                    )
            );
    }

    private function calculateDuration(
        string $startTime = self::UZBEKISTAN_WORK_TIME_START,
        string $endTime = self::UZBEKISTAN_WORK_TIME_END
    ): int {
        $startTime = Carbon::parse($startTime);
        $endTime = Carbon::parse($endTime);

        if($startTime->greaterThan($endTime)) { // например работа с 19:00 до 2:00
            $endTime->addWeekday();
        }

        return $startTime->diffInSeconds($endTime);
    }

    private function calculateCompleteTill(
        Carbon $createdAt,
        string $workingHoursStart = self::UZBEKISTAN_WORK_TIME_START,
        string $workingHoursEnd = self::UZBEKISTAN_WORK_TIME_END
    ): int {
        $startTime = Carbon::parse($workingHoursStart);
        $endTime = Carbon::parse($workingHoursEnd);

        if($createdAt->greaterThan($endTime)) { // если Задача создана после рабочего времени, переносим Задачу на завтра
            $createdAt->addWeekday();
        }
        $createdAt->setTime($startTime->hour, $startTime->minute); // задача начинается с 9:00

        return $createdAt->addWeekDays(4)->getTimestamp();
    }

}

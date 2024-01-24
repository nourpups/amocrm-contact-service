<?php
declare(strict_types=1);

namespace App\Services;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\CatalogElementsCollection;
use AmoCRM\Collections\ContactsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\Leads\LeadsCollection;
use AmoCRM\Collections\LinksCollection;
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
use AmoCRM\Models\TaskModel;
use AmoCRM\Models\UserModel;
use App\Enums\CatalogsIds;
use App\Enums\CustomFieldsValuesCodes;
use App\Enums\CustomFieldsValuesDefaultValues;
use App\Enums\CustomFieldsValuesIds;
use App\Enums\TasksDetails;
use Carbon\Carbon;

class AmoCRM
{
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

    public function createLeadByContact(ContactModel $contact): LeadModel
    {
        $lead = (new LeadModel())
            ->setName('Сделка с контактом ' . $contact->getName())
            ->setResponsibleUserId($this->getResponsibleUserId());

        return $this->apiClient->leads()->addOne($lead);
    }

    public function createContact(array $data): ContactModel
    {
        $contact = new ContactModel();
        $contact->setFirstName($data['first_name']);
        $contact->setLastName($data['last_name']);
        $contact->setName($data['first_name'] . ' ' . $data['last_name']);

        // беру (не) случайного пользователя и делаю ответственным за контакт

        $contact->setResponsibleUserId($this->getResponsibleUserId());

        // Заполняю поля (телефон, почта, возраст, пол) для Контакта
        $contactCustomFieldValues = $this->getContactCustomFieldValues($data['custom_fields_values']);
        $contact->setCustomFieldsValues($contactCustomFieldValues);

        return $this->apiClient->contacts()->addOne($contact);
    }

    public function linkLeadToContact(ContactModel $contact, LeadModel $lead): LinksCollection
    {
        $links = (new LinksCollection())->add($lead);

        return $this->apiClient->contacts()->link($contact, $links);
    }

    public function linkContactToCustomer(ContactModel $contact, CustomerModel $customer): LinksCollection
    {
        $links = (new LinksCollection())->add($contact);

        return $this->apiClient->customers()->link($customer, $links);
    }

    public function createTaskByLead(LeadModel $lead): TaskModel
    {
        $task = new TaskModel();
        $task->setTaskTypeId(TaskModel::TASK_TYPE_ID_FOLLOW_UP)
            ->setText('Новая задача для сделки с id ' . $lead->getId())
            ->setEntityType(EntityTypesInterface::LEADS)
            ->setEntityId($lead->getId())
            ->setDuration($this->calculateDuration())
            ->setCompleteTill(
                $this->calculateCompleteTill(now(TasksDetails::UZBEKISTAN_TIMEZONE->value))
            )
            ->setResponsibleUserId($lead->getResponsibleUserId());

        return $this->apiClient->tasks()->addOne($task);
    }

    public function createProducts(): CatalogElementsCollection
    {
        $catalogs = $this->apiClient->catalogs()->get();
        $productsCatalog = $catalogs->getBy('id', CatalogsIds::PRODUCTS_CATALOG_ID->value);

        // Создаю Элементов Каталога "Товары" и прикрепляю к ним цену
        $productsElementsCollection = $this->makeProductsCollection();

        return $this->apiClient->catalogElements($productsCatalog->getId())
            ->add($productsElementsCollection);
    }

    private function makeProductsCollection(): CatalogElementsCollection
    {
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

    private function makePriceCustomFieldValue(): CustomFieldsValuesCollection
    {
        return (new CustomFieldsValuesCollection)
            ->add((new NumericCustomFieldValuesModel())
                ->setFieldCode(CustomFieldsValuesCodes::PRODUCTS_CATALOG_ELEMENTS_PRICE_FIELD_CODE->value)
                ->setValues((new NumericCustomFieldValueCollection())
                    ->add((new NumericCustomFieldValueModel())
                        ->setValue(CustomFieldsValuesDefaultValues::PRODUCTS_CATALOG_ELEMENTS_PRICE_DEFAULT_VALUE->value)
                    )
                )
            );
    }

    public function linkProductsToLead(LeadModel $lead, CatalogElementsCollection $productsElements): LinksCollection
    {
        $links = new LinksCollection();

        /** @var CatalogElementModel $element */
        foreach ($productsElements as $element) {
            $links->add(
                $element->setQuantity(CustomFieldsValuesDefaultValues::PRODUCTS_CATALOG_ELEMENTS_QUANTITY_DEFAULT_VALUE->value)
            );
        }

        return $this->apiClient->leads()->link($lead, $links);
    }

    public function getValidContact(ContactsCollection $contacts, string $phone): ?ContactModel
    {

        $contact = $this->getNonUniqueContact($contacts, $phone);
        if ($contact === null) {
            return null;
        }
        if (!$this->isContactLeadSucceeded($contact->getLeads())) {
            return null;
        }

        return $contact; // контакт который существует (не валидный)
    }

    private function isContactLeadSucceeded(?LeadsCollection $leads): bool
    {
        if ($leads !== null) {
        /** @var LeadModel $lead */
            foreach ($leads as $lead) {
                $lead = $this->apiClient->leads()->getOne($lead->getId());
                if ($lead->getStatusId() === LeadModel::WON_STATUS_ID) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getNonUniqueContact(ContactsCollection $contacts, string $phone): ?ContactModel
    {
        /**
         * @var ContactModel $contact
         * @var MultitextCustomFieldValueModel $phoneNumber
         */
        foreach ($contacts as $contact) {
            $contactsPhoneNumbers = $contact->getCustomFieldsValues()
                ->getBy('fieldCode', CustomFieldsValuesCodes::CONTACT_PHONE_CUSTOM_FIELD_CODE->value)
                ->getValues();
            foreach ($contactsPhoneNumbers as $phoneNumber) {
                if ($phoneNumber->getValue() === $phone) {
                    return $contact; // существующий контакт
                }
            }
        }

        return null;
    }

    private function getResponsibleUserId(): int
    {
        $users = $this->apiClient->users()->get();
        /** @var UserModel $randomUser */
        $randomUser = collect($users)->random();

        return $randomUser->getId(); // (не(не)) cлучайный пользователь
    }

    private function getContactCustomFieldValues(array $customFieldValues): CustomFieldsValuesCollection
    {
        return (new CustomFieldsValuesCollection())
            ->add(
                (new MultitextCustomFieldValuesModel())
                    ->setFieldCode(CustomFieldsValuesCodes::CONTACT_PHONE_CUSTOM_FIELD_CODE->value)
                    ->setValues((new MultitextCustomFieldValueCollection())
                        ->add((new MultitextCustomFieldValueModel())
                            ->setEnum(CustomFieldsValuesCodes::CONTACT_PHONE_CUSTOM_FIELD_VALUE_ENUM->value)
                            ->setValue($customFieldValues['phone'])
                        )
                    )
            )
            ->add(
                (new MultitextCustomFieldValuesModel())
                    ->setFieldCode(CustomFieldsValuesCodes::CONTACT_EMAIL_CUSTOM_FIELD_CODE->value)
                    ->setValues((new MultitextCustomFieldValueCollection())
                        ->add((new MultitextCustomFieldValueModel())
                            ->setEnum(CustomFieldsValuesCodes::CONTACT_PHONE_CUSTOM_FIELD_VALUE_ENUM->value)
                            ->setValue($customFieldValues['email'])
                        )
                    )
            )
            ->add(
                (new NumericCustomFieldValuesModel())
                    ->setFieldId(CustomFieldsValuesIds::CONTACT_AGE_FIELD_ID->value)
                    ->setValues((new NumericCustomFieldValueCollection())->add(
                            (new NumericCustomFieldValueModel())->setValue($customFieldValues['age'])
                        )
                    )
            )
            ->add(
                (new SelectCustomFieldValuesModel())
                    ->setFieldId(CustomFieldsValuesIds::CONTACT_GENDER_FIELD_ID->value)
                    ->setValues((new SelectCustomFieldValueCollection())->add(
                            (new SelectCustomFieldValueModel())->setValue($customFieldValues['gender'])
                        )
                    )
            );
    }

    private function calculateDuration(
        TasksDetails|string $startTime = TasksDetails::UZBEKISTAN_WORK_TIME_START,
        TasksDetails|string $endTime = TasksDetails::UZBEKISTAN_WORK_TIME_END
    ): int {
        $startTime = Carbon::parse($startTime->value);
        $endTime = Carbon::parse($endTime->value);

        if ($startTime->greaterThan($endTime)) { // например работа с 19:00 до 2:00
            $endTime->addWeekday();
        }

        return $startTime->diffInSeconds($endTime);
    }

    private function calculateCompleteTill(
        Carbon $createdAt,
        TasksDetails|string $workingHoursStart = TasksDetails::UZBEKISTAN_WORK_TIME_START,
        TasksDetails|string $workingHoursEnd = TasksDetails::UZBEKISTAN_WORK_TIME_END
    ): int {
        $startTime = Carbon::parse($workingHoursStart->value);
        $endTime = Carbon::parse($workingHoursEnd->value);

        if ($createdAt->greaterThan($endTime)) { // если Задача создана после рабочего времени, переносим Задачу на завтра
            $createdAt->addWeekday();
        }
        $createdAt->setTime($startTime->hour, $startTime->minute); // задача начинается с 9:00

        return $createdAt->addWeekDays(4)->getTimestamp();
    }

}

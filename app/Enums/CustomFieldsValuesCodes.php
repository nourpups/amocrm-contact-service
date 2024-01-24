<?php
declare(strict_types=1);

namespace App\Enums;

enum CustomFieldsValuesCodes: string
{
    case CONTACT_PHONE_CUSTOM_FIELD_CODE = 'PHONE';

    case CONTACT_EMAIL_CUSTOM_FIELD_CODE = 'EMAIL';

    case CONTACT_PHONE_CUSTOM_FIELD_VALUE_ENUM = 'WORK';

    // Константы Элементов Каталога "Товары"
    case PRODUCTS_CATALOG_ELEMENTS_PRICE_FIELD_CODE = 'PRICE';
}

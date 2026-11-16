<?php

namespace App\Exceptions;

use RuntimeException;

/** A visitor's recipe that cannot be analysed; the message is shown to them as is. */
class RecipeRejected extends RuntimeException
{
    public static function notAKitchenDish(): self
    {
        return new self('يمكن تعديل وصفات المطبخ السوري فقط.');
    }

    /** @param  list<int>  $recordIds */
    public static function unknownIngredients(array $recordIds): self
    {
        return new self('بعض المكوّنات غير متوفرة في قاعدتنا المعتمدة ('.count($recordIds).'). اخترها من القائمة من جديد.');
    }

    public static function nothingToWeigh(): self
    {
        return new self('لم نستطع حساب وزن أي مكوّن. تأكد من الكميات والوحدات.');
    }
}

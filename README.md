[![StyleCI](https://styleci.io/repos/87166365/shield)](https://styleci.io/repos/87166365)

# JDT API
This is an api wrapper around dingo api that allows you to not have to create any controllers but purely focus on processing your api requests.

A quick overview on how it works is you define the api class in your route and pass it over to the api controller and the rest is handled for you.

## Usage
There are 3 different types of api helpers you can create, all require you define validation rules for the data that is passed in.

```php
// routes/api.php
$api->get('/{id}', [
    'as' => 'api.user.title.read',
    'uses' => 'JDT\Api\Http\Controllers\ApiController@actionExecute',
    'api' => App\Modules\User\Api\UserTitle\Read::class,
]);

// app/Modules/User/Api/UserTitle/Read.php
<?php

declare(strict_types=1);

namespace App\Modules\User\Api\UserTitle;

use App\Modules\User\Entities\UserTitle;
use Illuminate\Database\Eloquent\Model;
use JDT\Api\Contracts\ApiEndpoint;
use JDT\Api\Field\Field;
use JDT\Api\Field\FieldList;
use JDT\Api\Traits\ModelEndpoint;

/**
 * Class Read
 * @package App\Modules\User\Api\UserTitle
 */
class Read implements ApiEndpoint
{
    use ModelEndpoint;

    const RUN_TYPE = self::TYPE_READ;

    /**
     * @inheritDoc
     */
    protected function getFields():FieldList
    {
        $fieldList = new FieldList();
        $fieldList->addField(new Field('id', 'required|exists:user_title,id,deleted_at,NULL'));

        return $fieldList;
    }

    /**
     * @inheritDoc
     */
    protected function getModel():Model
    {
        return new UserTitle();
    }
}
```

### ApiEndpoint

### ModelEndpoint

### MultipleEndpoint
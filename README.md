## Motivation

Restful APIs try to obtain the same flexibility as GraphQL ones by implementing a `fields` parameter used to
specify the fields that should be included in the response.

Most APIs use a query parameter and specify the fields separating them by commas.

This library tries to fill this gap.

## Presenting the proposed standardized syntax

Assuming this structure:

```json
{
    "id": 123,
    "profile": {
        "name": "John Doe",
        "age": 25,
        "education": [
            {
                "institutionName": "Berkeley University",
                "startYear": 1998,
                "endYear": 2000
            },
            {
                "institutionName": "MIT",
                "startYear": 2001,
                "endYear": 2005
            }
        ]
    }
}
```


### Fields

A field can be part of a nested structure.
You can specify a path to a field or a nested field in a similar way to GraphQL.

### Root fields
In its basic form you specify the fields as keys with `true` or `false` as value.
- `true` means return me that field.
- `false` means do not return me that field (if you omit a field it is considered that you don't want that field)

`?fields=<url-encoded-json>`

```json
{
    "id": true,
    "profile": false
}
```

This is equivalent to not providing the `profile` field, because if you start providing fields, the missing fields are considered unwanted.

```json
{
    "id": true
}
```

### Field groups

Field groups can be useful to group certain fields and ask to return/not return them.

There are two special groups: `_defaults` and `_all`.

- If you want to indicate that the endpoint should return you the default fields use `_defauls` as field
- If you want to indicate that the endpoint should return you all available fields use `_all` as field

This brings all fields exception `profile`:

```json
{
    "_all": true,
    "profile": false
}
```

You can also declare your own field groups and put a custom logic behind them.

**Precedence rules:**
- `_all` and `_defaults` are mutually exclusive  
- `_all` has precedence over `_defaults`

### Nested fields

The nested fields are specified in curly braces, separated by comma

#### Example 1
To only retrieve the `id` from the root and `name` from `profile`:

`?fields=%7B%22id%22%3Atrue%2C%22profile%22%3A%7B%22name%22%3Atrue%7D%7D`

Decoded query:

```json
{
    "id": true,
    "profile": {
        "name": true
    }
}
```

Result:

```json
{
    "id": 123,
    "profile": {
        "name": "John Doe"
    }
}
```

Nested fields support field groups too.

#### Example 2
To only retrieve the `id` from the root and the defaults from `profile`, assuming name and age are the defaults and education is optional:

```json
{
    "id": true,
    "profile": {
        "_defaults": true
    }
}
```

Result:

```json
{
    "id": 123,
    "profile": {
        "name": "John Doe",
        "age": 25
    }
}
```

### Field options

The field options are specified using the `_opt` key on the field you need them.

#### Example 1
To retrieve the `id` from the root and from the `profile` only the first institution, ordered by `startYear`:

`?fields=%7B%22id%22%3Atrue%2C%22profile%22%3A%7B%22education%22%3A%7B%22_opt%22%3A%7B%22limit%22%3A1%2C%22sort%22%3A%22startYear%22%2C%22sortDir%22%3A%22asc%22%7D%7D%7D%7D`

```json
{
    "id": true,
    "profile": {
        "education": {
            "_opt": {
                "limit": 1,
                "sort": "startYear",
                "sortDir": "asc"
            }
        }
    }
}
```

This brings the default fields from the institution object. Assuming all are retrieved by default:

```json
{
    "id": 123,
    "profile": {
        "education": [
            {
                "institutionName": "Berkeley University",
                "startYear": 1998,
                "endYear": 2000
            }
        ]
    }
}
```

#### Example 2
To retrieve from the `profile`'s education all fields except the institution's name, ordered by `startYear`
you can make `_all` `true` and set the  `institutionName` to `false`.

```json
{
    "profile": {
        "education": {
            "_all": true,
            "institutionName": false,
            "_opt": {
                "limit": 1,
                "sort": "startYear",
                "sortDir": "asc"
            }
        }
    }
}
```

Result:

```json
{
    "profile": {
        "education": [
            {
                "startYear": 1998,
                "endYear": 2000
            }
        ]
    }
}
```


## Using the library

The library does not contain any code related to actually modify the response. 
Its goal is to only decode and encapsulate the client field options.

It is up to the caller to honor these field options.

To retrieve a path use the dot notation.

Assuming this json structure was sent on the request:
```json
{
    "_defaults": true,
    "id": true,
    "seo": false,
    "profile": {
        "education": {
            "_all": true,
            "_opt": {
                "limit": 1,
                "sort": "startYear",
                "sortDir": "asc"
            }
        }
    }
}
```

### FieldsOptionsBuilder

The earlier json structure of fields options can be configured programmatically using the builder.
In general, it is recommended to use the builder instead of manually creating the array.

```php
use Lucian\FieldsOptions\FieldsOptionsBuilder;
$builder = new FieldsOptionsBuilder();
$fieldsOptions = $builder
    ->setDefaultFieldsIncluded()
    ->setFieldIncluded('id')
    ->setFieldExcluded('seo')
    ->setAllFieldsIncluded('profile.education')
    ->setFieldOption('profile.education', 'limit', 1)
    ->setFieldOption('profile.education', 'sort', 'startYear')
    ->setFieldOption('profile.education', 'sortDir', 'asc')
    ->build()
```

You can include or exclude multiple fields at once from a given path
```php
$fieldsOptions = $this->builder
    ->setFieldIncluded(null, ['name']) // this is equivalent to setFieldIncluded('name') 
    ->setFieldIncluded('profile', ['workHistory']) // include profile.workHistory
    ->setFieldExcluded('profile.workHistory', ['institution']) // but exclude profile.workHistory.institution
    ->setFieldIncluded('profile.education', ['id', 'name']) // include profile.education.id and profile.education.name    
    ->build();
```

You also have methods to set all the options for a field at once

`public function setFieldOptions(string $fieldPath, array $options): self`

```php
$educationOptions = ['limit' => 2, 'offset' => 5];
$builder->setFieldOptions('profile.education', $educationOptions);
```

You can set a custom group field included

`public function setGroupFieldIncluded(string $groupField, ?string $fieldPath = null): self`

```php
$fieldsOptions = $builder->setGroupFieldIncluded('_basicInfo', 'profile')
    ->build();
$fieldsOptions->hasGroupField('_basicInfo', 'profile') // true
```

### Using the FieldsOptions class

```php
use Lucian\FieldsOptions\FieldsOptions;

// assuming we use the Symfony request
// $request = Request:::createFromGlobals();
$data = json_decode($request->getContent());

//?fields=%7B%22_defaults%22%3Atrue%2C%22id%22%3Atrue%2C%22seo%22%3Afalse%2C%22profile%22%3A%7B%22education%22%3A%7B%22_all%22%3Atrue%2C%22_opt%22%3A%7B%22limit%22%3A1%2C%22sort%22%3A%22startYear%22%2C%22sortDir%22%3A%22asc%22%7D%7D%7D%7D

$options = new FieldsOptions($data);

$options->isFieldIncluded('id'); // true
$options->isFieldIncluded('missing'); // false
// field is present but value is false
$options->isFieldIncluded('seo'); // false
$options->isFieldIncluded('profile'); // true
$options->isFieldIncluded('profile.education'); // true
$options->getFieldOption('profile.education', 'limit'); // 1
$options->getFieldOption('profile.education', 'missing', 1); // 1 - default
$options->getFieldOption('profile.education', 'missing'); // null - default

// field groups
$options->hasDefaultFields(); // true
$options->hasDefaultFields('profile'); // false
$options->hasAllFields('profile'); // false
$options->hasAllFields('profile.education'); // true
$options->hasAllFields('profiles.missing'); // throws exception
        
// you can export the entire structure
$array = $options->toArray();
```

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
- `true` means return me that field. It also means you want its default nested fields, if this is an object
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
The fields groups do not support any options, and they can only be `true` or `false`.

There are two special groups: `_defaults` and `_all`.

- If you want to indicate that the endpoint should return you the default fields or not use `_defauls` as field.   
- If you want to indicate that the endpoint should return you all available fields or not use `_all` as field.

#### _defaults group

`_defaults` is assumed `true` for a field nested fields if you only specify the parent field, and you don't provide a list of nested fields.
`_defaults` is implicit `true` only when you don't have a list of fields for root or for a sub-field.
When you specify a list of fields, it is considered `false`, and you have to be explicit to include the default fields too.


The default fields logic should be embedded into the serialized object.

In this case there is no list of fields, so we will assume you want to export the default fields from profile:

```json
{
    "profile": true
}
```

This is equivalent to:

```json
{
    "_defaults": false,
    "profile": true
}
```

or with

```json
{
    "_defaults": false,
    "profile": {
        "_defaults": true
    }
}
```

Since, the root definition contains a list of fields (profile), then we will assume you don't want the default fields from the root,
and you only want the `profile` object.

But if you want the root defaults in addition to the profile, you can specify `_defaults` on the root:

```json
{
    "_defaults": true,
    "profile": true
}
```

If you don't want the defaults from the profile, but you only want a specific field like the profile id you can ask it and
by leaving _defaults not specified we assume you don't want the defaults, because you asked for a specific field:


```json
{
    "profile": {
        "id": true
    }
}
```

which is equivalent to

```json
{
    "_defaults": false,
    "profile": {
        "_defaults": false,
        "id": true
    }
}
```

Specifying `_defaults: false` only makes sense with a list of non-default fields included.

This is valid but not very useful, because without any additional fields in profile and with defaults disabled
you will get null.

```json
{
    "profile": {
        "_defaults": false
    }
}
```

will get you after applying these:

```json
{
    "profile": null
}
```


These 3 examples are equivalent, and they all ask for the defaults in `profile`:

```json
{
    "profile": {
        "_defaults": true
    }
}
```

```json
{
    "profile": true
}
```

Note how an empty object translates to _defaults: true:

```json
{
    "profile": {}
}
```

#### _all group

Since `_all` is `false` by default, there is no point in setting it to `false`.
`_all` only makes sense if you want all fields, so use it with `_all: true`

As an example let's assume that the Profile DTO serializes by default only two fields: `id` and `name`.
The fields `age` and `education` will only be exported if specifically requested or with `_all: true`.
We also assume the root DTO exports all fields by default (both `id` and `profile`). 

This brings all fields exception `profile`:

```json
{
    "_all": true,
    "profile": false
}
```

This brings all fields from `profile`:

```json
{
    "profile": {
        "_all": true
    }
}
```

#### Precedence rules for built-in groups

- `_all` and `_defaults` are mutually exclusive
- `_all` has precedence over `_defaults`

#### Custom groups

You can also declare your own field groups and put a custom logic behind them.
It is required to prefix group names with underscore, so they are detected.

You can still have field names starting with underscore, but they will not support options, just boolean values.
If possible, avoid fields names starting with underscore.


```json
{
    "profile": {
        "_basicInfo": true
    }
}
```

### Nested fields

The nested fields use the dot notation.

#### Example 1
To only retrieve the `id` from the root and `name` from `profile`:

Decoded fields options:

```json
{
    "id": true,
    "profile": {
        "name": true
    }
}
```

Actual request with URL encoded fields options:
`?fields=%7B%22id%22%3Atrue%2C%22profile%22%3A%7B%22name%22%3Atrue%7D%7D`

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
To only retrieve `profile` field, and from `profile` the default fields + age (assuming `id` and `name` are defaults and `age` and `education` are optional):

Decoded fields options:

```json
{
    "profile": {
        "_defaults": true,
        "age": true
    }
}
```

Result:

```json
{
    "profile": {
        "id": 123,
        "name": "John Doe",
        "age": 25
    }
}
```

### Field options

The field options are specified using the `_opt` key on the field you need them.

#### Example 1
To retrieve the `id` from the root and from the `profile` only the first institution, ordered by `startYear`:

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

Actual request with URL encoded fields options:
`?fields=%7B%22id%22%3Atrue%2C%22profile%22%3A%7B%22education%22%3A%7B%22_opt%22%3A%7B%22limit%22%3A1%2C%22sort%22%3A%22startYear%22%2C%22sortDir%22%3A%22asc%22%7D%7D%7D%7D`

The result contains the default fields from the education object. Assuming all are retrieved by default:

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

The `FieldsOptions` object encapsulates the provided options and can be constructor from an array (coming from a request)
or using the `FieldsOptionsBuilder` if you want to configure them programmatically.

It is up to the caller to honor these field options, but the library comes with a class called `FieldsOptionsObjectApplier`
that can be used to recursively apply the options on an object such as a DTO and his nested properties, so that the object will serialize with only the desired fields.

To specify a field path use the dot notation.

The internal structure should never be specified manually. You should always use the `FieldsOptions` or `FieldsOptionsBuilder`
classes.
If you feel the need to manually build/alter the data array, then you are doing something wrong.
The library tries to hide these details, so it can protect you against BC breaks and structure changes.

Assuming this json structure was sent on the request:
```json
{
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
/**
 * Include fields from given path
 *
 * @param string|null $fieldPath Base path
 * @param array $fields Optional list of included field (you can use relative paths in dot notation too)
 * @return $this
*/
public function setFieldIncluded(?string $fieldPath, array $fields = []): self`
```

You can optionally use a validator that receives a sample DTO.
The validator will receive an object (preferred) or an array representing the schema of the response.

```php

use Lucian\FieldsOptions\FieldsOptionsBuilder;
use Lucian\FieldsOptions\Validator;

$validator = new Validator($this->getSampleDto());
$builder = new FieldsOptionsBuilder($validator);
$fieldsOptions = $builder
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
    ->setFieldIncluded('profile', ['education.startYear']) // include profile.education.startYear. the field can be a relative path
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

You can also create a builder with initial data. One scenario would be to add options to existing fields options.

```php
$data = $fieldsOptions->toArray();
$builder = new FieldsOptionsBuilder($validator, $data);
$builder->setFieldIncluded('fancyField');
```

### Using the FieldsOptions class

```php
use Lucian\FieldsOptions\FieldsOptions;

// assuming we use the Symfony request
// $request = Request:::createFromGlobals();
$data = json_decode($request->getContent());

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
$options->hasGroupField('_basicInfo', 'profile'); // false
        
// you can export the entire structure
$array = $options->toArray();
```

Note the difference between `isFieldIncluded()` and `isFieldSpecified()`.
`isFieldSpecified()` is simply a way to determine if the field was specified or not on the options, either with `true` or `false`.
`isFieldIncluded()` will also check if the field is set to `true`.

#### Validation if fields

This library comes with a Validator implementation that should work with array and object prototypes: `Lucian\FieldsOptions\Validator`.

You can provide the validator to both `FieldsOptions` and `FieldsOptionsBuilder`.
A basic validator will be used in case it is not provider, that will only check the data structure.

If the validator is constructed with tbe prototype/schema, then including invalid fields will trigger a RuntimeException.

> **How validation works:** To validate fields, the prototype is analyzed using reflection.
> All properties are considered valid fields. Typed properties have no issues, if they are scalar or classes.
> For properties without type-hinting the validator tries to inspect the value.
> If the value contains a collection, PHP does not have a Collection<T> type, and we cannot rely on phpdoc.
> In that case, you have to populate the array with at least an object, and we assume they are all the same type.

#### Testing field groups

```php
/**
 * WIll check if the options contain the default fields either by implicit or explicit inclusion
 *
 * @param string|null $fieldPath
 * @return bool true if _defaults is not specified or specified and is not false, false otherwise
 */
public function hasDefaultFields(?string $fieldPath = null): bool
```

```php
/**
 * WIll check if the options contain all fields either by implicit or explicit inclusion
 *
 * @param string|null $fieldPath
 * @return bool false if _all is not specified or specified and is not false, true otherwise
 */
public function hasAllFields(?string $fieldPath = null): bool
```

#### Getting a list of included fields for a path 

```php
/**
* Returns the list of actually explicitly included fields
* Does not know about defaults or groups. If a field is a default field it won't be returned here.
* This will probably change in future versions to also include the default fields or coming from group fields
* if they were included using the group
*
* @param string|null $fieldPath
* @return array
*/
public function getIncludedFields(?string $fieldPath = null): array
```

### Using the FieldsOptionsObjectApplier class

Applying the options means making sure the data is serialized as expected by the given options.
The approach in FieldsOptionsObjectApplier is to provide your DTO and your implementation of an ExportApplierInterface.

```php
interface ExportApplierInterface
{
    /**
     * @param object|array $data
     * @param array $fields
     * @return object|array $data with exported fields
     */
    public function setExportedFields(/*object|array*/ $data, ?array $fields);

    public function getExportedFields(/*object|array*/ $data): array;
}
```

- `setExportedFields()` is used to mark the exported properties on the object. It is up to the object and/or whatever
serialization method you have to actually only export those. The easiest way to do it is to implement the native PHP
`JsonSerializable`interface and write the logic right inside the object.
- `getExportedFields()` is used to get the properties exported by default on the object.


#### Example

```php
$applier = new FieldsOptionsObjectApplier(new SampleExportApplier());
$dto = $this->getSampleDto();

$fieldsOptions = (new FieldsOptionsBuilder())    
    ->setFieldIncluded('id')
    ->build();

$applier->apply($dto, $fieldsOptions);

// now DTO should only serialize the id field
echo json_encode($dto);
```

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

- If you want to indicate that the endpoint should return you the default fields or not use `_defaults` as field.   
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

The `FieldsOptions` object encapsulates the provided options and can be constructed from an array (coming from a request)
or using the `FieldsOptionsBuilder` if you want to configure them programmatically.

To resolve implicit defaults and groups, pass the response DTO (or its class-string) to `Validator`. `FieldsOptions` reads the prototype from there and validates field paths:

```php
$options = new FieldsOptions($data, new Validator(RootDto::class));
$options = new FieldsOptions($data, new Validator($sampleDto));
```

Unless a sample declares its own classes, the PHP samples in this section use the attributed DTOs from `tests/Fixture/Attributed`: `RootDto` (`id`, `seo`, `profile`), where `profile` is a `ProfileDto` (see [Declaring defaults and groups](#declaring-defaults-and-groups); the fixture has a few more properties) whose `education` items are `EducationDto` (`institutionId`, `institutionName`) and `workHistory` items are `WorkplaceDto` (`id`, `employerName`, `startYear`, `endYear`).

See [docs/adr/adr-001-calculated-field-inclusion.md](docs/adr/adr-001-calculated-field-inclusion.md) for the inclusion algorithm and nested type walk.

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
public function setFieldIncluded(?string $fieldPath, array $fields = []): self
```

You can optionally use a validator that receives a sample DTO.
The validator will receive an object or class-string (preferred) or an array representing the schema of the response. An array only validates field paths; it does not enable calculated inclusion.

```php

use Lucian\FieldsOptions\FieldsOptionsBuilder;
use Lucian\FieldsOptions\Validator;

$validator = new Validator(RootDto::class);
$builder = new FieldsOptionsBuilder($validator);
$fieldsOptions = $builder
    ->setFieldIncluded('id')
    ->setFieldExcluded('seo')
    ->setAllFieldsIncluded('profile.education')
    ->setFieldOption('profile.education', 'limit', 1)
    ->setFieldOption('profile.education', 'sort', 'startYear')
    ->setFieldOption('profile.education', 'sortDir', 'asc')
    ->build();
```

You can include or exclude multiple fields at once from a given path

```php
$fieldsOptions = (new FieldsOptionsBuilder($validator))
    ->setFieldIncluded(null, ['id']) // this is equivalent to setFieldIncluded('id')
    ->setFieldIncluded('profile.education', ['institutionId']) // include profile.education.institutionId
    ->setFieldIncluded('profile', ['education.institutionName']) // the field can be a relative path
    ->setDefaultFieldsIncluded('profile.workHistory') // include profile.workHistory with its default fields
    ->setFieldExcluded('profile.workHistory', ['employerName']) // but exclude profile.workHistory.employerName
    ->build();

$fieldsOptions->getIncludedFields('profile.workHistory'); // ['id']
```

Excluding a field adds it to that level's field list, which turns implicit defaults off under calculated inclusion. That is why `setDefaultFieldsIncluded()` comes first above; without it `getIncludedFields('profile.workHistory')` is `[]`. (Unannotated classes on the legacy applier path ignore a level that has only `false` keys and keep the object's own defaults, as 1.x did.)

You also have methods to set all the options for a field at once

`public function setFieldOptions(?string $fieldPath, array $options): self`

```php
$educationOptions = ['limit' => 2, 'offset' => 5];
$builder->setFieldOptions('profile.education', $educationOptions);
```

You can set a custom group field included

`public function setGroupFieldIncluded(string $groupField, ?string $fieldPath = null): self`

```php
$fieldsOptions = $builder->setGroupFieldIncluded('_basicInfo', 'profile')
    ->build();
$fieldsOptions->hasGroupField('_basicInfo', 'profile'); // true
```

You can also create a builder with initial data. One scenario would be to add options to existing fields options.

```php
$data = $fieldsOptions->toArray();
$builder = new FieldsOptionsBuilder($validator, $data);
$builder->setFieldIncluded('profile.description');
```

With a prototype, the builder validates every path it sets: an unknown field throws `RuntimeException`.

### Using the FieldsOptions class

```php
use Lucian\FieldsOptions\FieldsOptions;
use Lucian\FieldsOptions\Validator;

// assuming we use the Symfony request
// $request = Request::createFromGlobals();
$data = json_decode($request->getContent(), true);

$options = new FieldsOptions($data, new Validator(RootDto::class));

$options->isFieldIncluded('id'); // true
$options->isFieldIncluded('missing'); // false
// field is present but value is false
$options->isFieldIncluded('seo'); // false
$options->isFieldIncluded('profile'); // true
// profile has a field list (education), so its defaults are off
$options->isFieldIncluded('profile.name'); // false
$options->isFieldIncluded('profile.education'); // true
// _all on education
$options->isFieldIncluded('profile.education.institutionName'); // true
$options->getFieldOption('profile.education', 'limit'); // 1
$options->getFieldOption('profile.education', 'missing', 1); // 1 - default
$options->getFieldOption('profile.education', 'missing'); // null - default

// field groups (request only, attributes are not consulted)
$options->hasDefaultFields(); // false - the root has a field list and no _defaults
$options->hasDefaultFields('profile'); // false
$options->hasAllFields('profile'); // false
$options->hasAllFields('profile.education'); // true
$options->hasAllFields('profiles.missing'); // throws InvalidArgumentException
$options->hasGroupField('_basicInfo', 'profile'); // throws InvalidArgumentException - custom groups must be present in the request
        
// you can export the entire structure
$array = $options->toArray();
```

Pass the DTO (or class-string) to `Validator`; `FieldsOptions` reads the prototype from there and validates field paths.

Without a prototype, `isFieldIncluded()` stays explicit-only (plus `_all` at that scope; 1.x returned `false` for unlisted fields under `_all`).

Note the difference between the three checks:

- `isFieldSpecified()` — the path is present in the request (`true` or `false`).
- `isFieldExplicitlyIncluded()` — the path is present and not `false` (the pre-2.0 `isFieldIncluded()`).
- `isFieldIncluded()` — the **effective** result: explicit include/exclude, then `_all`, custom groups, and `#[DefaultField]` when defaults are active at that scope.

If you used `isFieldIncluded($f) || hasDefaultFields() || hasAllFields()`, replace it with `isFieldIncluded($f)` **and** pass a prototype. Keeping the old workaround after upgrade still over-includes non-default fields.

#### Declaring defaults and groups

```php
use Lucian\FieldsOptions\Attribute\DefaultField;
use Lucian\FieldsOptions\Attribute\Group;

class ProfileDto
{
    #[DefaultField]
    #[Group('_basicInfo')]
    public int $id;

    #[DefaultField]
    #[Group('_basicInfo')]
    public string $name;

    #[Group('_extended')]
    public ?string $description = null;

    /** @var EducationDto[] */
    public array $education;
}
```

Group names must start with `_` and cannot be `_defaults`, `_all`, or `_opt`. Invalid names throw `InvalidArgumentException` when the prototype is bound (`new FieldsOptions`, `withPrototype()`, `new FieldSchema`) — including nested DTO types — not when querying inclusion. The check walks the DTO graph and resolves its types, so the first bind of a class costs up to a few milliseconds depending on graph size; the result is remembered per class for the rest of the process and later binds skip the walk. A class with an invalid name is not remembered and throws on every bind. Nested collection item types are read from native types and phpdoc (`EducationDto[]`, `iterable<EducationDto>`, or `\iterable|EducationDto[]|null`). If a nested type cannot be resolved (mixed, multi-object union, intersection), descendants fall back to explicit inclusion only.

#### Excluding properties with `#[Ignore]`

Mark a property `#[Ignore]` when it is not a field: it is never requestable, exported, validated or walked. Static properties are never fields either. Both are rejected by `Validator` as field paths and are never passed to `setExportedFields()`. Property names starting with `__` get no special treatment; mark them `#[Ignore]` if they are bookkeeping state.

`#[Ignore]` survives redeclaration. If an ancestor's declaration (not `private`) or a used trait carries it, a subclass that redeclares the property is still ignored, so a DTO base class needs the attribute only once:

```php
use Lucian\FieldsOptions\Attribute\Ignore;

abstract class AbstractDto
{
    #[Ignore]
    protected ?array $__exportedProperties = null;

    #[Ignore]
    protected array $__excludedProperties = [];

    // ... export logic
}

class WorkplaceDto extends AbstractDto
{
    // still ignored: the AbstractDto declaration carries #[Ignore]
    protected ?array $__exportedProperties = ['id' => true, 'employerName' => true];

    public int $id;
    public string $employerName;
}

$options = new FieldsOptions(['_all' => true], new Validator(WorkplaceDto::class));
$options->getIncludedFields(); // ['id', 'employerName']

(new Validator(WorkplaceDto::class))->validateField('__exportedProperties'); // throws RuntimeException
```

If you upgrade from 1.x and your DTO base class keeps state in properties like these, add `#[Ignore]` on the base-class declarations. Without it they are ordinary fields: `_all` includes them and the validator accepts them.

#### Dependencies

This library requires `symfony/type-info` `^7.4 || ^8.0` and `phpstan/phpdoc-parser`. Type-info 7.4 is a standalone package and installs beside Symfony 5.4 / 6.4 apps. If Flex `extra.symfony.require` pins every `symfony/*` package to `5.4.*` or `6.4.*`, exclude `symfony/type-info` from that pin.

#### Validation of fields

This library comes with a Validator implementation that should work with array and object prototypes: `Lucian\FieldsOptions\Validator`.

You can provide the validator to both `FieldsOptions` and `FieldsOptionsBuilder`.
A basic validator will be used in case it is not provided, that will only check the data structure.

Custom `ValidatorInterface` implementations must provide `getPrototype()`. `FieldsOptions` and `FieldsOptionsBuilder` read the inclusion schema from that method on the interface — an object or class-string enables calculated inclusion; `null` or an array leaves it explicit-only.

If the validator is constructed with the prototype/schema, then including invalid fields will trigger a RuntimeException.
A class-string prototype that cannot be autoloaded throws `InvalidArgumentException` — it does not skip validation.

**How validation works:** An object or class-string prototype is walked with Symfony TypeInfo; an array prototype is matched key by key.
Native types and phpdoc (`@var EducationDto[]`) are enough to validate nested collection fields — a populated sample is no longer required.
A missing property on a **resolved** class is invalid (`education.missing`), and so are static and `#[Ignore]` properties.
Request keys starting with `_` are read as groups and are not checked against the prototype.

##### Relaxed validation of unknown nested types

The validator only rejects a nested path when it can prove the path is invalid (the parent class was resolved and the property does not exist, or the parent is a scalar). When the nested type cannot be determined, remaining path segments are **accepted**. That is not the same as “included”: inclusion stays stricter and still requires an explicit request (or resolved `#[DefaultField]` / `#[Group]` / `_all` on a known class).

Accepted by the validator, but nested type is **unknown** (no defaults/groups under that node):

| Declaration | Example path | Validator | `isFieldIncluded()` |
| --- | --- | --- | --- |
| no type, no `@var` | `payload.foo` | accepted (unknown) | only if `payload.foo` is explicitly listed |
| `mixed` | `mystery.foo` | accepted (unknown) | only if explicitly listed |
| `object` | `payload.foo` | accepted (unknown) | only if explicitly listed |
| `array` / `?array` with no item class | `items.foo` | accepted (unknown) | only if explicitly listed |
| `iterable` with no item class | `items.foo` | accepted (unknown) | only if explicitly listed |
| multi-object union `A\|B` | `union.foo` | accepted (unknown) | only if explicitly listed |
| intersection `A&B` | `both.foo` | accepted (unknown) | only if explicitly listed |

Still rejected (type is known enough to nest, or is not nestable):

| Declaration | Example path | Validator |
| --- | --- | --- |
| `int` / `string` / `string\|int` | `id.foo` | rejected |
| `LocationDto` | `location.missing` | rejected |
| `@var EducationDto[]` | `education.missing` | rejected |
| `@var int[]` | `ids.foo` | rejected |
| `LocationDto\|string` | `value.missing` | rejected (`LocationDto` is used) |

Untyped properties with `@var SomeDto` are resolved and stay strict. `_all` on an unknown parent does not invent child fields.

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
 * With a prototype: effective child field names (defaults, _all, groups, minus exclusions).
 * Without a prototype: keys present in the request that are explicitly included.
 *
 * @param string|null $fieldPath
 * @return array
 */
public function getIncludedFields(?string $fieldPath = null): array
```

`{"profile": true}` then `getIncludedFields('profile')` returns the default fields of the nested DTO (`['id', 'name']`), not `[]`.

If you call `getIncludedFields()` directly, the result changes once you pass a `Validator` prototype: `{"_defaults": true}` gives `['_defaults']` without a prototype (request keys, as in 1.x) and `['id', 'profile']` with `new Validator(RootDto::class)`.

#### Options for a nested field

`forField()` slices the options for a nested field. The slice carries the resolved child prototype, so inclusion inside it is calculated against the child DTO without passing a prototype again. The slice is not re-validated.

```php
$options = new FieldsOptions(
    ['profile' => ['education' => ['institutionName' => true]]],
    new Validator(RootDto::class)
);

$profile = $options->forField('profile'); // bound to ProfileDto
$profile->toArray(); // ['education' => ['institutionName' => true]]
$profile->isFieldIncluded('name'); // false - profile has a field list
$profile->getIncludedFields('education'); // ['institutionName']
```

### Using the FieldsOptionsObjectApplier class

Applying the options means making sure the data is serialized as expected by the given options.
The approach in FieldsOptionsObjectApplier is to provide your DTO and your implementation of an ExportApplierInterface.

Classes that declare `#[DefaultField]` / `#[Group]` use calculated inclusion (the `FieldsOptions` you pass should already have a prototype from the validator).
Classes **without** those attributes keep the pre-2.0 behavior: defaults still come from `ExportApplierInterface::getExportedFields()` (for example `__exportedProperties` on your DTO). That path uses `isFieldExplicitlyIncluded()`, so constructing `FieldsOptions` with a `Validator` does not change the list passed to `setExportedFields()`. A nested mix is allowed (annotated parent, unannotated child).

For unannotated classes the list passed to `setExportedFields()` matches 1.x, except:

- A request with only `_all` at a level (optionally with `_opt`) passes `null` (export everything) instead of the reflected property list.
- Static and `#[Ignore]` properties are never passed.
- Uninitialized typed properties are listed when included but not read; 1.x threw an `Error`.

See [the ADR](docs/adr/adr-001-calculated-field-inclusion.md#compatibility-of-the-applier-safety-net) for details.

```php
interface ExportApplierInterface
{
    /**
     * This is s used to mark the exported properties on the object.
     * It is up to the object and/or whatever serialization method you have to actually only export those.
     * The easiest way to do it is to implement the native PHP `JsonSerializable`interface and write the logic right
     * inside the object.
     *
     * @param object|array $data
     * @param array|null $fields
     * @return void
     */
    public function setExportedFields(object|array $data, ?array $fields);

    /**
     * Returns the properties exported by default on the object.
     *
     * @param object|array $data
     * @return string[]
     */
    public function getExportedFields(object|array $data): array;

    /**
     * Should return the base class of your DTO
     * This helps
     *
     * @return string
     */
    public function getSupportedClass(): string;
}

class SampleExportApplier implements ExportApplierInterface
{
    public function setExportedFields($data, ?array $fields): void
    {
        if ($data instanceof AbstractDto) {
            // keep valid properties only
            if ($fields) {
                $fields = array_filter($fields, [$data, 'propertyExists']);
            }
            $data->setExportedProperties($fields);
        }
    }

    public function getExportedFields($data): array
    {
        if ($data instanceof AbstractDto) {
            return array_keys(iterator_to_array($data->getIterator()));
        }

        return [];
    }

    public function getSupportedClass(): string
    {
        return AbstractDto::class;
    }
}
```


#### Example

```php
$applier = new FieldsOptionsObjectApplier(new SampleExportApplier());
$dto = ProfileDto::getSampleDto(); // a populated ProfileDto

$fieldsOptions = (new FieldsOptionsBuilder())    
    ->setFieldIncluded('id')
    ->build();

$applier->apply($dto, $fieldsOptions);

// now DTO should only serialize the id field
echo json_encode($dto); // {"id":1}
```

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
To retrieve from the `profile` only the first institution's name, ordered by `startYear` you can make `_all` or `_defaults` false
and specify that you still need the institution name

```json
{
    "profile": {
        "education": {
            "_all": false,
            "institutionName": true,
            "_opt": {
                "limit": 1,
                "sort": "startYear",
                "sortDir": "asc"
            }
        }
    }
}
```

## Using the library

The library does not contain any code related to actually modify the response. 
Its goal is to only decode and encapsulate the client field options.

It is up to the caller to honor these field options.

The method `parse(string $s)` from the Parser class will return an instance of `FieldsOptions`.

To retrieve a path use the dot notation.

```php
use Lucian\FieldsOptions\Parser;

// assuming we use the Symfony request
// $request = Request:::createFromGlobals();

$parser = new Parser();
// fields=id,profile{presentation{bar{other}}},documents{images(limit=10,order=name)}

$options = $parser->parse($request->get('fields'));

var_dump($options->isFieldPresent('profile.presentation.bar.other')); // true
var_dump($options->isFieldPresent('profile.missing')); // false
var_dump($options->isFieldPresent('missing')); // false
var_dump($options->getFieldOption('documents.images', 'limit'))); // 10
var_dump($options->getFieldOption('documents.images', 'missing', 1))); // 1 -> default
var_dump($options->getFieldOptions('documents.images'))); // ['limit' => '10', 'order' => 'name']

// you can export the entire structure
$array = $options->toArray();
```

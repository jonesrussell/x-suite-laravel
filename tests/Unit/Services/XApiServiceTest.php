<?php

declare(strict_types=1);

use JonesRussell\XSuite\Services\XApiService;

test('normalizeApiResponseToArray returns array unchanged', function (): void {
    $response = ['data' => [], 'meta' => ['result_count' => 0]];
    $result = invokeNormalizeApiResponseToArray($response);
    expect($result)->toBe($response);
});

test('normalizeApiResponseToArray decodes JSON string to array', function (): void {
    $response = '{"data":[],"includes":{},"meta":{"result_count":0}}';
    $result = invokeNormalizeApiResponseToArray($response);
    expect($result)->toBeArray();
    expect($result)->toHaveKeys(['data', 'includes', 'meta']);
    expect($result['data'])->toBe([]);
});

test('normalizeApiResponseToArray throws when string is invalid JSON', function (): void {
    invokeNormalizeApiResponseToArray('not valid json');
})->throws(Exception::class, 'user mentions response was not a valid array');

test('normalizeApiResponseToArray throws when decoded JSON is not array', function (): void {
    invokeNormalizeApiResponseToArray('"just a string"', 'test');
})->throws(Exception::class, 'test response was not a valid array');

/**
 * Invoke the protected static method for testing.
 *
 * @param  array<string, mixed>|string  $response
 * @return array<string, mixed>
 */
function invokeNormalizeApiResponseToArray(array|string $response, string $context = 'user mentions'): array
{
    $method = (new ReflectionClass(XApiService::class))->getMethod('normalizeApiResponseToArray');
    $method->setAccessible(true);

    $result = $method->invoke(null, $response, $context);

    return is_array($result) ? $result : [];
}

<?php

namespace JonesRussell\XSuite\Services;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

class XApiErrorParser
{
    public static function getFriendlyMessage(\Throwable $exception): string
    {
        if ($exception instanceof ClientException) {
            return self::parseGuzzleException($exception);
        }

        if ($exception instanceof GuzzleException) {
            return self::parseGuzzleMessage($exception->getMessage());
        }

        return $exception->getMessage();
    }

    protected static function parseGuzzleException(ClientException $exception): string
    {
        $statusCode = $exception->getResponse()?->getStatusCode();
        $responseBody = $exception->getResponse()?->getBody()?->getContents();

        $errorData = null;
        if ($responseBody) {
            $decoded = json_decode($responseBody, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $errorData = $decoded;
            }
        }

        return match ($statusCode) {
            402 => self::handlePaymentRequired($errorData),
            403 => self::handleForbidden($errorData, $exception->getRequest()?->getUri()?->getPath()),
            401 => 'Authentication failed. Please reconnect your X account in settings.',
            429 => 'Rate limit exceeded. Please wait a few minutes before trying again.',
            404 => 'The requested resource was not found on X API.',
            500, 502, 503, 504 => 'X API is currently experiencing issues. Please try again later.',
            default => self::extractErrorMessage($errorData, $exception->getMessage(), $statusCode),
        };
    }

    protected static function handlePaymentRequired(?array $errorData): string
    {
        if ($errorData && isset($errorData['title'])) {
            if ($errorData['title'] === 'CreditsDepleted') {
                $accountId = $errorData['account_id'] ?? 'your account';

                return "Your X API account has run out of credits. Please purchase more credits in the X Developer Portal (Account ID: {$accountId}).";
            }

            return "X API payment required: {$errorData['title']}. ".
                ($errorData['detail'] ?? 'Please check your X Developer Portal for billing information.');
        }

        return 'Your X API account requires payment. Please check your X Developer Portal to purchase credits or upgrade your plan.';
    }

    protected static function handleForbidden(?array $errorData, ?string $path): string
    {
        $baseMessage = 'Access forbidden.';

        if ($path && str_contains($path, 'media/upload')) {
            $baseMessage = 'Media upload is not available. ';
            if ($errorData && isset($errorData['title'])) {
                return $baseMessage.($errorData['detail'] ?? $errorData['title']);
            }

            return $baseMessage.'This may be due to missing permissions, insufficient API credits, or your X API plan does not support media uploads.';
        }

        if ($errorData && isset($errorData['title'])) {
            return $baseMessage.' '.($errorData['detail'] ?? $errorData['title']);
        }

        return $baseMessage.' This may be due to missing permissions or insufficient API credits.';
    }

    protected static function extractErrorMessage(?array $errorData, string $fallback, ?int $statusCode): string
    {
        if ($errorData) {
            $detail = $errorData['detail'] ?? $errorData['message'] ?? $errorData['title'] ?? null;
            if ($detail) {
                return $detail;
            }

            if (isset($errorData['errors']) && is_array($errorData['errors'])) {
                $errorMessages = array_map(function ($error) {
                    return is_array($error) ? ($error['message'] ?? json_encode($error)) : $error;
                }, $errorData['errors']);

                return implode(' ', $errorMessages);
            }
        }

        return self::parseGuzzleMessage($fallback);
    }

    protected static function parseGuzzleMessage(string $message): string
    {
        $message = strip_tags($message);
        $message = preg_replace('/Client error: `[^`]+` resulted in a `\d+ [^`]+` response:/', '', $message);
        $message = preg_replace('/Server error: `[^`]+` resulted in a `\d+ [^`]+` response:/', '', $message);
        $message = trim($message);

        if (empty($message) || strlen($message) < 10) {
            return 'An error occurred while communicating with the X API. Please try again or contact support if the issue persists.';
        }

        return $message;
    }
}

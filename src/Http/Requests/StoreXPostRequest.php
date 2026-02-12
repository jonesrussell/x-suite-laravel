<?php

declare(strict_types=1);

namespace JonesRussell\XSuite\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use JonesRussell\XSuite\Enums\XPostStatus;
use JonesRussell\XSuite\Models\XPost;

final class StoreXPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', XPost::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxLength = (int) config('x-suite.max_tweet_length', 280);

        return [
            'content' => [
                'required_without:thread_parts',
                'nullable',
                'string',
                'max:'.$maxLength,
            ],
            'thread_parts' => ['nullable', 'array', 'max:25'],
            'thread_parts.*' => ['required', 'string', 'max:'.$maxLength],
            'media_urls' => ['nullable', 'array', 'max:4'],
            'media_urls.*' => ['required', 'string'],
            'status' => [
                'sometimes',
                Rule::enum(XPostStatus::class)->only([
                    XPostStatus::Draft,
                    XPostStatus::Scheduled,
                ]),
            ],
            'scheduled_for' => [
                'required_if:status,'.XPostStatus::Scheduled->value,
                'nullable',
                'date',
                'after:+1 minute',
            ],
            'publish_immediately' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxLength = (int) config('x-suite.max_tweet_length', 280);

        return [
            'content.required_without' => 'Either main content or thread parts must be provided.',
            'content.max' => "Tweet content cannot exceed {$maxLength} characters.",
            'thread_parts.max' => 'A thread cannot have more than 25 tweets.',
            'thread_parts.*.max' => "Each tweet in the thread cannot exceed {$maxLength} characters.",
            'media_urls.max' => 'You can attach a maximum of 4 media files per post.',
            'scheduled_for.required_if' => 'A scheduled post must have a publish time.',
            'scheduled_for.after' => 'The scheduled time must be in the future.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'publish_immediately' => $this->boolean('publish_immediately'),
        ]);
    }
}

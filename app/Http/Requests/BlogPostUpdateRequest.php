<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogPostUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'title' => ['required', 'string', 'max:150'],
            'summary' => ['nullable', 'string'],
            'body' => ['required', 'string'],
            'category' => ['required', 'integer', 'exists:blog_categories,id'],
            'tags' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif', 'max:5120'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,gif', 'max:5120'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['image', 'mimes:jpeg,jpg,png,gif', 'max:5120'],
            'published' => ['nullable', Rule::in(['on', '1', 1, true, 'true'])],
            'featured' => ['nullable', Rule::in(['on', '1', 1, true, 'true'])],
            'subscriber_only' => ['nullable', 'boolean'],
        ];
    }
}

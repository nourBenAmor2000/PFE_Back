<?php


namespace App\Http\Requests;


use Illuminate\Foundation\Http\FormRequest;


class AssistantQueryRequest extends FormRequest
{
public function rules(): array
{
return [
'query' => ['required','string','max:2000'],
'userId' => ['nullable','string','max:100'],
'provider' => ['nullable','in:openrouter,gemini'],
];
}
}